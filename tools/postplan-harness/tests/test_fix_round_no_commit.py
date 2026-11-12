"""Fix-round empty-round cases, replayed from real runs.

The fixtures under ``fixtures/fix_round_no_commit`` are trimmed copies of real run
records. Each test rebuilds the finding bodies with ``render_body`` so a key never
drifts from what production computes.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fix_round, same_run_threads, thread_ingestion
from harness.armable import holding_findings
from harness.labels import LABEL_IMPORTANT, render_body
from harness.state import ArmDecision, ConditionResult, Finding, RunResult

PR = 77
FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures",
                        "fix_round_no_commit")
POSTED_FIXTURES = ["posted_no_thread_gm_tenure.json", "posted_no_thread_trade_v3_3.json",
                   "posted_no_thread_check_plan.json", "posted_no_thread_wt_gc.json"]


class _Gh:
    def __init__(self, thread_ids=(), actions=()):
        self.thread_ids = set(thread_ids)
        self._actions = list(actions)
        self.resolved = []

    def pr_thread_ids(self, pr):
        return set(self.thread_ids)

    def actions(self):
        return list(self._actions)

    def resolve_review_thread(self, pr, cid, body):
        self.resolved.append((cid, body))


class _Git:
    def __init__(self):
        self.sha = "head0"
        self.drops = []

    def head(self):
        return self.sha

    def is_dirty(self):
        return False

    def changed_files(self, base=None):
        return []

    def drop_commit(self, pre):
        self.drops.append(pre)
        self.sha = pre
        return ""

    def commit(self, msg):
        self.sha = "head1"
        return self.sha


class _Llm:
    calls = 0

    def call_tooled(self, purpose, model, prompt, **kw):
        type(self).calls += 1
        raise AssertionError("the fixer must not be called directly in these tests")


def _decision(*nums):
    return ArmDecision(armed=False, conditions=[ConditionResult(n, f"c{n}", True) for n in nums])


def _finding(spec: dict) -> Finding:
    f = Finding(source=spec["source"], agent=spec.get("agent", "A"), path=spec["path"],
                line=spec["line"], body=spec["detail"], score=spec["score"])
    f.label = LABEL_IMPORTANT
    return f


def _thread_row(spec: dict, finding: Finding) -> dict:
    body = render_body(finding, same_run_threads.POSTED_BODY_LIMIT)
    gate = spec.get("gate_held", False)
    return {"commentId": spec["commentId"], "path": finding.path, "line": finding.line,
            "body": body, "score": finding.score, "authorLogin": "a-jay85", "agent": None,
            "lastBody": thread_ingestion.GATE_HOLD_MARKER if gate else body,
            "lastAuthorLogin": "a-jay85", "lastAuthorType": "User",
            "outdated": bool(spec.get("outdated")), "label": LABEL_IMPORTANT}


def _load(name, monkeypatch, *, fetch_failed=None):
    """Return ``(fixture, res, gh, rows)``; the thread fetch is patched to the fixture's rows."""
    with open(os.path.join(FIXTURES, name)) as fh:
        fx = json.load(fh)
    findings = [_finding(s) for s in fx["findings"]]
    res = RunResult(terminal="x")
    res.findings = findings
    rows = [_thread_row(t, findings[t["finding"]]) for t in fx["open_threads"]]
    failed = fx["thread_fetch_failed"] if fetch_failed is None else fetch_failed
    actions = [{"action": "pr_review_findings",
                "findings": [{"path": findings[i].path,
                              "body": render_body(findings[i], same_run_threads.POSTED_BODY_LIMIT)}
                             for i in fx["posted"]]}] if fx["posted"] else []
    gh = _Gh(thread_ids={r["commentId"] for r in rows}, actions=actions)
    monkeypatch.setattr(fix_round.thread_ingestion, "fetch_trusted_threads",
                        lambda gh_, pr, ids, **kw: None if failed else list(rows))
    return fx, res, gh, rows


def _run(tmp_path, fx, res, gh, monkeypatch, outcome=None):
    """Run one round with a stubbed per-item disposition. Returns ``(record, calls)``."""
    calls = []

    def stub(item, llm, git, worktree, pr, gh_, log, *, defer, **kw):
        calls.append(item)
        if outcome is None:
            raise AssertionError("disposition_thread must not be called")
        if outcome == "declined-already-fixed" and item.get("commentId") is not None:
            defer.append({"comment_id": item["commentId"], "kind": "declined",
                          "reason": "already-fixed"})
        return "declined" if outcome.startswith("declined") else outcome

    monkeypatch.setattr(fix_round.thread_ingestion, "disposition_thread", stub)
    git = _Git()
    rec = fix_round.run_fix_round(
        decision=_decision(*fx["targets"]), res=res, gh=gh, llm=_Llm(), git=git,
        worktree=str(tmp_path), plan=None, pr=PR, out_dir=str(tmp_path), log=lambda m: None,
        commit=lambda msg: git.commit(msg),
        recheck=lambda pre: {"new_holding": [], "conformance_added": [], "degraded": False},
        already_tried=False, env={})
    return rec, calls


# --- characterization: these pass before and after the collect_items change ---------------

def test_gate_held_thread_stays_out_of_round(tmp_path, monkeypatch):
    fx, res, gh, _rows = _load("gate_path_check_docs.json", monkeypatch)
    rec, calls = _run(tmp_path, fx, res, gh, monkeypatch)
    assert calls == []
    assert rec["items"] == []
    assert (rec["status"], rec["reason"]) == ("no-change", "no-commit")
    assert rec["reevaluate"] is False


