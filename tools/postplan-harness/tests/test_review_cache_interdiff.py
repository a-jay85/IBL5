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
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, review, reviewcache
from harness.adapters.llm import FixtureLlm
from harness.review import VERIFIER_PURPOSES
from harness.review import ReviewPhase
from harness.state import Finding, HarnessError, PlanInfo, TerminalState, UsageLedger
from harness.adapters.gitad import ReplayGit
from test_review_cache_hit import (ALPHA, BETA, CANNED_FINDINGS, DIFF, FILES, KEY,
                                   REVIEW_PURPOSES, TITLE, _arm, _drive, _finding_dict, _php_section,
                                   _all_real, _pin_verifier_worktree, _purposes, _record)  # noqa: F401 (autouse fixture)
from test_runner_replay import _actions

# Replay runs reach fidelity's procedure lookup; see tests/conftest.py.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show", "standard_review_depth")

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


def _fresh_a_canned(findings, verdicts=None):
    """`verdicts` is the raw reply all three verifier seats return; default is all-real."""
    canned = dict(CANNED_FINDINGS)
    canned["review-agent-a"] = findings
    reply = verdicts if verdicts is not None else _all_real(len(findings))
    canned.update({p: reply for p in VERIFIER_PURPOSES})
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
                             _all_real(1))
    res, _ = _interdiff_drive(monkeypatch, tmp_path, name="merge", canned=canned, arm=arm)
    assert res.terminal != TerminalState.FAILED, res.error
    assert res.review_delta == [BETA]

    by_body = {f.body: f for f in res.findings}
    reused = by_body["cached unchanged-file finding"]
    assert reused.path == ALPHA and reused.score == 90
    assert by_body["fresh delta-file finding"].score == 100   # three real votes
    assert "stale delta-file finding" not in by_body
    assert [f.body for f in res.findings] == ["cached unchanged-file finding",
                                              "fresh delta-file finding"]
    # scored_findings follow the same merge: cached row for the unchanged file, no stale row.
    assert [(s["path"], s["score"]) for s in res.scored_findings] == [(ALPHA, 90), (BETA, 100)]


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


# ---- same-run delta review (Phase 4.5b) and fidelity re-pin ------------------------------

class _DeltaGh:
    """Records review posts; serves a fixed PR meta and body."""

    def __init__(self):
        self.findings_posts = []
        self.summary_posts = []

    def pr_meta(self):
        return {"number": 7, "title": TITLE, "headRefOid": "newhead"}

    def pr_body_fresh(self):
        return "fresh body"

    def post_review_findings(self, pr, sha, title, findings):
        self.findings_posts.append((pr, sha, title, list(findings)))

    def post_review_summary(self, pr, title, body):
        self.summary_posts.append((pr, title, body))


def _scored_row(f):
    return {"source": f.source, "agent": f.agent, "path": f.path, "line": f.line,
            "score": f.score, "body_head": f.body[:160]}


def _same_run_setup(monkeypatch, tmp_path, *, delta_agent_findings):
    monkeypatch.setattr(runner, "_harness_version", lambda *a, **k: "v" * 64)
    pre_diff = _php_section(ALPHA, 3) + _php_section(BETA, 3)
    post_diff = _php_section(ALPHA, 3) + _php_section(BETA, 4)
    canned = dict(CANNED_FINDINGS)
    canned["review-agent-a"] = delta_agent_findings
    canned["security-audit"] = []
    canned.update({p: _all_real(len(delta_agent_findings)) for p in VERIFIER_PURPOSES})
    llm = FixtureLlm(UsageLedger(), canned)
    gh = _DeltaGh()
    phase = ReviewPhase(llm, gh, worktree=str(tmp_path))
    kept = Finding(source="code-review", agent="A", path=ALPHA, line=3, body="kept on a", score=90)
    dropped = Finding(source="code-review", agent="A", path=BETA, line=3, body="old on b", score=85)
    res = types.SimpleNamespace(findings=[kept, dropped],
                                scored_findings=[_scored_row(kept), _scored_row(dropped)],
                                degraded_agents=[], review_delta=[])
    return phase, llm, gh, res, pre_diff, post_diff


