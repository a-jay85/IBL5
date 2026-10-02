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


# --- tail / redaction helpers -------------------------------------------------

def test_error_tail_keeps_last_three_lines_of_10kb():
    lines = [f"line {i:03d} " + "x" * 40 for i in range(200)]
    result = runner._error_tail("\n".join(lines))
    assert result == lines[-3:]
    assert len(" ".join(result)) <= 300
    assert all("\n" not in el for el in result)


def test_error_tail_cuts_single_huge_line_to_its_end():
    result = runner._error_tail("y" * 10000 + "END-MARKER")
    assert len(result) == 1
    assert len(result[0]) <= 300
    assert result[0].endswith("END-MARKER")
    assert result[0].startswith("…")


@pytest.mark.parametrize("text", [None, "", " \n\t\n "])
def test_error_tail_empty_inputs(text):
    assert runner._error_tail(text) == []


def test_error_tail_matches_flat_for_short_errors():
    from test_verdict_line import _RC3_CASES
    gate_ids = {"gate-adr", "gate-adr-drafted", "gate-unknown",
                "gate-stale-base", "gate-byte-budget", "gate-doc-staleness"}
    rows = [c for c in _RC3_CASES if c[0] in gate_ids]
    assert len(rows) == 6
    for _id, _kind, err, _extra, _expected in rows:
        assert " ".join(runner._error_tail(err)) == " ".join(err.split()), _id


_ALNUM36 = "A1b2C3d4E5f6G7h8I9j0K1l2M3n4O5p6Q7r8"


@pytest.mark.parametrize("text,secret,survivor", [
    (f"https://x-access-token:ghs_{_ALNUM36}@github.com/a/b.git", f"ghs_{_ALNUM36}",
     "github.com/a/b.git"),
    (f"push failed with ghp_{_ALNUM36}", f"ghp_{_ALNUM36}", None),
    ("token github_pat_" + "a1B2c3D4e5" * 4, "a1B2c3D4e5" * 4, None),
    ("Authorization: Bearer abc.def-ghi", "abc.def-ghi", None),
])
def test_redact_strips_credentials(text, secret, survivor):
    assert secret not in " ".join(runner._error_tail(text))
    assert secret not in runner._cmd_text(text)
    if survivor:
        assert survivor in " ".join(runner._error_tail(text))


def _stage_res(kind, error):
    r = RunResult(terminal=TerminalState.FAILED)
    r.error_kind = kind
    r.error = error
    return r


def test_stage_of_prefers_phase_then_kind():
    diverged = ("remote-head-diverged: phase7: remote head X diverged from Y "
                "with different content")
    assert runner._stage_of(_stage_res("remote-head-diverged", diverged)) == "phase7"
    assert runner._stage_of(_stage_res("local-gate", "local-gate: phase5.5: x")) == "phase5.5"
    assert runner._stage_of(_stage_res("git", "boom")) == "git"
    assert runner._stage_of(_stage_res(None, None)) == "unrecorded"
