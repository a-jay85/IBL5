"""Phase 5.5 Manual Testing restore on the non-committing paths (#1189).

Site A: the HarnessError handler in runner._run_fidelity restores the runner-owned
## Manual Testing section for every kind, before push-failed re-raises.
Site B: after the attempt loop, a round that produced no sha restores it once per round.
Site C (committed / body-only round) is covered by test_fidelity_body_only_round.py.
"""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.armable import manual_testing_clearance
from harness.classify import MANUAL_TESTING_SENTINEL
from harness.state import HarnessError, UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_rounds import (NOT_READY, TREE_1, _ScriptedLlm,  # noqa: F401
                                   _cleanup, _counting_git, _plan, _Res,
                                   git_shim)

BEFORE = ("## Summary\n\nOld bullet.\n\n## Manual Testing\n\n"
          f"{MANUAL_TESTING_SENTINEL}\n")
CORRUPTED = ("## Summary\n\nNew bullet.\n\n## Manual Testing\n\n"
             "Covered by `bin/test-pr-cycle`.\n")
CORRUPTED_2 = ("## Summary\n\nNewer bullet.\n\n## Manual Testing\n\n"
               "Skipped: trivial.\n")
NO_SECTION = "## Summary\n\nOld bullet.\n"
INTACT_EDIT = ("## Summary\n\nNew bullet.\n\n## Manual Testing\n\n"
               f"{MANUAL_TESTING_SENTINEL}\n")
REVERTED = "reverted fixer edit to ## Manual Testing"
_LAST_WRITE = "<<runner-last-write>>"


class _LiveBodyGh(_BodySeqGh):
    """_BodySeqGh plus GitHub's read-after-write: _LAST_WRITE reads the runner's last write."""

    def __init__(self, out_dir, bodies, **kw):
        super().__init__(out_dir, bodies, **kw)
        self.writes: list[str] = []

    def pr_edit_body(self, pr, body):
        super().pr_edit_body(pr, body)
        self.writes.append(body)

    def pr_body_fresh(self):
        value = super().pr_body_fresh()
        if value == _LAST_WRITE:
            assert self.writes, "script reads the runner's write before any write"
            return self.writes[-1]
        return value


def _llm(remediation):
    return _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": remediation,
    })


def _run(tmp_path, gh, git, llm, pr, lines):
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                         "diff", BEFORE, pr, "dead" * 10, TREE_1, False,
                         lines.append, _Res())


def _assert_restored(written):
    assert "Covered by" not in written and "Skipped: trivial." not in written
    assert manual_testing_clearance(written) == "CLEARED"


@pytest.mark.parametrize("kind", ["local-gate", "rebase-conflict", "push-retry-cap",
                                  "gate-path-edit"])
def test_terminal_denial_restores_manual_testing_at_the_handler(tmp_path, git_shim, kind):
    gh = _LiveBodyGh(str(tmp_path), [BEFORE, CORRUPTED, _LAST_WRITE],
                     fixture={"pr_number": 9881})
    llm = _llm([{"raise": {"kind": kind, "detail": "denied"}}])
    lines: list[str] = []
    try:
        _run(tmp_path, gh, _counting_git(), llm, 9881, lines)
        assert gh.fresh_calls == 3
        assert len(gh.writes) == 1
        assert "New bullet." in gh.writes[0]
        _assert_restored(gh.writes[0])
        reverted = [i for i, ln in enumerate(lines)
                    if ln.startswith(f"phase5.5 round 1: {REVERTED}")]
        denied = [i for i, ln in enumerate(lines)
                  if f"remediation unavailable ({kind})" in ln]
        assert len(reverted) == 1 and len(denied) == 1
        assert reverted[0] < denied[0]
    finally:
        _cleanup(9881, "9881-2")


