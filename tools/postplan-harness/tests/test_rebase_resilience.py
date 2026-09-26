"""Scenario tests for harness remote-head reconciliation.

Covers the four required scenarios plus supplementary guards for every
decision-tree branch added across Phases 1-6.
"""
from __future__ import annotations

import json
import os
import stat
import subprocess
import sys
import threading
import time
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import ciwatch, gitutil
from harness.ciwatch import BackgroundWatch, CiOutcome, _write_outcome
from harness.state import HarnessError, RunResult, TerminalState
import runner


# ---------------------------------------------------------------------------
# Shared fixtures / helpers
# ---------------------------------------------------------------------------

def fake_gh(tmp_path, outputs, rc=0):
    """Write an executable script that appends one line per call and prints outputs[n]."""
    script = tmp_path / "gh"
    script.write_text(
        f"#!/usr/bin/env bash\n"
        f"echo \"$@\" >> \"{tmp_path}/gh.calls\"\n"
        f"N=$(wc -l < \"{tmp_path}/gh.calls\" 2>/dev/null || echo 0)\n"
        f"N=$((N - 1))\n"
        f"OUTPUTS=({' '.join(repr(str(o)) for o in outputs)})\n"
        f"IDX=$N\n"
        f"MAX=$(( {len(outputs)} - 1 ))\n"
        f"[ $IDX -gt $MAX ] && IDX=$MAX\n"
        f"echo \"${{OUTPUTS[$IDX]}}\"\n"
        f"exit {rc}\n"
    )
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    return [str(script)]


class FakeRunGit:
    """Callable (args, cwd) -> CompletedProcess; maps args tuples to (rc, stdout)."""

    def __init__(self, mapping=None):
        self.mapping = dict(mapping or {})
        self.calls = []

    def __call__(self, args, cwd):
        self.calls.append(list(args))
        key = tuple(args)
        if key in self.mapping:
            rc, stdout = self.mapping[key]
        else:
            rc, stdout = 1, ""
        return types.SimpleNamespace(returncode=rc, stdout=stdout, stderr="")


class FakePushGit:
    def __init__(self, branch_name="feat-x", head_sha="ccc" + "0" * 37):
        self._branch = branch_name
        self._head = head_sha
        self.pushes = 0

    def branch(self): return self._branch
    def head(self): return self._head
    def push(self): self.pushes += 1


class FakeGh:
    def __init__(self):
        self.disabled = []

    def pr_disable_auto_merge(self, pr):
        self.disabled.append(pr)

    def branch_protection_strict(self): return True
    def merge_state_status(self, pr): return "BEHIND"


def _noop_log(msg): pass


@pytest.fixture(autouse=True)
def patch_sleep(monkeypatch):
    """Neutralize all confirm waits."""
    monkeypatch.setattr(gitutil.time, "sleep", lambda s: None)


# ---------------------------------------------------------------------------
# Required scenario 1: equivalent content rebase syncs
# ---------------------------------------------------------------------------

