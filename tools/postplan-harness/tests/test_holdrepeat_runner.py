"""Phase 6.5 hold-repeat wiring in runner.py: record, one-shot DM, RESULT note.

The record is advisory. These tests drive runner.run() in replay mode to a HELD
verdict on a structural condition (7, plan_auto_merge_false) and pin that the DM
fires once, that arming is never influenced, and that failures never change the run.
"""
import json
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import heldmarker, holdrepeat
from harness.adapters.llm import FixtureLlm
from harness.state import TerminalState, UsageLedger

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

SLUG = "holdrepeat-synthetic"

CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "commit_subject": "chore: replay commit",
                "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}

HOLD_7_PLAN = "---\nauto_merge: false\n---\n# Synthetic plan\n\nBody with no matrix.\n"
CLEAN_PLAN = "# Synthetic plan\n\nBody with no matrix and no frontmatter.\n"


def _fixture(plan=HOLD_7_PLAN, **over):
    fx = {
        "slug": SLUG,
        "diff": "diff --git a/ibl5/classes/Trade/TradeService.php b/ibl5/classes/Trade/TradeService.php\n+<?php echo 1;\n",
        "pr_number": 4242,
        "pr_meta": {"number": 4242, "title": "fix: synthetic",
                    "body": "## Manual Testing\n\nNo manual testing needed\n\n**Merge danger:** Door: two-way · Blast radius: localized · Why: mechanical: no one-way trigger in the realized diff\n",
                    "headRefOid": "deadbeef"},
        "labels": [],
        "final_state": "OPEN",
        "checks_outcome": {"exit": 0, "failed": []},
        "verify": {"phpunit": "OK (1 test)", "phpstan": "[OK] No errors"},
        "plan_content": plan,
    }
    fx.update(over)
    return fx


@pytest.fixture(autouse=True)
def _const_fingerprint(monkeypatch):
    monkeypatch.setattr(holdrepeat, "fingerprint", lambda *a, **k: "fp-const")


@pytest.fixture
def dm(tmp_path, monkeypatch):
    """Fake DM command: appends argv to a file, exits per HOLDREPEAT_FAKE_RC."""
    log = tmp_path / "dm.log"
    script = tmp_path / "fake-dm"
    script.write_text(f'#!/bin/sh\nprintf \'%s\\n---\\n\' "$*" >> "{log}"\n'
                      'exit "${HOLDREPEAT_FAKE_RC:-0}"\n')
    script.chmod(script.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("HOLDREPEAT_DM_CMD", str(script))
    monkeypatch.setenv("HOLDREPEAT_FAKE_RC", "0")

    def invocations():
        if not log.exists():
            return 0
        return log.read_text().count("---\n")

    return types.SimpleNamespace(log=log, count=invocations)


def _run(tmp_path, n, *, fixture=None, state=True, live=False):
    out = str(tmp_path / f"out{n}")
    llm = FixtureLlm(UsageLedger(), dict(CANNED))
    kw = {"state_dir": str(tmp_path / "state")} if state else {}
    res = runner.run(fixture or _fixture(), out, llm, mode="replay", live=live, **kw)
    rc = runner.exit_code_for(res)
    return res, rc, runner.verdict_line(res, rc), out


def _record_file(tmp_path):
    return tmp_path / "state" / f"{SLUG}.holdrepeat.json"


def test_identical_hold_twice_sends_one_dm_and_result_note(tmp_path, dm):
    res1, _, line1, _ = _run(tmp_path, 1)
    assert res1.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 7 for c in res1.arm.holds)
    assert "hold-repeat=" not in line1
    assert dm.count() == 0

    res2, _, line2, _ = _run(tmp_path, 2)
    assert dm.count() == 1
    sent = dm.log.read_text()
    assert SLUG in sent and "PR #4242" in sent
    assert "(7)" in sent and "bin/post-plan-now --force" in sent
    assert "--quiet" in sent and "--no-fallback" in sent
    assert "hold-repeat=2x" in line2 and "dm=sent" in line2
    assert res2.hold_repeat["action"] == "repeat-dm"


def test_third_identical_hold_sends_no_second_dm(tmp_path, dm):
    _run(tmp_path, 1)
    _run(tmp_path, 2)
    assert dm.count() == 1
    _, _, line3, _ = _run(tmp_path, 3)
    assert dm.count() == 1
    assert "hold-repeat=3x" in line3 and "dm=already-sent" in line3


def test_changed_hold_set_sends_no_dm(tmp_path, dm):
    _run(tmp_path, 1)
    # Second run holds on a different structural condition: 7 clears, 8 (UI review floor) holds.
    fx2 = _fixture(CLEAN_PLAN, diff="diff --git a/ibl5/design/input.css b/ibl5/design/input.css\n+a{}\n", pr_meta={"number": 4242, "title": "feat: synthetic ability",
                                        "body": "## Manual Testing\n\nNo manual testing needed\n",
                                        "headRefOid": "deadbeef"})
    res2, _, line2, _ = _run(tmp_path, 2, fixture=fx2)
    assert res2.terminal == TerminalState.SHIPPED_HELD
    held = {c.number for c in res2.arm.holds}
    assert 7 not in held and held & holdrepeat.STRUCTURAL_CONDITIONS
    assert dm.count() == 0
    assert "hold-repeat=" not in line2
    assert res2.hold_repeat["action"] == "recorded"


