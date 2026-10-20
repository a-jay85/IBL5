"""Drift guard for the frozen sticky-verdict layout fixtures.

`bin/fixtures/review-shared/post-plan-sticky-pr3267-v1.json` is the old layout and is
never regenerated. `-v2.json` is the new layout and must equal what the renderer emits,
because the shell consumers read it in place of a live PR.
"""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import sticky_shapes
from harness import fidelity

FIXTURE_DIR = os.path.join(
    os.path.dirname(os.path.abspath(__file__)), "..", "..", "..", "bin", "fixtures",
    "review-shared")
PR2892 = "post-plan-sticky-pr2892.json"
V1 = "post-plan-sticky-pr3267-v1.json"
V2 = "post-plan-sticky-pr3267-v2.json"


def _sticky_body(name: str) -> str:
    with open(os.path.join(FIXTURE_DIR, name)) as fh:
        return json.load(fh)["comments"][-1]["body"]


def test_pr3267_v2_fixture_matches_renderer(tmp_path):
    rendered = sticky_shapes.render_sticky(sticky_shapes.pr3267_inputs(tmp_path))
    assert rendered == _sticky_body(V2)


def test_pr3267_v1_fixture_is_frozen_old_layout():
    body = _sticky_body(V1)
    terminal = body.splitlines()[-2]
    assert terminal.startswith("READY (re-review) — ")
    assert "re-reviewed clean" in terminal
    assert "### Fixed in this PR" not in body


@pytest.mark.parametrize("name,expected", [(PR2892, "READY"), (V1, None), (V2, None)])
def test_python_readers_agree_on_both_layouts(name, expected):
    body = _sticky_body(name)
    assert fidelity._sticky_prior_verdict(body) == expected
    diff = fidelity.STICKY_REVIEWED_DIFF_RE.search(body)
    plan = fidelity.STICKY_PLAN_HASH_RE.search(body)
    verdict, _reason = fidelity.carry_forward_predicate(
        body, diff.group(1) if diff else "", plan.group(1) if plan else "")
    if name != PR2892:
        assert verdict is None


@pytest.mark.parametrize("name", [V1, V2])
def test_layout_fixtures_hold_no_local_paths(name):
    with open(os.path.join(FIXTURE_DIR, name)) as fh:
        text = fh.read()
    for needle in ("/tmp", "/var/folders", "/Users/"):
        assert needle not in text, f"{name} holds a local path: {needle}"


REPO_ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "..", "..")


def _read_repo(*parts: str) -> str:
    with open(os.path.join(REPO_ROOT, *parts)) as fh:
        return fh.read()


def test_skill_writers_match_harness_layout():
    skill = _read_repo(".claude", "skills", "post-plan", "_phase-5.5-fidelity.md")
    rwn = fidelity.terminal_line("NOT READY", "", "<sha>", "READY WITH NOTES", "<tree>", 1)
    assert f"| `{rwn}` |" in skill
    assert "re-review returned `READY` or `READY WITH NOTES`" not in skill

    pr_ready = _read_repo(".claude", "review-shared", "_phase7-verdict.md")
    for needle in ("### Fixed in this PR", "### Still open (non-blocking)", "**Code review:**"):
        assert needle in pr_ready

    review = _read_repo(".claude", "review-shared", "_plan-fidelity-review.md")
    assert "Phase 4B did not run in this run" in review
    for word in ("NONE", "STALE", "CURRENT", "UNKNOWN"):
        assert f"REVIEW-COVERAGE: {word}" in review
