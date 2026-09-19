"""Tests for the bounded push-retry and BEHIND-resolution helpers.

Covers Phases 1-6 of the harness-behind-lease-retry plan:
  - is_stale_lease classifier
  - _push_with_lease_retry (stale-lease loop, cap, terminal kinds, push-disabled)
  - _refresh_and_reprove (lostwork proof gate)
  - _resolve_behind (BEHIND loop, strict flag, CI red, cap + disarm)
  - verdict_line and terminal-state branches for retry caps
  - LiveGit.push() first-push zero-sha lease (real git)
"""
from __future__ import annotations

import os
import subprocess
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit, is_stale_lease
from harness.adapters.ghad import LiveGh
from harness.ciwatch import CiOutcome
from harness.state import ArmDecision, HarnessError, RunResult, TerminalState


# ---------------------------------------------------------------------------
# Fakes
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


class FakeGh:
    def __init__(self, states=("CLEAN",), strict=True):
        self.states = list(states)
        self.strict = strict
        self.reads = 0
        self.disarms = 0

    def branch_protection_strict(self): return self.strict

    def merge_state_status(self, pr=None):
        s = self.states[min(self.reads, len(self.states) - 1)]
        self.reads += 1
        return s

    def pr_disable_auto_merge(self, pr): self.disarms += 1


def _noop_log(msg): pass


def _make_ci_patches(monkeypatch, outcomes=None):
    """Patch ciwatch so _resolve_behind does not need a live CI system.
    Returns a list that receives each sha passed to start_background_watch."""
    watched_shas = []
    outcome_seq = list(outcomes or [CiOutcome(0, [])])
    call_count = [0]

    def fake_start(worktree, pr, sha, out_dir):
        watched_shas.append(sha)
        return object()

    def fake_watch(worktree, pr, sha, out_dir, bg):
        idx = call_count[0]
        call_count[0] += 1
        return outcome_seq[min(idx, len(outcome_seq) - 1)]

    def fake_reap(bg, timeout=None): pass

    monkeypatch.setattr(runner.ciwatch, "start_background_watch", fake_start)
    monkeypatch.setattr(runner.ciwatch, "watch_or_reuse", fake_watch)
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", fake_reap)
    return watched_shas


# ---------------------------------------------------------------------------
# is_stale_lease
# ---------------------------------------------------------------------------

def test_is_stale_lease_rejects_terminal_kinds():
    assert is_stale_lease(HarnessError("push-failed", "! [rejected] branch (stale info)")) is True
    assert is_stale_lease(HarnessError("push-failed", "detached HEAD: refusing to push")) is False
    assert is_stale_lease(HarnessError("local-gate", "stale info")) is False
    assert is_stale_lease(HarnessError("push-disabled", "no push remote")) is False
    assert is_stale_lease(HarnessError("push-failed", "stale-lease: origin holds")) is True
    assert is_stale_lease(HarnessError("push-failed", "non-fast-forward")) is True


# ---------------------------------------------------------------------------
# _push_with_lease_retry
# ---------------------------------------------------------------------------

def test_stale_lease_once_then_ok():
    stale = HarnessError("push-failed", "! [rejected] wt-slug (stale info)")
    shas = ["aaa" + "0" * 37, "bbb" + "0" * 37]
    git = FakeGit(push_errors=[stale], head_shas=shas)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert git.pushes == 2
    assert git.rebases == 1
    assert result == shas[1]


def test_stale_lease_three_times_cap():
    stale = HarnessError("push-failed", "! [rejected] wt-slug (stale info)")
    git = FakeGit(push_errors=[stale, stale, stale])
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "push-retry-cap"
    assert git.pushes == 3
    assert git.rebases == 2


def test_push_failed_detached_head_not_retried():
    err = HarnessError("push-failed", "detached HEAD: refusing to push without a branch name")
    git = FakeGit(push_errors=[err])
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "push-failed"
    assert git.pushes == 1
    assert git.rebases == 0