def test_armed_run_deletes_record(tmp_path, dm):
    res1, _, _, _ = _run(tmp_path, 1)
    assert res1.terminal == TerminalState.SHIPPED_HELD
    assert _record_file(tmp_path).exists()
    res2, _, line2, _ = _run(tmp_path, 2, fixture=_fixture(CLEAN_PLAN))
    assert res2.terminal == TerminalState.SHIPPED_ARMED
    assert not _record_file(tmp_path).exists()
    assert "hold-repeat=" not in line2
    assert dm.count() == 0


def test_dm_failure_keeps_rc_and_retries_next_time(tmp_path, dm, monkeypatch):
    base, base_rc, _, _ = _run(tmp_path, 0, state=False)   # no-record baseline
    monkeypatch.setenv("HOLDREPEAT_FAKE_RC", "1")
    _run(tmp_path, 1)
    res2, rc2, line2, _ = _run(tmp_path, 2)
    assert dm.count() == 1
    assert rc2 == base_rc and res2.terminal == base.terminal
    rec = json.loads(_record_file(tmp_path).read_text())
    assert rec["dm_sent_key"] is None
    assert "hold-repeat=2x" in line2 and "dm=failed" in line2
    _, _, line3, _ = _run(tmp_path, 3)
    assert dm.count() == 2
    assert "hold-repeat=3x" in line3 and "dm=failed" in line3


def test_arming_decision_unchanged_by_record(tmp_path, dm):
    base, _, _, _ = _run(tmp_path, 0, state=False)
    # Pre-seed a matching record so the next run is a repeat.
    pre = _run(tmp_path, 1)[0]
    assert pre.hold_repeat["action"] == "recorded"
    seeded, _, _, _ = _run(tmp_path, 2)
    assert seeded.hold_repeat["action"] in ("repeat-dm", "repeat-silent")
    assert seeded.arm.armed == base.arm.armed
    assert [c.number for c in seeded.arm.holds] == [c.number for c in base.arm.holds]
    assert seeded.terminal == base.terminal


def test_non_live_without_state_dir_writes_nothing(tmp_path, dm):
    res, _, line, out = _run(tmp_path, 1, state=False)
    res2, _, line2, out2 = _run(tmp_path, 2, state=False)
    assert res.hold_repeat is None and res2.hold_repeat is None
    assert "hold-repeat=" not in line2
    assert dm.count() == 0
    for root in (out, out2, str(tmp_path)):
        for dirpath, _dirs, files in os.walk(root):
            assert not [f for f in files if f.endswith(".holdrepeat.json")], dirpath


def test_dm_cmd_empty_env_uses_discord_dm(monkeypatch):
    monkeypatch.setenv("HOLDREPEAT_DM_CMD", "")
    captured = []

    def fake_run(argv, **kw):
        captured.append((argv, kw))
        return types.SimpleNamespace(returncode=0, stdout="", stderr="")

    monkeypatch.setattr(runner.subprocess, "run", fake_run)
    obs = holdrepeat.Observation("repeat-dm", "7:x", 2, [[7, "plan_auto_merge_false", "held"]])
    assert runner._send_hold_repeat_dm(SLUG, None, obs) is True
    repo_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(
        os.path.abspath(__file__)))))
    argv, kw = captured[0]
    assert argv[0] == os.path.join(repo_root, "bin", "discord-dm")
    assert kw["timeout"] == 60
    assert "PR #none" in argv[-1]


# --- repeat-DM wording: human-only vs mixed reasons ---

def _capture_dm(monkeypatch):
    sent = []
    monkeypatch.setattr(runner, "_dm", lambda msg: sent.append(msg) or True)
    return sent


OLD_TAIL = ("The next re-run with the same plan, diff, and harness will be declined\n"
            "before it spends tokens. Fix the reason, or re-fire with:\n"
            "  bin/post-plan-now --force")


def test_repeat_dm_mixed_reasons_keep_old_wording_byte_for_byte(monkeypatch):
    sent = _capture_dm(monkeypatch)
    obs = holdrepeat.Observation("repeat-dm", "7:x|13:y", 2, [
        [7, "plan_auto_merge_false", "plan declares auto_merge: false"],
        [13, "slug_drift", "plan adopted by slug drift"]])
    assert runner._send_hold_repeat_dm(SLUG, 4242, obs) is True
    assert sent == [
        "post-plan held twice on the same reason: holdrepeat-synthetic PR #4242\n"
        "Repeated hold (run 2): (7) plan declares auto_merge: false; (13) plan adopted by slug drift\n"
        + OLD_TAIL]


