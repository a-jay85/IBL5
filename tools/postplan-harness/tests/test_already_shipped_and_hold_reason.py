"""already-shipped terminal (empty diff + MERGED PR) and hold=<reasons> on the RESULT line."""
import os
import stat
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.adapters.llm import FixtureLlm
from harness.state import (ArmDecision, ConditionResult, HarnessError, RunResult,
                           TerminalState, UsageLedger)

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: x", "commit_subject": "chore: x",
                "summary_md": "## Summary\n- x\n"},
    "body-check": {"corrected_body": "## Summary\n- x\n", "findings": []},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [], "safety-verdict": {"holds": []},
    "manual-classify": [], "retrospective": {"save": False},
}
GH_NO_COMMITS = ("gh pr create --title...: GraphQL: No commits between master and "
                 "b (createPullRequest)")
MERGED = {"number": 2706, "url": "https://github.com/o/r/pull/2706"}


def _fixture(**over):
    fx = {
        "slug": "already-merged-branch",
        "diff": "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n",
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": "# Plan\n\nBody.\n",
    }
    fx.update(over)
    return fx


def _run(tmp_path, monkeypatch, *, merged, empty_after_create=True, fail=None):
    state = {"created": False}
    fail = fail or HarnessError("gh", GH_NO_COMMITS)

    def pr_create(self, *a, **k):
        state["created"] = True
        raise fail
    orig = ReplayGit.diff_vs_base
    monkeypatch.setattr(RecordingGh, "pr_create", pr_create)
    monkeypatch.setattr(
        ReplayGit, "diff_vs_base",
        lambda self, base="origin/master": "" if (state["created"] and empty_after_create)
        else orig(self, base))
    fx = _fixture(**({"merged_pr": merged} if merged else {}))
    return runner.run(fx, str(tmp_path / "out"), FixtureLlm(UsageLedger(), CANNED), mode="replay")


# ---- Fix 1: end to end through run() ---------------------------------------

def test_gh_no_commits_with_merged_pr_is_already_shipped(tmp_path, monkeypatch):
    res = _run(tmp_path, monkeypatch, merged=MERGED)
    assert res.terminal == TerminalState.ALREADY_SHIPPED
    assert res.pr_number == 2706 and res.error_kind is None
    assert runner.exit_code_for(res) == 0
    line = runner.verdict_line(res, 0, "https://github.com/o/r/pull")
    assert line.startswith("RESULT: post-plan complete — terminal=already-shipped PR #2706 "
                           "https://github.com/o/r/pull/2706")
    assert "FAILED" not in line and "BLOCKED" not in line and "\n" not in line


def test_gh_no_commits_without_merged_pr_stays_failed(tmp_path, monkeypatch):
    res = _run(tmp_path, monkeypatch, merged=None)
    assert res.terminal == TerminalState.FAILED and res.error_kind == "gh"
    assert runner.exit_code_for(res) == 1


def test_non_empty_diff_is_never_already_shipped(tmp_path, monkeypatch):
    res = _run(tmp_path, monkeypatch, merged=MERGED, empty_after_create=False)
    assert res.terminal == TerminalState.FAILED and res.error_kind == "gh"


# ---- Fix 1: the detector, per RESULT shape ----------------------------------

class _Git:
    def __init__(self, diff=""):
        self.diff = diff

    def diff_vs_base(self, base="origin/master"):
        return self.diff


class _Gh:
    def __init__(self, merged=MERGED):
        self.merged = merged

    def merged_pr(self):
        return self.merged


@pytest.mark.parametrize("kind,detail", [
    ("lostwork-unproved", "phase2: diff vs origin/master is empty before re-rebase"),
    ("lostwork-unproved", "phase7: lost-work proof failed after re-rebase 1: TREE DIVERGED — "
                          "/tmp/pr-ready-diff-post-x-r1.patch is missing or empty; nothing was compared"),
    ("gh", GH_NO_COMMITS),
])
def test_detector_matches_each_empty_diff_shape(kind, detail):
    e = HarnessError(kind, detail)
    assert runner._already_shipped(e, _Git(""), _Gh(), lambda m: None) == MERGED
    assert runner._already_shipped(e, _Git(""), _Gh(None), lambda m: None) is None
    assert runner._already_shipped(e, _Git("diff --git a b\n+x\n"), _Gh(), lambda m: None) is None


def test_detector_ignores_real_divergence_and_other_kinds():
    log = lambda m: None
    diverged = HarnessError("lostwork-unproved",
                            "phase7: lost-work proof failed after re-rebase 1: TREE DIVERGED — files differ")
    assert runner._already_shipped(diverged, _Git(""), _Gh(), log) is None
    other = HarnessError("push-failed", "diff vs origin/master is empty")
    assert runner._already_shipped(other, _Git(""), _Gh(), log) is None


