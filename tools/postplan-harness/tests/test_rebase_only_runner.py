"""Rebase-only mode: runner.run(..., rebase_only=True) merges master, pushes, and stops.

Real temp repos (bare origin + clone) so `LiveGit` runs the actual merge. A sentinel
`_pr_copy` marks "the full path was reached"; a recorder stands in for the push.
"""
import json
import os
import stat
import subprocess
import sys
import types
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import conformance, heldmarker, holdrepeat
from harness.adapters.gitad import LiveGit
from harness.adapters.llm import FixtureLlm
from harness.planfile import locate_plan
from harness.state import HarnessError, TerminalState, UsageLedger

SLUG = "rebase-only-run"

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit",
                "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}

CRITICAL = "ibl5/classes/RebaseOnlyMissing.php"
MATRIX_TEST = "ibl5/tests/RebaseOnlyMissingTest.php"
MISSING_PLAN = (
    "# Synthetic plan\n\n"
    f"## Critical Files\n\n- `{CRITICAL}` (new)\n\n"
    "## Verification Matrix\n\n"
    "| # | What | Test type | Timing | File |\n|---|---|---|---|---|\n"
    f"| 1 | thing works | PHPUnit | post-impl | `{MATRIX_TEST}` |\n"
)

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *args):
    # core.hooksPath=/dev/null: a developer's global hooks must not fire on fixture commits.
    return subprocess.run(["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *args],
                          check=True, capture_output=True, text=True, env=_ENV)


def _write(d, rel, text):
    target = Path(d) / rel
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(text)


def _real_repo(tmp_path, *, master_moves=True):
    """Bare origin + clone on branch SLUG with one branch commit; master optionally
    gains a commit on a file the branch does not touch (a clean merge)."""
    origin, seed, wt = tmp_path / "origin.git", tmp_path / "seed", tmp_path / "wt"
    _git(tmp_path, "init", "-q", "--bare", "-b", "master", str(origin))
    _git(tmp_path, "init", "-q", "-b", "master", str(seed))
    _write(seed, "a.txt", "base\n")
    _git(seed, "add", "-A")
    _git(seed, "commit", "-qm", "base")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    _git(tmp_path, "clone", "-q", str(origin), str(wt))
    _git(wt, "config", "user.email", "t@t")
    _git(wt, "config", "user.name", "t")
    _git(wt, "config", "core.hooksPath", "/dev/null")
    _git(wt, "checkout", "-qb", SLUG)
    _write(wt, "feature/work.txt", "branch work\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-qm", "chore: branch work")
    if master_moves:
        _write(seed, "master_only.txt", "master moved\n")
        _git(seed, "add", "-A")
        _git(seed, "commit", "-qm", "master moved")
        _git(seed, "push", "-q", "origin", "master")
        _git(wt, "fetch", "-q", "origin", "master")
    return wt


def _cond(n, reason="r"):
    return types.SimpleNamespace(number=n, name=f"cond-{n}", blocked=True, reason=reason)


def _seed(state, nums, *, dm_sent=False):
    conds = [_cond(n) for n in nums]
    holdrepeat.observe(str(state), SLUG, armed=False, conditions=conds,
                       fingerprint="fp", pr=4242, now="2026-10-12T00:00:00Z")
    if dm_sent:
        holdrepeat.mark_dm_sent(str(state), SLUG, holdrepeat.structural_key(conds))


@pytest.fixture
def dm(tmp_path, monkeypatch):
    """Fake DM command so no real DM can leave a test."""
    script = tmp_path / "fake-dm"
    script.write_text("#!/bin/sh\nexit 0\n")
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("HOLDREPEAT_DM_CMD", str(script))


@pytest.fixture
def push(monkeypatch):
    calls = []
    behaviour = {"raise": None}

    def rec(git, log, phase, **kw):
        calls.append((phase, kw))
        if behaviour["raise"] is not None:
            raise behaviour["raise"]
        return "deadbeef"
    monkeypatch.setattr(runner, "_push_with_lease_retry", rec)
    return types.SimpleNamespace(calls=calls, behaviour=behaviour)


@pytest.fixture
def sentinel(monkeypatch):
    calls = []

    def fake_pr_copy(*a, **k):
        calls.append(1)
        raise HarnessError("test-sentinel", "full path")
    monkeypatch.setattr(runner, "_pr_copy", fake_pr_copy)
    return calls


def _run(tmp_path, wt, *, rebase_only=True, plans=None):
    plans = plans or (tmp_path / "plans")
    plans.mkdir(exist_ok=True)
    out = tmp_path / "out"
    ledger = UsageLedger()
    llm = FixtureLlm(ledger, dict(CANNED))
    res = runner.run(None, str(out), llm, mode="live", live=False, worktree=str(wt),
                     plans_dir=str(plans), state_dir=str(tmp_path / "state"),
                     rebase_only=rebase_only)
    return res, ledger, out


def _calls(ledger):
    return ledger.totals()["llm_invocations"]


def _actions(out):
    path = out / "actions.jsonl"
    return path.read_text() if path.exists() else ""


def _result(out):
    return json.loads((out / "result.json").read_text())


def test_prior_hold_7_8_rebases_without_review(tmp_path, dm, push, sentinel):
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path)
    res, ledger, out = _run(tmp_path, wt)
    assert res.terminal == TerminalState.REBASED_HELD
    assert sentinel == []
    assert len(push.calls) == 1
    assert _calls(ledger) == 0
    assert res.arm is None
    acts = _actions(out)
    for verb in ("pr_sticky_verdict", "pr_merge_auto", "pr_create"):
        assert verb not in acts
    ro = _result(out)["rebase_only"]
    assert ro["pushed"] is True and ro["noop"] is False and ro["hold"] == "7,8"
    _git(wt, "merge-base", "--is-ancestor", "origin/master", "HEAD")
    assert runner.exit_code_for(res) == 0


