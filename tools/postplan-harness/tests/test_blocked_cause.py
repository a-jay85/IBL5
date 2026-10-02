"""Blocked post-plan runs name the failing command and its error.

Covers the carried command/output fields (HarnessError, RunResult), the bounded
redacted tail helper, the verdict arms, and the blocked-ship block.
"""
import json
import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError, RunResult, TerminalState


@pytest.fixture()
def repo():
    d = tempfile.mkdtemp(prefix="postplan-blocked-cause-")
    env = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}

    def sh(*a):
        subprocess.run(["git", "-C", d, *a], check=True, capture_output=True, env=env)
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    sh("config", "user.email", "t@t")
    sh("config", "user.name", "t")
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    sh("add", "-A")
    sh("commit", "-m", "base")
    return d


def _install_reject_hook(repo, body):
    """Write an executable pre-commit hook into the THROWAWAY fixture repo only."""
    hooks = os.path.join(repo, ".git", "hooks")
    os.makedirs(hooks, exist_ok=True)
    hook = os.path.join(hooks, "pre-commit")
    assert hook.startswith(tempfile.gettempdir()), f"refusing to write a hook at {hook}"
    with open(hook, "w") as fh:
        fh.write(body)
    os.chmod(hook, 0o755)
    return hook


_NOISY_HOOK = ("#!/bin/sh\n"
               "i=0\n"
               "while [ $i -lt 60 ]; do echo \"filler line $i\"; i=$((i+1)); done\n"
               "echo 'GUIDANCE: run bin/check-x'\n"
               "exit 1\n")


def test_harness_error_keeps_cmd_and_trims_output():
    e = HarnessError("local-gate", "d", cmd="git commit", output="x" * 9000 + "LAST")
    assert e.cmd == "git commit"
    assert len(e.output) == 4000
    assert e.output.endswith("LAST")
    plain = HarnessError("git", "d")
    assert plain.cmd == ""
    assert plain.output == ""


def test_commit_all_records_command_and_tail_detail_unchanged(repo):
    _install_reject_hook(repo, _NOISY_HOOK)
    g = LiveGit(repo)
    with open(os.path.join(repo, "b.txt"), "w") as fh:
        fh.write("blocked\n")
    with pytest.raises(HarnessError) as ei:
        g.commit_all("chore: should be rejected")
    e = ei.value
    assert e.kind == "local-gate"
    assert e.cmd == "git commit"
    assert "GUIDANCE: run bin/check-x" in e.output
    assert len(e.detail) <= 800
    assert "GUIDANCE: run bin/check-x" not in e.detail, "detail stays the old head slice"


def test_result_json_omits_unset_error_cmd():
    d = json.loads(RunResult(terminal=TerminalState.FAILED).to_json())
    assert "error_cmd" not in d
    assert "error_output_tail" not in d
    d2 = json.loads(RunResult(terminal=TerminalState.FAILED, error_cmd="git commit").to_json())
    assert d2["error_cmd"] == "git commit"
    assert "error_output_tail" not in d2