def test_equivalent_content_rebase_syncs(tmp_path):
    """start_background_watch with an equivalent remote head re-keys to bbb and resets."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    remote_sha = "bbb" + "0" * 37
    local_sha = "aaa" + "0" * 37
    tree_hash = "T1" + "0" * 38

    gh_cmd = fake_gh(tmp_path, [remote_sha])

    run_git = FakeRunGit({
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
        ("reset", "--hard", "origin/feat-x"): (0, ""),
        ("rev-parse", "HEAD"): (0, remote_sha + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
    })

    bg = ciwatch.start_background_watch(
        str(tmp_path), 42, local_sha, out,
        verify_head=True, gh_cmd=gh_cmd, run_git=run_git
    )

    assert bg is not None
    assert bg.sha == remote_sha
    assert bg.path.endswith(f"ci-{remote_sha}.json")
    assert any("reset" in str(c) for c in run_git.calls)


# ---------------------------------------------------------------------------
# Required scenario 2: diverged content rebase fails closed
# ---------------------------------------------------------------------------

def test_diverged_content_rebase_fails_closed(tmp_path):
    """Diverged tree hash: reconcile returns diverged, start_background_watch writes diverged,
    _push_with_lease_retry raises, and exit_code_for maps to 3."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    local_sha = "aaa" + "0" * 37
    remote_sha = "bbb" + "0" * 37
    tree_local = "TL" + "0" * 38
    tree_remote = "TR" + "0" * 38

    gh_cmd = fake_gh(tmp_path, [remote_sha])

    run_git = FakeRunGit({
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_local + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_remote + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
    })

    # (a) reconcile_remote_head returns diverged
    r = gitutil.reconcile_remote_head(42, local_sha, local_sha, "feat-x",
                                      str(tmp_path), gh_cmd=gh_cmd, run_git=run_git)
    assert r.action == "diverged"

    # (b) start_background_watch returns bg.status == "diverged", writes ci-aaa*.json
    run_git2 = FakeRunGit({
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_local + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_remote + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
    })
    gh_cmd2 = fake_gh(tmp_path, [remote_sha])
    bg = ciwatch.start_background_watch(
        str(tmp_path), 42, local_sha, out,
        verify_head=True, gh_cmd=gh_cmd2, run_git=run_git2
    )
    assert bg is not None
    assert bg.status == "diverged"
    outcome_file = os.path.join(out, f"ci-{local_sha}.json")
    assert os.path.exists(outcome_file)
    data = json.loads(open(outcome_file).read())
    assert data["status"] == "diverged"
    # (c) no reset recorded
    assert not any("reset" in str(c) for c in run_git2.calls)

    # (d) _push_with_lease_retry raises with kind remote-head-diverged, zero pushes
    git = FakePushGit(head_sha=local_sha)
    run_git3 = FakeRunGit({
        ("rev-parse", "--verify", "--quiet", f"refs/remotes/origin/feat-x"): (0, local_sha + "\n"),
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_local + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_remote + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
    })
    gh_cmd3 = fake_gh(tmp_path, [remote_sha])
    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(
            git, _noop_log, "phase5.5", pr=42,
            worktree=str(tmp_path), gh_cmd=gh_cmd3, run_git=run_git3
        )
    assert exc_info.value.kind == "remote-head-diverged"
    assert git.pushes == 0

    # (e) exit_code_for maps remote-head-diverged to 3
    res = RunResult(terminal=TerminalState.FAILED)
    res.error_kind = "remote-head-diverged"
    assert runner.exit_code_for(res) == 3


# ---------------------------------------------------------------------------
# Required scenario 3: head-change timeout restarts watch
# ---------------------------------------------------------------------------

def test_watch_timeout_with_head_change_restarts(tmp_path, monkeypatch):
    """A timed-out/head-changed bg watch plus an equal-tree remote restarts on bbb."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha_aaa = "aaa" + "0" * 37
    sha_bbb = "bbb" + "0" * 37
    tree_hash = "T1" + "0" * 38

    # Write a pre-existing head-changed outcome for aaa
    bg_old = BackgroundWatch(
        sha=sha_aaa, pr=42, worktree=str(tmp_path),
        path=os.path.join(out, f"ci-{sha_aaa}.json"), started=time.time(),
        verify_head=True
    )
    _write_outcome(bg_old, "head-changed", [],
                   f"remote head moved {sha_aaa[:8]} -> {sha_bbb[:8]}")
    bg_old.remote_sha = sha_bbb

    run_git = FakeRunGit({
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{sha_aaa}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--verify", f"{sha_bbb}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
        ("reset", "--hard", "origin/feat-x"): (0, ""),
        ("rev-parse", "HEAD"): (0, sha_bbb + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
    })

    # First gh call (initial reconcile in watch_or_reuse) returns sha_aaa → match,
    # so sha stays aaa and bg_old's "head-changed" status triggers the restart block.
    # Calls 2-4: sha_bbb (confirm mismatch in restart reconcile's remote_head_matches).
    # Call 5: sha_bbb (start_background_watch's reconcile for sha_bbb → match).
    gh_cmd = fake_gh(tmp_path, [sha_aaa, sha_bbb, sha_bbb, sha_bbb, sha_bbb])

    watched_shas = []

    def fake_watch_thread(bg, timeout, settle_tries, settle_wait):
        watched_shas.append(bg.sha)
        _write_outcome(bg, "success", [], "fake")

    monkeypatch.setattr(ciwatch, "_watch_thread", fake_watch_thread)

    outcome = ciwatch.watch_or_reuse(
        str(tmp_path), 42, sha_aaa, out, bg_old,
        timeout=30, verify_head=True, gh_cmd=gh_cmd, run_git=run_git
    )

    assert outcome.exit_code == 0
    assert outcome.head_sha == sha_bbb
    assert len(watched_shas) == 1
    assert watched_shas[0] == sha_bbb
    bbb_file = os.path.join(out, f"ci-{sha_bbb}.json")
    assert os.path.exists(bbb_file)
    data = json.loads(open(bbb_file).read())
    assert data["status"] == "success"


# ---------------------------------------------------------------------------
# Required scenario 4: fast path — remote head matches, no git calls
# ---------------------------------------------------------------------------

def test_remote_head_matches_fast_path(tmp_path):
    """When gh returns the same sha, no fetch/reset, exactly one gh call."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha = "aaa" + "0" * 37
    gh_cmd = fake_gh(tmp_path, [sha])
    run_git = FakeRunGit()

    bg = ciwatch.start_background_watch(
        str(tmp_path), 42, sha, out,
        verify_head=True, gh_cmd=gh_cmd, run_git=run_git
    )

    assert bg is not None
    assert bg.sha == sha
    assert run_git.calls == []
    calls_file = tmp_path / "gh.calls"
    lines = calls_file.read_text().strip().splitlines() if calls_file.exists() else []
    assert len(lines) == 1


