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
from harness import ciwatch, heldmarker, holdrepeat
from harness.adapters.llm import FixtureLlm
from harness.armable import meta_checks_flag_path
from harness.state import UsageLedger
from test_runner_replay import (CANNED, _LiveShapedGit, _actions, _audit, _red_fixture,
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


# ---------------------------------------------------------------------------
# Re-arm scenarios
# ---------------------------------------------------------------------------

_TREE = "a" * 40
_GREEN = [{"exit": 0, "failed": []}]


def _run_held(monkeypatch, out, fixture, canned_extra=None, **run_kw):
    """`_drive` plus the state_dir seam, so hold-repeat records can be asserted."""
    from harness.adapters.probe import FixtureProbe
    monkeypatch.setattr(runner, "ReplayGit", _LiveShapedGit)
    monkeypatch.setattr(ciwatch, "start_background_watch", lambda *a, **k: None)
    canned = dict(CANNED)
    canned.update(canned_extra or {"ci-fix": ["edits made"]})
    llm = FixtureLlm(UsageLedger(), canned)
    return runner.run(fixture, out, llm, mode="replay", headless=True, live=True,
                      probe=FixtureProbe(fixture), **run_kw)


def _scenario(monkeypatch, tmp_path, *, same_tree=True, rewatch=None, **extra):
    extra.setdefault("ci_fix_rewatch", rewatch if rewatch is not None else _GREEN)
    if same_tree:
        extra.setdefault("head_trees", [_TREE])
    fx, flag = _held16_fixture(monkeypatch, tmp_path, **extra)
    out = str(tmp_path / "out")
    state_dir = str(tmp_path / "state")
    res = _run_held(monkeypatch, out, fx, state_dir=state_dir)
    return res, out, fx, flag, state_dir


def _hold_repeat_lines(out):
    return [l for l in _audit(out).splitlines() if "phase6.5 hold-repeat:" in l]


def test_rearm_held16_fix_green_rc0_arms_and_deletes_flag(monkeypatch, tmp_path):
    res, out, fx, flag, state_dir = _scenario(
        monkeypatch, tmp_path, meta_checks_post_rc_after_fix=0)
    audit = _audit(out)
    assert res.arm.armed is True
    assert res.rearm["outcome"] == "armed"
    assert not os.path.exists(flag)
    acts = [a["action"] for a in _actions(out)]
    assert acts.count("pr_merge_auto") == 1
    last_sticky = max(i for i, a in enumerate(acts) if a == "pr_sticky_verdict")
    assert last_sticky < acts.index("pr_merge_auto")
    sticky_bodies = [a["body"] for a in _actions(out) if a["action"] == "pr_sticky_verdict"]
    assert "arming re-evaluated" in sticky_bodies[-1]
    assert "phase6.5: HELD — (16)" in audit
    assert "phase7 re-arm: ARMED" in audit
    assert res.terminal.name == "SHIPPED_ARMED"
    assert res.hold_repeat["action"] == "cleared"
    assert holdrepeat.load_record(state_dir, fx["slug"]) is None
    assert not os.path.exists(heldmarker.marker_path(state_dir, fx["slug"]))


def test_rearm_held16_post_fix_rc1_stays_held_flag_kept(monkeypatch, tmp_path):
    res, out, fx, flag, _ = _scenario(monkeypatch, tmp_path, meta_checks_post_rc_after_fix=1)
    assert res.arm.armed is False
    assert res.rearm == {"outcome": "held", "post_fix_meta_rc": 1, "holds": [16]}
    assert os.path.exists(flag)
    assert _merge_count(out) == 0
    assert "phase7 re-arm: HELD — (16)" in _audit(out)
    assert len(_hold_repeat_lines(out)) == 1


def test_rearm_held16_cleared_but_plan_auto_merge_false_holds_on_7(monkeypatch, tmp_path):
    from test_runner_replay import _INLINE_FIXTURE
    plan = "---\nauto_merge: false\n---\n" + _INLINE_FIXTURE["plan_content"]
    res, out, fx, flag, state_dir = _scenario(
        monkeypatch, tmp_path, meta_checks_post_rc_after_fix=0, plan_content=plan)
    audit = _audit(out)
    assert res.arm.armed is False
    assert res.rearm["holds"] == [7]
    assert not os.path.exists(flag)
    assert _merge_count(out) == 0
    assert "phase7 re-arm: HELD — (7)" in audit
    assert "re-arm: HELD — (16)" not in audit
    # (7) is the only structural hold on both sides, so Phase 6.5's record stands
    # and the run never records twice.
    assert len(_hold_repeat_lines(out)) == 1
    rec = holdrepeat.load_record(state_dir, fx["slug"])
    assert rec["structural_key"] == holdrepeat.structural_key(res.arm.conditions)


def test_rearm_held16_post_fix_rc2_unknown_stays_held(monkeypatch, tmp_path):
    res, out, fx, flag, _ = _scenario(monkeypatch, tmp_path, meta_checks_post_rc_after_fix=2)
    assert res.arm.armed is False
    assert res.rearm["holds"] == [16]
    assert os.path.exists(flag)
    assert _merge_count(out) == 0
    assert "rc=2 on current head; pre-push flag kept" in _audit(out)


def test_rearm_absent_after_fix_key_defaults_unknown(monkeypatch, tmp_path):
    res, out, fx, flag, _ = _scenario(monkeypatch, tmp_path)
    assert res.arm.armed is False
    assert res.rearm["holds"] == [16]
    assert res.rearm["post_fix_meta_rc"] == 2
    assert os.path.exists(flag)
    assert _merge_count(out) == 0


def test_rearm_refused_when_fix_loop_moved_tree(monkeypatch, tmp_path):
    res, out, fx, flag, _ = _scenario(
        monkeypatch, tmp_path, same_tree=False, meta_checks_post_rc_after_fix=0)
    acts = [a["action"] for a in _actions(out)]
    assert res.rearm["outcome"] == "refused-tree-moved"
    assert res.rearm["tree_65"] != res.rearm["tree_now"]
    assert res.arm.armed is False
    assert [c.number for c in res.arm.holds] == [16]
    assert not os.path.exists(flag)
    assert acts.count("pr_merge_auto") == 0
    assert acts.count("pr_sticky_verdict") == 1
    assert len(_hold_repeat_lines(out)) == 1
    assert "phase7 re-arm: REFUSED" in _audit(out)


def test_rearm_failed_check_in_green_rewatch_holds_on_15(monkeypatch, tmp_path):
    res, out, fx, flag, _ = _scenario(
        monkeypatch, tmp_path, meta_checks_post_rc_after_fix=0,
        rewatch=[{"exit": 0, "failed": ["Static guards"]}])
    assert res.arm.armed is False
    assert 15 in res.rearm["holds"]
    assert _merge_count(out) == 0


def test_rearm_call_site_is_load_bearing(monkeypatch, tmp_path):
    monkeypatch.setattr(runner, "_rearm_after_ci_fix", lambda *a, **k: a[7])
    res, out, fx, flag, _ = _scenario(monkeypatch, tmp_path, meta_checks_post_rc_after_fix=0)
    assert res.arm.armed is False
    assert os.path.exists(flag)
    assert _merge_count(out) == 0
    assert res.rearm is None


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
