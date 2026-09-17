"""Phase 5 anti-regression — remediation_sha aliases the last round's sha."""
import os
import stat
import sys
import types

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.state import UsageLedger
from harness.adapters.llm import FixtureLlm
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import RecordingGh

import runner

TREE_1 = "a" * 40
TREE_2 = "b" * 40
TREE_3 = "c" * 40
TREE_4 = "d" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


class _CountingGit(ReplayGit):
    """Returns a distinct sha per commit so rounds are distinguishable."""

    def commit_all(self, message):
        super().commit_all(message)
        return f"round-sha-{len(self.commit_messages)}"


def _counting_git():
    return _CountingGit({
        "slug": "demo",
        "worktree_diff": "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4],
    })


def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


class _Res:
    def __init__(self):
        self.fidelity = {}


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


def test_last_round_sha_is_aliased(tmp_path, monkeypatch):
    """After 2 rounds, remediation_sha is the round-2 sha; rounds[0] has round-1."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nNOT READY\n",
        "plan-fidelity-re-review-3": "READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _counting_git()
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff", "body", 99, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        assert res.fidelity["remediation_sha"] == "round-sha-2"
        assert res.fidelity["rounds"][0]["remediation_sha"] == "round-sha-1"
        assert res.fidelity["rounds_completed"] == 2
    finally:
        _cleanup(99, "99-2", "99-3")


def test_three_round_loop_emits_three_audit_lines(tmp_path, monkeypatch):
    """A fully-exhausted loop produces exactly 3 'phase5.5 round ' lines."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
        "plan-fidelity-re-review-3": "NOT READY\n",
        "plan-fidelity-re-review-4": "NOT READY\n",
    }
    logged = []
    llm = FixtureLlm(UsageLedger(), canned)
    git = _counting_git()
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff", "body", 99, "dead" * 10, TREE_1, False, logged.append, res,
        )
        round_lines = [l for l in logged if "phase5.5 round " in l]
        assert len(round_lines) == 3
    finally:
        _cleanup(99, "99-2", "99-3", "99-4")
