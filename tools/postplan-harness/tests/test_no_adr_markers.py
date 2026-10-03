"""Tests for parse_no_adr_markers (Phase 1) and _upsert_no_adr_markers (Phase 2)."""
import json
import os
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.planfile import parse_no_adr_markers
from harness.state import PlanInfo, RunResult, TerminalState

# ---------------------------------------------------------------------------
# Phase 1 — parse_no_adr_markers
# ---------------------------------------------------------------------------

def test_absent_marker_returns_empty_list():
    assert parse_no_adr_markers("# Plan\n\n## Phase 1\nno markers here") == []
    assert parse_no_adr_markers("") == []


def test_single_marker_returned_verbatim():
    content = "# Plan\n<!-- no-adr: pure tooling refactor -->\n## Phase 1\n"
    result = parse_no_adr_markers(content)
    assert result == ["<!-- no-adr: pure tooling refactor -->"]


def test_multiple_markers_preserve_order_and_stay_separate():
    content = (
        "# Plan\n"
        "<!-- no-adr: first bypass -->\n"
        "Some prose between markers.\n"
        "<!-- no-adr: second bypass -->\n"
        "## Phase 1\n"
    )
    result = parse_no_adr_markers(content)
    assert len(result) == 2
    assert result[0] == "<!-- no-adr: first bypass -->"
    assert result[1] == "<!-- no-adr: second bypass -->"
    assert "Some prose" not in result[0]
    assert "Some prose" not in result[1]


def test_multiline_marker_captured_whole():
    content = "# Plan\n<!-- no-adr:\n  reason line one\n  line two -->\n## Body\n"
    result = parse_no_adr_markers(content)
    assert len(result) == 1
    assert "reason line one" in result[0]
    assert "line two" in result[0]


def test_marker_inside_fenced_block_ignored():
    content = (
        "# Plan\n"
        "```\n"
        "<!-- no-adr: example only -->\n"
        "```\n"
        "## Phase 1\n"
    )
    assert parse_no_adr_markers(content) == []


def test_similar_but_wrong_comments_ignored():
    content = (
        "<!-- no-adrs: x -->\n"
        "<!-- adr: x -->\n"
        "<!-- no-adr: x\n"    # unterminated — no closing -->
        "## Phase 1\n"
    )
    result = parse_no_adr_markers(content)
    assert result == []


def test_state_dict_omits_empty_no_adr_markers():
    """to_json drops no_adr_markers when the list is empty (mirrors backlog_issues pop)."""
    res = RunResult(terminal=TerminalState.SHIPPED_HELD, slug="x",
                    plan=PlanInfo(found=True, path="x.md"))
    d = json.loads(res.to_json())
    assert "no_adr_markers" not in d["plan"]

    res.plan.no_adr_markers = ["<!-- no-adr: x -->"]
    d2 = json.loads(res.to_json())
    assert d2["plan"]["no_adr_markers"] == ["<!-- no-adr: x -->"]


def test_marker_only_in_fenced_block_returns_empty():
    """Marker inside an indented fence is also ignored."""
    content = (
        "# Plan\n"
        "   ```markdown\n"
        "   <!-- no-adr: example only -->\n"
        "   ```\n"
        "## Phase 1\n"
    )
    assert parse_no_adr_markers(content) == []


def test_marker_in_inline_code_ignored():
    """A marker quoted inside backticks on a list item or table row must return []."""
    content = (
        "# Plan\n"
        "- Use `<!-- no-adr: reason -->` to bypass the ADR gate.\n"
        "| col | `<!-- no-adr: table example -->` |\n"
        "## Phase 1\n"
    )
    assert parse_no_adr_markers(content) == []


def test_marker_after_blank_lines_has_no_leading_newline():
    """A blank line above the marker (the common plan layout) must not leak into the match."""
    assert parse_no_adr_markers("# Plan\n\n<!-- no-adr: x -->\n") == ["<!-- no-adr: x -->"]
    assert parse_no_adr_markers("# Plan\n\n\n<!-- no-adr: x -->\n") == ["<!-- no-adr: x -->"]


# ---------------------------------------------------------------------------
# Phase 2 — _upsert_no_adr_markers
# ---------------------------------------------------------------------------

from runner import _upsert_no_adr_markers


BODY = "## Summary\nx\n<!-- files-changed:begin -->\nf\n<!-- files-changed:end -->"
M1 = "<!-- no-adr: marker one -->"
M2 = "<!-- no-adr: marker two -->"


def _plan(found=True, markers=None):
    return types.SimpleNamespace(found=found, no_adr_markers=markers or [])


def test_upsert_prepends_markers_at_top():
    result = _upsert_no_adr_markers(BODY, _plan(markers=[M1, M2]))
    assert result.startswith(M1 + "\n" + M2 + "\n\n## Summary")
    assert M1 not in result.split("<!-- files-changed:begin -->")[1]
    assert M2 not in result.split("<!-- files-changed:begin -->")[1]


def test_upsert_is_idempotent():
    plan = _plan(markers=[M1, M2])
    first = _upsert_no_adr_markers(BODY, plan)
    second = _upsert_no_adr_markers(first, plan)
    assert first == second
    assert first.count(M1) == 1
    assert first.count(M2) == 1


def test_upsert_skips_marker_already_at_body_offset_zero():
    """Markers parsed from a blank-line plan layout match a body that starts with the marker."""
    markers = parse_no_adr_markers("# Plan\n\n" + M1 + "\n")
    body = M1 + "\n\n" + BODY
    assert _upsert_no_adr_markers(body, _plan(markers=markers)) == body


