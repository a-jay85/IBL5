"""Tests for harness/heldmarker.py: write, active-check staleness, fail-open, clear, CLI."""
from __future__ import annotations

import json
import os
import subprocess
import sys

import pytest

HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_ROOT)

from harness import heldmarker as hm
from harness import holdrepeat

SLUG = "demo-slug"
MISSING_A = "MISSING: ibl5/tests/Cli/CheckE2eHygieneCliTest.php"
MISSING_B = "MISSING: ibl5/tests/Cli/OtherTest.php"


def _observe(state_dir, *, reason=MISSING_A, extra=None, armed=False):
    conds = [{"number": 3, "name": "tests", "blocked": True, "reason": reason}]
    conds += extra or []
    return holdrepeat.observe(
        str(state_dir), SLUG, armed=armed, conditions=conds,
        fingerprint="fp", pr=1, now="t",
    )


@pytest.fixture
def env(tmp_path):
    state = tmp_path / "state"
    state.mkdir()
    plan = tmp_path / "plans" / f"{SLUG}.md"
    plan.parent.mkdir()
    plan.write_text("# plan\n")
    _observe(state)
    return state, plan


def _write(state, plan):
    return hm.write(str(state), SLUG, plan_path=str(plan), now="t0")


def _bump_mtime(plan):
    m = plan.stat().st_mtime_ns + 1_000_000_000
    os.utime(plan, ns=(m, m))


def _snapshot(state):
    return {p.name: p.read_bytes() for p in state.iterdir()}


def test_write_then_active_suppresses(env):
    state, plan = env
    assert _write(state, plan) is not None
    ok, reason = hm.active(str(state), SLUG, str(plan))
    assert ok is True
    assert hm.marker_path(str(state), SLUG) in reason


def test_plan_mtime_change_reenables(env):
    state, plan = env
    _write(state, plan)
    _bump_mtime(plan)
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_plan_variant_path_change_reenables(env, tmp_path):
    state, plan = env
    _write(state, plan)
    variant = tmp_path / "plans" / f"{SLUG}-2.md"
    variant.write_text("# plan\n")
    st = plan.stat()
    os.utime(variant, ns=(st.st_atime_ns, st.st_mtime_ns))
    assert hm.active(str(state), SLUG, str(variant)) == (False, "")


def test_record_structural_key_change_reenables(env):
    state, plan = env
    _write(state, plan)
    _observe(state, extra=[{"number": 7, "name": "auto-merge", "blocked": True, "reason": "held"}])
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_record_missing_set_change_reenables(env):
    state, plan = env
    _write(state, plan)
    _observe(state, reason=MISSING_B)
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_record_cleared_reenables(env):
    state, plan = env
    _write(state, plan)
    _observe(state, armed=True)
    assert holdrepeat.load_record(str(state), SLUG) is None
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_corrupt_marker_fails_open(env):
    state, plan = env
    with open(hm.marker_path(str(state), SLUG), "w") as fh:
        fh.write("{not json")
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


@pytest.mark.skipif(os.geteuid() == 0, reason="root ignores file modes")
def test_unreadable_marker_fails_open(env):
    state, plan = env
    _write(state, plan)
    path = hm.marker_path(str(state), SLUG)
    os.chmod(path, 0)
    try:
        assert hm.active(str(state), SLUG, str(plan)) == (False, "")
    finally:
        os.chmod(path, 0o644)


def test_wrong_schema_marker_fails_open(env):
    state, plan = env
    _write(state, plan)
    path = hm.marker_path(str(state), SLUG)
    doc = json.load(open(path))
    doc["schema_version"] = 99
    with open(path, "w") as fh:
        json.dump(doc, fh)
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_write_refuses_without_record(tmp_path):
    state = tmp_path / "state"
    state.mkdir()
    plan = tmp_path / "p.md"
    plan.write_text("x")
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t") is None
    assert not os.path.exists(hm.marker_path(str(state), SLUG))


def test_write_is_new_only_on_change(env):
    state, plan = env
    assert _write(state, plan)[1] is True
    assert _write(state, plan)[1] is False
    _bump_mtime(plan)
    assert _write(state, plan)[1] is True


def test_active_is_read_only(env):
    state, plan = env
    _write(state, plan)
    before = _snapshot(state)
    assert hm.active(str(state), SLUG, str(plan))[0] is True
    _bump_mtime(plan)
    assert hm.active(str(state), SLUG, str(plan))[0] is False
    assert _snapshot(state) == before


def test_clear_missing_file_is_noop(tmp_path):
    hm.clear(str(tmp_path), SLUG)


def _cli(state, plans, *args):
    env_vars = dict(os.environ, PYTHONPATH=HARNESS_ROOT, PLANS_DIR=str(plans))
    return subprocess.run(
        [sys.executable, "-m", "harness.heldmarker", "check", *args],
        capture_output=True, text=True, env=env_vars, cwd=HARNESS_ROOT,
    )


def test_cli_check_exit_10_when_active(env):
    state, plan = env
    _write(state, plan)
    r = _cli(state, plan.parent, "--state-dir", str(state), "--slug", SLUG)
    assert r.returncode == 10
    assert r.stdout.startswith("heldmarker: SUPPRESSED")


def test_cli_check_exit_0_when_absent(env):
    state, plan = env
    r = _cli(state, plan.parent, "--state-dir", str(state), "--slug", SLUG)
    assert r.returncode == 0
    assert r.stdout == ""


def test_cli_rejects_unknown_flag(env):
    state, plan = env
    r = _cli(state, plan.parent, "--state-dir", str(state), "--slug", SLUG, "--bogus")
    assert r.returncode == 2
    assert "--bogus" in r.stderr


def test_cli_requires_state_dir(env):
    state, plan = env
    r = _cli(state, plan.parent, "--slug", SLUG)
    assert r.returncode == 2
