"""Tests for stale-base push retry and _run_fidelity integration.

Covers:
  - classify_local_gate_denial / is_stale_base classification
  - _STALE_BASE_MARKER / _GATE_CLASSES declarations
  - _push_with_lease_retry: stale-base recovery, cap, ADR non-retry
  - _run_fidelity: round survives a stale-base push, rebase conflict leaves commit local
  - _GATE_REMEDY["stale-base"] content and verdict_line rendering
"""
from __future__ import annotations

import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from harness.adapters import gitad
from harness.adapters.gitad import (
    ReplayGit,
    _GATE_CLASSES,
    _STALE_BASE_MARKER,
    classify_local_gate_denial,
    is_stale_base,
)
from harness.adapters.ghad import RecordingGh
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger

# ---------------------------------------------------------------------------
# Hook text fixtures (exact text bin/pre-push-adr-hook emits, as LiveGit wraps it)
# ---------------------------------------------------------------------------

_STALE_BASE_TEXT = (
    "git push: pre-push-adr-hook: branch does not contain origin/master.\n"
    "Fetch and merge before pushing:\n"
    "  git fetch origin master && git merge origin/master"
)

_ADR_TEXT = (
    "git push: pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR.\n"
    "Resolve with ONE of:\n"
    '  1. Add an ADR under ibl5/docs/decisions/ (run: bin/next-adr "kebab-title").'
)

# ---------------------------------------------------------------------------
# FakeGit (copied from test_behind_lease_retry.py pattern)
# ---------------------------------------------------------------------------

class FakeGit:
    def __init__(self, push_errors=None, head_shas=None, proof_ok=True):
        self.push_errors = list(push_errors or [])
        self.head_shas = list(head_shas or ["a" * 40])
        self.proof_ok = proof_ok
        self.pushes = 0
        self.rebases = 0
        self.fetches = 0
        self.proofs = 0

    def branch(self): return "wt-slug"
    def head(self): return self.head_shas[min(self.rebases, len(self.head_shas) - 1)]

    def push(self):
        err = self.push_errors[self.pushes] if self.pushes < len(self.push_errors) else None
        self.pushes += 1
        if err:
            raise err

    def capture_lostwork_pre(self, key): return True
    def fetch_base(self, base="origin/master"): self.fetches += 1
    def rebase_onto(self, base="origin/master"): self.rebases += 1

    def prove_lostwork(self, key):
        self.proofs += 1
        return (True, "TREE-EQUIVALENT") if self.proof_ok else (False, "TREE DIVERGED")


def _noop_log(msg): pass


# ---------------------------------------------------------------------------
# git shim for subprocess calls inside fidelity.build_packet / fidelity.remediate
# ---------------------------------------------------------------------------

_GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


