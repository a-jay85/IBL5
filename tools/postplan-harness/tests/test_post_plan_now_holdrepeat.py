"""Tests for postplan_holdrepeat_decline in bin/post-plan-now (repeat-hold pre-flight decline)."""
from __future__ import annotations

import os
import subprocess
import sys
from types import SimpleNamespace

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat as hr

HARNESS = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = os.path.dirname(os.path.dirname(HARNESS))
PPN = os.path.join(REPO, "bin", "post-plan-now")

_COND7 = [SimpleNamespace(number=7, name="plan_auto_merge_false", blocked=True,
                          reason="plan sets auto_merge: false")]


def _git(cwd, *args):
    subprocess.run(["git", "-C", str(cwd), *args], check=True, capture_output=True,
                   env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})


def _setup(tmp_path, *, dm_sent=True, slug="hr-test-slug"):
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q", "-b", "master")
    (root / "a.txt").write_text("a\n")
    _git(root, "add", "-A")
    _git(root, "commit", "-q", "-m", "init")
    _git(root, "update-ref", "refs/remotes/origin/master", "HEAD")
    plans_dir = tmp_path / "plans"
    plans_dir.mkdir()
    plan = plans_dir / f"{slug}.md"
    plan.write_text("# plan\n")
    state = tmp_path / "state"
    fp = hr.fingerprint(str(plan), str(root))
    assert fp
    for n in (1, 2):
        hr.observe(str(state), slug, armed=False, conditions=_COND7, fingerprint=fp,
                   pr=1, now=f"2026-10-02T00:00:0{n}Z")
    if dm_sent:
        hr.mark_dm_sent(str(state), slug, hr.structural_key(_COND7))
    return root, plans_dir, state, slug


def _decline(root, plans_dir, state, slug, *, harness=HARNESS, force=None, state_env=None,
             plan=""):
    env = {**os.environ}
    env.pop("FORCE", None)
    env["HOLDREPEAT_STATE_DIR"] = str(state) if state_env is None else state_env
    env["PLANS_DIR"] = str(plans_dir)
    prefix = f"FORCE={force}; " if force is not None else ""
    script = (f'. "{PPN}"; {prefix}'
              f'postplan_holdrepeat_decline "{harness}" "{slug}" "{plan}" "{root}"; echo "rc=$?"')
    return subprocess.run(["bash", "-c", script], capture_output=True, text=True, env=env)


def test_declines_when_dm_sent_and_inputs_unchanged(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path)
    r = _decline(root, plans_dir, state, slug)
    assert "rc=0" in r.stdout
    assert "holdrepeat: DECLINE" in r.stdout


def test_proceeds_when_plan_changed(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path)
    with open(plans_dir / f"{slug}.md", "a") as fh:
        fh.write("edited\n")
    assert "rc=1" in _decline(root, plans_dir, state, slug).stdout


def test_proceeds_when_worktree_diff_changed(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path)
    (root / "new.txt").write_text("untracked\n")
    assert "rc=1" in _decline(root, plans_dir, state, slug).stdout


def test_proceeds_when_dm_not_yet_sent(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path, dm_sent=False)
    assert "rc=1" in _decline(root, plans_dir, state, slug).stdout


def test_force_overrides_decline(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path)
    r = _decline(root, plans_dir, state, slug, force=1)
    assert "rc=1" in r.stdout
    assert "DECLINE" not in r.stdout


def test_fails_open_when_check_errors(tmp_path):
    root, plans_dir, state, slug = _setup(tmp_path)
    empty = tmp_path / "empty-harness"
    empty.mkdir()
    r = _decline(root, plans_dir, state, slug, harness=str(empty))
    assert r.stdout.strip() == "rc=1"


def test_state_dir_empty_env_uses_harness_default(tmp_path):
    """An empty HOLDREPEAT_STATE_DIR is the same as unset: the function reads $H/out/state."""
    assert "${HOLDREPEAT_STATE_DIR:-$harness/out/state}" in open(PPN, encoding="utf-8").read()
    root, plans_dir, state, _ = _setup(tmp_path, slug="hr-unique-empty-env-slug-7f3a")
    r = _decline(root, plans_dir, state, "hr-unique-empty-env-slug-7f3a", state_env="")
    assert "rc=1" in r.stdout
    assert "DECLINE" not in r.stdout


def test_variant_plan_slug_still_declines(tmp_path):
    """locate_plan picks the highest variant (<slug>-2.md); the check must fingerprint it, not the bare slug."""
    slug = "hr-variant-slug"
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q", "-b", "master")
    (root / "a.txt").write_text("a\n")
    _git(root, "add", "-A")
    _git(root, "commit", "-q", "-m", "init")
    _git(root, "update-ref", "refs/remotes/origin/master", "HEAD")
    plans_dir = tmp_path / "plans"
    plans_dir.mkdir()
    (plans_dir / f"{slug}.md").write_text("# old plan\n")
    variant = plans_dir / f"{slug}-2.md"
    variant.write_text("# variant plan\n")
    state = tmp_path / "state"
    fp = hr.fingerprint(str(variant), str(root))
    assert fp
    for n in (1, 2):
        hr.observe(str(state), slug, armed=False, conditions=_COND7, fingerprint=fp,
                   pr=1, now=f"2026-10-02T00:00:0{n}Z")
    hr.mark_dm_sent(str(state), slug, hr.structural_key(_COND7))
    r = _decline(root, plans_dir, state, slug)
    assert "holdrepeat: DECLINE" in r.stdout, f"Expected decline; got: {r.stdout!r}"
    assert "rc=0" in r.stdout


def test_explicit_plan_path_is_the_one_fingerprinted(tmp_path):
    """--plan reaches the check: state is seeded from an explicit plan outside PLANS_DIR, while a
    decoy with different bytes sits at the PLANS_DIR/<slug>.md fallback. A DECLINE is only possible
    if the explicit path was read; ignoring it would fingerprint the decoy and proceed (rc=1)."""
    slug = "hr-explicit-plan-slug"
    root, plans_dir, _, _ = _setup(tmp_path, slug=slug)
    (plans_dir / f"{slug}.md").write_text("# decoy plan found by the PLANS_DIR fallback\n")
    explicit_dir = tmp_path / "elsewhere"
    explicit_dir.mkdir()
    explicit = explicit_dir / "custom-plan-name.md"
    explicit.write_text("# explicit plan outside PLANS_DIR\n")
    state = tmp_path / "explicit-state"
    fp = hr.fingerprint(str(explicit), str(root))
    assert fp
    for n in (1, 2):
        hr.observe(str(state), slug, armed=False, conditions=_COND7, fingerprint=fp,
                   pr=1, now=f"2026-10-02T00:00:0{n}Z")
    hr.mark_dm_sent(str(state), slug, hr.structural_key(_COND7))
    r = _decline(root, plans_dir, state, slug, plan=str(explicit))
    assert "holdrepeat: DECLINE" in r.stdout, f"Expected decline; got: {r.stdout!r}"
    assert "rc=0" in r.stdout
    # Control: without the explicit path the fallback reads the decoy, so the check proceeds.
    assert "rc=1" in _decline(root, plans_dir, state, slug).stdout
