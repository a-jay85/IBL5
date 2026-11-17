"""Tests for the stacked-child restart: post-review call sites raise StackedChildAdopted,
run() re-runs one pass, the pre-push site never opts in, and the evidence reaches the
DM block and the RESULT line."""
from __future__ import annotations

import os
import sys
import time
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import gitutil
from harness.ciwatch import CiOutcome
from harness.state import HarnessError, RunResult, TerminalState

SHA = "a" * 40
CHILD_SHA = "b" * 40


class FakeGit:
    def __init__(self):
        self.pushes = 0
        self.rebases = 0

    def branch(self): return "wt-slug"
    def head(self): return SHA
    def is_dirty(self): return False
    def push(self): self.pushes += 1
    def capture_lostwork_pre(self, key): return True
    def fetch_base(self, base="origin/master"): pass
    def rebase_onto(self, base="origin/master"): self.rebases += 1
    def prove_lostwork(self, key): return (True, "TREE-EQUIVALENT")


class FakeGh:
    def __init__(self, states=("CLEAN",)):
        self.states = list(states)
        self.disarms = []

    def branch_protection_strict(self): return True
    def merge_state_status(self, pr=None): return self.states[0]
    def pr_disable_auto_merge(self, pr): self.disarms.append(pr)


def _fake_reconcile(calls=None):
    def fake(pr, expected, local, branch, worktree, *, allow_stacked_child=False, **kw):
        if calls is not None:
            calls.append(allow_stacked_child)
        if allow_stacked_child:
            return gitutil.Reconcile("stacked-child", CHILD_SHA, "ev", child_prs=(41,))
        return gitutil.Reconcile("diverged", CHILD_SHA, "ev")
    return fake


@pytest.fixture
def stacked(monkeypatch, tmp_path):
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head", _fake_reconcile())
    verdict = tmp_path / "verdict.md"
    verdict.write_text("VERDICT: READY\n")
    monkeypatch.setattr(runner.fidelity, "verdict_path", lambda pr: str(verdict))
    reaped = []
    monkeypatch.setattr(runner.ciwatch, "reap_background_watch",
                        lambda bg, timeout=None: reaped.append(bg))
    return types.SimpleNamespace(verdict=verdict, reaped=reaped, logs=[])


def _before_arm(stacked, gh, *, stacked_restarts=0, bg="bg-watch"):
    return runner._reconcile_before_arm(FakeGit(), stacked.logs.append, gh, "/wt", 7, SHA,
                                        bg, "/out", stacked_restarts=stacked_restarts)


def test_phase65_restart_raises(stacked):
    with pytest.raises(runner.StackedChildAdopted) as exc:
        _before_arm(stacked, FakeGh())
    assert exc.value.child_prs == (41,)
    assert exc.value.phase == "phase6.5"
    assert not isinstance(exc.value, HarnessError)


def test_phase65_restart_disarms(stacked):
    gh = FakeGh()
    with pytest.raises(runner.StackedChildAdopted):
        _before_arm(stacked, gh)
    assert gh.disarms == [7]


def test_phase65_restart_reaps_ci_watch(stacked):
    with pytest.raises(runner.StackedChildAdopted):
        _before_arm(stacked, FakeGh(), bg="the-old-watch")
    assert "the-old-watch" in stacked.reaped


def test_resolve_behind_restart_propagates(stacked, monkeypatch):
    pushed = []
    monkeypatch.setattr(runner, "_push_with_lease_retry", lambda *a, **k: pushed.append(1) or SHA)
    gh = FakeGh(states=("BEHIND",))
    with pytest.raises(runner.StackedChildAdopted):
        runner._resolve_behind(FakeGit(), gh, stacked.logs.append, RunResult(terminal=TerminalState.SHIPPED_ARMED),
                               "/tmp", 7, SHA, CiOutcome(0, []), "/out")
    assert pushed == []


def test_ci_fix_loop_restart_propagates(stacked, monkeypatch):
    monkeypatch.setattr(runner, "_stale_red_regrace", lambda log, wt, pr, sha, outcome, *a: outcome)
    monkeypatch.setattr(runner, "_meta_check_skip", lambda *a, **k: [])
    res = types.SimpleNamespace(ci_head=None)
    with pytest.raises(runner.StackedChildAdopted):
        runner._ci_fix_loop(FakeGit(), FakeGh(), None, stacked.logs.append, res,
                            worktree="/wt", pr=7, sha=SHA, outcome=CiOutcome(8, ["PHPUnit"], "t"),
                            out_dir="/out", mode="live", fixture=None,
                            run_started=time.time())


def test_second_move_after_restart_fails_closed(stacked):
    with pytest.raises(HarnessError) as exc:
        _before_arm(stacked, FakeGh(), stacked_restarts=1)
    assert exc.value.kind == "remote-head-diverged"


def test_restart_removes_stale_verdict_file(stacked):
    assert stacked.verdict.exists()
    with pytest.raises(runner.StackedChildAdopted):
        _before_arm(stacked, FakeGh())
    assert not stacked.verdict.exists()


def test_restart_pass_forces_full_review():
    a, b = object(), object()
    assert runner._review_reuse(a, b, 1) == (None, None)
    assert runner._review_reuse(a, b, 0) == (a, b)