def _install_git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    g = bindir / "git"
    g.write_text(_GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")


# ---------------------------------------------------------------------------
# ReplayGit subclass that simulates stale-base push + fetch/rebase/prove
# ---------------------------------------------------------------------------

class _StaleBaseGit(ReplayGit):
    """ReplayGit extended with fetch/rebase/prove and stale-base push simulation.

    push() raises a stale-base local-gate on the first call; subsequent calls
    succeed (return None).  head() returns 'rebased-<commit-sha>' once a
    rebase has been recorded, else the bare commit sha.
    """

    def __init__(self, fixture, rebase_ok=True):
        super().__init__(fixture)
        self._push_call_count = 0
        self._rebase_count = 0
        self._commit_count = 0
        self._current_sha = fixture.get("initial_sha", "initial-" + "0" * 34)
        self.fetches = 0
        self.rebase_ok = rebase_ok

    # Override commit_all to return a distinct sha per commit.
    def commit_all(self, message):
        self._commit_count += 1
        self._current_sha = f"commit-sha-{self._commit_count}"
        return self._current_sha

    def head(self):
        return self._current_sha

    # Methods required by _refresh_and_reprove.
    def capture_lostwork_pre(self, key):
        return True

    def fetch_base(self, base="origin/master"):
        self.fetches += 1

    def rebase_onto(self, base="origin/master"):
        if not self.rebase_ok:
            raise HarnessError("rebase-conflict", "CONFLICT (content): x")
        self._rebase_count += 1
        if self._current_sha:
            self._current_sha = f"rebased-{self._current_sha}"

    def prove_lostwork(self, key):
        return (True, "TREE-EQUIVALENT")

    def push(self):
        idx = self._push_call_count
        self._push_call_count += 1
        if idx == 0:
            raise HarnessError("local-gate", _STALE_BASE_TEXT)
        # subsequent calls succeed


# ---------------------------------------------------------------------------
# Helpers for _run_fidelity tests
# ---------------------------------------------------------------------------

def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


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


# ---------------------------------------------------------------------------
# Test 1: classify_stale_base_beats_adr_prefix
# ---------------------------------------------------------------------------

def test_classify_stale_base_beats_adr_prefix():
    """stale-base text classifies as 'stale-base'; ADR text classifies as 'adr'."""
    assert classify_local_gate_denial(_STALE_BASE_TEXT) == "stale-base"
    assert classify_local_gate_denial(_ADR_TEXT) == "adr"

    # is_stale_base: True for local-gate + stale text
    assert is_stale_base(HarnessError("local-gate", _STALE_BASE_TEXT)) is True

    # is_stale_base: False for local-gate + ADR text
    assert is_stale_base(HarnessError("local-gate", _ADR_TEXT)) is False

    # is_stale_base: False for wrong kind even with stale text
    assert is_stale_base(HarnessError("push-failed", _STALE_BASE_TEXT)) is False


# ---------------------------------------------------------------------------
# Test 2: _STALE_BASE_MARKER is declared correctly
# ---------------------------------------------------------------------------

def test_stale_base_marker_is_declared_hook_marker():
    """_STALE_BASE_MARKER appears in _LOCAL_GATE_MARKERS and is FIRST in _GATE_CLASSES."""
    assert _STALE_BASE_MARKER in gitad._LOCAL_GATE_MARKERS
    assert _GATE_CLASSES[0] == ("stale-base", _STALE_BASE_MARKER)


# ---------------------------------------------------------------------------
# Test 3: _push_with_lease_retry recovers from one stale-base error
# ---------------------------------------------------------------------------

def test_push_retry_recovers_from_stale_base():
    """One stale-base error: recovery fetches + rebases + proves, then pushes."""
    stale = HarnessError("local-gate", _STALE_BASE_TEXT)
    shas = ["aaa" + "0" * 37, "bbb" + "0" * 37]
    git = FakeGit(push_errors=[stale], head_shas=shas)
    logged = []
    result = runner._push_with_lease_retry(git, logged.append, "phase5.5")

    assert result == shas[1]  # head after rebase
    assert git.pushes == 2
    assert git.fetches == 1
    assert git.rebases == 1
    assert git.proofs == 1
    assert any("stale base" in line for line in logged)


# ---------------------------------------------------------------------------
# Test 4: _push_with_lease_retry hits the cap on three stale-base errors
# ---------------------------------------------------------------------------

def test_push_retry_stale_base_cap():
    """Three stale-base errors exhaust the cap and raise push-retry-cap."""
    stale = HarnessError("local-gate", _STALE_BASE_TEXT)
    git = FakeGit(push_errors=[stale, stale, stale])
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase5.5")
    assert exc_info.value.kind == "push-retry-cap"
    assert "stale base" in (exc_info.value.detail or "")
    # Three pushes, two rebases (no rebase after the third/final failed attempt)
    assert git.pushes == 3
    assert git.rebases == 2


# ---------------------------------------------------------------------------
# Test 5: ADR local-gate denial is NOT retried
# ---------------------------------------------------------------------------

def test_push_retry_adr_denial_is_not_retried():
    """An ADR local-gate denial re-raises immediately; no rebase is attempted."""
    adr_err = HarnessError("local-gate", _ADR_TEXT)
    git = FakeGit(push_errors=[adr_err])
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase5.5")
    assert exc_info.value.kind == "local-gate"
    assert _ADR_TEXT in (exc_info.value.detail or "")
    assert git.rebases == 0
    assert git.pushes == 1


# ---------------------------------------------------------------------------
# Test 6: _run_fidelity round survives a stale-base push
# ---------------------------------------------------------------------------

_PR_STALE_BASE = 9910
_TREE_A = "a" * 40


def test_run_fidelity_round_survives_stale_base(tmp_path, monkeypatch):
    """A stale-base during Phase 5.5 push triggers fetch+rebase; the round completes."""
    _install_git_shim(tmp_path, monkeypatch)

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nREADY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _StaleBaseGit({
        "slug": "demo",
        "worktree_diff": "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": [_TREE_A],
    }, rebase_ok=True)
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    logged = []

    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff --git a/x b/x\n", "body", _PR_STALE_BASE, "dead" * 10,
            _TREE_A, False, logged.append, res,
        )
        assert res.fidelity["rounds_completed"] == 1
        rsha = res.fidelity["remediation_sha"]
        assert rsha is not None
        assert rsha.startswith("rebased-"), f"expected rebased sha, got {rsha!r}"
        round0_sha = res.fidelity["rounds"][0]["remediation_sha"]
        assert round0_sha.startswith("rebased-"), f"expected rebased round sha, got {round0_sha!r}"
        assert res.fidelity["remediation_sha"] == round0_sha
        assert any("stale base" in line and "phase5.5" in line for line in logged), (
            f"no 'phase5.5: stale base' log line found; logged: {logged}"
        )
    finally:
        _cleanup(_PR_STALE_BASE, f"{_PR_STALE_BASE}-2")


