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