def test_push_disabled_returns_empty():
    err = HarnessError("push-disabled", "no isolated push remote configured")
    git = FakeGit(push_errors=[err])
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert result == ""
    assert git.pushes == 1


def test_push_blocked_when_lostwork_unproved():
    stale = HarnessError("push-failed", "! [rejected] wt-slug (stale info)")
    git = FakeGit(push_errors=[stale, stale, stale], proof_ok=False)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "lostwork-unproved"
    assert git.pushes == 1


# ---------------------------------------------------------------------------
# _resolve_behind
# ---------------------------------------------------------------------------

def test_behind_once_then_clean(monkeypatch):
    git = FakeGit(head_shas=["old" + "0" * 37, "new" + "0" * 37])
    gh = FakeGh(states=["BEHIND", "CLEAN"], strict=True)
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    watched_shas = _make_ci_patches(monkeypatch, outcomes=[CiOutcome(0, [])])
    initial_outcome = CiOutcome(0, [])
    sha, outcome = runner._resolve_behind(
        git, gh, _noop_log, res, "/wt", 1, "old" + "0" * 37, initial_outcome, "/out"
    )
    assert git.rebases == 1
    assert git.pushes == 1
    assert len(watched_shas) == 1
    assert watched_shas[0] != "old" + "0" * 37
    assert res.retry_cap is None
    assert gh.disarms == 0


def test_behind_three_times_cap(monkeypatch):
    git = FakeGit(head_shas=["sha" + "0" * 37] * 4)
    gh = FakeGh(states=["BEHIND"] * 10, strict=True)
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    _make_ci_patches(monkeypatch, outcomes=[CiOutcome(0, [])] * 4)
    initial_outcome = CiOutcome(0, [])
    runner._resolve_behind(
        git, gh, _noop_log, res, "/wt", 1, "sha" + "0" * 37, initial_outcome, "/out"
    )
    assert git.rebases == 3
    assert res.retry_cap == "behind-retry-cap"
    assert gh.disarms == 1


def test_behind_non_strict_no_rebase(monkeypatch):
    git = FakeGit()
    gh = FakeGh(states=["BEHIND"], strict=False)
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    _make_ci_patches(monkeypatch)
    initial_outcome = CiOutcome(0, [])
    runner._resolve_behind(
        git, gh, _noop_log, res, "/wt", 1, "sha", initial_outcome, "/out"
    )
    assert git.rebases == 0
    assert git.pushes == 0
    assert gh.disarms == 0


def test_behind_ci_red_after_rebase(monkeypatch):
    git = FakeGit(head_shas=["sha0" + "0" * 36, "sha1" + "0" * 36])
    gh = FakeGh(states=["BEHIND"] * 5, strict=True)
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    _make_ci_patches(monkeypatch, outcomes=[CiOutcome(8, ["check-A"])])
    initial_outcome = CiOutcome(0, [])
    sha, outcome = runner._resolve_behind(
        git, gh, _noop_log, res, "/wt", 1, "sha0" + "0" * 36, initial_outcome, "/out"
    )
    assert git.rebases == 1
    assert res.retry_cap is None
    assert gh.disarms == 0
    assert outcome.exit_code == 8


def test_behind_loop_skipped_when_held():
    """_should_resolve_behind is False when armed=False; merge state is never read."""
    outcome = CiOutcome(0, [])
    assert not runner._should_resolve_behind(True, 1, ArmDecision(armed=False), outcome)
    assert runner._should_resolve_behind(True, 1, ArmDecision(armed=True), outcome)


def test_merge_state_read_failure_breaks_loop(tmp_path):
    """LiveGh.merge_state_status must fail open (return '') on any exception."""
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: (_ for _ in ()).throw(HarnessError("gh", "HTTP 403"))
    result = gh.merge_state_status(7)
    assert result == ""


# ---------------------------------------------------------------------------
# LiveGh.branch_protection_strict — fail-closed
# ---------------------------------------------------------------------------

