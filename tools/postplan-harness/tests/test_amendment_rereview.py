"""Appending an owner amendment rewrites the plan, which releases the held-unfixable marker
and changes the hold-repeat fingerprint. An append that adds nothing writes nothing, so a
declined run stays declined."""
from __future__ import annotations

import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import amendments, heldmarker as hm, holdrepeat

from tests.test_heldmarker import SLUG, _observe
from tests.test_owner_amendments import PLAN, comment


def _setup(tmp_path):
    state = tmp_path / "state"
    state.mkdir()
    plan = tmp_path / "plans" / f"{SLUG}.md"
    plan.parent.mkdir()
    plan.write_text(PLAN, encoding="utf-8")
    old = plan.stat().st_mtime_ns - 5_000_000_000
    os.utime(plan, ns=(old, old))
    _observe(state)
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t0") is not None
    assert hm.active(str(state), SLUG, str(plan))[0] is True
    cfile = tmp_path / "comments.json"
    cfile.write_text(json.dumps([comment()]), encoding="utf-8")
    return state, plan, str(cfile)


def _append(plan, cfile):
    return amendments.main(["append", "--plan", str(plan), "--comments", cfile])


def test_amendment_append_clears_held_marker(tmp_path):
    state, plan, cfile = _setup(tmp_path)
    assert _append(plan, cfile) == 0
    assert hm.active(str(state), SLUG, str(plan)) == (False, "")


def test_amendment_append_changes_hold_fingerprint(tmp_path):
    _, plan, cfile = _setup(tmp_path)
    wt = tmp_path / "wt"
    wt.mkdir()
    for args in (["init", "-q"], ["commit", "-q", "--allow-empty", "-m", "base"]):
        subprocess.run(["git", "-c", "user.name=t", "-c", "user.email=t@x.invalid",
                        "-c", "commit.gpgsign=false", *args], cwd=wt, check=True)
    subprocess.run(["git", "update-ref", "refs/remotes/origin/master", "HEAD"], cwd=wt, check=True)
    before = holdrepeat.fingerprint(str(plan), str(wt))
    assert before
    assert _append(plan, cfile) == 0
    after = holdrepeat.fingerprint(str(plan), str(wt))
    assert after and after != before


def test_idempotent_rerun_keeps_held_marker(tmp_path):
    state, plan, cfile = _setup(tmp_path)
    assert _append(plan, cfile) == 0
    old = plan.stat().st_mtime_ns - 5_000_000_000
    os.utime(plan, ns=(old, old))
    _observe(state)
    assert hm.write(str(state), SLUG, plan_path=str(plan), now="t1") is not None
    mtime = plan.stat().st_mtime_ns
    assert _append(plan, cfile) == 0
    assert plan.stat().st_mtime_ns == mtime
    assert hm.active(str(state), SLUG, str(plan))[0] is True