def test_push_failed_restores_manual_testing_then_reraises(tmp_path, git_shim):
    gh = _LiveBodyGh(str(tmp_path), [BEFORE, CORRUPTED], fixture={"pr_number": 9882})
    llm = _llm([{"raise": {"kind": "push-failed", "detail": "rejected"}}])
    lines: list[str] = []
    try:
        with pytest.raises(HarnessError) as ei:
            _run(tmp_path, gh, _counting_git(), llm, 9882, lines)
        assert ei.value.kind == "push-failed"
        assert gh.fresh_calls == 2
        assert len(gh.writes) == 1
        assert gh._body_override == gh.writes[0]
        _assert_restored(gh._body_override)
        assert any(ln.startswith(f"phase5.5 round 1: {REVERTED}") for ln in lines)
    finally:
        _cleanup(9882, "9882-2")


def test_no_edits_twice_restores_manual_testing_once_per_round(tmp_path, git_shim):
    gh = _LiveBodyGh(str(tmp_path),
                     [BEFORE, BEFORE, CORRUPTED, CORRUPTED, CORRUPTED,
                      _LAST_WRITE, _LAST_WRITE, CORRUPTED_2, CORRUPTED_2, CORRUPTED_2,
                      _LAST_WRITE],
                     fixture={"pr_number": 9883})
    git = _counting_git(commit_returns=[""])
    llm = _llm(["looked, changed nothing"])
    lines: list[str] = []
    try:
        res = _Res()
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", BEFORE, 9883, "dead" * 10, TREE_1, False,
                             lines.append, res)
        rounds = res.fidelity["rounds"]
        assert [r["outcome"] for r in rounds] == ["no-edits"] * 3
        assert [r["model"] for r in rounds] == ["sonnet", "opus", "opus"]
        assert [r["retries"] for r in rounds] == [1, 1, 1]
        assert gh.fresh_calls == 15
        assert len(gh.writes) == 2
        assert "New bullet." in gh.writes[0]
        assert "Newer bullet." in gh.writes[1]
        for written in gh.writes:
            _assert_restored(written)
        reverted = [ln for ln in lines if REVERTED in ln]
        assert [ln.split(":")[0] for ln in reverted] == ["phase5.5 round 1",
                                                       "phase5.5 round 2"]
    finally:
        _cleanup(9883, "9883-2", "9883-3")


@pytest.mark.parametrize("kind", ["llm-invalid-output", "llm-tooled-cli",
                                  "llm-tooled-empty"])
def test_spent_transient_kind_restores_manual_testing_after_the_round(tmp_path, git_shim,
                                                                      kind):
    gh = _LiveBodyGh(str(tmp_path), [BEFORE, "", CORRUPTED, "", CORRUPTED, _LAST_WRITE],
                     fixture={"pr_number": 9884})
    llm = _llm([{"raise": {"kind": kind, "detail": "flaked"}}])
    lines: list[str] = []
    try:
        _run(tmp_path, gh, _counting_git(), llm, 9884, lines)
        assert gh.fresh_calls == 15
        assert len(gh.writes) == 1
        _assert_restored(gh.writes[0])
        reverted = [i for i, ln in enumerate(lines)
                    if ln.startswith(f"phase5.5 round 1: {REVERTED}")]
        spent = [i for i, ln in enumerate(lines)
                 if ln.startswith(f"phase5.5 round 1 attempt 2: remediation unavailable ({kind})")]
        assert len(reverted) == 1 and len(spent) == 1
        assert reverted[0] > spent[0]
        assert not any(ln.startswith(("phase5.5 round 2", "phase5.5 round 3"))
                       and REVERTED in ln for ln in lines)
    finally:
        _cleanup(9884, "9884-2", "9884-3")