def test_detector_probe_failure_keeps_failure():
    class Boom(_Gh):
        def merged_pr(self):
            raise HarnessError("gh", "boom")
    e = HarnessError("gh", GH_NO_COMMITS)
    assert runner._already_shipped(e, _Git(""), Boom(), lambda m: None) is None


# ---- LiveGh.merged_pr is a read-only `pr list` -------------------------------

def _shim(tmp_path, monkeypatch, body):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text("#!/usr/bin/env bash\necho \"$*\" >> \"$GH_SHIM_LOG\"\n" + body + "\n")
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    log = tmp_path / "calls.log"
    log.write_text("")
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    monkeypatch.setenv("GH_SHIM_LOG", str(log))
    return log


def test_live_merged_pr_reads_pr_list(tmp_path, monkeypatch):
    log = _shim(tmp_path, monkeypatch,
                'echo \'[{"number":2706,"url":"https://github.com/o/r/pull/2706"}]\'')
    gh = LiveGh(str(tmp_path / "out"), str(tmp_path), "my-branch")
    assert gh.merged_pr() == MERGED
    assert log.read_text().splitlines() == [
        "pr list --head my-branch --state merged --json number,url --limit 1"]
    assert gh.actions() == []          # a read: nothing audited as a mutation


def test_live_merged_pr_empty_and_error_are_none(tmp_path, monkeypatch):
    _shim(tmp_path, monkeypatch, "echo '[]'")
    assert LiveGh(str(tmp_path / "o1"), str(tmp_path), "b").merged_pr() is None
    (tmp_path / "bin" / "gh").write_text("#!/usr/bin/env bash\nexit 1\n")
    assert LiveGh(str(tmp_path / "o2"), str(tmp_path), "b").merged_pr() is None


# ---- Fix 2: hold=<reasons> ----------------------------------------------------

def _held(*pairs, terminal=TerminalState.SHIPPED_HELD):
    r = RunResult(terminal=terminal, slug="hold-reason-pin-no-such-autoresolved-file",
                  pr_number=5)
    r.arm = ArmDecision(armed=False, conditions=[
        ConditionResult(n, f"c{n}", True, reason) for n, reason in pairs])
    return r


def test_held_result_line_ends_with_hold_reasons():
    r = _held((7, "plan declares auto_merge: false"), (8, "feat: PR awaiting human-signoff"))
    line = runner.verdict_line(r, 0, "https://github.com/o/r/pull")
    assert line.endswith(" findings=0 hold=(7) plan declares auto_merge: false; "
                         "(8) feat: PR awaiting human-signoff")
    assert "\n" not in line


def test_hold_reasons_are_capped_and_flat():
    r = _held((1, "x\ny " * 200))
    note = runner._hold_reasons_note(r)
    assert "\n" not in note
    assert len(note) <= len(" hold=") + runner.HOLD_REASONS_CAP and note.endswith("…")


def test_armed_and_blockless_runs_have_no_hold_suffix():
    armed = RunResult(terminal=TerminalState.SHIPPED_ARMED, slug="x", pr_number=5)
    armed.arm = ArmDecision(armed=True)
    assert "hold=" not in runner.verdict_line(armed, 0)
    assert runner._hold_reasons_note(RunResult(terminal=TerminalState.SHIPPED_HELD)) == ""


def test_degraded_held_run_also_reports_reasons():
    r = _held((9, "safety verdict unavailable"), terminal=TerminalState.DEGRADED)
    assert runner.verdict_line(r, 0).endswith("hold=(9) safety verdict unavailable")


# ---- Fix 3: a killed run leaves a RESULT line in $POSTPLAN_LOG_PATH ------------

def test_last_audit_phase():
    assert runner._last_audit_phase(["[1] phase2: rebased", "[2] phase6.5: HELD", "[3] note"]) \
        == "phase6.5"
    assert runner._last_audit_phase([]) == "start"


def test_sigterm_appends_killed_result_line(tmp_path):
    import subprocess
    log = tmp_path / "run.log"
    log.write_text("post-plan-now: started t pid=1\n")
    code = (
        "import os, signal, sys\n"
        f"sys.path.insert(0, {os.path.dirname(os.path.dirname(os.path.abspath(__file__)))!r})\n"
        "import runner\n"
        "runner._active_audit = ['[t] phase4: review launched']\n"
        "runner._install_sigterm_handler()\n"
        "os.kill(os.getpid(), signal.SIGTERM)\n"
    )
    p = subprocess.run([sys.executable, "-c", code], env={**os.environ,
                       "POSTPLAN_LOG_PATH": str(log)}, timeout=30)
    assert p.returncode == 143
    lines = log.read_text().splitlines()
    assert lines[0].startswith("post-plan-now: started")
    assert lines[1] == "RESULT: post-plan KILLED (signal 15) at phase4"
    assert [l for l in lines if l.startswith("RESULT:")] == [lines[1]]