# ---------------------------------------------------------------------------
# Test 7: rebase conflict during push leaves the commit local
# ---------------------------------------------------------------------------

_PR_REBASE_CONFLICT = 9911


def test_run_fidelity_rebase_conflict_leaves_commit_local(tmp_path, monkeypatch):
    """A rebase-conflict during Phase 5.5 push: loop breaks, no raise, commit is local."""
    _install_git_shim(tmp_path, monkeypatch)

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        # re-review-2 should never be called; omit it to surface any unexpected call
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _StaleBaseGit({
        "slug": "demo",
        "worktree_diff": "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": [_TREE_A],
    }, rebase_ok=False)  # rebase_onto raises rebase-conflict
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    logged = []

    try:
        # Must not raise
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff --git a/x b/x\n", "body", _PR_REBASE_CONFLICT, "dead" * 10,
            _TREE_A, False, logged.append, res,
        )
        assert res.fidelity["rounds_completed"] == 0
        assert res.fidelity["remediation_sha"] is None
        conflict_lines = [
            l for l in logged
            if "remediation unavailable (rebase-conflict)" in l and "is LOCAL and unpushed" in l
        ]
        assert conflict_lines, (
            f"expected a log line with both 'remediation unavailable (rebase-conflict)' "
            f"and 'is LOCAL and unpushed'; logged:\n" + "\n".join(logged)
        )
    finally:
        _cleanup(_PR_REBASE_CONFLICT, f"{_PR_REBASE_CONFLICT}-2")


# ---------------------------------------------------------------------------
# Test 8: _GATE_REMEDY["stale-base"] and verdict_line rendering
# ---------------------------------------------------------------------------

def test_gate_remedy_names_stale_base():
    """_GATE_REMEDY has a 'stale-base' key that mentions git fetch origin master."""
    assert "stale-base" in runner._GATE_REMEDY
    remedy_text = runner._GATE_REMEDY["stale-base"]
    assert "git fetch origin master" in remedy_text, (
        f"_GATE_REMEDY['stale-base'] does not mention 'git fetch origin master': {remedy_text!r}"
    )

    # verdict_line renders class=stale-base and the remedy for a matching local-gate error.
    # Pattern matches test_verdict_line.py's _local_gate_res + verdict_line calls.
    stale_error = (
        "local-gate: git push: pre-push-adr-hook: branch does not contain origin/master.\n"
        "Fetch and merge before pushing:\n"
        "  git fetch origin master && git merge origin/master"
    )
    r = RunResult(
        terminal=TerminalState.FAILED,
        error_kind="local-gate",
        error=stale_error,
    )
    line = runner.verdict_line(r, 3)
    assert "[class=stale-base]" in line, f"expected [class=stale-base] in: {line!r}"
    assert "git fetch origin master" in line, (
        f"expected 'git fetch origin master' (from _GATE_REMEDY) in: {line!r}"
    )