def test_condition_3_rebases_before_declining(tmp_path, dm, push, sentinel, monkeypatch):
    monkeypatch.setattr(holdrepeat, "fingerprint", lambda *a, **k: "fp-const")
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / f"{SLUG}.md").write_text(MISSING_PLAN)
    plan = locate_plan(SLUG, plans_dir=str(plans))
    items = conformance.early_missing_items(plan, [])
    assert items
    reason = "; ".join(items)
    holdrepeat.observe(str(tmp_path / "state"), SLUG, armed=False,
                       conditions=[_cond(3, reason)], fingerprint="fp-const",
                       pr=4242, now="t")
    wt = _real_repo(tmp_path)
    res, ledger, _ = _run(tmp_path, wt, plans=plans)
    assert res.terminal == TerminalState.REBASED_HELD
    assert res.terminal != TerminalState.HOLD_REPEAT_DECLINED
    assert _calls(ledger) == 0


def test_machine_fixable_condition_runs_full_path(tmp_path, dm, push, sentinel):
    _seed(tmp_path / "state", [7, 15])
    wt = _real_repo(tmp_path)
    res, _, _ = _run(tmp_path, wt)
    assert sentinel == [1]
    assert res.terminal != TerminalState.REBASED_HELD
    assert any("rebase-only: ignored" in line for line in res.audit)


def test_absent_record_runs_full_path(tmp_path, dm, push, sentinel):
    wt = _real_repo(tmp_path)
    res, _, _ = _run(tmp_path, wt)
    assert sentinel == [1]
    assert res.terminal != TerminalState.REBASED_HELD


def test_flag_off_runs_full_path(tmp_path, dm, push, sentinel):
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path)
    res, _, _ = _run(tmp_path, wt, rebase_only=False)
    assert sentinel == [1]
    assert res.terminal != TerminalState.REBASED_HELD


def test_already_up_to_date_is_noop(tmp_path, dm, push, sentinel):
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path, master_moves=False)
    res, _, out = _run(tmp_path, wt)
    assert res.terminal == TerminalState.REBASED_HELD
    assert push.calls == []
    ro = _result(out)["rebase_only"]
    assert ro["noop"] is True and ro["pushed"] is False
    assert "nothing pushed" in runner.verdict_line(res, 0)


def test_proof_failure_does_not_push(tmp_path, dm, push, sentinel, monkeypatch):
    def boom(self, *a, **k):
        raise HarnessError("lostwork-unproved", "LOST hunk")
    monkeypatch.setattr(LiveGit, "rebase_onto", boom)
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path)
    res, ledger, _ = _run(tmp_path, wt)
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "lostwork-unproved"
    assert push.calls == []
    assert sentinel == []
    assert _calls(ledger) == 0


def test_staged_changes_runs_full_path(tmp_path, dm, push, sentinel):
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path)
    _write(wt, "feature/uncommitted.txt", "not committed\n")
    res, _, _ = _run(tmp_path, wt)
    assert sentinel == [1]
    assert res.terminal != TerminalState.REBASED_HELD
    assert any("uncommitted changes" in line for line in res.audit)


class _StubGit:
    def branch(self):
        return SLUG

    def head(self):
        return "0" * 40


def _gate(tmp_path, gh, *, live=True):
    lines = []
    return runner._rebase_only_gate(
        _StubGit(), gh, SLUG, str(tmp_path), str(tmp_path / "out"), live,
        str(tmp_path / "state"), "live", lines.append), lines


