"""Tests for runner.verdict_line() and runner._pull_url_base()."""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.state import ArmDecision, RunResult, TerminalState


def _res(terminal, **kw):
    r = RunResult(terminal=terminal)
    for k, v in kw.items():
        setattr(r, k, v)
    return r


def _arm(armed=True):
    return ArmDecision(armed=armed)


# ---------------------------------------------------------------------------
# verdict_line — all five terminal branches
# ---------------------------------------------------------------------------

def test_shipped_armed_starts_with_result():
    r = _res(TerminalState.SHIPPED_ARMED, pr_number=42,
             ci_outcome="success", final_pr_state="merged",
             arm=_arm(True))
    line = runner.verdict_line(r, 0, "https://github.com/o/r/pull")
    assert line.startswith("RESULT: ")


def test_shipped_armed_contains_pr_number():
    r = _res(TerminalState.SHIPPED_ARMED, pr_number=42, arm=_arm(True))
    line = runner.verdict_line(r, 0, "https://github.com/o/r/pull")
    assert "PR #42" in line


def test_shipped_armed_with_pull_base_contains_pull_url():
    r = _res(TerminalState.SHIPPED_ARMED, pr_number=42, arm=_arm(True))
    line = runner.verdict_line(r, 0, "https://github.com/o/r/pull")
    assert "pull/42" in line


def test_shipped_armed_without_pull_base_no_pull_url():
    """Degrades gracefully: PR #<n> present, pull/<n> absent when base not provided."""
    r = _res(TerminalState.SHIPPED_ARMED, pr_number=42, arm=_arm(True))
    line = runner.verdict_line(r, 0, "")
    assert "PR #42" in line
    assert "pull/" not in line


def test_shipped_armed_mentions_armed():
    r = _res(TerminalState.SHIPPED_ARMED, arm=_arm(True))
    line = runner.verdict_line(r, 0)
    assert "armed" in line


def test_shipped_held_starts_with_result():
    r = _res(TerminalState.SHIPPED_HELD, pr_number=7, arm=_arm(False))
    line = runner.verdict_line(r, 0)
    assert line.startswith("RESULT: ")


def test_shipped_held_mentions_held():
    r = _res(TerminalState.SHIPPED_HELD, pr_number=7, arm=_arm(False))
    line = runner.verdict_line(r, 0)
    assert "HELD" in line


def test_shipped_held_is_not_armed():
    r = _res(TerminalState.SHIPPED_HELD, pr_number=7, arm=_arm(False))
    line = runner.verdict_line(r, 0)
    # must not claim the merge was armed
    assert "auto-merge=armed" not in line


def test_nothing_to_ship_starts_with_result():
    r = _res(TerminalState.NOTHING_TO_SHIP)
    line = runner.verdict_line(r, 0)
    assert line.startswith("RESULT: ")


def test_nothing_to_ship_mentions_nothing():
    r = _res(TerminalState.NOTHING_TO_SHIP)
    line = runner.verdict_line(r, 0)
    assert "nothing" in line.lower()


def test_failed_starts_with_result():
    r = _res(TerminalState.FAILED, error_kind="push-disabled",
             error="remote rejected the push")
    line = runner.verdict_line(r, 1)
    assert line.startswith("RESULT: ")


def test_failed_contains_failure_word():
    r = _res(TerminalState.FAILED, error_kind="push-disabled",
             error="remote rejected the push")
    line = runner.verdict_line(r, 1)
    assert any(w in line for w in ("FAILED", "ERROR", "conflict"))


def test_failed_includes_error_kind():
    r = _res(TerminalState.FAILED, error_kind="push-disabled",
             error="remote rejected the push")
    line = runner.verdict_line(r, 1)
    assert "push-disabled" in line


def test_rebase_conflict_starts_with_result():
    """rc=3 is the rebase-conflict sentinel — the exit code drives this branch."""
    r = _res(TerminalState.FAILED, error_kind="rebase-conflict")
    line = runner.verdict_line(r, 3)
    assert line.startswith("RESULT: ")


def test_rebase_conflict_contains_failure_word():
    r = _res(TerminalState.FAILED, error_kind="rebase-conflict")
    line = runner.verdict_line(r, 3)
    assert any(w in line for w in ("FAILED", "ERROR", "conflict"))


def test_rebase_conflict_no_pr_opened():
    """The rebase-conflict branch explicitly says no PR was opened."""
    r = _res(TerminalState.FAILED, error_kind="rebase-conflict")
    line = runner.verdict_line(r, 3)
    assert "no PR opened" in line


# ---------------------------------------------------------------------------
# _pull_url_base — URL shape normalization
# ---------------------------------------------------------------------------

def _make_repo_with_remote(tmp_path, remote_url):
    """Create a minimal git repo with origin set to remote_url."""
    subprocess.run(["git", "init", str(tmp_path)], check=True, capture_output=True)
    subprocess.run(["git", "-C", str(tmp_path), "remote", "add", "origin", remote_url],
                   check=True, capture_output=True)
    return str(tmp_path)


def test_pull_url_base_ssh_protocol(tmp_path):
    """ssh://git@github.com/o/r.git — the actual remote form this repo uses."""
    repo = _make_repo_with_remote(tmp_path, "ssh://git@github.com/o/r.git")
    assert runner._pull_url_base(repo) == "https://github.com/o/r/pull"


def test_pull_url_base_scp_style(tmp_path):
    """git@github.com:o/r.git — the SCP-style shorthand."""
    repo = _make_repo_with_remote(tmp_path, "git@github.com:o/r.git")
    assert runner._pull_url_base(repo) == "https://github.com/o/r/pull"


def test_pull_url_base_https(tmp_path):
    """https://github.com/o/r.git — plain HTTPS clone URL."""
    repo = _make_repo_with_remote(tmp_path, "https://github.com/o/r.git")
    assert runner._pull_url_base(repo) == "https://github.com/o/r/pull"


def test_pull_url_base_none_returns_empty():
    assert runner._pull_url_base(None) == ""


def test_pull_url_base_nonexistent_path_returns_empty():
    assert runner._pull_url_base("/nonexistent-path-xyz-abc") == ""
