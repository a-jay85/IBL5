"""Unit tests for harness/sql_comments.py — comment blanking ahead of merge-danger trigger 1."""
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import DESTRUCTIVE_SQL
from harness.sql_comments import strip_added_sql_comments, strip_sql_comments

FIXTURES = Path(__file__).parent / "fixtures" / "merge-danger-migrations"

LIVE_PR3513 = "-- G1: a target longer than the column (would truncate or error on write)"
LIVE_PR3495 = ("-- Rollback (lossless for all other data): "
               "ALTER TABLE `ibl_team_info` DROP COLUMN `trade_mode`;")


def _hits(text):
    return bool(DESTRUCTIVE_SQL.search(text))


def _plus(*bodies):
    return "\n".join("+" + b for b in bodies)


# --- strip_sql_comments --------------------------------------------------------------------------

def test_dash_dash_line_comment_is_blanked():
    out = strip_sql_comments("-- DROP TABLE x\nSELECT 1;")
    assert out == " " * 15 + "\nSELECT 1;"
    assert not _hits("+" + out.split("\n")[0])


def test_hash_comment_is_blanked():
    assert strip_sql_comments("# TRUNCATE t\n") == " " * 12 + "\n"


def test_block_comment_on_one_line_is_blanked():
    inp = "/* DROP COLUMN c */ SELECT 1;"
    out = strip_sql_comments(inp)
    assert out.endswith(" SELECT 1;")
    assert out == " " * len("/* DROP COLUMN c */") + " SELECT 1;"


def test_block_comment_across_lines_keeps_line_count():
    inp = "SELECT 1; /* start\nRENAME COLUMN a TO b\nend */ SELECT 2;"
    out = strip_sql_comments(inp)
    assert out.count("\n") == 2
    assert "RENAME" not in out
    assert len(out) == len(inp)
    assert out.endswith(" SELECT 2;")


def test_statement_after_closed_block_still_matches():
    assert _hits("+" + strip_sql_comments("/* note */ ALTER TABLE t DROP COLUMN c;"))


def test_statement_with_trailing_comment_still_matches():
    out = strip_sql_comments("ALTER TABLE t DROP COLUMN c; -- bye")
    assert _hits("+" + out)
    assert out.endswith("      ")


def test_dash_dash_inside_single_quotes_is_not_a_comment():
    inp = "INSERT INTO t VALUES ('a -- b'); DROP TABLE t;"
    out = strip_sql_comments(inp)
    assert out == inp
    assert _hits("+" + out)


def test_slash_star_inside_double_quotes_is_not_a_comment():
    inp = 'SELECT "/*"; DROP TABLE t;'
    out = strip_sql_comments(inp)
    assert out == inp
    assert _hits("+" + out)


def test_escaped_and_doubled_quotes_keep_quote_state():
    out = strip_sql_comments("SELECT 'it''s', 'a\\'b'; -- DROP TABLE t")
    assert out.startswith("SELECT 'it''s', 'a\\'b'; ")
    assert "DROP" not in out
    assert not _hits("+" + out)


def test_backtick_identifier_with_dashes_is_not_a_comment():
    inp = "SELECT `a--b`; DROP TABLE t;"
    out = strip_sql_comments(inp)
    assert out == inp
    assert _hits("+" + out)


def test_dash_dash_without_space_is_code():
    assert strip_sql_comments("--DROP TABLE t") == "--DROP TABLE t"
    assert _hits("+--DROP TABLE t")


def test_executable_comment_is_code():
    inp = "/*!40000 DROP TABLE t */"
    assert strip_sql_comments(inp) == inp
    assert _hits("+" + strip_sql_comments(inp))


def test_unterminated_block_returns_input_unchanged():
    inp = "/* DROP TABLE t\nSELECT 1;"
    assert strip_sql_comments(inp) == inp
    assert _hits("+" + strip_sql_comments(inp))


def test_unterminated_quote_returns_input_unchanged():
    inp = "SELECT 'open; DROP TABLE t;"
    assert strip_sql_comments(inp) == inp
    assert _hits("+" + strip_sql_comments(inp))


def test_stray_close_is_code():
    inp = "*/ DROP TABLE t;"
    assert strip_sql_comments(inp) == inp
    assert _hits("+" + strip_sql_comments(inp))


def test_empty_input_and_comment_only_input():
    assert strip_sql_comments("") == ""
    assert strip_sql_comments("-- only\n") == " " * 7 + "\n"


def test_live_pr3513_comment_line_does_not_hit():
    assert _hits("+" + LIVE_PR3513)
    assert not _hits(strip_added_sql_comments("+" + LIVE_PR3513))


def test_live_pr3495_rollback_comment_does_not_hit():
    assert _hits("+" + LIVE_PR3495)
    assert not _hits(strip_added_sql_comments("+" + LIVE_PR3495))


# --- strip_added_sql_comments --------------------------------------------------------------------

MIG = "ibl5/migrations/200_drop_col.sql"


def _diff(*body_lines):
    head = [f"diff --git a/{MIG} b/{MIG}", f"--- a/{MIG}", f"+++ b/{MIG}"]
    return "\n".join(head + list(body_lines)) + "\n"


def test_added_lines_only_are_stripped():
    text = _diff("@@ -1,2 +1,2 @@", " -- DROP TABLE ctx", "--- DROP TABLE old", "+-- DROP TABLE new")
    out_lines = strip_added_sql_comments(text).split("\n")
    in_lines = text.split("\n")
    assert len(out_lines) == len(in_lines)
    assert out_lines[2] == f"+++ b/{MIG}"
    assert out_lines[4] == " -- DROP TABLE ctx"
    assert out_lines[5] == "--- DROP TABLE old"
    assert out_lines[6] == "+" + " " * len("-- DROP TABLE new")


def test_hunks_are_tokenized_independently():
    text = _diff("@@ -0,0 +1,2 @@", "+/* open", "+ALTER TABLE t DROP COLUMN c;",
                 "@@ -10,0 +12,1 @@", "+*/")
    out = strip_added_sql_comments(text)
    assert _hits(out)
    assert "+/* open\n+ALTER TABLE t DROP COLUMN c;\n" in out


def test_added_runs_split_at_context_lines():
    text = _diff("@@ -1,1 +1,3 @@", "+/* a", " SELECT 1;", "+*/ ALTER TABLE t DROP COLUMN c;")
    out = strip_added_sql_comments(text)
    assert out == text
    assert _hits(out)


def test_diff_without_headers_strips_plain_plus_lines():
    out = strip_added_sql_comments("+-- DROP TABLE t\n+SELECT 1;\n")
    assert out == "+" + " " * len("-- DROP TABLE t") + "\n+SELECT 1;\n"
    assert not _hits(out)


@pytest.mark.parametrize("name", ("pr3513.diff", "pr3495.diff", "pr3472.diff"))
def test_line_count_is_preserved_for_every_fixture(name):
    text = (FIXTURES / name).read_text(encoding="utf-8")
    assert strip_added_sql_comments(text).count("\n") == text.count("\n")
