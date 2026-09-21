"""Phase 5.5 round taxonomy: bounded retry, per-round model, per-round record.

A transient round failure retries once at the same model and the loop continues; a
terminal one breaks with the tree left local. Escalation is across rounds, never
across retries, and no retry can push the loop past MAX_FIDELITY_ROUNDS.
"""
from __future__ import annotations

import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger

import runner

TREE_1 = "a" * 40
TREE_2 = "b" * 40
TREE_3 = "c" * 40
TREE_4 = "d" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


def _plan(auto_merge_false=False):
    return types.SimpleNamespace(found=False, path="", auto_merge_false=auto_merge_false)


class _Res:
    def __init__(self):
        self.fidelity = {}
        self.adr_drafted = False
        self.adr_path = None
        self.adr_draft_model = None


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


class _CountingGit(ReplayGit):
    """Returns a distinct sha per commit so rounds are distinguishable."""

    def __init__(self, fixture, touched=("ibl5/x.php",), commit_returns=None):
        super().__init__(fixture)
        self._touched = list(touched)
        self._commit_returns = list(commit_returns or [])

    def changed_files(self, base="origin/master"):
        return list(self._touched)

    def commit_all(self, message):
        super().commit_all(message)
        n = len(self.commit_messages)
        if self._commit_returns:
            i = min(n - 1, len(self._commit_returns) - 1)
            return self._commit_returns[i]
        return f"round-sha-{n}"


def _counting_git(**kw):
    return _CountingGit({"slug": "demo", "worktree_diff": "",
                         "diff": "diff --git a/x b/x\n",
                         "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4]}, **kw)


class _ScriptedLlm(FixtureLlm):
    """Serves a QUEUE of responses per purpose, and records (purpose, model).

    The last entry repeats once the queue is spent, so a test that wants "forever"
    writes one entry rather than a count that has to track the round cap.
    """

    def __init__(self, ledger, scripts):
        super().__init__(ledger, {k: v[0] for k, v in scripts.items()})
        self.scripts = {k: list(v) for k, v in scripts.items()}
        self.calls: list[tuple[str, str]] = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.calls.append((purpose, model))
        queue = self.scripts.get(purpose)
        if queue is None:
            raise HarnessError("llm-fixture-missing", purpose)
        val = queue.pop(0) if len(queue) > 1 else queue[0]
        if isinstance(val, dict) and "raise" in val:
            raise HarnessError(val["raise"]["kind"], val["raise"].get("detail", ""))
        return val


def _fixer_calls(llm):
    return [c for c in llm.calls if c[0] == "fidelity-remediation"]


def _drive(tmp_path, llm, git, pr, log=None, **kw):
    res = _Res()
    runner._run_fidelity(
        llm, str(tmp_path), str(tmp_path), git, RecordingGh(str(tmp_path)), _plan(),
        "diff", "body", pr, "dead" * 10, TREE_1, False,
        log if log is not None else (lambda _m: None), res, **kw)
    return res


NOT_READY = "6d checks\n\n- finding one\n\nNOT READY\n"


# --- transient kinds retry once, then the round continues ---------------------

@pytest.mark.parametrize("kind", ["llm-invalid-output", "llm-tooled-cli",
                                  "llm-tooled-empty"])
def test_transient_llm_error_is_retried_once_then_round_continues(tmp_path, git_shim,
                                                                  kind):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": [{"raise": {"kind": kind, "detail": "flaked"}}, "edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9301)
        assert len(_fixer_calls(llm)) == 2
        assert res.fidelity["rounds"][0]["retries"] == 1
        assert res.fidelity["rounds"][0]["outcome"] == "committed"
        assert res.fidelity["verdict_2"] == "READY"
    finally:
        _cleanup(9301, "9301-2")


def test_no_edits_twice_moves_to_round_two_on_opus(tmp_path, git_shim):
    """A round the fixer left empty escalates; the retry inside it does not."""
    git = _counting_git(commit_returns=["", "", "round-sha-3"])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    try:
        res = _drive(tmp_path, llm, git, 9302)
        rounds = res.fidelity["rounds"]
        assert rounds[0]["retries"] == 1
        assert rounds[0]["outcome"] == "no-edits"
        assert rounds[0]["model"] == "sonnet"
        assert rounds[1]["model"] == "opus"
        assert [m for _p, m in _fixer_calls(llm)] == ["sonnet", "sonnet", "opus"]
        assert res.fidelity["models"] == ["sonnet", "opus"]
        assert res.fidelity["rounds_completed"] == 1
    finally:
        _cleanup(9302, "9302-2")


def test_divergent_live_body_alone_is_not_a_body_only_round(tmp_path, git_shim):
    """A live body that merely differs from the harness's copy is not agent work.

    Pins the comparison semantics Phase 3 introduces: before-attempt vs after-attempt,
    never live-body vs the `body` parameter. RecordingGh with no fixture answers
    pr_body() with "" while callers pass a non-empty `body`, so a parameter-based
    comparison reports a phantom body-only round on every empty remediation.
    """
    gh = RecordingGh(str(tmp_path), fixture={"body": "live-and-static", "pr_number": 9403})
    git = _counting_git(commit_returns=[""])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["looked, changed nothing"],
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "harness-copy-of-body", 9403, "dead" * 10,
                             TREE_1, False, lambda _m: None, res)
        rounds = res.fidelity["rounds"]
        assert res.fidelity["rounds_completed"] == 0
        assert all(r["outcome"] == "no-edits" for r in rounds)
        assert res.fidelity["verdict_2"] is None
    finally:
        _cleanup(9403, "9403-2")