def test_reply_only_round_sets_reevaluate(tmp_path, monkeypatch):
    fx, res, gh, rows = _load("reply_only_conflict_summary.json", monkeypatch)
    rec, calls = _run(tmp_path, fx, res, gh, monkeypatch, outcome="declined-already-fixed")
    assert len(calls) == 1
    assert (rec["status"], rec["reason"]) == ("no-change", "no-commit")
    assert rec["reevaluate"] is True
    assert rec["defer"] == [{"comment_id": rows[0]["commentId"], "kind": "declined",
                             "reason": "already-fixed"}]
    assert same_run_threads._key(rows[0]["path"], rows[0]["body"]) in fix_round.prune_keys(rec)


def test_open_thread_finding_collected_once(monkeypatch):
    fx, res, gh, rows = _load("reply_only_conflict_summary.json", monkeypatch)
    items = fix_round.collect_items(gh, PR, res, gh.actions(), {})
    assert len(items) == 1
    assert items[0]["threaded"] is True
    assert items[0]["commentId"] == rows[0]["commentId"]


def test_fetch_failure_skips_posted_findings(monkeypatch):
    fx, res, gh, _rows = _load("posted_no_thread_gm_tenure.json", monkeypatch, fetch_failed=True)
    record = {}
    assert fix_round.collect_items(gh, PR, res, gh.actions(), record) == []
    assert record["thread_fetch"] == "failed"


# --- the collect_items change ----------------------------------------------------------

@pytest.mark.parametrize("name", POSTED_FIXTURES)
def test_replay_posted_finding_without_thread_enters_round(name, monkeypatch):
    fx, res, gh, _rows = _load(name, monkeypatch)
    items = fix_round.collect_items(gh, PR, res, gh.actions(), {})
    assert len(items) == fx["expect_unthreaded_items"] == 1
    assert items[0]["threaded"] is False
    assert items[0]["commentId"] is None
    assert items[0]["path"] == fx["findings"][0]["path"]


def test_replay_round_reaches_fixer(tmp_path, monkeypatch):
    fx, res, gh, _rows = _load("posted_no_thread_gm_tenure.json", monkeypatch)
    rec, calls = _run(tmp_path, fx, res, gh, monkeypatch, outcome="fixed")
    assert [c["path"] for c in calls] == [fx["findings"][0]["path"]]
    assert len(rec["items"]) == 1
    assert rec["items"][0]["outcome"] == "fixed"
    assert rec["items"][0]["threaded"] is False


def test_unthreaded_decline_does_not_release(tmp_path, monkeypatch):
    fx, res, gh, _rows = _load("posted_no_thread_gm_tenure.json", monkeypatch)
    rec, calls = _run(tmp_path, fx, res, gh, monkeypatch, outcome="declined")
    assert len(calls) == 1
    assert rec["status"] == "no-change"
    f = res.findings[0]
    key = same_run_threads._key(f.path, render_body(f, same_run_threads.POSTED_BODY_LIMIT))
    assert key not in fix_round.prune_keys(rec)
    assert f in holding_findings(res.findings)


def test_outdated_capped_thread_suppresses_finding(monkeypatch):
    fx, res, gh, rows = _load("posted_no_thread_gm_tenure.json", monkeypatch)
    f = res.findings[0]
    fillers = []
    for i in range(12):
        filler = Finding(source="code-review", agent="A", path=f"ibl5/filler{i}.php", line=1,
                         body=f"Filler finding {i}.", score=90)
        filler.label = LABEL_IMPORTANT
        fillers.append(_thread_row({"commentId": 100 + i, "outdated": True}, filler))
    newest = _thread_row({"commentId": 999, "outdated": True}, f)
    all_rows = fillers + [newest]
    monkeypatch.setattr(fix_round.thread_ingestion, "fetch_trusted_threads",
                        lambda gh_, pr, ids, **kw: list(all_rows))
    # Not posted this run, so only the open-thread key can keep it out of the round.
    assert len(all_rows) > thread_ingestion.MAX_OUTDATED_PER_PASS
    record = {}
    items = fix_round.collect_items(gh, PR, res, [], record)
    assert all(i["path"] != f.path for i in items)
    assert record["collect_skips"]["open_thread"] == 1


def test_fetch_failure_records_posted_no_fetch(monkeypatch):
    fx, res, gh, _rows = _load("posted_no_thread_gm_tenure.json", monkeypatch, fetch_failed=True)
    record = {}
    assert fix_round.collect_items(gh, PR, res, gh.actions(), record) == []
    assert record["collect_skips"] == {"posted_no_fetch": 1}


def test_collect_skips_counts(monkeypatch):
    fx, res, gh, _rows = _load("gate_path_check_docs.json", monkeypatch)
    record = {}
    assert fix_round.collect_items(gh, PR, res, gh.actions(), record) == []
    assert record["collect_skips"] == {"gate_held": 1, "open_thread": 1}
    # No threads and no findings: nothing is skipped, so the key stays absent.
    monkeypatch.setattr(fix_round.thread_ingestion, "fetch_trusted_threads",
                        lambda gh_, pr, ids, **kw: [])
    clean = {}
    assert fix_round.collect_items(_Gh(), PR, RunResult(terminal="x"), [], clean) == []
    assert "collect_skips" not in clean
