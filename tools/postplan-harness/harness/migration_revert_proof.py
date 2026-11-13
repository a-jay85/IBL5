"""Proof that a destructive migration reverts automatically, for merge-danger trigger 1.

Since PR #3889 a deploy runs the stored down SQL of a reverted migration. This module proves that
for one migration: the PR adds the up file; a `<stem>.down.sql` the runner pairs by stem exists;
the down file has a `-- restores:` line naming every table the up file writes and holds DML only;
every row-destroying up statement runs after a backup copy of its table; the down file restores
each such table from its backup; no site code path writes any table the pair touches
(SITE_STATIC_TABLES, empty today, so no migration is proven yet); and the round-trip test
that globs every down file is on the candidate branch. The required CI checks decide whether that test passed. Anything the module
cannot parse is unproven (fail closed). Pure: the only IO is the caller's read_candidate.
"""
from __future__ import annotations

import re
from collections.abc import Callable, Iterable
from dataclasses import dataclass, field

from harness.sql_comments import strip_sql_comments

ROUNDTRIP_TEST = "ibl5/tests/DatabaseIntegration/MigrationDownSqlIntegrationTest.php"
ROUNDTRIP_MARKERS = ("testEveryPairedMigrationRoundTrips", "/migrations/*.down.sql", "restores:")
DOWN_SUFFIX = ".down.sql"
_UP_RE = re.compile(r"^ibl5/migrations/[^/]+\.sql$")
_RESTORES_RE = re.compile(r"^--\s*restores:\s*(.+)$", re.IGNORECASE | re.MULTILINE)
_N = r"`?(\w+)`?"
_ID = re.IGNORECASE | re.DOTALL
_DOWN_DDL_RE = re.compile(r"\b(DROP|ALTER|RENAME|CREATE|TRUNCATE|DELIMITER)\b", re.IGNORECASE)
_DELIMITER_RE = re.compile(r"^\s*DELIMITER\b", re.IGNORECASE | re.MULTILINE)
_NEUTRAL_RE = re.compile(r"^(SET|START\s+TRANSACTION|BEGIN|COMMIT)\b", re.IGNORECASE)
# MariaDB `SET STATEMENT var=val FOR <stmt>` runs <stmt>, so it is never neutral (fail closed).
_SET_FOR_RE = re.compile(r"^SET\s+STATEMENT\b", re.IGNORECASE)
_SCHEMA_DROP_RE = re.compile(r"^(DROP|RENAME)\b|^ALTER\s+TABLE\b.*\b(DROP|RENAME)\b", _ID)
_OUTFILE_RE = re.compile(r"\bINTO\s+(OUTFILE|DUMPFILE)\b|\bLOAD\s+DATA\b", re.IGNORECASE)
_CTAS_RE = re.compile(rf"^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?{_N}\s+(?:AS\s+)?SELECT\b.*?\bFROM\s+{_N}", _ID)
_CREATE_RE = re.compile(rf"^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?{_N}", re.IGNORECASE)
_INSERT_RE = re.compile(rf"^INSERT\s+(?:IGNORE\s+)?INTO\s+{_N}", re.IGNORECASE)
_INSERT_FROM_RE = re.compile(rf"^INSERT\s+(?:IGNORE\s+)?INTO\s+{_N}.*?\bSELECT\b.*?\bFROM\s+{_N}", _ID)
_ODKU_RE = re.compile(r"\bON\s+DUPLICATE\s+KEY\s+UPDATE\b", re.IGNORECASE)
_REWRITE_RE = re.compile(rf"^(?:TRUNCATE\s+(?:TABLE\s+)?|DELETE\s+FROM\s+|REPLACE\s+(?:INTO\s+)?){_N}", re.IGNORECASE)
# A multi-table DELETE (`DELETE FROM a, b USING ...` or `DELETE a, b FROM ...`) empties tables
# _REWRITE_RE never sees. A `,` or USING before any WHERE makes it unparseable (fail closed).
_MULTI_DELETE_RE = re.compile(r"^DELETE\s+(?:FROM\s+)?(?:(?!\bWHERE\b).)*?(?:,|\bUSING\b)", _ID)
_UPDATE_RE = re.compile(rf"^UPDATE\s+{_N}\s+SET\b", re.IGNORECASE)
_IF_NOT_EXISTS_RE = re.compile(r"^CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b", re.IGNORECASE)
# Tables no site code path writes after merge: no page, script, cron job or JSB import. A
# revert that reloads a migration-time backup into any other table deletes or overwrites every
# row written since merge, so a migration that writes or restores one is never proven, whatever
# its down file says. Empty on purpose (fail closed): a scan can only say it found no write,
# and it misses dynamic table names and paths outside ibl5/classes. Only the owner adds a table.
# This module is an arming path, so an addition holds its PR for the owner.
SITE_STATIC_TABLES: frozenset[str] = frozenset()
_FULL_COPY_RE = re.compile(
    rf"^(?:CREATE\s+TABLE\s+{_N}\s+(?:AS\s+)?|INSERT\s+(?:IGNORE\s+)?INTO\s+{_N}\s+)SELECT\s+\*\s+FROM\s+{_N}\s*$",
    re.IGNORECASE)