# ---------------------------------------------------------------------------
# Supplementary tests
# ---------------------------------------------------------------------------

def test_gh_failure_fails_safe(tmp_path):
    """gh exit 1 returns (True, "") with a WARNING — not a head change."""
    gh_cmd = fake_gh(tmp_path, [""], rc=1)
    ok, remote = gitutil.remote_head_matches(42, "aaa" + "0" * 37, gh_cmd=gh_cmd)
    assert ok is True
    assert remote == ""


def test_gh_lag_confirm_retry_counts_as_match(tmp_path):
    """First call returns old sha, second returns the expected sha → match after one sleep."""
    local_sha = "aaa" + "0" * 37
    gh_cmd = fake_gh(tmp_path, ["old" + "0" * 37, local_sha])
    sleep_calls = []
    ok, remote = gitutil.remote_head_matches(
        42, local_sha, gh_cmd=gh_cmd, sleep=lambda s: sleep_calls.append(s)
    )
    assert ok is True
    assert remote == local_sha
    assert len(sleep_calls) == 1


def test_background_thread_marks_head_changed(tmp_path, monkeypatch):
    """Chunk timeout + moved head ends the watch as head-changed, proc killed."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha = "aaa" + "0" * 37

    monkeypatch.setattr(ciwatch, "HEAD_CHECK_INTERVAL", 0.01)

    class _FakeProc:
        def __init__(self):
            self.killed = False
            self._comm_calls = 0

        def communicate(self, timeout=None):
            self._comm_calls += 1
            # Drain call after kill() must not raise — mimic proc.communicate() behaviour.
            if self.killed:
                return ("", "")
            raise subprocess.TimeoutExpired("gh", timeout)

        def kill(self):
            self.killed = True

    fake_proc = _FakeProc()

    monkeypatch.setattr(ciwatch.subprocess, "Popen", lambda *a, **kw: fake_proc)
    monkeypatch.setattr(ciwatch.gitutil, "remote_head_matches",
                        lambda pr, sha, **kw: (False, "bbb" + "0" * 37))

    bg = BackgroundWatch(
        sha=sha, pr=42, worktree=str(tmp_path),
        path=os.path.join(out, f"ci-{sha}.json"),
        started=time.time(), verify_head=True
    )

    ciwatch._watch_thread(bg, timeout=1, settle_tries=1, settle_wait=0)

    assert bg.status == "head-changed"
    assert bg.remote_sha == "bbb" + "0" * 37
    assert fake_proc.killed


def test_background_thread_gh_error_keeps_watching(tmp_path, monkeypatch):
    """Mid-watch gh error (True, "") does not end the watch; run ends success."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha = "aaa" + "0" * 37

    monkeypatch.setattr(ciwatch, "HEAD_CHECK_INTERVAL", 0.01)

    comm_calls = [0]

    class _FakeProc:
        returncode = 0

        def communicate(self, timeout=None):
            comm_calls[0] += 1
            if comm_calls[0] == 1:
                raise subprocess.TimeoutExpired("gh", timeout)
            return ("", "")

        def kill(self): pass
        def poll(self): return None

    fake_proc = _FakeProc()
    monkeypatch.setattr(ciwatch.subprocess, "Popen", lambda *a, **kw: fake_proc)
    monkeypatch.setattr(ciwatch.gitutil, "remote_head_matches",
                        lambda pr, sha, **kw: (True, ""))
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda *a, **kw: [])

    bg = BackgroundWatch(
        sha=sha, pr=42, worktree=str(tmp_path),
        path=os.path.join(out, f"ci-{sha}.json"),
        started=time.time(), verify_head=True
    )

    ciwatch._watch_thread(bg, timeout=5, settle_tries=2, settle_wait=0)

    assert bg.status == "success"