def test_gate_requires_open_pr_when_live(tmp_path, monkeypatch):
    _seed(tmp_path / "state", [7, 8])
    wt = _real_repo(tmp_path, master_moves=False)
    gh = types.SimpleNamespace(pr_exists=lambda: False, pr_number=lambda: 4242)
    lines = []
    ok, why, pr, _ = runner._rebase_only_gate(
        _StubGit(), gh, SLUG, str(wt), str(tmp_path / "out"), True,
        str(tmp_path / "state"), "live", lines.append)
    assert ok is False and pr is None
    assert why == "no open PR"
    gh_open = types.SimpleNamespace(pr_exists=lambda: True, pr_number=lambda: 4242)
    ok, _, pr, hold = runner._rebase_only_gate(
        _StubGit(), gh_open, SLUG, str(wt), str(tmp_path / "out"), True,
        str(tmp_path / "state"), "live", lines.append)
    assert ok is True and pr == 4242 and hold == "7,8"


def test_gate_fails_closed_on_error(tmp_path, monkeypatch):
    def boom(*a, **k):
        raise RuntimeError("boom")
    monkeypatch.setattr(holdrepeat, "rebase_only_eligible", boom)
    gh = types.SimpleNamespace(pr_exists=lambda: True, pr_number=lambda: 4242)
    lines = []
    ok, why, _, _ = runner._rebase_only_gate(
        _StubGit(), gh, SLUG, str(tmp_path), str(tmp_path / "out"), True,
        str(tmp_path / "state"), "live", lines.append)
    assert ok is False
    assert why.startswith("gate error")
    assert any("running full path" in line for line in lines)


def test_cli_rejects_rebase_only_without_live(tmp_path, monkeypatch):
    monkeypatch.setattr(sys, "argv", ["runner.py", "--mode", "isolated", "--worktree",
                                      str(tmp_path), "--rebase-only"])
    with pytest.raises(SystemExit) as exc:
        runner.main()
    assert exc.value.code == 2


# --- Phase 4: conditional hold-record refresh -------------------------------------------

def test_unchanged_patch_id_refreshes_record(tmp_path, dm, push, sentinel):
    state = tmp_path / "state"
    _seed(state, [7, 8], dm_sent=True)
    before = holdrepeat.load_record(str(state), SLUG)
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / f"{SLUG}.md").write_text("---\nauto_merge: false\n---\n# Synthetic plan\n")
    wt = _real_repo(tmp_path)
    res, _, out = _run(tmp_path, wt, plans=plans)
    assert res.terminal == TerminalState.REBASED_HELD
    plan_path = res.plan.path
    live_fp = holdrepeat.fingerprint(plan_path, str(wt))
    rec = holdrepeat.load_record(str(state), SLUG)
    assert live_fp and rec["fingerprint"] == live_fp != "fp"
    assert rec["repeat_count"] == before["repeat_count"]
    assert _result(out)["rebase_only"]["record_refreshed"] is True
    assert holdrepeat.should_decline(str(state), SLUG, live_fp)[0] is True


def test_changed_patch_id_skips_refresh_and_sticky(tmp_path, dm, push, sentinel, monkeypatch):
    ids = iter(["a" * 40, "b" * 40])
    monkeypatch.setattr(runner.fidelity, "diff_patch_id", lambda _d: next(ids))
    state = tmp_path / "state"
    _seed(state, [7, 8], dm_sent=True)
    wt = _real_repo(tmp_path)
    res, _, out = _run(tmp_path, wt)
    assert res.terminal == TerminalState.REBASED_HELD
    assert holdrepeat.load_record(str(state), SLUG)["fingerprint"] == "fp"
    assert _result(out)["rebase_only"]["record_refreshed"] is False
    assert "pr_sticky_verdict" not in _actions(out)


def test_push_failure_skips_refresh(tmp_path, dm, push, sentinel):
    push.behaviour["raise"] = HarnessError("push-retry-cap", "stale")
    state = tmp_path / "state"
    _seed(state, [7, 8], dm_sent=True)
    wt = _real_repo(tmp_path)
    res, _, _ = _run(tmp_path, wt)
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "push-retry-cap"
    assert holdrepeat.load_record(str(state), SLUG)["fingerprint"] == "fp"


def test_refresh_keeps_heldmarker_active(tmp_path, dm, push, sentinel):
    plans = tmp_path / "plans"
    plans.mkdir()
    (plans / f"{SLUG}.md").write_text(MISSING_PLAN)
    plan = locate_plan(SLUG, plans_dir=str(plans))
    reason = "; ".join(conformance.early_missing_items(plan, []))
    state = tmp_path / "state"
    holdrepeat.observe(str(state), SLUG, armed=False, conditions=[_cond(3, reason)],
                       fingerprint="fp", pr=4242, now="t")
    assert heldmarker.write(str(state), SLUG, plan_path=plan.path, now="t")
    assert heldmarker.active(str(state), SLUG, plan.path)[0] is True
    wt = _real_repo(tmp_path)
    res, _, out = _run(tmp_path, wt, plans=plans)
    assert res.terminal == TerminalState.REBASED_HELD
    assert _result(out)["rebase_only"]["record_refreshed"] is True
    assert heldmarker.active(str(state), SLUG, plan.path)[0] is True