# --- terminal kinds break after one call --------------------------------------

@pytest.mark.parametrize("kind", ["rebase-conflict", "push-retry-cap", "local-gate",
                                  "gate-path-edit"])
def test_each_terminal_kind_breaks(tmp_path, git_shim, kind):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": [{"raise": {"kind": kind, "detail": "no"}}],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9303)
        assert len(_fixer_calls(llm)) == 1
        assert res.fidelity["rounds"][0]["outcome"] == kind
        assert res.fidelity["rounds_completed"] == 0
        assert res.fidelity["remediation_sha"] is None
    finally:
        _cleanup(9303, "9303-2")


def test_push_failed_still_propagates(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": [{"raise": {"kind": "push-failed", "detail": "no"}}],
    })
    try:
        with pytest.raises(HarnessError) as ei:
            _drive(tmp_path, llm, _counting_git(), 9304)
        assert ei.value.kind == "push-failed"
    finally:
        _cleanup(9304, "9304-2")


def test_gate_path_edit_is_terminal_via_path_check(tmp_path, git_shim):
    """The path check inside remediate is what produces the kind; the loop breaks on it."""
    git = _counting_git(touched=["bin/check-plan"])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
    })
    try:
        res = _drive(tmp_path, llm, git, 9305)
        assert res.fidelity["rounds"][0]["outcome"] == "gate-path-edit"
        assert git.pushes == 0
        assert len(_fixer_calls(llm)) == 1
    finally:
        _cleanup(9305, "9305-2")


def test_retries_never_exceed_max_rounds(tmp_path, git_shim):
    """Every round spends its retry and never commits: the cap still holds."""
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": [{"raise": {"kind": "llm-tooled-cli", "detail": "x"}}],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9306)
        assert len(_fixer_calls(llm)) == 2 * fidelity.MAX_FIDELITY_ROUNDS
        assert len(res.fidelity["rounds"]) == fidelity.MAX_FIDELITY_ROUNDS
        assert res.fidelity["rounds_completed"] == 0
    finally:
        _cleanup(9306, "9306-2")


# --- re-review retry ----------------------------------------------------------

def test_re_review_none_is_retried_exactly_once_then_breaks(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": [{"raise": {"kind": "llm-tooled-cli",
                                                 "detail": "x"}}],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9307)
        assert len([c for c in llm.calls if c[0] == "plan-fidelity-re-review-2"]) == 2
        assert res.fidelity["rounds"][0]["outcome"] == "re-review-indeterminate"
        assert res.fidelity["rounds"][0]["retries"] == 1
        assert len(res.fidelity["rounds"]) == 1
        assert res.fidelity["verdict_2"] is None
    finally:
        _cleanup(9307, "9307-2")