def test_phase7_divergence_disables_auto_merge():
    """_fail_closed_on_divergence(disarm=True) disables auto-merge before raising."""
    gh = FakeGh()
    with pytest.raises(HarnessError) as exc_info:
        runner._fail_closed_on_divergence(gh, _noop_log, 42, "phase7", "evidence",
                                          disarm=True)
    assert exc_info.value.kind == "remote-head-diverged"
    assert gh.disabled == [42]


def test_pre_push_guard_blocks_push_on_divergence(tmp_path):
    """Diverged remote raises remote-head-diverged before any push attempt."""
    local_sha = "aaa" + "0" * 37
    remote_sha = "bbb" + "0" * 37
    tree_local = "TL" + "0" * 38
    tree_remote = "TR" + "0" * 38

    run_git = FakeRunGit({
        ("rev-parse", "--verify", "--quiet", f"refs/remotes/origin/feat-x"): (0, local_sha + "\n"),
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_local + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_remote + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
    })
    gh_cmd = fake_gh(tmp_path, [remote_sha])
    git = FakePushGit(head_sha=local_sha)

    with pytest.raises(HarnessError) as exc_info:
        runner._push_with_lease_retry(
            git, _noop_log, "phase5.5", pr=42,
            worktree=str(tmp_path), gh_cmd=gh_cmd, run_git=run_git
        )
    assert exc_info.value.kind == "remote-head-diverged"
    assert git.pushes == 0


def test_pre_push_equivalent_syncs_without_pushing(tmp_path):
    """Equal-tree remote returns bbb sha without pushing."""
    local_sha = "aaa" + "0" * 37
    remote_sha = "bbb" + "0" * 37
    tree_hash = "T1" + "0" * 38

    run_git = FakeRunGit({
        ("rev-parse", "--verify", "--quiet", f"refs/remotes/origin/feat-x"): (0, local_sha + "\n"),
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{local_sha}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--verify", f"{remote_sha}^{{tree}}"): (0, tree_hash + "\n"),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
        ("reset", "--hard", "origin/feat-x"): (0, ""),
        ("rev-parse", "HEAD"): (0, remote_sha + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
    })
    gh_cmd = fake_gh(tmp_path, [remote_sha])
    git = FakePushGit(head_sha=local_sha)

    result = runner._push_with_lease_retry(
        git, _noop_log, "phase5.5", pr=42,
        worktree=str(tmp_path), gh_cmd=gh_cmd, run_git=run_git
    )
    assert result == remote_sha
    assert git.pushes == 0


def test_pre_push_guard_skips_without_tracking_ref(tmp_path):
    """No tracking ref (first push) skips the guard; pushes once; no gh calls."""
    run_git = FakeRunGit({
        ("rev-parse", "--verify", "--quiet", "refs/remotes/origin/feat-x"): (1, ""),
    })
    gh_cmd = fake_gh(tmp_path, ["any"])
    git = FakePushGit(head_sha="ccc" + "0" * 37)

    result = runner._push_with_lease_retry(
        git, _noop_log, "phase5.5", pr=42,
        worktree=str(tmp_path), gh_cmd=gh_cmd, run_git=run_git
    )
    assert result == git._head
    assert git.pushes == 1
    calls_file = tmp_path / "gh.calls"
    assert not calls_file.exists()


def test_resolve_behind_divergence_blocks_rerebase(tmp_path, monkeypatch):
    """BEHIND with diverged remote raises before _refresh_and_reprove; auto-merge disabled."""
    refresh_calls = []

    def fake_refresh(git, log, phase, attempt):
        refresh_calls.append(attempt)

    monkeypatch.setattr(runner, "_refresh_and_reprove", fake_refresh)
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **kw: gitutil.Reconcile(
                            "diverged", "bbb" + "0" * 37,
                            "remote head bbb diverged from aaa with different content"
                        ))
    monkeypatch.setattr(runner.os.path, "isdir", lambda p: True)

    def fake_start(*a, **kw): return object()
    def fake_watch(*a, **kw): return CiOutcome(0, [])
    def fake_reap(*a, **kw): pass

    monkeypatch.setattr(runner.ciwatch, "start_background_watch", fake_start)
    monkeypatch.setattr(runner.ciwatch, "watch_or_reuse", fake_watch)
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", fake_reap)

    import importlib, sys as _sys
    _bt = importlib.import_module("tests.test_behind_lease_retry")
    git = _bt.FakeGit(head_shas=["aaa" + "0" * 37])
    gh = _bt.FakeGh(states=["BEHIND"])
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)

    with pytest.raises(HarnessError) as exc_info:
        runner._resolve_behind(
            git, gh, _noop_log, res, str(tmp_path), 42, "aaa" + "0" * 37,
            CiOutcome(0, []), str(tmp_path / "out")
        )
    assert exc_info.value.kind == "remote-head-diverged"
    assert refresh_calls == []
    assert gh.disarms >= 1