def test_upsert_adds_only_missing_marker():
    body_with_m1 = "some text\n" + M1 + "\nmore text"
    result = _upsert_no_adr_markers(body_with_m1, _plan(markers=[M1, M2]))
    assert result.count(M1) == 1
    assert M2 in result


def test_upsert_plan_blind_passthrough():
    assert _upsert_no_adr_markers(BODY, None) == BODY
    assert _upsert_no_adr_markers(BODY, _plan(found=False, markers=[M1])) == BODY


def test_upsert_no_markers_passthrough():
    result = _upsert_no_adr_markers(BODY, _plan(found=True, markers=[]))
    assert result == BODY


def test_upsert_stub_missing_field_passthrough():
    stub = types.SimpleNamespace(found=True)
    assert _upsert_no_adr_markers(BODY, stub) == BODY


def test_upsert_empty_body():
    result = _upsert_no_adr_markers("", _plan(markers=[M1]))
    assert result == M1 + "\n\n"


# ---------------------------------------------------------------------------
# Replay integration: markers reach the PR body (backlog#1100, #1101)
# ---------------------------------------------------------------------------

import tempfile

import runner
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger
from test_runner_replay import CANNED, _actions, _fixture

RM1 = "<!-- no-adr: first marker reason long enough -->"
RM2 = "<!-- no-adr: second marker reason long enough -->"


def _marker_plan(*markers):
    head = "# P\n\n"
    mid = "".join(m + "\n\n" for m in markers)
    return head + mid + "## Phase 1\nx\n"


def _replay(**over):
    out = tempfile.mkdtemp()
    runner.run(_fixture(**over), out, FixtureLlm(UsageLedger(), CANNED),
               mode="replay", headless=True)
    return _actions(out)


def _bodies(actions, kind):
    return [a["body"] for a in actions if a.get("action") == kind]


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_create_path_markers_reach_pr_create_body():
    acts = _replay(pr_number=None, pr_meta=None,
                   plan_content=_marker_plan(RM1, RM2))
    creates = _bodies(acts, "pr_create")
    assert len(creates) == 1
    body = creates[0]
    assert body.startswith(RM1 + "\n" + RM2 + "\n\n")
    assert body.count(RM1) == 1
    assert body.count(RM2) == 1


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_create_path_markers_survive_phase4_pr_edit_body():
    acts = _replay(pr_number=None, pr_meta=None,
                   plan_content=_marker_plan(RM1, RM2))
    edits = _bodies(acts, "pr_edit_body")
    assert edits
    last = edits[-1]
    assert last.count(RM1) == 1
    assert last.count(RM2) == 1
    assert last.index(RM1) < last.index(RM2)


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_edit_path_markers_reach_pr_edit_body():
    acts = _replay(plan_content=_marker_plan(RM1, RM2))
    assert _bodies(acts, "pr_create") == []
    edits = _bodies(acts, "pr_edit_body")
    assert edits
    last = edits[-1]
    assert last.startswith(RM1 + "\n" + RM2 + "\n\n")
    assert last.count(RM1) == 1
    assert last.count(RM2) == 1


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_edit_path_marker_already_in_body_not_duplicated():
    meta = dict(_fixture()["pr_meta"],
                body=RM1 + "\n\n## Manual Testing\n\nNo manual testing needed\n")
    acts = _replay(pr_meta=meta, plan_content=_marker_plan(RM1, RM2))
    last = _bodies(acts, "pr_edit_body")[-1]
    assert last.count(RM1) == 1
    assert last.count(RM2) == 1
    assert last.startswith(RM2 + "\n\n")


FENCED_ONLY_PLAN = (
    "# P\n\n"
    "```\n"
    "<!-- no-adr: fenced example marker reason -->\n"
    "```\n\n"
    "## Phase 1\nx\n"
)


@pytest.mark.usefixtures("stub_ambient_git_show")
@pytest.mark.parametrize("over", [
    {"pr_number": None, "pr_meta": None},
    {},
], ids=["create-path", "edit-path"])
def test_replay_no_marker_plan_adds_no_marker(over):
    acts = _replay(plan_content=_marker_plan(), **over)
    bodies = _bodies(acts, "pr_create") + _bodies(acts, "pr_edit_body")
    assert bodies
    assert all("no-adr" not in b for b in bodies)


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_fenced_only_marker_adds_no_marker():
    acts = _replay(pr_number=None, pr_meta=None, plan_content=FENCED_ONLY_PLAN)
    bodies = _bodies(acts, "pr_create") + _bodies(acts, "pr_edit_body")
    assert bodies
    assert all("no-adr" not in b for b in bodies)


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_plan_blind_adds_no_marker(monkeypatch, tmp_path):
    monkeypatch.setenv("PLANS_DIR", str(tmp_path))
    assert not runner.locate_plan("synthetic-degrade", content_override=None).found
    acts = _replay(plan_content="")
    edits = _bodies(acts, "pr_edit_body")
    assert edits
    assert all("no-adr" not in b for b in edits)


@pytest.mark.usefixtures("stub_ambient_git_show")
def test_replay_control_identity_upsert_drops_markers(monkeypatch):
    monkeypatch.setattr(runner, "_upsert_no_adr_markers", lambda body, plan: body)
    acts = _replay(pr_number=None, pr_meta=None,
                   plan_content=_marker_plan(RM1, RM2))
    creates = _bodies(acts, "pr_create")
    edits = _bodies(acts, "pr_edit_body")
    assert creates and edits
    assert all(RM1 not in b and RM2 not in b for b in creates + edits)