@dataclass(frozen=True)
class RevertProof:
    proven: bool
    reason: str


@dataclass
class _State:
    created: set[str] = field(default_factory=set)
    backups: dict[str, str] = field(default_factory=dict)   # source table -> its backup table
    written: set[str] = field(default_factory=set)
    rewritten: set[str] = field(default_factory=set)


def _proof(reason: str) -> RevertProof:
    return RevertProof(reason == "proven", reason)


def new_file_text(diff_text: str, path: str) -> str | None:
    """The text of a file the diff adds, rebuilt from its `+` lines. None unless it is a new file."""
    header = f"diff --git a/{path} b/{path}\n"
    start = diff_text.find(header)
    if start < 0:
        return None
    end = diff_text.find("\ndiff --git ", start + len(header))
    section = diff_text[start:] if end < 0 else diff_text[start:end + 1]
    head, sep, body = section.partition("\n@@")
    if not sep or "\nnew file mode " not in head:
        return None
    return "\n".join(line[1:] for line in body.split("\n")[1:] if line.startswith("+"))


def _statements(sql: str) -> list[str] | None:
    """Split SQL on `;` outside quotes. None for an unterminated quote or a DELIMITER block."""
    if _DELIMITER_RE.search(sql):
        return None
    out: list[str] = []
    cur: list[str] = []
    quote: str | None = None
    escaped = False
    for ch in sql:
        if quote is None and ch == ";":
            out.append("".join(cur).strip())
            cur = []
            continue
        cur.append(ch)
        if escaped:
            escaped = False
        elif quote is not None and ch == "\\":
            escaped = True
        elif quote is not None and ch == quote:
            quote = None
        elif quote is None and ch in "'\"`":
            quote = ch
    if quote is not None:
        return None
    out.append("".join(cur).strip())
    return [s for s in out if s]


def _apply(stmt: str, st: _State) -> str | None:
    """Fold one up statement into the state. Returns a failure reason, or None when it parses."""
    if _SET_FOR_RE.match(stmt):
        return "unparseable:SET"
    if _NEUTRAL_RE.match(stmt):
        return None
    if _SCHEMA_DROP_RE.match(stmt):
        return "schema-drop"
    if _OUTFILE_RE.search(stmt):
        return "external-side-effect"
    # A table created IF NOT EXISTS may already hold stale prod rows, so it is never a backup.
    # A backup copy counts only as an unfiltered `SELECT *` of its source (_FULL_COPY_RE).
    fresh = not _IF_NOT_EXISTS_RE.match(stmt)
    m = _CTAS_RE.match(stmt)
    if m:
        if fresh:
            st.created.add(m.group(1).lower())
            if _FULL_COPY_RE.match(stmt):
                st.backups.setdefault(m.group(2).lower(), m.group(1).lower())
        return None
    m = _CREATE_RE.match(stmt)
    if m:
        if fresh:
            st.created.add(m.group(1).lower())
        return None
    if _MULTI_DELETE_RE.match(stmt):
        return "unparseable:DELETE"
    ins = _INSERT_RE.match(stmt)
    rw = _REWRITE_RE.match(stmt) or _UPDATE_RE.match(stmt) or (ins if ins and _ODKU_RE.search(stmt) else None)
    if rw:
        table = rw.group(1).lower()
        if table not in st.backups:
            return f"rewrite-no-backup:{table}"
        st.rewritten.add(table)
        st.written.add(table)
        return None
    m = _INSERT_FROM_RE.match(stmt)
    if (m and m.group(1).lower() in st.created and m.group(1).lower() != m.group(2).lower()
            and _FULL_COPY_RE.match(stmt)):
        st.backups.setdefault(m.group(2).lower(), m.group(1).lower())
        return None
    if ins:
        st.written.add(ins.group(1).lower())
        return None
    return "unparseable:" + stmt.split(None, 1)[0].upper()


