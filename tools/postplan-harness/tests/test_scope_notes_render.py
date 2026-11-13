"""render_scope_notes: the risk-ordered, count-bearing `## Unplanned changes` block.

Pure rendering tests. Notes and numstat dicts are built by hand.
"""
from harness.classify import (SCOPE_GAP_RULE, SCOPE_NOTES_BEGIN, SCOPE_NOTES_END, SCOPE_RULE,
                              numstat_counts, render_scope_notes)


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
        numstat={"ibl5/a.php": (20, 20), "tools/postplan-harness/tests/test_x.py": (0, 147)})
    assert out.index("tests/test_x.py") < out.index("ibl5/a.php")


def test_render_orders_large_new_code_before_rest():
    out = render_scope_notes(
        [_u("ibl5/edit.php"), _u("ibl5/new.php")],
        numstat={"ibl5/edit.php": (30, 30), "ibl5/new.php": (150, 0)})
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


def test_numstat_counts_parses_rows_and_binary():
    diff = ("diff --git a/x.php b/x.php\nindex 1..2 100644\n--- a/x.php\n+++ b/x.php\n"
            "@@ -1 +1,2 @@\n-a\n+b\n+c\n")
    assert numstat_counts(diff).get("x.php") == (2, 1)
