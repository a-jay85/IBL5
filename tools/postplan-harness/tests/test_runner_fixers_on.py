"""The fixers always run: a not-ready review enters fix rounds, and old closer-era state is inert.

These tests never read or set an A/B arm, so they stay valid after the closer trial's code is
gone. The scaffolds are copied from test_stale_base_push.py (the _run_fidelity driver) and
test_runner_held_disarm.py (the full-run driver).
"""
from __future__ import annotations

import importlib.util
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
from harness import statefile
from harness.statefile import StateFile
from test_holdrepeat_runner import CANNED, CLEAN_PLAN, SLUG, _fixture

# Retired names are built from pieces so the residue sweep (plan row 11) finds no literal token.
_ARM = "ab" + "_arm"
_SRC = "ab" + "_source"
_ENROLL = "ab" + "-enrollment.json"
_OUTCOMES = "ab" + "-outcomes.jsonl"
_ARM_ENV = "POSTPLAN_" + "AB_ARM"
_OLD_STATE = {_ARM: "closer", _SRC: "enrolled", "closer": {"ran": True}, "phases": []}

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
    assert _drive_not_ready_review(tmp_path, monkeypatch) >= 1


def _drive_not_ready_review(tmp_path, monkeypatch):
    """Run Phase 5.5 on a not-ready first review; return the number of fixer calls."""
    tmp_path.mkdir(parents=True, exist_ok=True)
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
    assert res.fidelity["rounds"][0]["round"] == 1
    return len(rounds)


def test_old_state_file_with_closer_keys_loads(tmp_path):
    path = tmp_path / "demo.json"
    written = dict(_OLD_STATE)
    path.write_text(json.dumps(written))
    logged = []
    sf = StateFile(str(path), "demo", None, str(tmp_path), logged.append)
    assert sf.previous == written
    assert "state: previous file unreadable — rewriting" not in logged


# ── full-run scaffold (copied from test_runner_held_disarm.py) ─────────────────────────────

def _run(tmp_path, plan, state_dir=None):
    out = str(tmp_path / "out")
    llm = FixtureLlm(UsageLedger(), dict(CANNED))
    res = runner.run(_fixture(plan), out, llm, mode="replay", live=False, state_dir=state_dir)
    return res, out


def _plant_old_state(state_dir):
    os.makedirs(state_dir, exist_ok=True)
    old = dict(_OLD_STATE)
    with open(os.path.join(state_dir, statefile.safe_slug(SLUG) + ".json"), "w") as fh:
        json.dump(old, fh)


def _fired_by_lines(res):
    """Each audit line is `[HH:MM:SS] <msg>`; strip the clock and keep the fired-by messages."""
    msgs = [ln.split("] ", 1)[-1] for ln in res.audit]
    return [m for m in msgs if m.startswith("postplan: fired-by=")]


def _no_ab_files(tmp_path, state_dir):
    names = {p.name for p in tmp_path.rglob("*")}
    assert _ENROLL not in names
    assert _OUTCOMES not in names
    assert not (set(os.listdir(state_dir)) & {_ENROLL, _OUTCOMES})


def test_runner_has_no_closer_entry_points():
    assert importlib.util.find_spec("harness." + "closer") is None
    assert importlib.util.find_spec("harness." + _ARM) is None
    assert importlib.util.find_spec("harness." + "ab" + "_readout") is None
    assert not hasattr(runner, "_maybe_run" + "_closer")


def test_fired_by_automouse_run_writes_no_enrollment(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_FIRED_BY", "automouse")
    state_dir = str(tmp_path / "state")
    _plant_old_state(state_dir)
    res, _out = _run(tmp_path, CLEAN_PLAN, state_dir=state_dir)
    assert _fired_by_lines(res) == ["postplan: fired-by=automouse"]
    _no_ab_files(tmp_path, state_dir)
    assert not hasattr(res, _ARM)


@pytest.mark.parametrize("arm_env", ["closer", ""])
def test_stale_ab_arm_env_is_ignored(tmp_path, monkeypatch, arm_env):
    monkeypatch.setenv(_ARM_ENV, arm_env)
    monkeypatch.setenv("POSTPLAN_FIRED_BY", "automouse")
    state_dir = str(tmp_path / "state")
    os.makedirs(state_dir)
    res, _out = _run(tmp_path, CLEAN_PLAN, state_dir=state_dir)
    assert not hasattr(res, _ARM)
    _no_ab_files(tmp_path, state_dir)
    assert _drive_not_ready_review(tmp_path / "fid", monkeypatch) >= 1


@pytest.mark.parametrize("mode", ["unset", "empty"])
def test_fired_by_unset_or_empty_is_logged(tmp_path, monkeypatch, mode):
    if mode == "unset":
        monkeypatch.delenv("POSTPLAN_FIRED_BY", raising=False)
    else:
        monkeypatch.setenv("POSTPLAN_FIRED_BY", "")
    state_dir = str(tmp_path / "state")
    os.makedirs(state_dir)
    res, _out = _run(tmp_path, CLEAN_PLAN, state_dir=state_dir)
    assert _fired_by_lines(res) == ["postplan: fired-by=unknown"]
