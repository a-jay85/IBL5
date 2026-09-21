import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.body_numbers import BodyNumberFacts, body_number_facts, correct_body_numbers
from harness.classify import FILES_CHANGED_BEGIN, FILES_CHANGED_END

_NAME_STATUS = """\
A\tibl5/migrations/047_add_x.sql
A\tibl5/docs/decisions/ADR-0131-foo.md
M\ttools/postplan-harness/runner.py
"""

_NUMSTAT = """\
0\t0\tibl5/migrations/047_add_x.sql
5\t2\tibl5/docs/decisions/ADR-0131-foo.md
120\t30\ttools/postplan-harness/runner.py
"""

# Fixture gives: files_changed=3, lines_added=125, lines_deleted=32,
#                migration_numbers=("047",), adr_numbers=("0131",)


def test_wrong_file_count_is_corrected():
    body = "2 files changed"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert "3 files changed" in result
    assert "2 files changed" not in result


def test_wrong_line_delta_is_corrected():
    body = "10 lines added, 5 lines deleted"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert "125 lines added" in result
    assert "32 lines deleted" in result
    assert "10 lines added" not in result
    assert "5 lines deleted" not in result


def test_wrong_migration_number_is_corrected():
    body = "migration #999 was added"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert "migration #047" in result
    assert "migration #999" not in result


def test_wrong_adr_number_is_corrected():
    body = "See ADR-9999 for details"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert "ADR-0131" in result
    assert "ADR-9999" not in result


def test_two_added_migrations_leave_the_cited_number_alone():
    name_status_two_migrations = """\
A\tibl5/migrations/047_add_x.sql
A\tibl5/migrations/048_add_y.sql
M\ttools/postplan-harness/runner.py
"""
    numstat_two = """\
0\t0\tibl5/migrations/047_add_x.sql
0\t0\tibl5/migrations/048_add_y.sql
120\t30\ttools/postplan-harness/runner.py
"""
    body = "migration #999 was added"
    result = correct_body_numbers(body, name_status_two_migrations, numstat_two)
    assert result == body


def test_body_already_agreeing_is_returned_byte_identical():
    body = "3 files changed and 125 lines added"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert result == body


def test_files_changed_block_numbers_are_never_rewritten():
    inner = "2 files changed"
    body = f"{FILES_CHANGED_BEGIN}{inner}{FILES_CHANGED_END}"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert inner in result
    assert FILES_CHANGED_BEGIN in result
    assert FILES_CHANGED_END in result


def test_manual_testing_section_numbers_are_never_rewritten():
    body = "## Manual Testing\n2 files changed\nSome steps here\n"
    result = correct_body_numbers(body, _NAME_STATUS, _NUMSTAT)
    assert "2 files changed" in result
    assert "3 files changed" not in result