def test_record_stacked_child_fails_closed():
    sc = runner.StackedChildAdopted("phase7", CHILD_SHA, (41,), "ev")
    res = types.SimpleNamespace()
    runner._record_stacked_child(res, sc)
    assert res.error_kind == "stacked-child-restart"
    assert res.terminal == TerminalState.FAILED
    assert "#41" in res.stacked_child_restart


def test_restart_log_names_child_prs(stacked):
    with pytest.raises(runner.StackedChildAdopted):
        _before_arm(stacked, FakeGh())
    assert any("#41" in line for line in stacked.logs)


# --- run() wrapper ----------------------------------------------------------

def _patch_passes(monkeypatch, results, out_dir=None, audit_line=None):
    calls = []

    def fake_pass(fixture, od, llm, **kw):
        calls.append(kw)
        if audit_line is not None and len(calls) == 1:
            with open(os.path.join(od, "audit.log"), "w") as fh:
                fh.write(audit_line + "\n")
        return results[len(calls) - 1]

    monkeypatch.setattr(runner, "_run_pass", fake_pass)
    return calls


def _restart_res(**kw):
    return types.SimpleNamespace(error_kind="stacked-child-restart",
                                 stacked_child_restart="#41 merged into this branch at bbbbbbbb (phase7)",
                                 error="stacked-child-restart: x", **kw)


def test_run_wrapper_reruns_once_and_carries_evidence(monkeypatch, tmp_path):
    pass2 = types.SimpleNamespace(error_kind=None, stacked_child_restart=None)
    calls = _patch_passes(monkeypatch, [_restart_res(), pass2])
    out = runner.run(None, str(tmp_path), types.SimpleNamespace(ledger=None))
    assert len(calls) == 2
    assert calls[0]["stacked_restarts"] == 0
    assert calls[1]["stacked_restarts"] == 1
    assert out is pass2
    assert out.stacked_child_restart.startswith("#41")


def test_run_wrapper_single_pass_when_no_restart(monkeypatch, tmp_path):
    only = types.SimpleNamespace(error_kind=None, stacked_child_restart=None)
    calls = _patch_passes(monkeypatch, [only])
    out = runner.run(None, str(tmp_path), types.SimpleNamespace(ledger=None))
    assert len(calls) == 1
    assert out is only


def test_run_wrapper_second_restart_fails_closed(monkeypatch, tmp_path):
    calls = _patch_passes(monkeypatch, [_restart_res(), _restart_res()])
    out = runner.run(None, str(tmp_path), types.SimpleNamespace(ledger=None))
    assert len(calls) == 2
    assert out.error_kind == "remote-head-diverged"


def test_restart_pass_carries_audit(monkeypatch, tmp_path):
    pass2 = types.SimpleNamespace(error_kind=None, stacked_child_restart=None)
    calls = _patch_passes(monkeypatch, [_restart_res(), pass2], audit_line="[00:00:00] pass one line")
    runner.run(None, str(tmp_path), types.SimpleNamespace(ledger=None))
    assert calls[1]["prior_audit"] == ["[00:00:00] pass one line"]


def test_pre_push_site_never_opts_in(monkeypatch):
    calls = []
    monkeypatch.setattr(runner.gitutil, "reconcile_remote_head", _fake_reconcile(calls))
    monkeypatch.setattr(runner.gitutil, "tracking_sha", lambda *a, **k: SHA)
    git = FakeGit()
    with pytest.raises(HarnessError) as exc:
        runner._push_with_lease_retry(git, lambda m: None, "phase2", pr=7, worktree="/wt")
    assert exc.value.kind == "remote-head-diverged"
    assert calls == [False]
    assert git.pushes == 0


# --- DM block and RESULT line -----------------------------------------------

def test_human_block_names_child_prs():
    r = RunResult(terminal=TerminalState.FAILED)
    r.error_kind = "remote-head-diverged"
    r.error = "remote-head-diverged: phase7: remote moved again"
    r.slug = "feat/thing"
    r.stacked_child_restart = "#41 merged into this branch at bbbbbbbb (phase7)"
    block = runner.human_block(r, 3, "/Users/x/IBL5-worktrees/feat-thing", "/tmp/log")
    assert "Stacked child: #41" in block


def test_verdict_line_names_child_prs():
    r = RunResult(terminal=TerminalState.SHIPPED_ARMED, pr_number=42)
    r.stacked_child_restart = "#41 merged into this branch at bbbbbbbb (phase7)"
    line = runner.verdict_line(r, 0, "https://github.com/o/r/pull")
    assert "Stacked child: #41" in line
    assert "\n" not in line


def test_restart_pass_keeps_llm_workdir(monkeypatch, tmp_path):
    """Pass 1 must not close the adapter that pass 2 reuses (close() removes its temp cwd)."""
    pass2 = types.SimpleNamespace(error_kind=None, stacked_child_restart=None)
    calls = _patch_passes(monkeypatch, [_restart_res(), pass2])
    runner.run(None, str(tmp_path), types.SimpleNamespace(ledger=None))
    assert calls[0]["defer_llm_close"] is True
    assert "defer_llm_close" not in calls[1]
    restart = types.SimpleNamespace(error_kind="stacked-child-restart")
    done = types.SimpleNamespace(error_kind=None)
    assert runner._should_close_llm(True, restart) is False
    assert runner._should_close_llm(True, done) is True
    assert runner._should_close_llm(False, restart) is True
