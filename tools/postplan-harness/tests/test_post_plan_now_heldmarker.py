"""Tests for postplan_heldmarker_decline in bin/post-plan-now (held-unfixable marker pre-flight decline)."""
from __future__ import annotations

import os
import re
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import heldmarker as hm
from harness import holdrepeat as hr

HARNESS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = os.path.dirname(os.path.dirname(HARNESS))
PPN = os.path.join(REPO, "bin", "post-plan-now")

SLUG = "hm-test-slug"
MISSING_A = "MISSING: ibl5/tests/Cli/CheckE2eHygieneCliTest.php"
MISSING_B = "MISSING: ibl5/tests/Cli/OtherTest.php"


def _observe(state, reason=MISSING_A, slug=SLUG):
    conds = [{"number": 3, "name": "tests", "blocked": True, "reason": reason}]
    hr.observe(str(state), slug, armed=False, conditions=conds, fingerprint="fp", pr=1, now="t")


def _setup(tmp_path, slug=SLUG):
    plans_dir = tmp_path / "plans"
    plans_dir.mkdir()
    plan = plans_dir / f"{slug}.md"
    plan.write_text("# plan\n")
    state = tmp_path / "state"
    state.mkdir()
    _observe(state, slug=slug)
    assert hm.write(str(state), slug, plan_path=str(plan), now="t0") is not None
    return plans_dir, plan, state


def _decline(plans_dir, state, slug=SLUG, *, harness=HARNESS, force=None, state_env=None,
             state_changed=None, cwd=None):
    env = {**os.environ}
    env.pop("FORCE", None)
    env.pop("STATE_CHANGED", None)
    env["HOLDREPEAT_STATE_DIR"] = str(state) if state_env is None else state_env
    env["PLANS_DIR"] = str(plans_dir)
    prefix = f"FORCE={force}; " if force is not None else ""
    if state_changed is not None:
        prefix += f"STATE_CHANGED={state_changed}; "
    script = (f'. "{PPN}"; {prefix}'
              f'postplan_heldmarker_decline "{harness}" "{slug}" ""; echo "rc=$?"')
    # cwd outside the harness dir: `python3 -m` puts the cwd on sys.path, so a fake-harness
    # case would otherwise import the real package and never fail open.
    return subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env,
                          cwd=str(cwd or plans_dir))


def test_active_marker_declines(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    r = _decline(plans_dir, state)
    assert "rc=0" in r.stdout
    assert "heldmarker: SUPPRESSED" in r.stdout


def test_force_bypasses_marker(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    r = _decline(plans_dir, state, force=1)
    assert "rc=1" in r.stdout
    assert "SUPPRESSED" not in r.stdout


def test_state_changed_bypasses_marker(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    r = _decline(plans_dir, state, state_changed=1)
    assert "rc=1" in r.stdout
    assert "SUPPRESSED" not in r.stdout


def test_state_changed_zero_still_declines_marker(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    r = _decline(plans_dir, state, state_changed=0)
    assert "rc=0" in r.stdout
    assert "heldmarker: SUPPRESSED" in r.stdout


def test_touched_plan_proceeds(tmp_path):
    plans_dir, plan, state = _setup(tmp_path)
    m = plan.stat().st_mtime_ns + 1_000_000_000
    os.utime(plan, ns=(m, m))
    r = _decline(plans_dir, state)
    assert "rc=1" in r.stdout
    assert "SUPPRESSED" not in r.stdout


def test_changed_hold_proceeds(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    _observe(state, reason=MISSING_B)
    r = _decline(plans_dir, state)
    assert "rc=1" in r.stdout
    assert "SUPPRESSED" not in r.stdout


def test_corrupt_marker_proceeds(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    with open(hm.marker_path(str(state), SLUG), "wb") as fh:
        fh.write(b"\x00\xff not json {{{")
    r = _decline(plans_dir, state)
    assert "rc=1" in r.stdout
    assert "SUPPRESSED" not in r.stdout


def test_missing_harness_proceeds(tmp_path):
    plans_dir, _, state = _setup(tmp_path)
    r = _decline(plans_dir, state, harness=str(tmp_path / "no-such-harness"))
    assert r.stdout.strip() == "rc=1"


def test_marker_state_dir_empty_env_uses_harness_default(tmp_path):
    """An empty HOLDREPEAT_STATE_DIR is the same as unset: the function reads <harness>/out/state."""
    assert "${HOLDREPEAT_STATE_DIR:-$harness/out/state}" in open(PPN, encoding="utf-8").read()
    h = tmp_path / "harness-copy"
    shutil.copytree(os.path.join(HARNESS, "harness"), h / "harness",
                    ignore=shutil.ignore_patterns("__pycache__"))
    plans_dir = tmp_path / "plans"
    plans_dir.mkdir()
    plan = plans_dir / f"{SLUG}.md"
    plan.write_text("# plan\n")
    state = h / "out" / "state"
    state.mkdir(parents=True)
    _observe(state)
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t0") is not None
    r = _decline(plans_dir, state, harness=str(h), state_env="", cwd=tmp_path)
    assert "rc=0" in r.stdout
    assert "heldmarker: SUPPRESSED" in r.stdout


def test_marker_call_site_exits_8_before_launch():
    lines = open(PPN, encoding="utf-8").read().splitlines()
    idx = [i for i, ln in enumerate(lines) if ln.strip() == "if postplan_heldmarker_decline \\"]
    assert len(idx) == 1, "expected exactly one call site"
    i = idx[0]
    window = lines[i + 1:i + 9]
    echo_at = next((k for k, ln in enumerate(window) if 'echo "RESULT: post-plan DECLINED' in ln), None)
    assert echo_at is not None, "RESULT: post-plan DECLINED echo must follow the call"
    assert any(re.fullmatch(r"\s*exit 8\s*", ln) for ln in window[echo_at:]), "exit 8 must follow"
    ts = next(k for k, ln in enumerate(lines) if ln.startswith("TS=$(date +%Y%m%d-%H%M%S)"))
    assert i < ts, "marker decline must precede the launch"