def _restores_from(down_sql: str, table: str, backup: str) -> bool:
    t, b = re.escape(table), re.escape(backup)
    # The restore must bring back the whole backup, every column and every row: an unfiltered
    # `SELECT *` (no column list, no WHERE or LIMIT after the backup name), copied in after the table
    # is emptied by an unfiltered `DELETE FROM <table>` or `TRUNCATE`. A bare REPLACE never counts:
    # it overwrites rows whose keys are in the backup but leaves rows the up file added under new keys.
    # INSERT IGNORE never counts: it skips rows whose key still exists, so an UPDATE stays applied.
    copy = rf"SELECT\s+\*\s+FROM\s+`?{b}`?(?!\w)\s*(?:;|\Z)"
    empty = rf"(?:\bDELETE\s+FROM\s+`?{t}`?|\bTRUNCATE\s+(?:TABLE\s+)?`?{t}`?)\s*;"
    empty_then_copy = (rf"{empty}(?:[^;]*;)*?\s*"
                       rf"(?:INSERT|REPLACE)\s+(?:INTO\s+)?`?{t}`?\s+{copy}")
    return re.search(empty_then_copy, down_sql, re.IGNORECASE | re.DOTALL) is not None


def _down_targets(down_sql: str) -> set[str] | None:
    """Every table a DML-only down file writes. None when a statement is not one plain
    single-table INSERT, REPLACE, DELETE, UPDATE or TRUNCATE (fail closed)."""
    stmts = _statements(down_sql)
    if stmts is None:
        return None
    targets: set[str] = set()
    for down_stmt in stmts:
        if _SET_FOR_RE.match(down_stmt) or _MULTI_DELETE_RE.match(down_stmt):
            return None
        if _NEUTRAL_RE.match(down_stmt):
            continue
        m = _REWRITE_RE.match(down_stmt) or _UPDATE_RE.match(down_stmt) or _INSERT_RE.match(down_stmt)
        if m is None:
            return None
        targets.add(m.group(1).lower())
    return targets


def prove_revertible(up_path: str, diff_text: str,
                     read_candidate: Callable[[str], str | None]) -> RevertProof:
    """Prove one up migration reverts automatically. The reason names the first failed condition."""
    if not _UP_RE.match(up_path) or up_path.endswith(DOWN_SUFFIX):
        return _proof("not-an-up-file")
    up_text = new_file_text(diff_text, up_path)
    if up_text is None:
        return _proof("up-not-added")
    down_path = up_path[:-len(".sql")] + DOWN_SUFFIX
    down_text = new_file_text(diff_text, down_path)
    if down_text is None:
        down_text = read_candidate(down_path)
    if down_text is None:
        return _proof("down-file-missing")
    restores_line = _RESTORES_RE.search(down_text)
    if restores_line is None:
        return _proof("restores-line-missing")
    restores = {t.strip().strip("`").lower() for t in restores_line.group(1).split(",") if t.strip()}
    down_sql = strip_sql_comments(down_text)
    if _DOWN_DDL_RE.search(down_sql):
        return _proof("down-not-dml")
    stmts = _statements(strip_sql_comments(up_text))
    if stmts is None:
        return _proof("unparseable")
    st = _State()
    for stmt in stmts:
        reason = _apply(stmt, st)
        if reason is not None:
            return _proof(reason)
    missing = sorted(st.written - restores)
    if missing:
        return _proof("restores-missing:" + ",".join(missing))
    for table in sorted(st.rewritten):
        if not _restores_from(down_sql, table, st.backups[table]):
            return _proof(f"down-no-restore:{table}")
    down_targets = _down_targets(down_sql)
    if down_targets is None:
        return _proof("down-unparseable")
    site_written = sorted((st.written | restores | down_targets) - SITE_STATIC_TABLES)
    if site_written:
        return _proof("site-writes:" + ",".join(site_written))
    test_text = read_candidate(ROUNDTRIP_TEST)
    if test_text is None or not all(k in test_text for k in ROUNDTRIP_MARKERS):
        return _proof("roundtrip-test-missing")
    return _proof("proven")


def unproven_migrations(migrations: Iterable[str], diff_text: str,
                        read_candidate: Callable[[str], str | None]) -> list[str]:
    """[] when every up migration in the diff is proven and every down file pairs with one of them.
    Otherwise every path, which is what trigger 1 flagged before this module existed."""
    paths = list(migrations)
    ups = [p for p in paths if not p.endswith(DOWN_SUFFIX)]
    downs = [p for p in paths if p.endswith(DOWN_SUFFIX)]
    if not ups or any(p[:-len(DOWN_SUFFIX)] + ".sql" not in ups for p in downs):
        return paths
    if all(prove_revertible(p, diff_text, read_candidate).proven for p in ups):
        return []
    return paths
