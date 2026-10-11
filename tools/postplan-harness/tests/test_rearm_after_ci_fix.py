"""Phase 7 re-arm after the CI fix loop turns a run green.

A run held at Phase 6.5 only on condition (16) (the pre-push meta-checks flag) used to stay
held after the Phase 7 fix loop made CI green, with the flag still on disk. These tests drive
the live-shaped replay path and pin that flow.
"""
from __future__ import annotations

import os
import sys
import types

sys.path.insert(0, os.path.join(os.path.dirname(__file__), ".."))

import runner
from harness.armable import meta_checks_flag_path
from harness.state import TerminalState
from test_runner_replay import (_LiveShapedGit, _actions, _audit, _red_fixture,
                                _run_live_shaped_with)


def _install_stubs(monkeypatch, fx):
    """Make a live-shaped replay able to arm.

    The live-shaped run reads the post-pr meta-checks rc from a subprocess, runs the real
    Phase 5.5 reviewer, and holds on the manual-testing box, so a bare `_red_fixture` can
    never arm. These stubs clear exactly those three, leaving every other condition real.
    The first post-pr call returns fixture `meta_checks_post_rc` (Phase 6.5); a later one
    returns `meta_checks_post_rc_after_fix`, 2 (UNKNOWN) when the key is absent.
    """
    real_run = runner.subprocess.run
    rcs = [int(fx.get("meta_checks_post_rc", 0)),
           int(fx.get("meta_checks_post_rc_after_fix", 2))]
    calls = []

    def fake_run(argv, *a, **k):
        if (isinstance(argv, (list, tuple)) and "run-meta-checks-local" in str(argv[0])
                and "post-pr" in argv):
            rc = rcs[min(len(calls), len(rcs) - 1)]
            calls.append(rc)
            return types.SimpleNamespace(returncode=rc, stdout="", stderr="")
        return real_run(argv, *a, **k)

    def fake_fidelity(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
                      reviewed_tree, live, log, res, **kw):
        res.fidelity = {"verdict_1": "READY", "error_kind": None,
                        "reviewed_tree": reviewed_tree, "verdict_path": "",
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None, "findings_round": 0, "rounds": [],
                        "rounds_completed": 0, "backlog_issue_numbers": [],
                        "diff_id": "", "plan_hash": "", "models": []}
        return "READY", ""

    monkeypatch.setattr(runner.subprocess, "run", fake_run)
    monkeypatch.setattr(runner, "_run_fidelity", fake_fidelity)
    return calls


def _armable_fixture(monkeypatch, tmp_path, **extra):
    monkeypatch.setattr(runner, "META_CHECKS_TMP_DIR", str(tmp_path))
    fx = _red_fixture(meta_checks_post_rc=0, **extra)
    fx["pr_meta"]["body"] = "## Manual Testing\n\nNo manual testing needed\n"
    _install_stubs(monkeypatch, fx)
    return fx


def _held16_fixture(monkeypatch, tmp_path, **extra):
    """A fixture whose Phase 6.5 holds on (16) via the pre-push flag file only."""
    fx = _armable_fixture(monkeypatch, tmp_path, **extra)
    flag = meta_checks_flag_path(fx["slug"], str(tmp_path))
    with open(flag, "w") as fh:
        fh.write("check-docs-since\n")
    return fx, flag


def _merge_count(out):
    return [a["action"] for a in _actions(out)].count("pr_merge_auto")


def _drive(monkeypatch, out, fx, canned_extra=None):
    res, _ = _run_live_shaped_with(monkeypatch, out, git_cls=_LiveShapedGit,
                                   fixture=fx, canned_extra=canned_extra)
    return res


# ---------------------------------------------------------------------------
# Characterization (pre-impl): invariants the re-arm must not disturb
# ---------------------------------------------------------------------------

