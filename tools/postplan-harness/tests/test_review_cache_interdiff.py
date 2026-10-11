"""Review-agent interdiff mode: only per-file patch-id deltas are re-reviewed, and findings
on unchanged files are merged back by path.

Pure rows cover `reviewcache.file_delta` / `subset_diff` / `reusable_by_path`; runner rows
drive the replay runner (helpers shared with test_review_cache_hit.py) with a seeded
`<slug>.reviewcache.json`, a monkeypatched `_review_cache_context` whose current key and
per-file ids differ from the record's, and a spy on `review.agent_a_prompt` that captures
the diff the review agents are actually handed.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import review, reviewcache
from harness.adapters.llm import FixtureLlm
from harness.state import Finding, HarnessError, TerminalState, UsageLedger
from test_review_cache_hit import (ALPHA, BETA, CANNED_FINDINGS, DIFF, FILES, KEY,
                                   REVIEW_PURPOSES, TITLE, _arm, _drive, _finding_dict,
                                   _purposes, _record)
from test_runner_replay import _actions

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

NEW_DIFF_ID = "e" * 40


# ---- pure rows ---------------------------------------------------------------------------

def test_file_delta_added_removed_modified():
    prior = {"a": "1", "b": "2", "c": "3"}
    current = {"a": "1", "b": "9", "d": "4"}
    # b modified, c removed (prior-only), d added (current-only); a untouched.
    assert reviewcache.file_delta(prior, current) == {"b", "c", "d"}
    assert reviewcache.file_delta(prior, prior) == set()


def _sec(path, body):
    return (f"diff --git a/{path} b/{path}\nindex 111..222 100644\n--- a/{path}\n+++ b/{path}\n"
            f"@@ -1 +1 @@\n-old\n+{body}\n")


def test_subset_diff_preserves_order_and_drops_others():
    diff = _sec("z.py", "zz") + _sec("m.py", "mm") + _sec("a.py", "aa") + _sec("q.py", "qq")
    sub = reviewcache.subset_diff(diff, {"q.py", "z.py", "a.py"})
    # Original order (z, a, q), not set order or sorted order; m.py dropped.
    assert sub == _sec("z.py", "zz") + _sec("a.py", "aa") + _sec("q.py", "qq")
    assert "m.py" not in sub
    assert reviewcache.subset_diff(diff, set()) == ""
    assert reviewcache.subset_diff(diff, {"nope.py"}) == ""


def _f(path, body):
    return Finding(source="code-review", agent="A", path=path, line=3, body=body, score=90)


def _scored(f):
    return {"source": f.source, "agent": f.agent, "path": f.path, "line": f.line,
            "score": f.score, "body_head": f.body[:160]}


def test_reusable_by_path_drops_delta_pathless_and_removed():
    unchanged = _f("keep.php", "unchanged file")
    in_delta = _f("changed.php", "delta file")
    pathless = _f("", "no path")
    removed = _f("gone.php", "removed file")
    findings = [unchanged, in_delta, pathless, removed]
    scored = [_scored(f) for f in findings]
    delta = {"changed.php", "gone.php"}
    current_files = {"keep.php", "changed.php"}      # gone.php no longer in the PR

    keep_f, keep_s = reviewcache.reusable_by_path(findings, scored, delta, current_files)

    assert keep_f == [unchanged]
    assert keep_s == [_scored(unchanged)]
    # Each exclusion rule alone is load-bearing: a path-less finding is dropped even though
    # "" is not in the delta, and a removed-file finding is dropped even without delta.
    keep_f2, _ = reviewcache.reusable_by_path([pathless, removed], [], set(), current_files)
    assert keep_f2 == []


# ---- runner rows -------------------------------------------------------------------------

def _changed_files(path=BETA):
    files = dict(FILES)
    files[path] = "0" * 40
    return files


def _key(files, **over):
    fields = dict(KEY, diff_id=NEW_DIFF_ID, file_list=sorted(files))
    fields.update(over)
    return fields


def _headers(diff):
    return [ln for ln in diff.splitlines() if ln.startswith("diff --git ")]


def _spy_agent_a(monkeypatch):
    seen = []
    real = review.agent_a_prompt

    def spy(meta, cls, plan):
        seen.append(cls.filtered_diff)
        return real(meta, cls, plan)

    monkeypatch.setattr(review, "agent_a_prompt", spy)
    return seen


def _interdiff_drive(monkeypatch, tmp_path, *, name, canned, arm=None, record_key=KEY,
                     files=None, key=None, llm=None):
    """Seed a record on the old key/ids, then run with the changed key and ids."""
    files = files if files is not None else _changed_files()
    key = key if key is not None else _key(files)
    record = _record(arm if arm is not None else _arm(), key=record_key)
    return _drive(monkeypatch, tmp_path, name=name, canned=canned, record=record,
                  key=key, files=files, llm=llm)


def _fresh_a_canned(findings, scores=None):
    canned = dict(CANNED_FINDINGS)
    canned["review-agent-a"] = findings
    canned["score-findings"] = scores if scores is not None else []
    return canned


def test_one_changed_file_is_reviewed_alone(monkeypatch, tmp_path):
    seen = _spy_agent_a(monkeypatch)
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="one", canned=CANNED_FINDINGS)
    assert res.terminal != TerminalState.FAILED, res.error

    assert len(seen) == 1
    # The spy saw only the delta file's section, not the full three-file diff.
    assert _headers(seen[0]) == [f"diff --git a/{BETA} b/{BETA}"]
    assert ALPHA not in seen[0]
    assert len(_headers(DIFF)) == 3                  # the full diff really was wider
    assert res.review_delta == [BETA]
    assert res.reused_from == {"review": "live-prior"}
    assert set(REVIEW_PURPOSES) <= set(_purposes(res))


def test_interdiff_merges_reused_findings_by_path(monkeypatch, tmp_path):
    arm = _arm(findings=[_finding_dict(ALPHA, 90, body="cached unchanged-file finding"),
                         _finding_dict(BETA, 85, body="stale delta-file finding")])
    canned = _fresh_a_canned([{"path": BETA, "line": 1, "body": "fresh delta-file finding"}],
                             [{"n": 1, "score": 88}])
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="merge", canned=canned, arm=arm)
    assert res.terminal != TerminalState.FAILED, res.error
    assert res.review_delta == [BETA]

    by_body = {f.body: f for f in res.findings}
    reused = by_body["cached unchanged-file finding"]
    assert reused.path == ALPHA and reused.score == 90
    assert by_body["fresh delta-file finding"].score == 88
    assert "stale delta-file finding" not in by_body
    assert [f.body for f in res.findings] == ["cached unchanged-file finding",
                                              "fresh delta-file finding"]
    # scored_findings follow the same merge: cached row for the unchanged file, no stale row.
    assert [(s["path"], s["score"]) for s in res.scored_findings] == [(ALPHA, 90), (BETA, 88)]


def test_interdiff_summary_names_reused_count_and_never_no_issues(monkeypatch, tmp_path):
    arm = _arm(findings=[_finding_dict(ALPHA, 90, body="cached unchanged-file finding")])
    canned = _fresh_a_canned([])                      # fresh review finds nothing
    res, out = _interdiff_drive(monkeypatch, tmp_path, name="summary", canned=canned, arm=arm)
    assert res.terminal != TerminalState.FAILED, res.error
    assert [f.body for f in res.findings] == ["cached unchanged-file finding"]

    summaries = [a for a in _actions(out)
                 if a["action"] == "pr_comment" and a.get("title") == "Code review"]
    assert len(summaries) == 1
    body = summaries[0]["body"]
    assert "1 finding(s) on unchanged files reused from a prior run" in body
    assert "reused from a prior run" in body
    assert "No issues found" not in body
    # The reused finding was posted by the producing run; nothing is re-posted inline.
    assert not [a for a in _actions(out) if a["action"] == "pr_review_findings"]


def _assert_full_review(res, seen):
    assert res.terminal != TerminalState.FAILED, res.error
    assert res.reused_from == {}
    assert res.review_delta == []
    assert set(REVIEW_PURPOSES) <= set(_purposes(res))
    assert len(seen) == 1
    assert {f"diff --git a/{ALPHA} b/{ALPHA}", f"diff --git a/{BETA} b/{BETA}"} <= set(_headers(seen[0]))


def test_plan_hash_change_forces_full_review(monkeypatch, tmp_path):
    seen = _spy_agent_a(monkeypatch)
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="plan", canned=CANNED_FINDINGS,
                              record_key=dict(KEY, plan_hash="q" * 64))
    _assert_full_review(res, seen)


def test_version_change_forces_full_review(monkeypatch, tmp_path):
    seen = _spy_agent_a(monkeypatch)
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="version", canned=CANNED_FINDINGS,
                              record_key=dict(KEY, version="w" * 64))
    _assert_full_review(res, seen)


def test_empty_delta_with_changed_diff_id_forces_full_review(monkeypatch, tmp_path):
    seen = _spy_agent_a(monkeypatch)
    # Per-file ids identical to the record's, but the whole-diff id differs: unexplained.
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="empty", canned=CANNED_FINDINGS,
                              files=dict(FILES))
    _assert_full_review(res, seen)


class _DegradingB(FixtureLlm):
    """review-agent-b returns unparseable output on both attempts (the degraded path)."""

    def call(self, purpose, *a, **k):
        if purpose == "review-agent-b":
            raise HarnessError("llm-invalid-output", purpose)
        return super().call(purpose, *a, **k)


def test_degraded_interdiff_propagates(monkeypatch, tmp_path):
    canned = {k: v for k, v in CANNED_FINDINGS.items() if k != "review-agent-b"}
    llm = _DegradingB(UsageLedger(), canned)
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="degraded", canned=canned, llm=llm)
    assert res.terminal != TerminalState.FAILED, res.error

    assert res.review_delta == [BETA]                 # it really was the interdiff path
    assert "review-agent-b" in res.degraded_agents
    assert res.arm.armed is False
    assert any(c.blocked for c in res.arm.conditions)