def test_same_run_delta_review_posts_only_delta_and_merges_reused(monkeypatch, tmp_path):
    phase, llm, gh, res, pre_diff, post_diff = _same_run_setup(
        monkeypatch, tmp_path,
        delta_agent_findings=[{"path": BETA, "line": 1, "body": "new on b"}])
    git = ReplayGit({"diff": post_diff})
    pre_key = runner._review_cache_context(ReplayGit({"diff": pre_diff}), None, "m", {"title": TITLE},
                                           PlanInfo(), runner.HARNESS_ROOT, lambda m: None)[0]

    count, cls, meta, cache_key, cache_files = runner._same_run_delta_review(
        phase, {"number": 7, "title": TITLE}, None, PlanInfo(), git, gh, res, {BETA},
        None, "m", lambda m: None)

    assert count == 1
    # Only the delta file's finding is posted; the kept finding on ALPHA is never re-posted.
    assert len(gh.findings_posts) == 1
    posted = gh.findings_posts[0][3]
    assert [f.path for f in posted] == [BETA]
    assert [f.body for f in posted] == ["new on b"]
    assert gh.findings_posts[0][1] == "newhead"        # meta refreshed from the post-fix head
    assert meta["headRefOid"] == "newhead"
    # Reused ALPHA finding merged back; the stale BETA finding is gone.
    assert [f.body for f in res.findings] == ["kept on a", "new on b"]
    assert [(r["path"], r["score"]) for r in res.scored_findings] == [(ALPHA, 90), (BETA, 100)]
    assert res.review_delta == [BETA]
    assert set(cache_files) == {ALPHA, BETA}
    # The key describes the post-fix tree, not the pre-fix one.
    assert cache_key["diff_id"] == fidelity.diff_patch_id(git.diff_vs_base())
    assert cache_key["diff_id"] and cache_key["diff_id"] != pre_key["diff_id"]
    assert cls.filtered_diff == post_diff


def test_same_run_delta_review_skips_when_delta_is_filtered_out(monkeypatch, tmp_path):
    phase, llm, gh, res, _pre, post_diff = _same_run_setup(
        monkeypatch, tmp_path, delta_agent_findings=[])
    ran = []
    monkeypatch.setattr(phase, "run", lambda *a, **k: ran.append(1))
    logs = []

    count, _cls, _meta, cache_key, _files = runner._same_run_delta_review(
        phase, {"number": 7, "title": TITLE}, None, PlanInfo(), ReplayGit({"diff": post_diff}), gh,
        res, {"ibl5/classes/Absent.php"}, None, "m", logs.append)

    assert count == 0
    assert ran == [] and llm.ledger.calls == []
    assert gh.findings_posts == [] and gh.summary_posts == []
    assert any("delta lives only in filtered-out files" in m for m in logs)
    assert [f.body for f in res.findings] == ["kept on a", "old on b"]    # untouched
    assert res.review_delta == []
    assert cache_key["diff_id"] == fidelity.diff_patch_id(post_diff)


def _repin_args(res, logs):
    return (None, None, _DeltaGh(), "/out", "/wt", PlanInfo(), "m", "body", 7, "fixsha", res,
            logs.append)


def test_repin_noop_without_verdict_2(monkeypatch):
    def boom(*a, **k):
        raise AssertionError("re_review must not run without verdict_2")

    monkeypatch.setattr(runner.fidelity, "re_review", boom)
    res = types.SimpleNamespace(fidelity={"verdict_1": "READY"})
    logs = []
    runner._repin_fidelity_after_same_run(*_repin_args(res, logs))
    assert res.fidelity == {"verdict_1": "READY"}
    assert logs == []


def test_repin_updates_verdict_2_and_tree_with_round_past_max(monkeypatch):
    seen = {}

    def fake(llm, git, out_dir, worktree, plan, master_sha, pr_body, pr, remediation_sha,
             verdict1_path, *, round_num, log=None):
        seen.update(round_num=round_num, pr_body=pr_body, remediation_sha=remediation_sha,
                    verdict1_path=verdict1_path)
        return "READY", "newtree", "/p"

    monkeypatch.setattr(runner.fidelity, "re_review", fake)
    res = types.SimpleNamespace(fidelity={"verdict_2": "NOT READY", "reviewed_tree_2": "old",
                                          "rounds": 2, "verdict_path": "/v1"})
    runner._repin_fidelity_after_same_run(*_repin_args(res, []))

    assert seen["round_num"] == fidelity.MAX_FIDELITY_ROUNDS + 1
    assert seen["pr_body"] == "fresh body"
    assert seen["remediation_sha"] == "fixsha"
    assert seen["verdict1_path"] == "/v1"
    assert res.fidelity["verdict_2"] == "READY"
    assert res.fidelity["reviewed_tree_2"] == "newtree"
    assert res.fidelity["same_run_repin"] == {"sha": "fixsha", "verdict": "READY",
                                              "verdict_path": "/p"}
    assert res.fidelity["rounds"] == 2                 # loop bookkeeping untouched


def test_repin_indeterminate_leaves_verdict_2_stale(monkeypatch):
    monkeypatch.setattr(runner.fidelity, "re_review", lambda *a, **k: (None, None, None))
    res = types.SimpleNamespace(fidelity={"verdict_2": "READY", "reviewed_tree_2": "old"})
    logs = []
    runner._repin_fidelity_after_same_run(*_repin_args(res, logs))

    assert res.fidelity["verdict_2"] == "READY"
    assert res.fidelity["reviewed_tree_2"] == "old"
    assert "same_run_repin" not in res.fidelity
    assert any("condition (12) falls back to verdict 1" in m for m in logs)
