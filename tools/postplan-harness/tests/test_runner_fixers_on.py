"""The fixers always run: a not-ready review enters fix rounds, and old closer-era state is inert.

These tests never read or set an A/B arm, so they stay valid after the closer trial's code is
gone. The scaffolds are copied from test_stale_base_push.py (the _run_fidelity driver) and
test_runner_held_disarm.py (the full-run driver).
"""
from __future__ import annotations

import json
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import runner
from harness import fidelity
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger
from harness.statefile import StateFile

_PR = 9920
_TREE_A = "a" * 40

_GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


def _install_git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir(exist_ok=True)
    g = bindir / "git"
    g.write_text(_GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")


class _CommitGit(ReplayGit):
    """ReplayGit that hands out a distinct sha per commit and accepts every push."""

    def __init__(self, fixture):
        super().__init__(fixture)
        self._commit_count = 0
        self._current_sha = "initial-" + "0" * 34

    def commit_all(self, message):
        self._commit_count += 1
        self._current_sha = f"commit-sha-{self._commit_count}"
        return self._current_sha

    def head(self):
        return self._current_sha

    def capture_lostwork_pre(self, key):
        return True

    def fetch_base(self, base="origin/master"):
        pass

    def rebase_onto(self, base="origin/master"):
        pass

    def prove_lostwork(self, key):
        return (True, "TREE-EQUIVALENT")

    def push(self):
        return None


class _Res:
    def __init__(self):
        self.fidelity = {}
        self.adr_drafted = False
        self.adr_path = None
        self.adr_draft_model = None


def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


def test_not_ready_first_review_enters_fidelity_rounds(tmp_path, monkeypatch):
    _install_git_shim(tmp_path, monkeypatch)
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nREADY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _CommitGit({"slug": "demo", "worktree_diff": "",
                      "diff": "diff --git a/x b/x\n", "head_trees": [_TREE_A]})
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    rounds = []
    real_remediate = fidelity.remediate

    def spy(*args, **kwargs):
        rounds.append(1)
        return real_remediate(*args, **kwargs)

    monkeypatch.setattr(fidelity, "remediate", spy)
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff --git a/x b/x\n", "body", _PR, "dead" * 10,
            _TREE_A, False, lambda m: None, res,
        )
    finally:
        _cleanup(_PR, f"{_PR}-2")
    assert rounds, "a not-ready first review must call the fixer for round 1"
    assert res.fidelity["rounds"][0]["round"] == 1


def test_old_state_file_with_closer_keys_loads(tmp_path):
    path = tmp_path / "demo.json"
    written = {"ab_arm": "closer", "ab_source": "enrolled", "closer": {"ran": True}, "phases": []}
    path.write_text(json.dumps(written))
    logged = []
    sf = StateFile(str(path), "demo", None, str(tmp_path), logged.append)
    assert sf.previous == written
    assert "state: previous file unreadable — rewriting" not in logged