def test_behind_403_protection_read(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: (_ for _ in ()).throw(HarnessError("gh", "HTTP 403"))
    assert gh.branch_protection_strict() is True


def test_branch_protection_strict_false_when_api_returns_false(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: "false\n"
    assert gh.branch_protection_strict() is False


def test_branch_protection_strict_null_fails_closed(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: "null\n"
    assert gh.branch_protection_strict() is True


# ---------------------------------------------------------------------------
# verdict_line for cap paths
# ---------------------------------------------------------------------------

def test_verdict_push_retry_cap():
    res = RunResult(
        terminal=TerminalState.FAILED,
        error_kind="push-retry-cap",
        error="phase2: stale lease after 3 push attempts: rejected\nsecond line",
    )
    line = runner.verdict_line(res, rc=1)
    assert "\n" not in line
    assert "BLOCKED" in line
    assert "push retry cap reached" in line


def test_verdict_behind_retry_cap():
    res = RunResult(
        terminal=TerminalState.SHIPPED_HELD,
        retry_cap="behind-retry-cap",
        findings=[],
    )
    line = runner.verdict_line(res, rc=0)
    assert "BLOCKED" in line
    assert "BEHIND retry cap reached" in line
    assert "auto-merge=armed" not in line


def test_retry_cap_beats_armed():
    """_compute_terminal returns SHIPPED_HELD when retry_cap is set, even when armed=True."""
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED, retry_cap="behind-retry-cap")
    assert runner._compute_terminal(res, armed=True) == TerminalState.SHIPPED_HELD
    res_no_cap = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    assert runner._compute_terminal(res_no_cap, armed=True) == TerminalState.SHIPPED_ARMED


# ---------------------------------------------------------------------------
# test_no_upstream_branch — real git
# ---------------------------------------------------------------------------

def test_no_upstream_branch(tmp_path):
    """First push to an empty bare remote uses a zero-sha lease."""
    worktree = tmp_path / "wt"
    bare = tmp_path / "bare"
    worktree.mkdir()
    bare.mkdir()

    env = {**os.environ,
           "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}

    def sh(*args):
        subprocess.run(list(args), check=True, capture_output=True, env=env)

    sh("git", "init", "-b", "my-branch", str(worktree))
    sh("git", "-C", str(worktree), "config", "user.email", "t@t")
    sh("git", "-C", str(worktree), "config", "user.name", "t")
    (worktree / "file.txt").write_text("hello\n")
    sh("git", "-C", str(worktree), "add", "-A")
    sh("git", "-C", str(worktree), "commit", "-m", "init")
    sh("git", "init", "--bare", str(bare))
    sh("git", "-C", str(worktree), "remote", "add", "origin", str(bare))

    git = LiveGit(str(worktree), push_remote="origin")
    recorded_args = []
    original_run_out = git._run_out

    def spy_run_out(*args):
        recorded_args.append(args)
        return original_run_out(*args)

    git._run_out = spy_run_out
    git.push()

    push_args = [a for a in recorded_args if a and a[0] == "push"]
    assert push_args, "no push argv recorded"
    push_argv = push_args[0]
    zero_sha = "0" * 40
    assert any(f"--force-with-lease=my-branch:{zero_sha}" in str(a) for a in push_argv), \
        f"expected zero-sha lease in push argv, got: {push_argv}"
    assert any("HEAD:refs/heads/my-branch" in str(a) for a in push_argv), \
        f"expected explicit refspec in push argv, got: {push_argv}"

    result = subprocess.run(
        ["git", "-C", str(bare), "rev-parse", "refs/heads/my-branch"],
        capture_output=True, text=True,
    )
    assert result.returncode == 0
    local_head = subprocess.run(
        ["git", "-C", str(worktree), "rev-parse", "HEAD"],
        capture_output=True, text=True,
    ).stdout.strip()
    assert result.stdout.strip() == local_head