@pytest.mark.parametrize("scenario", ["terminal", "no-edits"])
def test_snapshot_without_manual_testing_skips_restore(tmp_path, git_shim, scenario):
    if scenario == "terminal":
        gh = _LiveBodyGh(str(tmp_path), [NO_SECTION, CORRUPTED], fixture={"pr_number": 9885})
        llm, git, reads = _llm([{"raise": {"kind": "local-gate", "detail": "no"}}]), \
            _counting_git(), 1
    else:
        gh = _LiveBodyGh(str(tmp_path), [NO_SECTION], fixture={"pr_number": 9885})
        llm, git, reads = _llm(["looked, changed nothing"]), \
            _counting_git(commit_returns=[""]), 12
    lines: list[str] = []
    try:
        _run(tmp_path, gh, git, llm, 9885, lines)
        assert gh.fresh_calls == reads
        assert gh.writes == []
        assert not any(REVERTED in ln for ln in lines)
    finally:
        _cleanup(9885, "9885-2", "9885-3")


def test_intact_manual_testing_section_is_not_rewritten(tmp_path, git_shim):
    gh = _LiveBodyGh(str(tmp_path), [BEFORE, INTACT_EDIT], fixture={"pr_number": 9886})
    llm = _llm([{"raise": {"kind": "rebase-conflict", "detail": "no"}}])
    lines: list[str] = []
    try:
        _run(tmp_path, gh, _counting_git(), llm, 9886, lines)
        assert gh.fresh_calls == 3
        assert gh.writes == []
        assert not any(REVERTED in ln for ln in lines)
    finally:
        _cleanup(9886, "9886-2")


@pytest.mark.parametrize("kind,reads", [("local-gate", 3), ("push-failed", 2)])
def test_empty_live_read_skips_restore(tmp_path, git_shim, kind, reads):
    gh = _LiveBodyGh(str(tmp_path), [BEFORE, ""], fixture={"pr_number": 9887})
    llm = _llm([{"raise": {"kind": kind, "detail": "no"}}])
    lines: list[str] = []
    try:
        if kind == "push-failed":
            with pytest.raises(HarnessError):
                _run(tmp_path, gh, _counting_git(), llm, 9887, lines)
        else:
            _run(tmp_path, gh, _counting_git(), llm, 9887, lines)
        assert gh.fresh_calls == reads
        assert gh.writes == []
        assert not any(REVERTED in ln for ln in lines)
    finally:
        _cleanup(9887, "9887-2")


_BITE = {
    "terminal": ([BEFORE, CORRUPTED], "local-gate", None, 3),
    "push-failed": ([BEFORE, CORRUPTED], "push-failed", None, 2),
    "no-edits": ([BEFORE, BEFORE, CORRUPTED], None, [""], 15),
}


@pytest.mark.parametrize("disabled", [False, True])
@pytest.mark.parametrize("scenario", sorted(_BITE))
def test_restore_disabled_leaves_the_body_corrupted(tmp_path, git_shim, monkeypatch,
                                                    scenario, disabled):
    """Disabling runner.restore_manual_testing_section leaves the corrupted body unwritten."""
    script, kind, commits, reads = _BITE[scenario]
    if disabled:
        monkeypatch.setattr(runner, "restore_manual_testing_section",
                            lambda after, before: (after, False))
    gh = _LiveBodyGh(str(tmp_path), script, fixture={"pr_number": 9888})
    remediation = ([{"raise": {"kind": kind, "detail": "no"}}] if kind
                   else ["looked, changed nothing"])
    git = _counting_git(commit_returns=commits) if commits else _counting_git()
    lines: list[str] = []
    try:
        if kind == "push-failed":
            with pytest.raises(HarnessError):
                _run(tmp_path, gh, git, _llm(remediation), 9888, lines)
        else:
            _run(tmp_path, gh, git, _llm(remediation), 9888, lines)
        assert gh.fresh_calls == reads
        if disabled:
            assert gh.writes == []
            assert not any(REVERTED in ln for ln in lines)
        else:
            assert len(gh.writes) >= 1
            _assert_restored(gh.writes[0])
            assert any(REVERTED in ln for ln in lines)
    finally:
        _cleanup(9888, "9888-2", "9888-3")