def test_repeat_dm_condition_13_alone_keeps_old_wording(monkeypatch):
    sent = _capture_dm(monkeypatch)
    obs = holdrepeat.Observation("repeat-dm", "13:y", 2, [
        [13, "slug_drift", "plan adopted by slug drift"]])
    assert runner._send_hold_repeat_dm(SLUG, 4242, obs) is True
    assert sent == [
        "post-plan held twice on the same reason: holdrepeat-synthetic PR #4242\n"
        "Repeated hold (run 2): (13) plan adopted by slug drift\n" + OLD_TAIL]


def test_repeat_dm_empty_reasons_keep_old_wording(monkeypatch):
    sent = _capture_dm(monkeypatch)
    obs = holdrepeat.Observation("repeat-dm", "", 2, [])
    assert runner._send_hold_repeat_dm(SLUG, None, obs) is True
    assert sent == [
        "post-plan held twice on the same reason: holdrepeat-synthetic PR #none\n"
        "Repeated hold (run 2): \n" + OLD_TAIL]


def _hold_conds(*pairs):
    return [{"number": n, "name": name, "blocked": True, "reason": reason}
            for n, name, reason in pairs]


HOLD_CONDS = {
    "human_only_extra_19": _hold_conds(
        (7, "plan_auto_merge_false", "plan declares auto_merge: false"),
        (19, "other_gate", "some re-runnable gate")),
    "mixed_13": _hold_conds(
        (7, "plan_auto_merge_false", "plan declares auto_merge: false"),
        (13, "slug_drift", "plan adopted by slug drift")),
}


def _repeat_calls(tmp_path, monkeypatch, conds):
    monkeypatch.setattr(holdrepeat, "fingerprint", lambda *a, **k: "fp")
    res = types.SimpleNamespace(plan=None, pr_number=4242)
    decision = types.SimpleNamespace(armed=False, conditions=conds)
    state = str(tmp_path / "state")
    return [runner._record_hold_repeat(res, decision, SLUG, str(tmp_path), state, lambda s: None)
            for _ in range(3)]


@pytest.mark.parametrize("conds", HOLD_CONDS.values(), ids=HOLD_CONDS.keys())
def test_record_hold_repeat_outcome_sequence_unchanged(tmp_path, monkeypatch, conds):
    sent = _capture_dm(monkeypatch)
    out1, out2, out3 = _repeat_calls(tmp_path, monkeypatch, conds)
    assert (out1["action"], out1["repeat_count"]) == ("recorded", 1)
    assert (out2["action"], out2["repeat_count"], out2["dm"]) == ("repeat-dm", 2, "sent")
    assert (out3["action"], out3["repeat_count"], out3["dm"]) == ("repeat-silent", 3, "already-sent")
    assert len(sent) == 1
    assert (tmp_path / "state" / f"{SLUG}.holdrepeat.json").exists()


# --- held-unfixable marker cleanup in _record_hold_repeat ------------------------------------

MISSING_A = "MISSING: ibl5/tests/Cli/CheckE2eHygieneCliTest.php"
MISSING_B = "MISSING: ibl5/tests/Cli/OtherTest.php"


def _seed_marker(tmp_path):
    state = str(tmp_path / "state")
    plan = tmp_path / "plan.md"
    plan.write_text("# plan\n")
    cond = [{"number": 3, "name": "tests", "blocked": True, "reason": MISSING_A}]
    holdrepeat.observe(state, SLUG, armed=False, conditions=cond, fingerprint="fp", pr=1, now="t")
    assert heldmarker.write(state, SLUG, plan_path=str(plan), now="t") is not None
    marker = tmp_path / "state" / f"{SLUG}.held-unfixable.json"
    assert marker.exists()
    return state, plan, marker


def _record_call(state, plan, *, armed, reason):
    res = types.SimpleNamespace(plan=types.SimpleNamespace(found=True, path=str(plan)), pr_number=1)
    decision = types.SimpleNamespace(
        armed=armed,
        conditions=[{"number": 3, "name": "tests", "blocked": True, "reason": reason}])
    return runner._record_hold_repeat(res, decision, SLUG, None, state, lambda m: None)


def test_record_hold_repeat_clears_marker_when_armed(tmp_path, dm):
    state, plan, marker = _seed_marker(tmp_path)
    _record_call(state, plan, armed=True, reason="")
    assert not marker.exists()


def test_record_hold_repeat_clears_marker_on_new_key(tmp_path, dm):
    state, plan, marker = _seed_marker(tmp_path)
    out = _record_call(state, plan, armed=False, reason=MISSING_B)
    assert out["action"] == "recorded"
    assert not marker.exists()


def test_record_hold_repeat_keeps_marker_on_repeat(tmp_path, dm):
    state, plan, marker = _seed_marker(tmp_path)
    out = _record_call(state, plan, armed=False, reason=MISSING_A)
    assert out["action"].startswith("repeat")
    assert marker.exists()