def test_char_held16_plain_replay_stays_held_flag_kept(monkeypatch, tmp_path):
    fx, flag = _held16_fixture(monkeypatch, tmp_path)
    out = str(tmp_path / "out")
    res = _drive(monkeypatch, out, fx)
    assert res.arm.armed is False
    assert "phase6.5: HELD — (16)" in _audit(out)
    assert _merge_count(out) == 0
    assert os.path.exists(flag)
    assert res.ci_outcome == "failed"


def test_char_held16_fix_loop_still_red_stays_held_flag_kept(monkeypatch, tmp_path):
    fx, flag = _held16_fixture(
        monkeypatch, tmp_path,
        ci_fix_rewatch=[{"exit": 8, "failed": ["Tests and Analysis"]}])
    out = str(tmp_path / "out")
    res = _drive(monkeypatch, out, fx, canned_extra={"ci-fix": ["edits made"]})
    assert res.arm.armed is False
    assert os.path.exists(flag)
    assert _merge_count(out) == 0
    assert "outcome=still-red" in _audit(out)
    assert res.ci_outcome != "green"


def test_char_armed_at_65_fix_green_arms_exactly_once(monkeypatch, tmp_path):
    fx = _armable_fixture(monkeypatch, tmp_path,
                          ci_fix_rewatch=[{"exit": 0, "failed": []}])
    out = str(tmp_path / "out")
    res = _drive(monkeypatch, out, fx, canned_extra={"ci-fix": ["edits made"]})
    assert res.ci_outcome == "green"
    assert _merge_count(out) == 1


def test_char_held16_fix_green_defect_stays_held(monkeypatch, tmp_path):
    # characterization of the defect this plan fixes; Phase 5 inverts these assertions
    fx, flag = _held16_fixture(monkeypatch, tmp_path,
                               ci_fix_rewatch=[{"exit": 0, "failed": []}])
    out = str(tmp_path / "out")
    res = _drive(monkeypatch, out, fx, canned_extra={"ci-fix": ["edits made"]})
    assert res.ci_outcome == "green"
    assert res.arm.armed is False
    assert os.path.exists(flag)
    assert _merge_count(out) == 0
    assert res.terminal == TerminalState.SHIPPED_HELD


# ---------------------------------------------------------------------------
# _post_fix_meta_checks unit tests
# ---------------------------------------------------------------------------

def _post_fix(tmp_path, fixture, *, live=False, pr=7):
    flag = tmp_path / "flag.failed"
    flag.write_text("check-docs-since\n")
    logged = []
    rc = runner._post_fix_meta_checks(None, pr, str(flag), live=live,
                                      fixture=fixture, log=logged.append)
    return rc, str(flag), logged


def test_post_fix_meta_checks_rc0_deletes_flag(tmp_path):
    rc, flag, logged = _post_fix(tmp_path, {"meta_checks_post_rc_after_fix": 0})
    assert rc == 0
    assert not os.path.exists(flag)
    assert any("pre-push flag deleted" in m for m in logged)


def test_post_fix_meta_checks_rc1_keeps_flag(tmp_path):
    rc, flag, logged = _post_fix(tmp_path, {"meta_checks_post_rc_after_fix": 1})
    assert rc == 1
    assert os.path.exists(flag)
    assert any("rc=1 on current head; pre-push flag kept" in m for m in logged)


def test_post_fix_meta_checks_rc2_keeps_flag(tmp_path):
    rc, flag, _ = _post_fix(tmp_path, {"meta_checks_post_rc_after_fix": 2})
    assert rc == 2
    assert os.path.exists(flag)


def test_post_fix_meta_checks_absent_key_is_unknown(tmp_path):
    rc, flag, _ = _post_fix(tmp_path, {})
    assert rc == 2
    assert os.path.exists(flag)


def test_post_fix_meta_checks_live_oserror_is_unknown(tmp_path):
    import unittest.mock as mock
    with mock.patch.object(runner.subprocess, "run", side_effect=OSError):
        rc, flag, _ = _post_fix(tmp_path, {}, live=True, pr=7)
    assert rc == 2
    assert os.path.exists(flag)
