"""Unit tests for harness/migration_revert_proof.py — the proof that a destructive migration reverts."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import migration_revert_proof as mrp
from harness.migration_revert_proof import RevertProof


def _migration_diff(path, *added):
    lines = [f"diff --git a/{path} b/{path}", "new file mode 100644", "--- /dev/null", f"+++ b/{path}",
             "@@ -0,0 +1 @@"]
    lines += [f"+{a}" for a in added]
    return "\n".join(lines) + "\n"


UP = "ibl5/migrations/900_demo_truncate.sql"
DOWN = "ibl5/migrations/900_demo_truncate.down.sql"
UP_SQL = ("CREATE TABLE ibl_demo_backup_900 AS SELECT * FROM ibl_demo;",
          "TRUNCATE TABLE ibl_demo;",
          "INSERT INTO ibl_demo (id, name) VALUES (1, 'a');")
DOWN_SQL = ("-- restores: ibl_demo",
            "DELETE FROM ibl_demo;",
            "INSERT INTO ibl_demo SELECT * FROM ibl_demo_backup_900;")
ROUNDTRIP_TEXT = "function testEveryPairedMigrationRoundTrips() { glob('/migrations/*.down.sql'); '/^--\\s*restores:/'; }"


# The list as shipped, read before the autouse fixture below swaps it.
SHIPPED_STATIC_TABLES = mrp.SITE_STATIC_TABLES


@pytest.fixture(autouse=True)
def _demo_is_static(monkeypatch):
    """Treat the synthetic ibl_demo table as one no site code writes, so each test below
    reaches the condition it names. The site-writes tests use real tables."""
    monkeypatch.setattr(mrp, "SITE_STATIC_TABLES", frozenset({"ibl_demo"}))


def _read_test(path):
    return ROUNDTRIP_TEXT if path == mrp.ROUNDTRIP_TEST else None


def _pair(up=UP_SQL, down=DOWN_SQL):
    return _migration_diff(UP, *up) + _migration_diff(DOWN, *down)


def _reason(diff, read=_read_test):
    return mrp.prove_revertible(UP, diff, read).reason


# --- the proven shape -------------------------------------------------------------------------

def test_truncate_with_backup_and_down_is_proven():
    assert mrp.prove_revertible(UP, _pair(), _read_test) == RevertProof(True, "proven")


def test_down_from_candidate_branch_is_read():
    def read(path):
        if path == DOWN:
            return "\n".join(DOWN_SQL)
        return _read_test(path)

    assert _reason(_migration_diff(UP, *UP_SQL), read) == "proven"


def test_keyword_inside_string_is_not_a_rewrite():
    diff = _pair(up=("INSERT INTO ibl_demo VALUES (1, 'DELETE FROM ibl_demo; TRUNCATE');",),
                 down=("-- restores: ibl_demo", "DELETE FROM ibl_demo WHERE id = 1;"))
    assert _reason(diff) == "proven"


# --- the down file ----------------------------------------------------------------------------

def test_no_down_file_is_unproven():
    assert _reason(_migration_diff(UP, *UP_SQL)) == "down-file-missing"


def test_missing_restores_line_is_unproven():
    assert _reason(_pair(down=DOWN_SQL[1:])) == "restores-line-missing"


@pytest.mark.parametrize("first_line", [
    "-- this file restores ibl_demo",
    "-- restoresx: ibl_demo",
    "--restores ibl_demo",
])
def test_restores_near_miss_is_not_a_restores_line(first_line):
    assert _reason(_pair(down=(first_line,) + DOWN_SQL[1:])) == "restores-line-missing"


def test_down_with_ddl_is_unproven():
    assert _reason(_pair(down=DOWN_SQL + ("DROP TABLE ibl_demo_backup_900;",))) == "down-not-dml"


def test_restores_missing_written_table_is_unproven():
    diff = _pair(up=UP_SQL + ("INSERT INTO ibl_other VALUES (1);",))
    assert _reason(diff) == "restores-missing:ibl_other"


def test_down_without_restore_from_backup_is_unproven():
    diff = _pair(down=("-- restores: ibl_demo", "DELETE FROM ibl_demo;"))
    assert _reason(diff) == "down-no-restore:ibl_demo"


# --- the up file ------------------------------------------------------------------------------

@pytest.mark.parametrize("rewrite", [
    "TRUNCATE TABLE ibl_demo;",
    "DELETE FROM ibl_demo WHERE id > 3;",
    "UPDATE ibl_demo SET name = 'x';",
    "REPLACE INTO ibl_demo VALUES (1, 'b');",
    "INSERT INTO ibl_demo VALUES (1, 'b') ON DUPLICATE KEY UPDATE name = 'b';",
])
def test_rewrite_without_backup_is_unproven(rewrite):
    assert _reason(_pair(up=(rewrite,))) == "rewrite-no-backup:ibl_demo"


@pytest.mark.parametrize("delete", [
    "DELETE FROM ibl_demo, ibl_other USING ibl_demo JOIN ibl_other ON ibl_demo.id = ibl_other.id;",
    "DELETE FROM ibl_demo USING ibl_demo JOIN ibl_other ON ibl_demo.id = ibl_other.id;",
    "DELETE ibl_demo, ibl_other FROM ibl_demo JOIN ibl_other ON ibl_demo.id = ibl_other.id;",
])
def test_multi_table_delete_is_unparseable(delete):
    up = UP_SQL[:1] + (delete,) + UP_SQL[1:]
    assert _reason(_pair(up=up)) == "unparseable:DELETE"


@pytest.mark.parametrize("backup", [
    "CREATE TABLE ibl_demo_backup_900 AS SELECT * FROM ibl_demo WHERE 1=0;",
    "CREATE TABLE ibl_demo_backup_900 AS SELECT * FROM ibl_demo LIMIT 1;",
    "CREATE TABLE ibl_demo_backup_900 AS SELECT id FROM ibl_demo;",
    "CREATE TABLE IF NOT EXISTS ibl_demo_backup_900 AS SELECT * FROM ibl_demo;",
], ids=["where", "limit", "column-subset", "if-not-exists"])
def test_partial_backup_copy_is_not_a_backup(backup):
    assert _reason(_pair(up=(backup,) + UP_SQL[1:])) == "rewrite-no-backup:ibl_demo"


@pytest.mark.parametrize("copy", [
    "INSERT INTO ibl_demo_backup_900 SELECT * FROM ibl_demo WHERE id > 3;",
    "INSERT INTO ibl_demo_backup_900 SELECT id FROM ibl_demo;",
], ids=["where", "column-subset"])
def test_partial_insert_select_copy_is_not_a_backup(copy):
    up = ("CREATE TABLE ibl_demo_backup_900 (id INT, name VARCHAR(8));", copy) + UP_SQL[1:]
    assert _reason(_pair(up=up)) == "rewrite-no-backup:ibl_demo"


def test_full_insert_select_copy_is_a_backup():
    up = ("CREATE TABLE ibl_demo_backup_900 (id INT, name VARCHAR(8));",
          "INSERT INTO ibl_demo_backup_900 SELECT * FROM ibl_demo;") + UP_SQL[1:]
    assert _reason(_pair(up=up)) == "proven"


def test_stale_if_not_exists_table_is_not_a_backup():
    up = ("CREATE TABLE IF NOT EXISTS ibl_demo_backup_900 (id INT, name VARCHAR(8));",
          "INSERT INTO ibl_demo_backup_900 SELECT * FROM ibl_demo;") + UP_SQL[1:]
    assert _reason(_pair(up=up)) == "rewrite-no-backup:ibl_demo"


def test_filtered_restore_from_backup_is_unproven():
    down = DOWN_SQL[:2] + ("INSERT INTO ibl_demo SELECT * FROM ibl_demo_backup_900 WHERE id = 1;",)
    assert _reason(_pair(down=down)) == "down-no-restore:ibl_demo"


def test_column_subset_restore_is_unproven():
    down = DOWN_SQL[:2] + ("INSERT INTO ibl_demo (id) SELECT id FROM ibl_demo_backup_900;",)
    assert _reason(_pair(down=down)) == "down-no-restore:ibl_demo"


def test_insert_ignore_restore_without_delete_is_unproven():
    down = DOWN_SQL[:1] + ("INSERT IGNORE INTO ibl_demo SELECT * FROM ibl_demo_backup_900;",)
    assert _reason(_pair(down=down)) == "down-no-restore:ibl_demo"


def test_bare_replace_restore_is_unproven():
    down = DOWN_SQL[:1] + ("REPLACE INTO ibl_demo SELECT * FROM ibl_demo_backup_900;",)
    assert _reason(_pair(down=down)) == "down-no-restore:ibl_demo"


def test_delete_then_replace_full_copy_restore_is_proven():
    down = DOWN_SQL[:1] + ("DELETE FROM ibl_demo;", "REPLACE INTO ibl_demo SELECT * FROM ibl_demo_backup_900;")
    assert _reason(_pair(down=down)) != "down-no-restore:ibl_demo"


def test_backup_after_rewrite_is_unproven():
    diff = _pair(up=("TRUNCATE TABLE ibl_demo;", UP_SQL[0]))
    assert _reason(diff) == "rewrite-no-backup:ibl_demo"


@pytest.mark.parametrize("drop", [
    "ALTER TABLE ibl_demo DROP COLUMN name;",
    "DROP TABLE ibl_demo;",
    "ALTER TABLE ibl_demo RENAME COLUMN name TO nm;",
])
def test_schema_drop_is_unproven_even_with_down(drop):
    assert _reason(_pair(up=(UP_SQL[0], drop))) == "schema-drop"


def test_unterminated_quote_is_unparseable():
    diff = _pair(up=UP_SQL[:2] + ("INSERT INTO ibl_demo VALUES (1, 'a);",))
    assert _reason(diff) == "unparseable"


def test_delimiter_block_is_unparseable():
    diff = _pair(up=UP_SQL + (
        "DELIMITER //",
        "CREATE TRIGGER trg BEFORE INSERT ON ibl_demo FOR EACH ROW BEGIN SET NEW.name = 'x'; END //",
    ))
    assert _reason(diff) == "unparseable"


def test_outfile_is_external_side_effect():
    diff = _pair(up=UP_SQL + ("SELECT * FROM ibl_demo INTO OUTFILE '/tmp/x';",))
    assert _reason(diff) == "external-side-effect"


def test_multi_table_update_is_unparseable():
    diff = _pair(up=(UP_SQL[0], "UPDATE ibl_demo d JOIN ibl_other o ON o.id = d.id SET o.name = d.name;"))
    assert _reason(diff) == "unparseable:UPDATE"


def test_set_statement_for_rewrite_is_unparseable():
    diff = _pair(up=(UP_SQL[0], "SET STATEMENT max_statement_time=1 FOR TRUNCATE TABLE ibl_demo;"))
    assert _reason(diff) == "unparseable:SET"


def test_modified_up_migration_is_unproven():
    up_section = "\n".join([
        f"diff --git a/{UP} b/{UP}", "index 1111111..2222222 100644", f"--- a/{UP}", f"+++ b/{UP}",
        "@@ -1 +1,3 @@",
    ] + [f"+{a}" for a in UP_SQL]) + "\n"
    diff = up_section + _migration_diff(DOWN, *DOWN_SQL)
    assert _reason(diff) == "up-not-added"


# --- the round-trip test ----------------------------------------------------------------------

@pytest.mark.parametrize("read", [
    lambda p: None,
    lambda p: ROUNDTRIP_TEXT.replace("*.down.sql", "*.sql") if p == mrp.ROUNDTRIP_TEST else None,
], ids=["absent", "glob-no-longer-covers-down-files"])
def test_roundtrip_test_missing_is_unproven(read):
    assert _reason(_pair(), read) == "roundtrip-test-missing"


# --- tables site code writes after merge ------------------------------------------------------

def _pair_on(table):
    up = tuple(x.replace("ibl_demo", table) for x in UP_SQL)
    down = tuple(x.replace("ibl_demo", table) for x in DOWN_SQL)
    return mrp.prove_revertible(UP, _pair(up=up, down=down), _read_test).reason


def test_shipped_static_tables_are_empty():
    assert SHIPPED_STATIC_TABLES == frozenset()


@pytest.mark.parametrize("table", ["ibl_plr", "ibl_team_info", "ibl_schedule"])
def test_backup_and_restore_of_live_table_is_unproven(table):
    assert _pair_on(table) == f"site-writes:{table}"


def test_down_writing_an_unlisted_live_table_is_unproven():
    diff = _pair(down=DOWN_SQL + ("DELETE FROM ibl_plr WHERE pid = 1;",))
    assert _reason(diff) == "site-writes:ibl_plr"


def test_insert_only_live_table_is_unproven():
    up = UP_SQL + ("INSERT INTO ibl_plr (pid) VALUES (9);",)
    down = ("-- restores: ibl_demo, ibl_plr",) + DOWN_SQL[1:]
    assert _reason(_pair(up=up, down=down)) == "site-writes:ibl_plr"


@pytest.mark.parametrize("stmt", [
    "SELECT 1;",
    "DELETE ibl_demo, ibl_plr FROM ibl_demo JOIN ibl_plr ON ibl_demo.id = ibl_plr.pid;",
    "UPDATE ibl_demo d JOIN ibl_plr p ON p.pid = d.id SET p.name = d.name;",
    "SET STATEMENT max_statement_time=1 FOR DELETE FROM ibl_plr;",
], ids=["select", "multi-table-delete", "multi-table-update", "set-statement-for"])
def test_unparseable_down_statement_is_unproven(stmt):
    assert _reason(_pair(down=DOWN_SQL + (stmt,))) == "down-unparseable"
