"""render_scope_notes: the risk-ordered, count-bearing `## Unplanned changes` block.

Pure rendering tests. Notes and numstat dicts are built by hand.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.classify import (SCOPE_GAP_RULE, SCOPE_NOTES_BEGIN, SCOPE_NOTES_END, SCOPE_RULE,
                              extract_scope_reasons, numstat_counts, render_scope_notes)
from harness.scope_conformance import scope_notes
from harness.state import Classification, HarnessError, PlanInfo


def _plan(tmp_path, critical: list[str], name: str = "plan.md") -> PlanInfo:
    lines = ["# Plan", "", "## Critical Files", ""]
    lines += [f"- `{p}` (modified)" for p in critical]
    path = tmp_path / name
    path.write_text("\n".join(lines) + "\n")
    return PlanInfo(found=True, path=str(path))


def _u(path: str) -> str:
    return f"unplanned {path} (changed path that no plan Critical File accounts for)"


def test_render_states_rule_once():
    out = render_scope_notes([_u("ibl5/a.php"), _u("ibl5/b.php"), _u("ibl5/c.php")])
    assert out.count(SCOPE_RULE) == 1


def test_render_drops_per_note_parenthetical():
    out = render_scope_notes([_u("ibl5/a.php")])
    assert "(changed path" not in out


def test_render_shows_numstat_counts():
    out = render_scope_notes([_u("ibl5/a.php")], numstat={"ibl5/a.php": (12, 3)})
    assert "- `ibl5/a.php` +12/-3: " in out


def test_render_orders_claude_paths_first():
    out = render_scope_notes(
        [_u("ibl5/big.php"), _u(".claude/rules/x.md")],
        numstat={"ibl5/big.php": (300, 0), ".claude/rules/x.md": (1, 0)})
    assert out.index(".claude/rules/x.md") < out.index("ibl5/big.php")


def test_render_orders_test_net_deletion_before_source_edit():
    out = render_scope_notes(
        [_u("ibl5/a.php"), _u("tools/postplan-harness/tests/test_x.py")],
        numstat={"ibl5/a.php": (80, 80), "tools/postplan-harness/tests/test_x.py": (0, 147)})
    assert out.index("tests/test_x.py") < out.index("ibl5/a.php")


def test_render_orders_large_new_code_before_rest():
    out = render_scope_notes(
        [_u("ibl5/edit.php"), _u("ibl5/new.php")],
        numstat={"ibl5/edit.php": (90, 90), "ibl5/new.php": (150, 0)})
    assert out.index("ibl5/new.php") < out.index("ibl5/edit.php")


def test_render_folds_four_line_edit_not_five():
    out = render_scope_notes(
        [_u("ibl5/a.php"), _u("ibl5/b.php")],
        numstat={"ibl5/a.php": (2, 2), "ibl5/b.php": (3, 2)})
    summary = out.index("1 small edits (4 lines or fewer)")
    assert out.index("`ibl5/a.php`") > summary
    assert out.index("`ibl5/b.php`") < summary


def test_render_never_folds_claude_path():
    out = render_scope_notes([_u(".claude/rules/x.md")], numstat={".claude/rules/x.md": (1, 0)})
    assert "<details>" not in out
    assert ".claude/rules/x.md" in out


def test_render_labels_binary_path():
    out = render_scope_notes([_u("ibl5/img.png")], numstat={"ibl5/img.png": (None, None)})
    assert "`ibl5/img.png` binary: " in out
    assert "<details>" not in out


def test_render_keeps_gap_notes_visible():
    out = render_scope_notes(["gap ibl5/b.php (absent)"])
    assert SCOPE_GAP_RULE in out
    assert "- gap `ibl5/b.php` (absent)" in out
    assert "<details>" not in out


def test_render_keeps_unmatched_note():
    out = render_scope_notes(["scope check unavailable (exit 2: boom)"])
    assert "- scope check unavailable (exit 2: boom)" in out
    assert out.startswith(SCOPE_NOTES_BEGIN) and out.endswith(SCOPE_NOTES_END)


def test_render_empty_notes_returns_empty():
    assert render_scope_notes([], numstat={"a": (1, 1)}, changed_count=3) == ""


def _many(n: int) -> list[str]:
    return [_u(f"ibl5/f{i}.php") for i in range(n)]


def test_render_collapses_when_most_paths_unplanned():
    out = render_scope_notes(_many(6), changed_count=10)
    assert "The plan's file list matches only 4 of 10 changed files. Check the plan." in out
    assert "<summary>All 6 unplanned paths</summary>" in out


def test_render_no_collapse_at_exact_half():
    assert "matches only" not in render_scope_notes(_many(6), changed_count=12)


def test_render_no_collapse_below_min_count():
    assert "matches only" not in render_scope_notes(_many(4), changed_count=5)


def test_render_no_collapse_without_changed_count():
    assert "matches only" not in render_scope_notes(_many(6))


def test_render_inlines_reason():
    out = render_scope_notes([_u("ibl5/a.php")], numstat={"ibl5/a.php": (1, 1)},
                             reasons={"ibl5/a.php": "needed for the fix"})
    assert "- `ibl5/a.php` +1/-1: needed for the fix" in out


def test_render_placeholder_for_missing_reason():
    out = render_scope_notes([_u("ibl5/a.php")], numstat={"ibl5/a.php": (1, 1)})
    assert "`ibl5/a.php` +1/-1: (no reason given)" in out


def test_extract_reasons_strips_section():
    body = ("## Summary\n- x\n\n## Unplanned change reasons\n- `ibl5/a.php`: why\n\n"
            "## Notes\n- y\n")
    reasons, rest = extract_scope_reasons(body)
    assert reasons == {"ibl5/a.php": "why"}
    assert "Unplanned change reasons" not in rest
    assert "## Summary" in rest and "## Notes" in rest


def test_extract_reasons_ignores_near_miss_heading():
    for heading in ("## Unplanned change reasons (draft)", "### Unplanned change reasons"):
        body = f"## Summary\n- x\n\n{heading}\n- `ibl5/a.php`: why\n"
        assert extract_scope_reasons(body) == ({}, body)


def test_extract_reasons_ignores_unbackticked_bullet():
    body = "## Unplanned change reasons\n- ibl5/a.php: why\n"
    assert extract_scope_reasons(body)[0] == {}


def test_extract_reasons_neutralizes_marker_text():
    body = "## Unplanned change reasons\n- `ibl5/a.php`: x <!-- scope-notes:end --> y\n"
    reason = extract_scope_reasons(body)[0]["ibl5/a.php"]
    assert "<!--" not in reason and "-->" not in reason


def test_reason_section_never_clears_note(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    body = "## Unplanned change reasons\n- `ibl5/rogue.php`: because\n"
    notes = scope_notes(plan, ["ibl5/a.php", "ibl5/rogue.php"], "", body)
    assert any(n.startswith("unplanned ibl5/rogue.php") for n in notes)


def test_numstat_counts_parses_rows_and_binary():
    diff = ("diff --git a/x.php b/x.php\nindex 1..2 100644\n--- a/x.php\n+++ b/x.php\n"
            "@@ -1 +1,2 @@\n-a\n+b\n+c\n")
    assert numstat_counts(diff).get("x.php") == (2, 1)


def _mod_diff(paths: list[str]) -> str:
    out = []
    for p in paths:
        out += [f"diff --git a/{p} b/{p}", "index 1111111..2222222 100644",
                f"--- a/{p}", f"+++ b/{p}", "@@ -1 +1 @@", "-x", "+y"]
    return "\n".join(out) + "\n"


def test_inject_scope_notes_inlines_reason_and_strips_section(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    files = ["ibl5/a.php", "ibl5/rogue.php"]
    copy = {"summary_md": "## Summary\n- x\n\n## Unplanned change reasons\n"
                          "- `ibl5/rogue.php`: hand fix\n"}
    runner._inject_scope_notes(copy, plan, files, _mod_diff(files), copy["summary_md"],
                               lambda _m: None)
    body = copy["summary_md"]
    assert "## Unplanned change reasons" not in body
    block = body[body.index(SCOPE_NOTES_BEGIN):body.index(SCOPE_NOTES_END)]
    assert "hand fix" in block


def test_unplanned_candidates_lists_unplanned_paths(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    files = ["ibl5/a.php", "ibl5/rogue.php"]
    out = runner._unplanned_candidates(plan, files, _mod_diff(files), lambda _m: None)
    assert out == ["ibl5/rogue.php"]


def test_unplanned_candidates_swallows_scope_timeout(tmp_path, monkeypatch):
    plan = _plan(tmp_path, ["ibl5/a.php"])

    def _hang(*_a, **_k):
        raise HarnessError("subprocess-timeout", "scope-check timed out")

    monkeypatch.setattr(runner.scope_conformance, "scope_notes", _hang)
    logs: list[str] = []
    out = runner._unplanned_candidates(plan, ["ibl5/a.php"], "", logs.append)
    assert out == []
    assert any("scope pre-pass skipped" in m for m in logs)


def test_pr_copy_forwards_unplanned_paths(monkeypatch):
    monkeypatch.setattr(runner, "_unplanned_candidates", lambda *_a, **_k: ["ibl5/rogue.php"])
    seen: dict = {}

    def _spy(*_a, **kw):
        seen.update(kw)
        return "prompt"

    monkeypatch.setattr(runner.llm_calls, "pr_copy_prompt", _spy)

    class _Gh:
        def pr_exists(self):
            return False

    class _Git:
        def branch_head_subject(self, *_a, **_k):
            return "chore: x"

    class _Llm:
        def call(self, *_a, **_k):
            raise HarnessError("llm-invalid-output", "bad json")

    runner._pr_copy(_Llm(), _Git(), _Gh(), None, "slug", Classification(), PlanInfo(),
                    lambda _m: None, files=["ibl5/rogue.php"], diff_body="x")
    assert seen.get("unplanned_paths") == ["ibl5/rogue.php"]


def test_inject_scope_notes_empty_notes_removes_stale_block(tmp_path):
    plan = _plan(tmp_path, ["ibl5/a.php"])
    stale = (f"## Summary\n- x\n\n{SCOPE_NOTES_BEGIN}\n## Unplanned changes\n- `ibl5/old.php`: "
             f"(no reason given)\n{SCOPE_NOTES_END}\n")
    copy = {"summary_md": stale}
    runner._inject_scope_notes(copy, plan, ["ibl5/a.php"], _mod_diff(["ibl5/a.php"]),
                               copy["summary_md"], lambda _m: None)
    assert SCOPE_NOTES_BEGIN not in copy["summary_md"]