def test_resolve_behind_equivalent_syncs_then_rerebases(tmp_path, monkeypatch):
    """BEHIND with equal-tree remote syncs, re-rebases once, pushes with pr=42."""
    refresh_calls = []
    push_kwargs = []

    def fake_refresh(git, log, phase, attempt):
        refresh_calls.append(attempt)

    def fake_push_retry(git, log, phase, *, pr=None, worktree=None, **kw):
        push_kwargs.append({"pr": pr, "worktree": worktree})
        return git.head()

    monkeypatch.setattr(runner, "_refresh_and_reprove", fake_refresh)
    monkeypatch.setattr(runner, "_push_with_lease_retry", fake_push_retry)
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **kw: gitutil.Reconcile(
                            "synced", "bbb" + "0" * 37,
                            "remote head moved aaa -> bbb; tree-equivalent; synced"
                        ))
    monkeypatch.setattr(runner.os.path, "isdir", lambda p: True)

    def fake_start(*a, **kw): return object()
    def fake_watch(*a, **kw): return CiOutcome(0, [])
    def fake_reap(*a, **kw): pass

    monkeypatch.setattr(runner.ciwatch, "start_background_watch", fake_start)
    monkeypatch.setattr(runner.ciwatch, "watch_or_reuse", fake_watch)
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", fake_reap)

    import importlib
    _bt = importlib.import_module("tests.test_behind_lease_retry")
    git = _bt.FakeGit(head_shas=["aaa" + "0" * 37, "bbb" + "0" * 37])
    gh = _bt.FakeGh(states=["BEHIND", "CLEAN"])
    res = RunResult(terminal=TerminalState.SHIPPED_ARMED)

    runner._resolve_behind(
        git, gh, _noop_log, res, str(tmp_path), 42, "aaa" + "0" * 37,
        CiOutcome(0, []), str(tmp_path / "out")
    )
    assert len(refresh_calls) == 1
    assert len(push_kwargs) == 1
    assert push_kwargs[0]["pr"] == 42


