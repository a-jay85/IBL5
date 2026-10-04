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
import shutil
import subprocess
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import gitutil
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
# LiveGh.aggregator_required — fail-closed
# ---------------------------------------------------------------------------

def test_aggregator_required_true_when_context_listed(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: "Other check\nAll checks green\n"
    assert gh.aggregator_required("All checks green") is True


def test_aggregator_required_false_when_context_absent(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: "Other check\n"
    assert gh.aggregator_required("All checks green") is False


def test_aggregator_required_false_on_api_error(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: (_ for _ in ()).throw(HarnessError("gh", "HTTP 403"))
    assert gh.aggregator_required("All checks green") is False


def test_aggregator_required_exact_match(tmp_path):
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: "All checks green \n"
    assert gh.aggregator_required("All checks green") is False


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


# ---------------------------------------------------------------------------
# Real-git side assertions (issues #827-#830)
# ---------------------------------------------------------------------------

_GIT_ENV = {**os.environ,
            "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
            "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _sh(*args):
    return subprocess.run(list(args), check=True, capture_output=True, text=True,
                          env=_GIT_ENV).stdout.strip()


def _commit(repo, name, text="x\n"):
    (repo / name).write_text(text)
    _sh("git", "-C", str(repo), "add", "-A")
    _sh("git", "-C", str(repo), "commit", "-m", name)


def _feature_repo(tmp_path):
    """Worktree on `feature` (a commit ahead of master) plus a bare origin holding master."""
    wt, bare = tmp_path / "wt", tmp_path / "bare"
    wt.mkdir()
    bare.mkdir()
    _sh("git", "init", "-b", "master", str(wt))
    _sh("git", "-C", str(wt), "config", "user.email", "t@t")
    _sh("git", "-C", str(wt), "config", "user.name", "t")
    _commit(wt, "base.txt")
    _sh("git", "init", "--bare", str(bare))
    _sh("git", "-C", str(wt), "remote", "add", "origin", str(bare))
    _sh("git", "-C", str(wt), "push", "origin", "master")
    _sh("git", "-C", str(wt), "fetch", "origin", "master")
    _sh("git", "-C", str(wt), "checkout", "-b", "feature")
    _commit(wt, "feat.txt")
    return wt, bare


def _advance_origin_master(tmp_path, bare, extra=None):
    other = tmp_path / "other"
    _sh("git", "clone", "-b", "master", str(bare), str(other))
    _sh("git", "-C", str(other), "config", "user.email", "t@t")
    _sh("git", "-C", str(other), "config", "user.name", "t")
    if extra:
        for rel, text in extra.items():
            p = other / rel
            p.parent.mkdir(parents=True, exist_ok=True)
            p.write_text(text)
        _sh("git", "-C", str(other), "add", "-A", "-f")
        _sh("git", "-C", str(other), "commit", "-m", "extra")
    _commit(other, "master-moved.txt")
    _sh("git", "-C", str(other), "push", "origin", "master")


def _spy_live_git(wt, stub_proof=True):
    git = LiveGit(str(wt), push_remote="origin")
    argvs = []
    orig = git._run_out

    def spy(*args):
        argvs.append(args)
        return orig(*args)

    git._run_out = spy
    if stub_proof:
        # the lostwork.sh gate itself is exercised in test_push_blocked_when_lostwork_unproved_real_gate
        git.capture_lostwork_pre = lambda key: True
        git.prove_lostwork = lambda key: (True, "TREE-EQUIVALENT")
    return git, argvs


def _reject_hook(bare, once):
    """pre-receive hook rejecting with git's stale-lease wording; `once` rejects only the first push."""
    marker = bare / "rejected-once"
    body = "#!/bin/sh\n"
    if once:
        body += f'[ -e "{marker}" ] && exit 0\ntouch "{marker}"\n'
    body += 'echo "! [remote rejected] feature (stale info)" >&2\nexit 1\n'
    hook = bare / "hooks" / "pre-receive"
    hook.write_text(body)
    hook.chmod(0o755)


def _push_argvs(argvs):
    return [a for a in argvs if a and a[0] == "push"]


def test_stale_lease_once_then_ok_real_remote(tmp_path):
    """#827: real remote rejects the first push; the retry lands HEAD on the bare remote
    and both pushes carried the explicit lease + refspec argv."""
    wt, bare = _feature_repo(tmp_path)
    _reject_hook(bare, once=True)
    git, argvs = _spy_live_git(wt)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    local_head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    assert result == local_head
    assert _sh("git", "-C", str(bare), "rev-parse", "refs/heads/feature") == local_head
    pushes = _push_argvs(argvs)
    assert len(pushes) == 2
    for argv in pushes:
        assert f"--force-with-lease=feature:{'0' * 40}" in argv
        assert "HEAD:refs/heads/feature" in argv


def test_stale_lease_three_times_cap_real_remote(tmp_path):
    """#827: a remote that always rejects is pushed exactly 3 times, raises
    push-retry-cap, and the bare remote never receives the branch."""
    wt, bare = _feature_repo(tmp_path)
    _reject_hook(bare, once=False)
    git, argvs = _spy_live_git(wt)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "push-retry-cap"
    assert len(_push_argvs(argvs)) == 3
    probe = subprocess.run(["git", "-C", str(bare), "rev-parse", "--verify", "--quiet",
                            "refs/heads/feature"], capture_output=True, text=True)
    assert probe.returncode != 0, "capped push must leave the remote untouched"


def _publish_feature(wt):
    """Push `feature` so the worktree holds refs/remotes/origin/feature (lease Y)."""
    _sh("git", "-C", str(wt), "push", "origin", "feature")
    return _sh("git", "-C", str(wt), "rev-parse", "refs/remotes/origin/feature")


def _rival_push(tmp_path, bare, *, from_branch, files=None, merge_master=False):
    """Second clone moves origin/feature. Returns the new remote tip Z."""
    rival = tmp_path / "rival"
    _sh("git", "clone", "-b", from_branch, str(bare), str(rival))
    _sh("git", "-C", str(rival), "config", "user.email", "r@r")
    _sh("git", "-C", str(rival), "config", "user.name", "r")
    if from_branch != "feature":
        _sh("git", "-C", str(rival), "checkout", "-b", "feature")
    if merge_master:
        _sh("git", "-C", str(rival), "merge", "--no-ff", "--no-edit", "origin/master")
    for name, text in (files or {}).items():
        _commit(rival, name, text)
    _sh("git", "-C", str(rival), "push", "origin", "HEAD:refs/heads/feature")
    return _sh("git", "-C", str(bare), "rev-parse", "refs/heads/feature")


def _tracking(wt):
    r = subprocess.run(["git", "-C", str(wt), "rev-parse", "--verify", "--quiet",
                        "refs/remotes/origin/feature"], capture_output=True, text=True)
    return r.stdout.strip()


def _bare_feature(bare):
    return _sh("git", "-C", str(bare), "rev-parse", "refs/heads/feature")


def test_lease_owner_update_branch_merge_adopted_real_remote(tmp_path):
    """backlog#1286: the remote branch moved by a GitHub "Update branch" merge of a newer
    master is ours; the retry adopts it as the lease and lands HEAD."""
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    _advance_origin_master(tmp_path, bare)
    z = _rival_push(tmp_path, bare, from_branch="feature", merge_master=True)
    _commit(wt, "local.txt", "mine\n")
    git, argvs = _spy_live_git(wt)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    local_head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    assert result == local_head
    assert _bare_feature(bare) == local_head
    pushes = _push_argvs(argvs)
    assert f"--force-with-lease=feature:{y}" in pushes[0]
    assert f"--force-with-lease=feature:{z}" in pushes[-1]


def test_lease_owner_tree_equivalent_adopted_real_remote(tmp_path):
    """backlog#1286: a remote tip with our exact tree is ours; the retry leases on it."""
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    _commit(wt, "local.txt", "same\n")
    z = _rival_push(tmp_path, bare, from_branch="feature", files={"local.txt": "same\n"})
    git, argvs = _spy_live_git(wt)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    local_head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    assert result == local_head
    assert _bare_feature(bare) == local_head
    pushes = _push_argvs(argvs)
    assert f"--force-with-lease=feature:{y}" in pushes[0]
    assert f"--force-with-lease=feature:{z}" in pushes[-1]


def test_lease_owner_foreign_tip_fails_closed_real_remote(tmp_path):
    """backlog#1286: a foreign commit on the remote branch is never overwritten and never
    becomes the lease: one rejected push, then remote-head-diverged."""
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    z = _rival_push(tmp_path, bare, from_branch="feature", files={"foreign.txt": "theirs\n"})
    _commit(wt, "local.txt", "mine\n")
    git, argvs = _spy_live_git(wt)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "remote-head-diverged"
    assert len(_push_argvs(argvs)) == 1
    assert _bare_feature(bare) == z
    assert _tracking(wt) == y


def test_lease_owner_no_tracking_equivalent_adopted_real_remote(tmp_path):
    """backlog#1286: no tracking ref, remote holds our tree already (pushed elsewhere):
    adopt it from an absent ref and land with one push."""
    wt, bare = _feature_repo(tmp_path)
    z = _rival_push(tmp_path, bare, from_branch="master", files={"feat.txt": "x\n"})
    git, argvs = _spy_live_git(wt)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    local_head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    assert result == local_head
    assert _bare_feature(bare) == local_head
    pushes = _push_argvs(argvs)
    assert len(pushes) == 1
    assert f"--force-with-lease=feature:{z}" in pushes[0]


def test_lease_owner_no_tracking_foreign_fails_closed_real_remote(tmp_path):
    """backlog#1286: no tracking ref and a foreign remote branch: zero pushes, remote
    untouched, tracking ref still absent."""
    wt, bare = _feature_repo(tmp_path)
    z = _rival_push(tmp_path, bare, from_branch="master", files={"foreign.txt": "theirs\n"})
    git, argvs = _spy_live_git(wt)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "remote-head-diverged"
    assert _push_argvs(argvs) == []
    assert _bare_feature(bare) == z
    assert _tracking(wt) == ""


def test_lease_owner_remote_branch_deleted_fails_closed_real_remote(tmp_path):
    """backlog#1286: the remote branch was deleted after we published it; recreating a
    deleted PR branch is never the harness's call."""
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    _sh("git", "-C", str(bare), "update-ref", "-d", "refs/heads/feature")
    _commit(wt, "local.txt", "mine\n")
    git, argvs = _spy_live_git(wt)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "remote-head-diverged"
    assert len(_push_argvs(argvs)) == 1
    gone = subprocess.run(["git", "-C", str(bare), "rev-parse", "--verify", "--quiet",
                           "refs/heads/feature"], capture_output=True, text=True)
    assert gone.returncode != 0
    assert _tracking(wt) == y


def test_lease_owner_unmoved_tip_falls_through_real_remote(tmp_path):
    """backlog#1286: a rejection with no remote movement (Z == Y) keeps today's path."""
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    _commit(wt, "local.txt", "mine\n")
    _reject_hook(bare, once=True)
    git, argvs = _spy_live_git(wt)
    result = runner._push_with_lease_retry(git, _noop_log, "phase2")
    local_head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    assert result == local_head
    assert _bare_feature(bare) == local_head
    pushes = _push_argvs(argvs)
    assert len(pushes) == 2
    for argv in pushes:
        assert f"--force-with-lease=feature:{y}" in argv


def test_verdict_remote_head_diverged_no_pr():
    res = RunResult(
        terminal=TerminalState.FAILED,
        error_kind="remote-head-diverged",
        error="remote-head-diverged: phase2: remote head 1234abcd diverged from lease 5678ef01",
    )
    line = runner.verdict_line(res, rc=3)
    assert "\n" not in line
    assert "remote-head-diverged" in line
    assert "1234abcd" in line


def test_probe_remote_tip_leaves_tracking_ref(tmp_path):
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    z = _rival_push(tmp_path, bare, from_branch="feature", files={"foreign.txt": "theirs\n"})
    assert gitutil.probe_remote_tip("origin", "feature", str(wt)) == (True, z)
    assert _tracking(wt) == y


def test_probe_remote_tip_absent_branch(tmp_path):
    wt, _bare = _feature_repo(tmp_path)
    assert gitutil.probe_remote_tip("origin", "feature", str(wt)) == (True, "")


def test_probe_remote_tip_bad_remote(tmp_path):
    wt, _bare = _feature_repo(tmp_path)
    assert gitutil.probe_remote_tip("no-such-remote", "feature", str(wt)) == (False, "")


def test_adopt_tracking_ref_cas(tmp_path):
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    z = _rival_push(tmp_path, bare, from_branch="feature", files={"other.txt": "o\n"})
    assert gitutil.probe_remote_tip("origin", "feature", str(wt)) == (True, z)
    assert gitutil.adopt_tracking_ref("origin", "feature", z, "0123" * 10, str(wt)) is False
    assert _tracking(wt) == y
    assert gitutil.adopt_tracking_ref("origin", "feature", z, y, str(wt)) is True
    assert _tracking(wt) == z


def test_restore_tracking_ref(tmp_path):
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    z = _rival_push(tmp_path, bare, from_branch="feature", files={"other.txt": "o\n"})
    head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    gitutil.content_equivalent(head, z, str(wt))
    assert _tracking(wt) == z, "content_equivalent's plain fetch moves the tracking ref"
    assert gitutil.restore_tracking_ref("origin", "feature", y, str(wt)) is True
    assert _tracking(wt) == y


def test_restore_tracking_ref_no_lease_deletes(tmp_path):
    wt, bare = _feature_repo(tmp_path)
    z = _rival_push(tmp_path, bare, from_branch="master", files={"other.txt": "o\n"})
    head = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    gitutil.content_equivalent(head, z, str(wt))
    assert _tracking(wt) == z
    assert gitutil.restore_tracking_ref("origin", "feature", "", str(wt)) is True
    assert _tracking(wt) == ""


def test_owned_remote_tip_arms(tmp_path):
    wt, bare = _feature_repo(tmp_path)
    y = _publish_feature(wt)
    _commit(wt, "local.txt", "same\n")
    h = _sh("git", "-C", str(wt), "rev-parse", "HEAD")
    z_same = _rival_push(tmp_path, bare, from_branch="feature", files={"local.txt": "same\n"})
    assert gitutil.probe_remote_tip("origin", "feature", str(wt)) == (True, z_same)
    assert gitutil.owned_remote_tip(y, h, z_same, str(wt))
    _sh("git", "-C", str(bare), "update-ref", "refs/heads/feature", y)
    shutil.rmtree(tmp_path / "rival")
    z_foreign = _rival_push(tmp_path, bare, from_branch="feature",
                            files={"foreign.txt": "theirs\n"})
    assert gitutil.probe_remote_tip("origin", "feature", str(wt)) == (True, z_foreign)
    assert gitutil.owned_remote_tip(y, h, z_foreign, str(wt)) == ""
    assert gitutil.owned_remote_tip(y, h, "", str(wt)) == ""


def test_push_disabled_and_detached_head_record_no_push_argv(tmp_path):
    """#827: push-disabled / detached-HEAD short-circuit before any `git push` runs."""
    wt, bare = _feature_repo(tmp_path)
    git, argvs = _spy_live_git(wt)
    git.push_remote = None
    assert runner._push_with_lease_retry(git, _noop_log, "phase2") == ""
    assert _push_argvs(argvs) == []

    git2, argvs2 = _spy_live_git(wt)
    _sh("git", "-C", str(wt), "checkout", "--detach")
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git2, _noop_log, "phase2")
    assert exc_info.value.kind == "push-failed"
    assert _push_argvs(argvs2) == []


def test_push_blocked_when_lostwork_unproved_real_gate(tmp_path):
    """#828: with a real LiveGit.prove_lostwork, a lostwork.sh that prints TREE-EQUIVALENT
    but exits non-zero fails the conjunctive gate, so the retry push never happens."""
    wt, bare = _feature_repo(tmp_path)
    _advance_origin_master(
        tmp_path, bare,
        extra={".claude/review-shared/scripts/lostwork.sh":
               "#!/bin/sh\necho TREE-EQUIVALENT\nexit 1\n"})
    _sh("git", "-C", str(wt), "fetch", "origin", "master")
    _reject_hook(bare, once=False)
    git, argvs = _spy_live_git(wt, stub_proof=False)
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(git, _noop_log, "phase2")
    assert exc_info.value.kind == "lostwork-unproved"
    assert "lost-work proof failed" in exc_info.value.detail
    assert len(_push_argvs(argvs)) == 1


def test_merge_state_read_failure_drives_resolve_behind(tmp_path, monkeypatch):
    """#829: an unreadable merge state ('') makes _resolve_behind return at once: no
    rebase, no push, no CI watch, no disarm, no retry cap."""
    gh = LiveGh(str(tmp_path), str(tmp_path), "main")
    gh._gh = lambda *a, **kw: (_ for _ in ()).throw(HarnessError("gh", "HTTP 403"))
    disarms = []
    gh.pr_disable_auto_merge = lambda pr: disarms.append(pr)
    git = FakeGit()
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    watched = _make_ci_patches(monkeypatch)
    initial = CiOutcome(0, [])
    sha, outcome = runner._resolve_behind(
        git, gh, _noop_log, res, "/wt", 1, "sha-in", initial, "/out")
    assert (sha, outcome) == ("sha-in", initial)
    assert git.rebases == 0 and git.pushes == 0 and git.proofs == 0
    assert watched == []
    assert disarms == []
    assert res.retry_cap is None


def test_reviewed_tree_invalidated_by_re_rebase(tmp_path, monkeypatch):
    """#830 (condition 12): a verdict-2 reviewed before a BEHIND re-rebase carries the
    pre-rebase tree; once _resolve_behind rewrites HEAD the tree differs, verdict 2 is
    stale, and condition 12 falls back to the NOT READY verdict 1 and holds."""
    from harness import fidelity
    from harness.armable import ArmInputs, evaluate
    from harness.state import Classification

    wt, bare = _feature_repo(tmp_path)
    _sh("git", "-C", str(wt), "push", "origin", "feature")
    _advance_origin_master(tmp_path, bare)
    git, argvs = _spy_live_git(wt)

    verdict = tmp_path / "verdict-2.md"
    verdict.write_text("READY\n")
    pre_tree = git.head_tree()
    fidelity.record_reviewed_tree(str(verdict), pre_tree)
    tree2 = fidelity.read_reviewed_tree(str(verdict))
    assert tree2 == pre_tree

    def arm_inputs(current_tree):
        return ArmInputs(
            pr_body="## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed — covered.\n",
            pr_title="chore: x", pr_labels=[], classification=Classification(),
            findings=[], unresolved_conformance=[], phase5_status="pass",
            plan_auto_merge_false=False, headless=True,
            dep_state_lookup=lambda n: "MERGED", fidelity_verdict="NOT READY",
            fidelity_verdict_2="READY", fidelity_tree_2=tree2,
            unresolved_findings=[], conflict_resolved=False, current_tree=current_tree)

    # control: HEAD unchanged -> verdict 2 is fresh and condition 12 does not hold
    assert 12 not in {c.number for c in evaluate(arm_inputs(pre_tree)).holds}

    gh = FakeGh(states=["BEHIND", "CLEAN"], strict=True)
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)
    _make_ci_patches(monkeypatch, outcomes=[CiOutcome(0, [])])
    head_before = git.head()
    runner._resolve_behind(git, gh, _noop_log, res, "", 1, head_before,
                           CiOutcome(0, []), "/out")
    assert git.head() != head_before, "re-rebase must rewrite HEAD"
    post_tree = git.head_tree()
    assert post_tree != pre_tree

    decision = evaluate(arm_inputs(post_tree))
    assert not decision.armed
    held12 = [c for c in decision.holds if c.number == 12]
    assert held12 and "source: verdict-1" in held12[0].reason
