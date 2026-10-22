"""Tests for harness/heldmarker.py: write, active-check staleness, fail-open, clear, CLI."""
from __future__ import annotations

import json
import os
import subprocess
import sys
from types import SimpleNamespace

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


# --- UNREALISED-assertion markers: keyed on head SHA and origin/master tokens -------------

ROW_V2 = "UNREALISED-ASSERTION: row V2 -- tests/test_x.py::test_y"
ROW_V3 = "UNREALISED-ASSERTION: row V3 -- tests/test_x.py::test_y"
TOKENS = ["token_zzz_absent"]


def _g(cwd, *args):
    return subprocess.run(["git", "-C", str(cwd), *args], check=True,
                          capture_output=True, text=True).stdout.strip()


def _commit(repo, name, text):
    (repo / name).write_text(text)
    _g(repo, "add", "-A")
    _g(repo, "commit", "-qm", f"add {name}")
    return _g(repo, "rev-parse", "HEAD")


def _clone(tmp_path, bare, name):
    wt = tmp_path / name
    _g(tmp_path, "clone", "-q", str(bare), str(wt))
    _g(wt, "config", "user.email", "t@t")
    _g(wt, "config", "user.name", "t")
    return wt


@pytest.fixture
def unr(tmp_path):
    """UNREALISED-only hold, a bare origin with master, a clone, and the written marker."""
    state = tmp_path / "state"
    state.mkdir()
    plan = tmp_path / "plans" / f"{SLUG}.md"
    plan.parent.mkdir()
    plan.write_text("# plan\n")
    _observe(state, reason=ROW_V2)
    bare = tmp_path / "origin.git"
    seed = tmp_path / "seed"
    _g(tmp_path, "init", "-q", "--bare", "-b", "master", str(bare))
    _g(tmp_path, "init", "-q", "-b", "master", str(seed))
    _g(seed, "config", "user.email", "t@t")
    _g(seed, "config", "user.name", "t")
    _commit(seed, "a.txt", "base\n")
    _g(seed, "remote", "add", "origin", str(bare))
    _g(seed, "push", "-q", "origin", "master")
    wt = _clone(tmp_path, bare, "wt")
    head = _g(wt, "rev-parse", "HEAD")
    res = hm.write(str(state), SLUG, plan_path=str(plan), now="t0",
                   head_sha=head, missing_tokens=TOKENS)
    assert res is not None
    return SimpleNamespace(state=state, plan=plan, bare=bare, wt=wt, head=head, seed=seed,
                           tmp=tmp_path)


def _active(u, **kw):
    kw.setdefault("head_sha", u.head)
    kw.setdefault("repo", str(u.wt))
    kw.setdefault("worktree", str(u.wt))
    return hm.active(str(u.state), SLUG, str(u.plan), **kw)


def test_unrealised_marker_written_with_head_sha_and_tokens(unr):
    doc = json.load(open(hm.marker_path(str(unr.state), SLUG)))
    assert doc["schema_version"] == 2
    assert doc["head_sha"] == unr.head
    assert doc["missing_tokens"] == sorted(TOKENS)


def test_unrealised_marker_refused_without_head_sha(tmp_path):
    state = tmp_path / "state"
    state.mkdir()
    plan = tmp_path / "p.md"
    plan.write_text("x")
    _observe(state, reason=ROW_V2)
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t",
                    head_sha="", missing_tokens=TOKENS) is None
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t",
                    head_sha="abc", missing_tokens=()) is None
    assert not os.path.exists(hm.marker_path(str(state), SLUG))


def test_unrealised_marker_active_at_same_head(unr):
    ok, reason = _active(unr)
    assert ok is True
    assert unr.head[:12] in reason


def test_unrealised_marker_inactive_on_new_head_sha(unr):
    new_head = _commit(unr.wt, "b.txt", "more\n")
    assert _active(unr, head_sha=new_head) == (False, "")


def test_unrealised_marker_inactive_on_changed_row_set(unr):
    _observe(unr.state, reason=ROW_V3)
    assert _active(unr) == (False, "")
    assert hm.write(str(unr.state), SLUG, plan_path=str(unr.plan), now="t1",
                    head_sha=unr.head, missing_tokens=TOKENS)[1] is True


def test_unrealised_marker_inactive_without_head_sha(unr):
    assert _active(unr, head_sha="", worktree="") == (False, "")


def test_unrealised_marker_inactive_when_master_has_token(unr):
    _commit(unr.seed, "c.txt", "now has token_zzz_absent\n")
    _g(unr.seed, "push", "-q", "origin", "master")
    assert _active(unr, fetch=True) == (False, "")


def test_unrealised_marker_inactive_on_dirty_worktree(unr):
    (unr.wt / "a.txt").write_text("edited\n")
    assert _active(unr) == (False, "")


def test_unrealised_marker_inactive_when_fetch_fails(unr):
    _g(unr.wt, "remote", "set-url", "origin", str(unr.tmp / "missing.git"))
    assert _active(unr, fetch=False)[0] is True
    assert _active(unr, fetch=True) == (False, "")


def test_rewrite_at_new_head_is_not_new(unr):
    res = hm.write(str(unr.state), SLUG, plan_path=str(unr.plan), now="t1",
                   head_sha="f" * 40, missing_tokens=TOKENS)
    assert res is not None and res[1] is False


def test_v1_marker_reads_inactive(env):
    state, plan = env
    _write(state, plan)
    path = hm.marker_path(str(state), SLUG)
    doc = json.load(open(path))
    doc["schema_version"] = 1
    with open(path, "w") as fh:
        json.dump(doc, fh)
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_missing_only_marker_ignores_head_sha(env):
    state, plan = env
    _write(state, plan)
    assert hm.active(str(state), SLUG, str(plan), head_sha="")[0] is True
    assert hm.active(str(state), SLUG, str(plan), head_sha="deadbeef")[0] is True


def test_cli_check_suppresses_unrealised_marker_at_same_head(unr):
    r = _cli(unr.state, unr.plan.parent, "--state-dir", str(unr.state), "--slug", SLUG,
             "--head-sha", unr.head, "--repo", str(unr.wt))
    assert r.returncode == 10
    assert r.stdout.startswith("heldmarker: SUPPRESSED")
    r = _cli(unr.state, unr.plan.parent, "--state-dir", str(unr.state), "--slug", SLUG,
             "--head-sha", "0" * 40, "--repo", str(unr.wt))
    assert r.returncode == 0