def test_pre_arm_reconcile_synced_restarts_watch(tmp_path, monkeypatch):
    """Pre-arm sync reaps the old watch and starts a new one on bbb."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha_aaa = "aaa" + "0" * 37
    sha_bbb = "bbb" + "0" * 37

    reaped = []
    started = []

    fake_bg_old = types.SimpleNamespace(sha=sha_aaa, status="", done=threading.Event())
    fake_bg_new = types.SimpleNamespace(sha=sha_bbb)

    monkeypatch.setattr(runner.ciwatch, "reap_background_watch",
                        lambda bg, **kw: reaped.append(getattr(bg, "sha", None)))
    monkeypatch.setattr(runner.ciwatch, "start_background_watch",
                        lambda wt, pr, sha, out_dir, **kw: (started.append(sha), fake_bg_new)[1])
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **kw: gitutil.Reconcile(
                            "synced", sha_bbb,
                            "remote head moved aaa -> bbb; tree-equivalent; synced"
                        ))

    class _Git:
        def head(self): return sha_aaa
        def branch(self): return "feat-x"

    new_sha, new_bg = runner._reconcile_before_arm(
        _Git(), _noop_log, FakeGh(), str(tmp_path), 42, sha_aaa,
        fake_bg_old, out
    )
    assert new_sha == sha_bbb
    assert new_bg is fake_bg_new
    assert sha_aaa in reaped
    assert sha_bbb in started


def test_pre_arm_reconcile_diverged_raises(tmp_path, monkeypatch):
    """Pre-arm divergence raises without touching auto-merge (not yet armed)."""
    out = str(tmp_path / "out")
    os.makedirs(out)

    sha = "aaa" + "0" * 37
    fake_bg = types.SimpleNamespace(sha=sha, status="", done=threading.Event())
    reaped = []

    monkeypatch.setattr(runner.ciwatch, "reap_background_watch",
                        lambda bg, **kw: reaped.append(getattr(bg, "sha", None)))
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **kw: gitutil.Reconcile(
                            "diverged", "bbb" + "0" * 37,
                            "remote head bbb diverged from aaa"
                        ))

    class _Git:
        def head(self): return sha
        def branch(self): return "feat-x"

    gh = FakeGh()

    with pytest.raises(HarnessError) as exc_info:
        runner._reconcile_before_arm(
            _Git(), _noop_log, gh, str(tmp_path), 42, sha, fake_bg, out
        )
    assert exc_info.value.kind == "remote-head-diverged"
    assert gh.disabled == []


def test_reconcile_gh_lag_resolved_by_ls_remote(tmp_path):
    """gh lag: confirms mismatch, but ls-remote shows expected sha → match."""
    local_sha = "aaa" + "0" * 37
    old_sha = "old" + "0" * 37

    gh_cmd = fake_gh(tmp_path, [old_sha, old_sha, old_sha])

    run_git = FakeRunGit({
        ("ls-remote", "origin", "refs/heads/feat-x"): (0, f"{local_sha}\trefs/heads/feat-x\n"),
    })

    r = gitutil.reconcile_remote_head(
        42, local_sha, local_sha, "feat-x", str(tmp_path),
        gh_cmd=gh_cmd, run_git=run_git
    )
    assert r.action == "match"


# ---------------------------------------------------------------------------
# Phase 3 fail-closed unit tests — patch-series equivalence arm
# ---------------------------------------------------------------------------

_S_LOCAL = "aaa" + "0" * 37
_S_REMOTE = "bbb" + "0" * 37
_S_BL = "b10" + "0" * 37
_S_BR = "b20" + "0" * 37
_S_RANGE_DIFF_OK = (
    "1:  1111111 = 1:  aaaaaaa c1\n"
    "2:  2222222 = 2:  bbbbbbb c2\n"
    "3:  3333333 = 3:  ccccccc c3\n"
)


def _series_map(range_diff=_S_RANGE_DIFF_OK, n_local="3", n_remote="3"):
    """Happy-path FakeRunGit mapping: trees differ, patch series equivalent."""
    return {
        ("fetch", "origin"): (0, ""),
        ("rev-parse", "--verify", f"{_S_LOCAL}^{{tree}}"): (0, "TL" + "0" * 38 + "\n"),
        ("rev-parse", "--verify", f"{_S_REMOTE}^{{tree}}"): (0, "TR" + "0" * 38 + "\n"),
        ("ls-remote", "origin", "refs/heads/feat-x"): (1, ""),
        ("rev-parse", "--abbrev-ref", "HEAD"): (0, "feat-x\n"),
        ("merge-base", _S_LOCAL, "origin/master"): (0, _S_BL + "\n"),
        ("merge-base", _S_REMOTE, "origin/master"): (0, _S_BR + "\n"),
        ("merge-base", "--is-ancestor", _S_BL, "origin/master"): (0, ""),
        ("merge-base", "--is-ancestor", _S_BR, "origin/master"): (0, ""),
        ("merge-base", "--is-ancestor", _S_BL, _S_BR): (0, ""),
        ("rev-list", "--count", f"{_S_BL}..{_S_LOCAL}"): (0, n_local + "\n"),
        ("rev-list", "--count", f"{_S_BR}..{_S_REMOTE}"): (0, n_remote + "\n"),
        ("range-diff", "--no-color", f"{_S_BL}..{_S_LOCAL}", f"{_S_BR}..{_S_REMOTE}"): (0, range_diff),
        ("reset", "--hard", "origin/feat-x"): (0, ""),
        ("rev-parse", "HEAD"): (0, _S_REMOTE + "\n"),
    }


_S_ARM_KEYS = [k for k in _series_map() if k[0] in ("merge-base", "rev-list", "range-diff")]
# 8 keys: 2 merge-base, 3 is-ancestor, 2 rev-list, 1 range-diff


def _reconcile_series(tmp_path, run_git):
    gh_cmd = fake_gh(tmp_path, [_S_REMOTE])
    return gitutil.reconcile_remote_head(42, _S_LOCAL, _S_LOCAL, "feat-x", str(tmp_path),
                                         gh_cmd=gh_cmd, run_git=run_git)


def _reset_issued(run_git):
    return any(c[:2] == ["reset", "--hard"] for c in run_git.calls)


_TEXT1 = (
    "1:  1111111 ! 1:  aaaaaaa c1\n"
    "    @@ -1 +1 @@\n"
    "    -b\n"
    "    +b-edited\n"
    "2:  2222222 = 2:  bbbbbbb c2\n"
    "3:  3333333 = 3:  ccccccc c3\n"
)
_TEXT2 = (
    "1:  1111111 = 1:  aaaaaaa c1\n"
    "2:  2222222 = 2:  bbbbbbb c2\n"
    "3:  3333333 < -:  ------- c3\n"
)
_TEXT3 = (
    "1:  1111111 = 1:  aaaaaaa c1\n"
    "2:  2222222 = 2:  bbbbbbb c2\n"
    "-:  ------- > 4:  ddddddd d\n"
)
_TEXT4 = (
    "1:  1111111 = 2:  bbbbbbb c2\n"
    "2:  2222222 = 1:  aaaaaaa c1\n"
    "3:  3333333 = 3:  ccccccc c3\n"
)
_TEXT5 = (
    "1:  1111111 = 1:  aaaaaaa c1\n"
    "2:  2222222 = 2:  bbbbbbb c2\n"
)
_TEXT6 = ""


def test_patch_series_mock_happy_path_syncs(tmp_path):
    run_git = FakeRunGit(_series_map())
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "synced"
    assert r.remote_sha == _S_REMOTE
    assert "patch-series-equivalent (rebased onto newer master)" in r.evidence
    assert _reset_issued(run_git)
    assert any(c[0] == "range-diff" for c in run_git.calls)


@pytest.mark.parametrize("key", _S_ARM_KEYS, ids=lambda k: " ".join(k)[:40])
def test_patch_series_fails_closed_when_any_git_step_fails(tmp_path, key):
    m = _series_map()
    m[key] = (1, "")
    run_git = FakeRunGit(m)
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "diverged"
    assert not _reset_issued(run_git)


def test_patch_series_fails_closed_on_timeout(tmp_path):
    class TimeoutOnRangeDiff(FakeRunGit):
        def __call__(self, args, cwd):
            if args[0] == "range-diff":
                raise subprocess.TimeoutExpired(cmd="git", timeout=1)
            return super().__call__(args, cwd)

    run_git = TimeoutOnRangeDiff(_series_map())
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "diverged"
    assert not _reset_issued(run_git)


def test_patch_series_fails_closed_on_oserror(tmp_path):
    class OSErrorOnMergeBase(FakeRunGit):
        def __call__(self, args, cwd):
            if args[0] == "merge-base":
                raise OSError("injected oserror")
            return super().__call__(args, cwd)

    run_git = OSErrorOnMergeBase(_series_map())
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "diverged"
    assert not _reset_issued(run_git)


@pytest.mark.parametrize("n_local,n_remote", [("3", "4"), ("3", "2"), ("0", "0"), ("x", "3")])
def test_patch_series_count_mismatch_stays_diverged(tmp_path, n_local, n_remote):
    run_git = FakeRunGit(_series_map(n_local=n_local, n_remote=n_remote))
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "diverged"
    assert not _reset_issued(run_git)


@pytest.mark.parametrize("text", [_TEXT1, _TEXT2, _TEXT3, _TEXT4, _TEXT5, _TEXT6])
def test_patch_series_range_diff_markers_stay_diverged(tmp_path, text):
    run_git = FakeRunGit(_series_map(range_diff=text))
    r = _reconcile_series(tmp_path, run_git)
    assert r.action == "diverged"
    assert not _reset_issued(run_git)


def test_parse_range_diff_skips_interdiff_body():
    result = gitutil._parse_range_diff(_TEXT1)
    assert len(result) == 3
    assert result[0] == ("1", "!", "1")
    assert result[1] == ("2", "=", "2")
    assert result[2] == ("3", "=", "3")


def test_series_all_equal_rules():
    assert gitutil._series_all_equal([("1", "=", "1"), ("2", "=", "2")], 2) is True
    assert gitutil._series_all_equal([("1", "=", "1"), ("2", "=", "2")], 3) is False
    assert gitutil._series_all_equal([("1", "=", "2"), ("2", "=", "1")], 2) is False
    assert gitutil._series_all_equal([("1", "!", "1")], 1) is False
    assert gitutil._series_all_equal([], 0) is False
    assert gitutil._series_all_equal([("1", "=", "1"), ("2", "<", "-")], 2) is False


def test_patch_series_rejects_empty_shas(tmp_path):
    run_git = FakeRunGit(_series_map())
    result1 = gitutil.patch_series_equivalent("", _S_REMOTE, str(tmp_path), run_git=run_git)
    result2 = gitutil.patch_series_equivalent(_S_LOCAL, "", str(tmp_path), run_git=run_git)
    assert result1 is False
    assert result2 is False
    assert run_git.calls == []


# ---------------------------------------------------------------------------
# Phase 4: Caller CI re-key proof (Phase 6.5 _reconcile_before_arm)
# ---------------------------------------------------------------------------


def test_reconcile_before_arm_synced_restarts_ci_on_remote_sha(tmp_path, monkeypatch):
    """Phase 6.5: a patch-series 'synced' reaps the old watch and re-keys CI to the remote sha."""
    old_sha, new_sha = "aaa" + "0" * 37, "bbb" + "0" * 37
    reaped, started = [], []
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **k: gitutil.Reconcile(
                            "synced", new_sha,
                            f"remote head moved {old_sha[:8]} -> {new_sha[:8]}; "
                            "patch-series-equivalent (rebased onto newer master); synced"))
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", lambda bg: reaped.append(bg))
    monkeypatch.setattr(runner.ciwatch, "start_background_watch",
                        lambda wt, pr, sha, out, **kw: started.append((wt, pr, sha, out)) or "NEW-BG")
    sha, bg = runner._reconcile_before_arm(FakePushGit(head_sha=old_sha), _noop_log, FakeGh(),
                                           str(tmp_path), 42, old_sha, "OLD-BG",
                                           str(tmp_path / "out"))
    assert sha == new_sha and bg == "NEW-BG"
    assert reaped == ["OLD-BG"]
    assert started == [(str(tmp_path), 42, new_sha, str(tmp_path / "out"))]


def test_reconcile_before_arm_diverged_reaps_and_raises(tmp_path, monkeypatch):
    """Phase 6.5: 'diverged' reaps the watch, never starts a new one, raises remote-head-diverged."""
    old_sha, new_sha = "aaa" + "0" * 37, "bbb" + "0" * 37
    reaped, started = [], []
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head",
                        lambda *a, **k: gitutil.Reconcile("diverged", new_sha, "different content"))
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch", lambda bg: reaped.append(bg))
    monkeypatch.setattr(runner.ciwatch, "start_background_watch",
                        lambda *a, **kw: started.append(a))
    with pytest.raises(HarnessError) as ei:
        runner._reconcile_before_arm(FakePushGit(head_sha=old_sha), _noop_log, FakeGh(),
                                     str(tmp_path), 42, old_sha, "OLD-BG",
                                     str(tmp_path / "out"))
    assert ei.value.kind == "remote-head-diverged"
    assert reaped == ["OLD-BG"] and started == []