def test_re_review_none_then_ready_continues(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": [{"raise": {"kind": "llm-tooled-cli", "detail": "x"}},
                                      "READY\n"],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9308)
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["rounds_completed"] == 1
        assert res.fidelity["rounds"][0]["retries"] == 1
    finally:
        _cleanup(9308, "9308-2")


# --- the per-round record -----------------------------------------------------

def test_round_records_carry_model_sizes_retries_outcome(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9309,
                     meta_check_failures=[{"name": "check-docs", "output": "FAIL"}])
        rec = res.fidelity["rounds"][0]
        assert set(rec) == {"round", "model", "work_list_sizes", "retries", "outcome",
                            "remediation_sha", "verdict", "reviewed_tree",
                            "verdict_path"}
        assert rec["work_list_sizes"]["16"] == 1
        assert rec["model"] == "sonnet"
        assert rec["outcome"] == "committed"
        assert res.fidelity["models"] == ["sonnet"]
    finally:
        _cleanup(9309, "9309-2")


def test_rounds_completed_counts_only_committed_rounds(tmp_path, git_shim):
    git = _counting_git(commit_returns=["", "", "round-sha-3"])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    try:
        res = _drive(tmp_path, llm, git, 9310)
        assert len(res.fidelity["rounds"]) == 2
        assert res.fidelity["rounds_completed"] == 1
    finally:
        _cleanup(9310, "9310-2")


def test_audit_line_names_model_and_outcome(tmp_path, git_shim):
    logged: list[str] = []
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    try:
        _drive(tmp_path, llm, _counting_git(), 9311, log=logged.append)
        assert any(
            l.startswith("phase5.5 round 1: model=sonnet retries=0 outcome=committed")
            for l in logged), logged
    finally:
        _cleanup(9311, "9311-2")


# --- replay of the PR #2314 raw fixer output ----------------------------------

FIXTURE_2314 = os.environ.get("PR2314_RUN_DIR") or (
    "/Users/ajaynicolas/GitHub/IBL5/tools/postplan-harness/out/"
    "live-harness-conflict-autoresolve-20260920-145236-48667")


def test_replay_pr2314_round_one_completes(tmp_path, git_shim):
    """The run where 0 of 3 rounds completed now completes round 1 in one spawn.

    The run dir is a live-run artifact under the main checkout's gitignored out/, so
    CI has nothing to replay and the row skips there.
    """
    raw = os.path.join(FIXTURE_2314, "raw-fidelity-remediation-attempt0.txt")
    if not os.path.exists(raw):
        pytest.skip(f"PR #2314 run dir absent: {FIXTURE_2314}")
    with open(raw) as fh:
        fixer_output = fh.read()
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": ["6d checks\n\n- finding one\n\nNOT READY\n"],
        "fidelity-remediation": [fixer_output],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    git = _counting_git()
    try:
        res = _drive(tmp_path, llm, git, 9314,
                     meta_check_failures=[{"name": "check-docs",
                                           "output": "FAIL  check-docs"}])
        rec = res.fidelity["rounds"][0]
        assert rec["outcome"] == "committed"
        assert rec["model"] == "sonnet"
        assert rec["work_list_sizes"]["16"] == 1
        assert res.fidelity["verdict_2"] == "READY"
        assert git.pushes == 1
        # A round that lands first time never spends its retry spawn.
        assert len(_fixer_calls(llm)) == 1
    finally:
        _cleanup(9314, "9314-2")


def test_scored_findings_reach_work_list_via_before_remediation(tmp_path, git_shim):
    """Hold (2) items must come from res.scored_findings set by before_remediation.

    before_remediation runs before the loop; the fix reads res.scored_findings inside
    the loop rather than the call-site binding, which was still empty at that point.
    """
    high_score_finding = {"score": 85, "path": "x.py", "line": 1, "body_head": "bad"}
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": ["6d checks\n\n- finding one\n\nNOT READY\n"],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    res = _Res()

    def _set_scored():
        res.scored_findings = [high_score_finding]

    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), _counting_git(),
            RecordingGh(str(tmp_path)), _plan(),
            "diff", "body", 9315, "dead" * 10, TREE_1, False,
            lambda _m: None, res,
            before_remediation=_set_scored,
        )
        assert res.fidelity["rounds"][0]["work_list_sizes"]["2"] == 1
    finally:
        _cleanup(9315, "9315-2")
