"""Pin bin/lib/hold-trigger-tokens.txt to armable.py and to bin/check-plan gate [H].

The token file is the list bash reads; armable.py keeps its frozensets. These tests
turn drift between the two (or the plan-skill paragraph) into a red test.
"""
import subprocess
from pathlib import Path

import pytest

from harness.armable import CLEARABLE_PLAN_TRIGGERS, OWNER_HELD_PLAN_TRIGGERS

REPO_ROOT = Path(__file__).resolve().parents[3]
TOKEN_FILE = REPO_ROOT / "bin" / "lib" / "hold-trigger-tokens.txt"
VALIDATE_AND_WRITE = REPO_ROOT / ".claude" / "skills" / "plan" / "_validate-and-write.md"
CHECK_PLAN = REPO_ROOT / "bin" / "check-plan"

MISSING_MSG = "no non-empty hold_trigger:"
UNKNOWN_MSG = "hold_trigger token '"

# The real events-review-likely-bot-split.md frontmatter: a hold with no token.
EVENTS_REVIEW_FRONTMATTER = ["impl_model: sonnet", "auto_merge: false"]


def _token_file_sets():
    sets = {"clearable": set(), "owner-held": set()}
    for raw in TOKEN_FILE.read_text().splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        set_name, token = line.split()
        sets[set_name].add(token)
    return sets


def _all_tokens():
    return sorted(CLEARABLE_PLAN_TRIGGERS | OWNER_HELD_PLAN_TRIGGERS)


def test_token_file_matches_armable_sets():
    sets = _token_file_sets()
    assert sets["clearable"] == set(CLEARABLE_PLAN_TRIGGERS)
    assert sets["owner-held"] == set(OWNER_HELD_PLAN_TRIGGERS)


def test_token_file_sets_are_disjoint():
    sets = _token_file_sets()
    assert not (sets["clearable"] & sets["owner-held"])


def test_validate_and_write_lists_every_token():
    text = VALIDATE_AND_WRITE.read_text()
    paragraphs = [p for p in text.split("\n\n")
                  if p.lstrip().startswith("When `auto_merge: false` is written")]
    assert len(paragraphs) == 1, "hold_trigger paragraph not found exactly once"
    para = paragraphs[0]
    sets = _token_file_sets()
    for token in sorted(sets["clearable"] | sets["owner-held"]):
        assert f"`{token}`" in para, f"{token} missing from the hold_trigger paragraph"


def _run_check_plan(tmp_path, frontmatter_lines, body=""):
    plan = tmp_path / "plan.md"
    text = "---\n" + "\n".join(frontmatter_lines) + "\n---\n"
    text += "# Plan: toy hold-trigger fixture\n\n"
    text += body
    text += ("\n## Automouse Hold Justification\n\n"
             "**Decision:** the owner picks whether this toy plan ships.\n")
    plan.write_text(text)
    proc = subprocess.run([str(CHECK_PLAN), str(plan)], cwd=REPO_ROOT,
                          capture_output=True, text=True, timeout=120)
    return proc.stdout + proc.stderr


@pytest.mark.parametrize("token", _all_tokens())
def test_check_plan_accepts_every_known_token(tmp_path, token):
    out = _run_check_plan(tmp_path, EVENTS_REVIEW_FRONTMATTER + [f"hold_trigger: {token}"])
    assert UNKNOWN_MSG not in out
    assert MISSING_MSG not in out


def test_check_plan_flags_held_plan_without_hold_trigger(tmp_path):
    out = _run_check_plan(tmp_path, EVENTS_REVIEW_FRONTMATTER)
    assert MISSING_MSG in out


def test_check_plan_rejects_unknown_token(tmp_path):
    out = _run_check_plan(tmp_path, EVENTS_REVIEW_FRONTMATTER + ["hold_trigger: golden-snap"])
    assert "hold_trigger token 'golden-snap'" in out


@pytest.mark.parametrize("frontmatter_extra,body,expected", [
    pytest.param([], "## Context\n\nhold_trigger: gate-weakening\n", MISSING_MSG, id="body_only"),
    pytest.param(["hold_triggers: gate-weakening"], "", MISSING_MSG, id="plural_key"),
    pytest.param(["hold_trigger:"], "", MISSING_MSG, id="empty_value"),
    pytest.param(["hold_trigger: gate weakening"], "", "hold_trigger token 'gate weakening'",
                 id="space_in_token"),
])
def test_check_plan_rejects_near_miss_hold_trigger_forms(tmp_path, frontmatter_extra, body, expected):
    out = _run_check_plan(tmp_path, EVENTS_REVIEW_FRONTMATTER + frontmatter_extra, body)
    assert expected in out


@pytest.mark.parametrize("line", [
    pytest.param("hold_trigger: Gate-Weakening", id="upper"),
    pytest.param("hold_trigger: gate-weakening,", id="trailing_comma"),
])
def test_check_plan_accepts_case_and_trailing_comma_variants(tmp_path, line):
    out = _run_check_plan(tmp_path, EVENTS_REVIEW_FRONTMATTER + [line])
    assert UNKNOWN_MSG not in out
    assert MISSING_MSG not in out


def test_check_plan_ignores_unheld_plan_without_hold_trigger(tmp_path):
    out = _run_check_plan(tmp_path, ["impl_model: sonnet"])
    assert UNKNOWN_MSG not in out
    assert MISSING_MSG not in out
