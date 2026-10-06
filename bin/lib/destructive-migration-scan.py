#!/usr/bin/env python3
"""Statement-level engine behind bin/check-destructive-migrations.

The bash entry point does git plumbing only (post-image text, added line
ranges) and calls this engine once per migration file. The engine splits the
text into SQL statements (strings and comments understood), extracts SQL from
PHP migrations, runs the trigger rules, resolves old column types and table
existence, and applies the bypass-marker policy.

Subcommands:
  scan --file <path> (--added-ranges a-b[,c-d] | --all-lines)
       [--marker-policy strict|legacy] [--pr-body-file <path>]
       [--schema <path>] [--migrations-dir <path>]
                       statement source on stdin; exit 0 clean, 1 triggers,
                       2 bad arguments or internal error
  split --file <path>  debug aid: print <start>-<end><TAB><masked> per statement
  --self-test          run the inline fixture table

Python 3.8 syntax, stdlib only.
"""

import argparse
import os
import re
import sys

BYPASS_MIN_LENGTH = 20

KNOWN_TRIGGERS = [
    "drop-column",
    "drop-table",
    "truncate",
    "rename-column",
    "add-not-null-no-default",
    "tighten-not-null",
    "drop-index",
    "rename-table",
    "delete-no-where",
    "update-no-where",
    "narrow-type",
    "drop-recreate-existing",
]

NAME = r"([a-z0-9_$]+(?:\.[a-z0-9_$]+)?)"

MARKER_RE = re.compile(
    r"^\s*destructive-migration(?:\[([^\]]*)\])?\s*:\s*(.*)$", re.IGNORECASE
)
PR_MARKER_RE = re.compile(
    r"<!--\s*destructive-migration(?:\[([^\]]*)\])?\s*:\s*(.+?)\s*-->", re.IGNORECASE
)


def last_part(name):
    return name.split(".")[-1]


# ---------------------------------------------------------------------------
# SQL splitter
# ---------------------------------------------------------------------------


class Statement(object):
    def __init__(self, start_line, end_line, masked, clean):
        self.start_line = start_line
        self.end_line = end_line
        self.masked = masked
        self.clean = clean
        self.lower = masked.lower()
        self.clean_lower = clean.lower()


def _collapse(text):
    return re.sub(r"\s+", " ", text).strip()


class Splitter(object):
    """Character state machine: NORMAL, SQUOTE, DQUOTE, BACKTICK,
    LINE_COMMENT, BLOCK_COMMENT. `;` in NORMAL ends a statement."""

    def __init__(self, text, line_offset=0):
        self.text = text
        self.line_offset = line_offset

    def split(self):
        text = self.text
        n = len(text)
        i = 0
        line = 1 + self.line_offset
        statements = []
        comments = []
        masked = []
        clean = []
        start = None
        last_sig = None

        def finish(end_line):
            m = _collapse("".join(masked))
            c = _collapse("".join(clean))
            if m and start is not None:
                statements.append(Statement(start, end_line, m, c))

        while i < n:
            ch = text[i]
            nxt = text[i + 1] if i + 1 < n else ""
            if ch == "-" and nxt == "-" and (i + 2 >= n or text[i + 2] in " \t\r\n"):
                j = text.find("\n", i)
                if j == -1:
                    j = n
                comments.append((line, text[i + 2:j]))
                masked.append(" ")
                clean.append(" ")
                i = j
                continue
            if ch == "#":
                j = text.find("\n", i)
                if j == -1:
                    j = n
                comments.append((line, text[i + 1:j]))
                masked.append(" ")
                clean.append(" ")
                i = j
                continue
            if ch == "/" and nxt == "*":
                j = text.find("*/", i + 2)
                if j == -1:
                    j = n
                body = text[i + 2:j]
                for k, part in enumerate(body.split("\n")):
                    comments.append((line + k, part))
                line += body.count("\n")
                masked.append(" ")
                clean.append(" ")
                i = j + 2
                continue
            if ch in ("'", '"'):
                quote = ch
                if start is None:
                    start = line
                j = i + 1
                buf = [quote]
                while j < n:
                    c2 = text[j]
                    if c2 == "\\" and j + 1 < n:
                        buf.append(text[j:j + 2])
                        if text[j + 1] == "\n":
                            line += 1
                        j += 2
                        continue
                    if c2 == quote:
                        if j + 1 < n and text[j + 1] == quote:
                            buf.append(quote + quote)
                            j += 2
                            continue
                        break
                    if c2 == "\n":
                        line += 1
                    buf.append(c2)
                    j += 1
                buf.append(quote)
                masked.append("''")
                clean.append("".join(buf))
                last_sig = line
                i = j + 1
                continue
            if ch == "`":
                if start is None:
                    start = line
                j = text.find("`", i + 1)
                if j == -1:
                    j = n
                ident = text[i + 1:j]
                line += ident.count("\n")
                masked.append(ident)
                clean.append(ident)
                last_sig = line
                i = j + 1
                continue
            if ch == ";":
                finish(line)
                masked = []
                clean = []
                start = None
                last_sig = None
                i += 1
                continue
            if ch == "\n":
                line += 1
            elif not ch.isspace():
                if start is None:
                    start = line
                last_sig = line
            masked.append(ch)
            clean.append(ch)
            i += 1
        if last_sig is not None:
            finish(last_sig)
        return statements, comments


# ---------------------------------------------------------------------------
# PHP extraction
# ---------------------------------------------------------------------------


class Fragment(object):
    def __init__(self, text, first_line, last_line):
        self.text = text
        self.first_line = first_line
        self.last_line = last_line


_DQ_INTERP = re.compile(
    r"\{\$[^}]*\}|\$\{[^}]*\}|\$[A-Za-z_][A-Za-z0-9_]*(?:->[A-Za-z_][A-Za-z0-9_]*|\[[^\]]*\])*"
)


def _dq_unescape(body):
    out = []
    i = 0
    while i < len(body):
        c = body[i]
        if c == "\\" and i + 1 < len(body):
            e = body[i + 1]
            if e in "ntr":
                out.append(" ")
            elif e in "\\\"$":
                out.append(e)
            else:
                out.append(c + e)
            i += 2
            continue
        out.append(c)
        i += 1
    return "".join(out)


class PhpExtractor(object):
    """Collects SQL-bearing string fragments and comments from PHP source.

    Fragment text keeps the source's newlines, so fragment line k maps to
    PHP line first_line + k - 1."""

    HEREDOC_RE = re.compile(r"<<<[ \t]*(['\"]?)([A-Za-z_][A-Za-z0-9_]*)\1[ \t]*\r?\n")

    def __init__(self, text):
        self.text = text
        self.n = len(text)
        self.fragments = []
        self.comments = []

    def line_at(self, pos):
        return self.text.count("\n", 0, pos) + 1

    def read_literal(self, i):
        """Return (text, end_pos) for the literal starting at i, or None."""
        t = self.text
        ch = t[i]
        if ch == "'":
            j = i + 1
            buf = []
            while j < self.n:
                c = t[j]
                if c == "\\" and j + 1 < self.n and t[j + 1] in "\\'":
                    buf.append(t[j + 1])
                    j += 2
                    continue
                if c == "'":
                    break
                buf.append(c)
                j += 1
            return "".join(buf), j + 1
        if ch == '"':
            j = i + 1
            while j < self.n:
                c = t[j]
                if c == "\\":
                    j += 2
                    continue
                if c == '"':
                    break
                j += 1
            body = t[i + 1:j]
            body = _DQ_INTERP.sub("__php_expr__", body)
            return _dq_unescape(body), j + 1
        m = self.HEREDOC_RE.match(t, i)
        if m:
            nowdoc = m.group(1) == "'"
            ident = m.group(2)
            body_start = m.end()
            term = re.compile(r"^[ \t]*" + re.escape(ident) + r"(?![A-Za-z0-9_])", re.M)
            tm = term.search(t, body_start)
            if tm is None:
                body = t[body_start:]
                end = self.n
            else:
                body = t[body_start:tm.start()]
                end = tm.end()
            if not nowdoc:
                body = _DQ_INTERP.sub("__php_expr__", body)
            # Leading newline keeps line alignment: fragment line 1 is the <<< line.
            return "\n" + body, end
        return None

    def skip_ws_comments(self, i, record=True):
        t = self.text
        while i < self.n:
            c = t[i]
            if c.isspace():
                i += 1
                continue
            if t.startswith("//", i) or (c == "#" and not t.startswith("#[", i)):
                j = t.find("\n", i)
                if j == -1:
                    j = self.n
                start = i + (2 if c == "/" else 1)
                if record:
                    self.comments.append((self.line_at(i), t[start:j]))
                i = j
                continue
            if t.startswith("/*", i):
                j = t.find("*/", i + 2)
                if j == -1:
                    j = self.n
                if record:
                    base = self.line_at(i)
                    for k, part in enumerate(t[i + 2:j].split("\n")):
                        self.comments.append((base + k, part))
                i = j + 2
                continue
            break
        return i

    def is_literal_start(self, i):
        t = self.text
        return t[i] in "'\"" or t.startswith("<<<", i)

    def skip_expression(self, i):
        """Consume a concatenated expression. Stops at a literal start, or at
        `;`, `,`, `)` at depth 0. Returns (end_pos, newlines_consumed)."""
        t = self.text
        depth = 0
        start = i
        while i < self.n:
            c = t[i]
            if depth == 0 and self.is_literal_start(i):
                break
            if depth == 0 and c in ";,)":
                break
            if c in "([{":
                depth += 1
            elif c in ")]}":
                depth -= 1
            elif c in "'\"":
                lit = self.read_literal(i)
                i = lit[1]
                continue
            i += 1
        return i, t.count("\n", start, i)

    def extract(self):
        t = self.text
        i = 0
        while i < self.n:
            j = self.skip_ws_comments(i)
            if j != i:
                i = j
                continue
            if i >= self.n:
                break
            if self.is_literal_start(i):
                lit = self.read_literal(i)
                if lit is None:
                    i += 3
                    continue
                first_line = self.line_at(i)
                parts = [lit[0]]
                i = lit[1]
                while True:
                    k = self.skip_ws_comments(i)
                    if k < self.n and t[k] == "." and not t.startswith(".=", k):
                        k = self.skip_ws_comments(k + 1)
                        if k < self.n and self.is_literal_start(k):
                            lit = self.read_literal(k)
                            if lit is None:
                                break
                            gap = t.count("\n", i, k)
                            parts.append("\n" * gap + lit[0])
                            i = lit[1]
                            continue
                        end, nl = self.skip_expression(k)
                        gap = t.count("\n", i, k)
                        parts.append("\n" * gap + " __php_expr__ " + "\n" * nl)
                        i = end
                        if i < self.n and self.is_literal_start(i):
                            lit = self.read_literal(i)
                            if lit is None:
                                break
                            parts.append(lit[0])
                            i = lit[1]
                            continue
                        break
                    break
                last_line = self.line_at(max(i - 1, 0))
                self.fragments.append(Fragment("".join(parts), first_line, last_line))
                continue
            i += 1
        return self.fragments, self.comments


PREPARED_DDL_HEADS = ("alter", "create", "drop", "rename", "truncate", "delete", "update")
PREPARE_VAR_RE = re.compile(r"^prepare [a-z0-9_$]+ from @([a-z0-9_$]+)$")
PREPARE_LIT_RE = re.compile(r"^prepare [a-z0-9_$]+ from ''$")


def _string_literals(clean):
    """Yield the unescaped body of every '...' and "..." literal in `clean`.
    Doubled quotes ('' inside '...') and backslash escapes are honored;
    backtick identifiers are skipped. Never raises on an unterminated
    literal: the tail is dropped."""
    n = len(clean)
    i = 0
    while i < n:
        ch = clean[i]
        if ch == "`":
            j = clean.find("`", i + 1)
            if j == -1:
                return
            i = j + 1
            continue
        if ch not in ("'", '"'):
            i += 1
            continue
        quote = ch
        buf = []
        j = i + 1
        closed = False
        while j < n:
            c2 = clean[j]
            if c2 == "\\" and j + 1 < n:
                buf.append(clean[j + 1])
                j += 2
                continue
            if c2 == quote:
                if j + 1 < n and clean[j + 1] == quote:
                    buf.append(quote)
                    j += 2
                    continue
                closed = True
                break
            buf.append(c2)
            j += 1
        if not closed:
            return
        yield "".join(buf)
        i = j + 1


def _ddl_from_literals(clean):
    """Return the literal bodies whose first word (lowercased, after
    leading whitespace and comments) is in PREPARED_DDL_HEADS. 'SELECT 1'
    and any other head return nothing."""
    out = []
    for body in _string_literals(clean):
        virt, _ = Splitter(body).split()
        words = virt[0].lower.split(None, 1) if virt else []
        if words and words[0] in PREPARED_DDL_HEADS:
            out.append(body)
    return out


def _resolve_set(statements, k, var):
    """Walk statements[k-1] .. statements[0]. Return the first statement
    whose `.lower` assigns @var: `set @var =` / `set @var :=` returns
    that statement; `... into @var` (SELECT ... INTO) returns None. No
    assignment found returns None."""
    set_re = re.compile(r"^set @" + re.escape(var) + r" ?:?=")
    into_re = re.compile(r"\binto @" + re.escape(var) + r"(?![a-z0-9_$])")
    for idx in range(k - 1, -1, -1):
        st = statements[idx]
        if set_re.match(st.lower):
            return st
        if into_re.search(st.lower):
            return None
    return None


def expand_prepared(statements):
    """Return a new list: every statement in order, and after each
    `PREPARE n FROM @v` / `PREPARE n FROM '<lit>'` the virtual statements
    re-split from its DDL literals."""
    out = []
    for k, st in enumerate(statements):
        out.append(st)
        src = None
        m = PREPARE_VAR_RE.match(st.lower)
        if m:
            src = _resolve_set(statements, k, m.group(1))
            if src is not None and "concat(" in src.lower:
                src = None
        elif PREPARE_LIT_RE.match(st.lower):
            src = st
        if src is None:
            continue
        for body in _ddl_from_literals(src.clean):
            virt, _ = Splitter(body).split()
            for v in virt:
                v.start_line = src.start_line
                v.end_line = src.end_line
                out.append(v)
    return out


def extract_sql(path, text):
    """Return (units, comments). Each unit is (Statement list, select_range,
    report_line_or_None). For .sql there is one unit with per-statement
    ranges; for .php one unit per fragment, selected as a whole.
    PREPARE n FROM @v / FROM '<lit>' is followed by virtual statements
    re-split from the DDL literal of the nearest preceding SET @v,
    reported at the SET's lines."""
    if path.endswith(".php"):
        fragments, comments = PhpExtractor(text).extract()
        units = []
        for frag in fragments:
            stmts, sql_comments = Splitter(frag.text, frag.first_line - 1).split()
            comments.extend(sql_comments)
            for st in stmts:
                st.start_line = frag.first_line
                st.end_line = frag.last_line
            units.extend(stmts)
        return expand_prepared(units), comments
    statements, comments = Splitter(text).split()
    return expand_prepared(statements), comments


# ---------------------------------------------------------------------------
# Type narrowing
# ---------------------------------------------------------------------------

INT_RANK = {"tinyint": 1, "smallint": 2, "mediumint": 3, "int": 4, "bigint": 5}
TEXT_RANK = {"tinytext": 1, "text": 2, "mediumtext": 3, "longtext": 4}
BLOB_RANK = {"tinyblob": 1, "blob": 2, "mediumblob": 3, "longblob": 4}
CHAR_FAMILY = {"char": "c", "varchar": "c", "binary": "b", "varbinary": "b"}


def take_type(s):
    """Read `<word>[(args)][ unsigned]` from the front of s."""
    s = s.strip()
    m = re.match(r"([a-z]+)", s)
    if not m:
        return None
    out = m.group(1)
    i = m.end()
    rest = s[i:]
    stripped = rest.lstrip()
    if stripped.startswith("("):
        j = len(rest) - len(stripped)
        depth = 0
        quote = None
        k = j
        while k < len(rest):
            c = rest[k]
            if quote:
                if c == "\\":
                    k += 2
                    continue
                if c == quote:
                    quote = None
            elif c in "'\"":
                quote = c
            elif c == "(":
                depth += 1
            elif c == ")":
                depth -= 1
                if depth == 0:
                    break
            k += 1
        out += rest[j:k + 1]
        rest = rest[k + 1:]
    m2 = re.match(r"\s+(unsigned|signed)\b", rest)
    if m2 and m2.group(1) == "unsigned":
        out += " unsigned"
    return out


def parse_type(t):
    t = re.sub(r"\s+", " ", t.strip().lower())
    unsigned = t.endswith(" unsigned")
    if unsigned:
        t = t[: -len(" unsigned")].strip()
    m = re.match(r"([a-z]+)\s*(?:\((.*)\))?$", t)
    if not m:
        return t, None, unsigned
    base = m.group(1)
    args = m.group(2)
    if base == "integer":
        base = "int"
    if base == "numeric":
        base = "decimal"
    if base in ("bool", "boolean"):
        base = "tinyint"
    if base == "real":
        base = "double"
    if base in INT_RANK:
        args = None
    return base, args, unsigned


def _values(args):
    return set(v.strip() for v in re.findall(r"'((?:[^'\\]|\\.|'')*)'", args or ""))


def _nums(args, defaults):
    if not args:
        return defaults
    parts = [p.strip() for p in args.split(",")]
    out = []
    for k, d in enumerate(defaults):
        if k < len(parts) and parts[k].isdigit():
            out.append(int(parts[k]))
        else:
            out.append(d)
    return out


def is_narrower(old, new):
    ob, oa, ou = parse_type(old)
    nb, na, nu = parse_type(new)
    if (ob, oa, ou) == (nb, na, nu):
        return False
    if ob in INT_RANK and nb in INT_RANK:
        if INT_RANK[nb] < INT_RANK[ob]:
            return True
        if nu and not ou:
            return True
        if ou and not nu and INT_RANK[nb] == INT_RANK[ob]:
            return True
        return False
    if ob in CHAR_FAMILY and nb in CHAR_FAMILY:
        if CHAR_FAMILY[ob] != CHAR_FAMILY[nb]:
            return True
        return _nums(na, [1])[0] < _nums(oa, [1])[0]
    if ob in CHAR_FAMILY and (nb in TEXT_RANK or nb in BLOB_RANK):
        return not (
            (CHAR_FAMILY[ob] == "c" and nb in TEXT_RANK)
            or (CHAR_FAMILY[ob] == "b" and nb in BLOB_RANK)
        )
    if ob in TEXT_RANK and nb in TEXT_RANK:
        return TEXT_RANK[nb] < TEXT_RANK[ob]
    if ob in BLOB_RANK and nb in BLOB_RANK:
        return BLOB_RANK[nb] < BLOB_RANK[ob]
    if ob == "decimal" and nb == "decimal":
        op, os_ = _nums(oa, [10, 0])
        np_, ns = _nums(na, [10, 0])
        return np_ < op or ns < os_ or (np_ - ns) < (op - os_) or (nu and not ou)
    if ob in ("float", "double") and nb in ("float", "double"):
        return ob == "double" and nb == "float"
    if ob in ("enum", "set") and nb == ob:
        return not _values(oa).issubset(_values(na))
    if ob == "date" and nb in ("datetime", "timestamp"):
        return False
    if ob == nb:
        o = _nums(oa, [0])[0]
        n = _nums(na, [0])[0]
        if oa is not None and na is not None and oa.strip().isdigit() and na.strip().isdigit():
            return n < o
        return oa != na
    return True


# ---------------------------------------------------------------------------
# Schema and existence lookups
# ---------------------------------------------------------------------------

_SKIP_ENTRY = {"primary", "key", "unique", "index", "constraint", "fulltext", "spatial", "check", "foreign", "period"}


def split_depth0(s):
    """Split on commas at parenthesis depth 0, outside quotes."""
    parts = []
    depth = 0
    quote = None
    buf = []
    i = 0
    while i < len(s):
        c = s[i]
        if quote:
            buf.append(c)
            if c == "\\" and i + 1 < len(s):
                buf.append(s[i + 1])
                i += 2
                continue
            if c == quote:
                quote = None
        elif c in "'\"":
            quote = c
            buf.append(c)
        elif c == "(":
            depth += 1
            buf.append(c)
        elif c == ")":
            depth -= 1
            buf.append(c)
        elif c == "," and depth == 0:
            parts.append("".join(buf).strip())
            buf = []
        else:
            buf.append(c)
        i += 1
    if "".join(buf).strip():
        parts.append("".join(buf).strip())
    return parts


CREATE_TABLE_RE = re.compile(r"^create (?:temporary )?table (?:if not exists )?" + NAME)


def create_table_columns(clean_lower):
    m = CREATE_TABLE_RE.match(clean_lower)
    if not m:
        return None, {}
    table = last_part(m.group(1))
    rest = clean_lower[m.end():].strip()
    cols = {}
    if not rest.startswith("("):
        return table, cols
    depth = 0
    quote = None
    end = len(rest)
    for k, c in enumerate(rest):
        if quote:
            if c == quote:
                quote = None
            continue
        if c in "'\"":
            quote = c
        elif c == "(":
            depth += 1
        elif c == ")":
            depth -= 1
            if depth == 0:
                end = k
                break
    for entry in split_depth0(rest[1:end]):
        em = re.match(r"([a-z0-9_$]+)\s+(.*)$", entry)
        if not em or em.group(1) in _SKIP_ENTRY:
            continue
        typ = take_type(em.group(2))
        if typ:
            cols[em.group(1)] = typ
    return table, cols


class Lookups(object):
    def __init__(self, schema_path, migrations_dir, scanned_file, schema_text=None):
        self.schema_path = schema_path
        self.migrations_dir = migrations_dir
        self.scanned_file = scanned_file
        self.schema_text = schema_text
        self._schema = None
        self._existing = None

    def _read(self, path):
        try:
            with open(path, encoding="utf-8", errors="replace") as fh:
                return fh.read()
        except (IOError, OSError):
            return None

    def has_schema(self):
        return self.schema()[2]

    def schema(self):
        """Return (columns map {(table, col): type}, table set, dump_present)."""
        if self._schema is None:
            cols = {}
            tables = set()
            text = self.schema_text
            if text is None and self.schema_path:
                text = self._read(self.schema_path)
            if text:
                stmts, _ = Splitter(text).split()
                for st in stmts:
                    table, tcols = create_table_columns(st.clean_lower)
                    if table:
                        tables.add(table)
                        for c, t in tcols.items():
                            cols[(table, c)] = t
            self._schema = (cols, tables, text is not None)
        return self._schema

    def existing_tables(self):
        if self._existing is None:
            tables = set(self.schema()[1])
            if self.migrations_dir and os.path.isdir(self.migrations_dir):
                scanned_base = os.path.basename(self.scanned_file)
                sm = re.match(r"^(\d+)", scanned_base)
                for name in sorted(os.listdir(self.migrations_dir)):
                    if not (name.endswith(".sql") or name.endswith(".php")):
                        continue
                    if name == scanned_base:
                        continue
                    pm = re.match(r"^(\d+)", name)
                    if not pm:
                        continue
                    if sm and int(pm.group(1)) >= int(sm.group(1)):
                        continue
                    text = self._read(os.path.join(self.migrations_dir, name))
                    if not text:
                        continue
                    stmts, _ = extract_sql(name, text)
                    for st in stmts:
                        m = CREATE_TABLE_RE.match(st.lower)
                        if m:
                            tables.add(last_part(m.group(1)))
            self._existing = tables
        return self._existing


# ---------------------------------------------------------------------------
# Rules
# ---------------------------------------------------------------------------

ALTER_RE = re.compile(r"^alter (?:online |ignore )*table " + NAME + r"\s*(.*)$")
DROP_EXCLUDED = {"primary", "foreign", "index", "key", "constraint", "check", "partition", "tablespace", "system"}
ADD_EXCLUDED = {"index", "key", "unique", "fulltext", "spatial", "primary", "foreign", "constraint", "check", "partition"}
READD_TMPL = (
    r"(add ((unique|fulltext|spatial) )?(index|key)|create ((unique|fulltext|spatial) )?index) "
    r"(if not exists )?{name}(\W|$)"
)


def alter_clauses(st):
    """Return (table, [(masked_clause, clean_clause)]) for ALTER TABLE."""
    m = ALTER_RE.match(st.lower)
    if not m:
        return None, []
    table = last_part(m.group(1))
    cm = ALTER_RE.match(st.clean_lower)
    masked_parts = split_depth0(m.group(2))
    clean_parts = split_depth0(cm.group(2)) if cm else masked_parts
    if len(clean_parts) != len(masked_parts):
        clean_parts = masked_parts
    return table, list(zip(masked_parts, clean_parts))


def _not_null_no_default(clause):
    return re.search(r"\bnot null\b", clause) and not re.search(r"\bdefault\b", clause)


class Context(object):
    def __init__(self, statements, index, selected, lookups):
        self.statements = statements
        self.index = index
        self.selected = selected
        self.lookups = lookups

    @property
    def st(self):
        return self.statements[self.index]


def rule_drop_column(clause, ctx):
    m = re.match(r"^drop (column )?(if exists )?([a-z0-9_$]+)", clause)
    if m and (m.group(1) or m.group(3) not in DROP_EXCLUDED):
        return [("drop-column", "")]
    return []


def rule_rename_column(clause, ctx):
    if re.match(r"^rename column ", clause):
        return [("rename-column", "")]
    return []


def rule_change(clause, ctx):
    m = re.match(r"^change (column )?(if exists )?([a-z0-9_$]+) ([a-z0-9_$]+)", clause)
    if not m:
        return []
    if m.group(3) != m.group(4):
        return [("rename-column", "")]
    if _not_null_no_default(clause):
        return [("tighten-not-null", "")]
    return []


def rule_add_not_null(clause, ctx):
    m = re.match(r"^add (column )?(if not exists )?([a-z0-9_$]+)", clause)
    if not m:
        return []
    if not m.group(1) and m.group(3) in ADD_EXCLUDED:
        return []
    if _not_null_no_default(clause):
        return [("add-not-null-no-default", "")]
    return []


def rule_modify(clause, ctx):
    if re.match(r"^modify (column )?(if exists )?\S", clause) and _not_null_no_default(clause):
        return [("tighten-not-null", "")]
    return []


def _index_readded(name, ctx):
    rx = re.compile(READD_TMPL.format(name=re.escape(name)))
    for k in ctx.selected:
        if rx.search(ctx.statements[k].lower):
            return True
    return False


def rule_drop_index_clause(clause, ctx):
    m = re.match(r"^drop (index|key) (if exists )?([a-z0-9_$]+)", clause)
    if m and not _index_readded(m.group(3), ctx):
        return [("drop-index", "")]
    return []


def rule_rename_table_clause(clause, ctx):
    m = re.match(r"^rename (to |as )?([a-z0-9_$.]+)$", clause)
    if m and (m.group(1) or m.group(2) not in ("column", "index", "key")):
        return [("rename-table", "")]
    return []


def _definitions_before(ctx, table):
    """Column types defined earlier in the same file: {col: type}."""
    cols = {}
    for k in range(ctx.index):
        st = ctx.statements[k]
        t, tcols = create_table_columns(st.clean_lower)
        if t == table:
            cols.update(tcols)
            continue
        at, clauses = alter_clauses(st)
        if at != table:
            continue
        for _, cl in clauses:
            m = re.match(r"^add (column )?(if not exists )?([a-z0-9_$]+) (.*)$", cl)
            if m and (m.group(1) or m.group(3) not in ADD_EXCLUDED):
                typ = take_type(m.group(4))
                if typ:
                    cols[m.group(3)] = typ
                continue
            m = re.match(r"^modify (column )?(if exists )?([a-z0-9_$]+) (.*)$", cl)
            if m:
                typ = take_type(m.group(4))
                if typ:
                    cols[m.group(3)] = typ
                continue
            m = re.match(r"^change (column )?(if exists )?([a-z0-9_$]+) ([a-z0-9_$]+) (.*)$", cl)
            if m:
                typ = take_type(m.group(5))
                if typ:
                    cols.pop(m.group(3), None)
                    cols[m.group(4)] = typ
    return cols


def rule_narrow_type(clean_clause, ctx, table):
    m = re.match(r"^modify (column )?(if exists )?([a-z0-9_$]+) (.*)$", clean_clause)
    if m:
        col, rest = m.group(3), m.group(4)
    else:
        m = re.match(r"^change (column )?(if exists )?([a-z0-9_$]+) ([a-z0-9_$]+) (.*)$", clean_clause)
        if not m:
            return []
        col, rest = m.group(3), m.group(5)
    new = take_type(rest)
    if not new:
        return []
    old = ctx.lookups.schema()[0].get((table, col))
    if old is None:
        old = _definitions_before(ctx, table).get(col)
    if old is None:
        # With a schema dump present, a column it does not know fails closed.
        # Without one (a scratch repo) there is nothing to resolve against,
        # so only same-file definitions can raise narrow-type.
        if not ctx.lookups.has_schema():
            return []
        return [("narrow-type", "(old type unresolved: %s.%s)" % (table, col))]
    if is_narrower(old, new):
        return [("narrow-type", "(old: %s -> new: %s)" % (old, new))]
    return []


ALTER_CLAUSE_RULES = [
    rule_drop_column,
    rule_rename_column,
    rule_change,
    rule_add_not_null,
    rule_modify,
    rule_drop_index_clause,
    rule_rename_table_clause,
]


def rule_drop_table(ctx):
    st = ctx.st
    m = re.match(r"^drop (temporary )?table (if exists )?" + NAME, st.lower)
    if not m:
        return []
    table = last_part(m.group(3))
    if m.group(2):
        rx = re.compile(r"^create (temporary )?table (if not exists )?(\S+\.)?" + re.escape(table) + r"(\W|$)")
        for k in ctx.selected:
            if k != ctx.index and rx.match(ctx.statements[k].lower):
                if table in ctx.lookups.existing_tables():
                    return [("drop-recreate-existing", "(%s already exists)" % table)]
                return []
    return [("drop-table", "")]


def rule_truncate(ctx):
    if re.match(r"^truncate (table )?\S", ctx.st.lower):
        return [("truncate", "")]
    return []


def rule_rename_table(ctx):
    if re.match(r"^rename table ", ctx.st.lower):
        return [("rename-table", "")]
    return []


def rule_drop_index_statement(ctx):
    m = re.match(r"^drop index (if exists )?([a-z0-9_$]+)", ctx.st.lower)
    if m and not _index_readded(m.group(2), ctx):
        return [("drop-index", "")]
    return []


def rule_delete_no_where(ctx):
    low = ctx.st.lower
    if re.match(r"^delete\b", low) and re.search(r"\bfrom\b", low) and not re.search(r"\bwhere\b", low):
        return [("delete-no-where", "")]
    return []


def rule_update_no_where(ctx):
    low = ctx.st.lower
    if re.match(r"^update\b", low) and re.search(r"\bset\b", low) and not re.search(r"\bwhere\b", low):
        return [("update-no-where", "")]
    return []


STATEMENT_RULES = [
    rule_drop_table,
    rule_truncate,
    rule_rename_table,
    rule_drop_index_statement,
    rule_delete_no_where,
    rule_update_no_where,
]


def evaluate(statements, selected, lookups):
    """Return a list of (tag, line, extra, statement_text) hits."""
    hits = []
    seen = set()
    for k in selected:
        ctx = Context(statements, k, selected, lookups)
        st = statements[k]
        found = []
        for rule in STATEMENT_RULES:
            found.extend(rule(ctx))
        table, clauses = alter_clauses(st)
        for masked_clause, clean_clause in clauses:
            for rule in ALTER_CLAUSE_RULES:
                found.extend(rule(masked_clause, ctx))
            found.extend(rule_narrow_type(clean_clause, ctx, table))
        for tag, extra in found:
            key = (tag, st.start_line, extra)
            if key in seen:
                continue
            seen.add(key)
            hits.append((tag, st.start_line, extra, st.lower))
    return hits


# ---------------------------------------------------------------------------
# Markers
# ---------------------------------------------------------------------------


def parse_marker(tags_text, reason):
    """Return (tags_or_None, reason, error_or_None)."""
    reason = reason.strip()
    tags = None
    if tags_text is not None:
        tags = [t.strip().lower() for t in tags_text.split(",") if t.strip()]
        unknown = [t for t in tags if t not in KNOWN_TRIGGERS]
        if unknown:
            return tags, reason, "unknown trigger '%s' in bypass marker (known: %s)" % (
                unknown[0],
                " ".join(KNOWN_TRIGGERS),
            )
        if not tags:
            return tags, reason, "empty trigger list in bypass marker (known: %s)" % " ".join(KNOWN_TRIGGERS)
    return tags, reason, None


def collect_markers(comments, in_scope, pr_body, policy):
    """Return (markers, errors). markers: list of (tags_or_ALL, reason, file_scope)."""
    markers = []
    errors = []
    sources = []
    for line, text in comments:
        if not in_scope(line):
            continue
        m = MARKER_RE.match(text)
        if m:
            sources.append((None, line, m.group(1), m.group(2)))
    if pr_body is not None:
        for m in PR_MARKER_RE.finditer(pr_body):
            line = pr_body.count("\n", 0, m.start()) + 1
            sources.append(("PR-body", line, m.group(1), m.group(2)))
    for where, line, tags_text, reason_text in sources:
        tags, reason, err = parse_marker(tags_text, reason_text)
        if err:
            errors.append((where, line, err))
            continue
        if tags is None and policy == "strict":
            errors.append(
                (
                    where,
                    line,
                    "untagged bypass marker; use -- destructive-migration[<trigger>]: <reason> (known triggers: %s)"
                    % " ".join(KNOWN_TRIGGERS),
                )
            )
            continue
        if len(reason) < BYPASS_MIN_LENGTH:
            errors.append((where, line, "bypass reason shorter than %d chars; marker ignored" % BYPASS_MIN_LENGTH))
            continue
        markers.append((tags, reason))
    return markers, errors


# ---------------------------------------------------------------------------
# scan / split commands
# ---------------------------------------------------------------------------


def parse_ranges(spec):
    ranges = []
    for part in spec.split(","):
        part = part.strip()
        if not part:
            continue
        m = re.match(r"^(\d+)(?:-(\d+))?$", part)
        if not m:
            raise ValueError("bad range: %s" % part)
        a = int(m.group(1))
        b = int(m.group(2) or a)
        ranges.append((a, b))
    return ranges


def run_scan(path, text, ranges, policy, pr_body, lookups):
    """Return (exit_code, output_lines)."""
    statements, comments = extract_sql(path, text)

    def in_ranges(lo, hi):
        if ranges is None:
            return True
        return any(lo <= b and a <= hi for a, b in ranges)

    selected = [k for k, st in enumerate(statements) if in_ranges(st.start_line, st.end_line)]
    hits = evaluate(statements, selected, lookups)
    markers, errors = collect_markers(comments, lambda ln: in_ranges(ln, ln), pr_body, policy)

    out = []
    remaining = []
    suppressed = {}
    for tag, line, extra, text_ in hits:
        reason = None
        for tags, why in markers:
            if tags is None or tag in tags:
                reason = why
                break
        if reason is None:
            remaining.append((tag, line, extra, text_))
        else:
            key = (tag, reason)
            suppressed[key] = suppressed.get(key, 0) + 1
    for (tag, reason), count in sorted(suppressed.items()):
        out.append("  suppressed [%s] x%d in %s by marker: %s" % (tag, count, path, reason))
    for where, line, err in errors:
        out.append("  [marker-error] %s:%d %s" % (where or path, line, err))
    for tag, line, extra, text_ in remaining:
        snippet = text_ if len(text_) <= 200 else text_[:197] + "..."
        msg = "  [%s] %s:%d " % (tag, path, line)
        if extra:
            msg += extra + " "
        out.append(msg + snippet)
    if remaining or errors:
        return 1, out
    if suppressed:
        reasons = []
        for (_, reason) in sorted(suppressed):
            if reason not in reasons:
                reasons.append(reason)
        out.append("PASS (bypass): %s" % "; ".join(reasons))
    return 0, out


def cmd_scan(args):
    if args.all_lines:
        ranges = None
    else:
        try:
            ranges = parse_ranges(args.added_ranges)
        except ValueError as exc:
            sys.stderr.write("destructive-migration-scan: %s\n" % exc)
            return 2
    pr_body = None
    if args.pr_body_file:
        try:
            with open(args.pr_body_file, encoding="utf-8", errors="replace") as fh:
                pr_body = fh.read()
        except (IOError, OSError) as exc:
            sys.stderr.write("destructive-migration-scan: cannot read PR body: %s\n" % exc)
            return 2
    text = sys.stdin.read()
    lookups = Lookups(args.schema, args.migrations_dir, args.file)
    code, out = run_scan(args.file, text, ranges, args.marker_policy, pr_body, lookups)
    for line in out:
        print(line)
    return code


def cmd_split(args):
    statements, _ = extract_sql(args.file, sys.stdin.read())
    for st in statements:
        print("%d-%d\t%s" % (st.start_line, st.end_line, st.masked))
    return 0


# ---------------------------------------------------------------------------
# Self-test
# ---------------------------------------------------------------------------

SELF_TEST_CASES = [
    # (name, file, sql, added_ranges_or_None, expected_tags)
    ("multi drop-column", "x.sql", "ALTER TABLE t\n  DROP COLUMN c;\n", None, ["drop-column"]),
    ("multi drop-table", "x.sql", "DROP TABLE\n IF EXISTS\n t;\n", None, ["drop-table"]),
    ("multi change rename", "x.sql", "ALTER TABLE t\n CHANGE\n old\n new INT;\n", None, ["rename-column"]),
    ("multi modify not null", "x.sql", "ALTER TABLE t\n MODIFY c INT\n NOT NULL;\n", None, ["tighten-not-null"]),
    ("multi add not null", "x.sql", "ALTER TABLE t\n ADD COLUMN c INT\n NOT NULL;\n", None, ["add-not-null-no-default"]),
    ("multi drop index", "x.sql", "ALTER TABLE t\n DROP INDEX i;\n", None, ["drop-index"]),
    ("multi rename to", "x.sql", "ALTER TABLE a\n RENAME TO b;\n", None, ["rename-table"]),
    ("multi truncate", "x.sql", "TRUNCATE\n TABLE t;\n", None, ["truncate"]),
    # Phase 1 fixtures replayed
    ("p1 drop-column", "x.sql", "ALTER TABLE ibl_x DROP COLUMN legacy_flag,\n  ADD COLUMN new_flag TINYINT NOT NULL DEFAULT 0;\n", None, ["drop-column"]),
    ("p1 drop-table", "x.sql", "DROP TABLE\n  ibl_scratch;\n", None, ["drop-table"]),
    ("p1 recreate", "x.sql", "DROP TABLE IF EXISTS ibl_scratch;\nCREATE TABLE ibl_scratch (\n  id INT NOT NULL\n);\n", None, []),
    ("p1 rename column", "x.sql", "ALTER TABLE ibl_x RENAME COLUMN old_name TO new_name,\n  ADD INDEX idx_new (new_name);\n", None, ["rename-column"]),
    ("p1 change rename", "x.sql", "ALTER TABLE ibl_x\n  CHANGE old_name new_name VARCHAR(32) NOT NULL DEFAULT '';\n", None, ["rename-column"]),
    ("p1 add default", "x.sql", "ALTER TABLE ibl_x ADD COLUMN c INT NOT NULL DEFAULT 0;\n", None, []),
    ("p1 change same", "x.sql", "ALTER TABLE ibl_x\n  CHANGE c c INT NOT NULL;\n", None, ["tighten-not-null"]),
    ("p1 drop key", "x.sql", "ALTER TABLE ibl_x\n  DROP KEY idx_old;\n", None, ["drop-index"]),
    ("p1 index readd", "x.sql", "ALTER TABLE ibl_x DROP INDEX idx_old;\nALTER TABLE ibl_x ADD INDEX idx_old (c, d);\n", None, []),
    ("p1 rename table", "x.sql", "RENAME TABLE ibl_a TO ibl_b;\n", None, ["rename-table"]),
    ("string literal", "x.sql", "INSERT INTO ibl_log (msg) VALUES ('we will DROP TABLE ibl_x and TRUNCATE TABLE ibl_y later');\n", None, []),
    # splitter negatives
    ("splitter quotes", "x.sql", "INSERT INTO t VALUES ('a;b -- not a comment', 'it''s', 'esc\\'ape');\n", None, []),
    ("block comment", "x.sql", "/* DROP TABLE t */ SELECT 1;\n", None, []),
    ("hash comment", "x.sql", "# DROP TABLE t\n", None, []),
    ("dash in block", "x.sql", "/*\n-- DROP TABLE t\n*/\nSELECT 1;\n", None, []),
    # exclusions
    ("drop pk fk", "x.sql", "ALTER TABLE t DROP PRIMARY KEY, DROP FOREIGN KEY fk_x;\n", None, []),
    ("rename index", "x.sql", "ALTER TABLE t RENAME INDEX a TO b;\n", None, []),
    ("modify default", "x.sql", "CREATE TABLE t (c INT);\nALTER TABLE t MODIFY c INT NOT NULL DEFAULT 0;\n", None, []),
    # selection
    ("selection miss", "x.sql", "ALTER TABLE t\n DROP COLUMN c;\nSELECT 1;\n", [(3, 3)], []),
    ("selection hit", "x.sql", "ALTER TABLE t\n DROP COLUMN c;\nSELECT 1;\n", [(1, 1)], ["drop-column"]),
    # new triggers
    ("delete no where", "x.sql", "DELETE FROM t\n;\n", None, ["delete-no-where"]),
    ("delete where", "x.sql", "DELETE FROM t\nWHERE id = 1;\n", None, []),
    ("delete where in comment", "x.sql", "DELETE FROM t; -- where id = 1\n", None, ["delete-no-where"]),
    ("update no where", "x.sql", "UPDATE t SET a = 1;\n", None, ["update-no-where"]),
    ("update join", "x.sql", "UPDATE a JOIN b ON a.id = b.id SET a.x = b.x;\n", None, ["update-no-where"]),
    ("update where", "x.sql", "UPDATE t SET a = 1 WHERE id = 2;\n", None, []),
    ("on duplicate", "x.sql", "INSERT INTO t (a) VALUES (1) ON DUPLICATE KEY UPDATE a = 1;\n", None, []),
    ("bare update", "x.sql", "update", None, []),
    ("bare delete", "x.sql", "delete", None, []),
    ("narrow same file", "x.sql", "ALTER TABLE t ADD COLUMN c VARCHAR(64) NULL;\nALTER TABLE t MODIFY c VARCHAR(32) NULL;\n", None, ["narrow-type"]),
    ("widen same file", "x.sql", "ALTER TABLE t ADD COLUMN c VARCHAR(32) NULL;\nALTER TABLE t MODIFY c VARCHAR(64) NULL;\n", None, []),
    ("narrow no dump", "x.sql", "ALTER TABLE t MODIFY c VARCHAR(32) NULL;\n", None, []),
    ("recreate new table", "x.sql", "DROP TABLE IF EXISTS fresh_t;\nCREATE TABLE fresh_t (id INT);\n", None, []),
    # PHP extraction
    ("php heredoc", "x.php", "<?php\n$db->query(<<<SQL\nALTER TABLE t\n  DROP COLUMN c;\nSQL\n);\n", None, ["drop-column"]),
    ("php nowdoc", "x.php", "<?php\n$db->query(<<<'SQL'\nDELETE FROM t;\nSQL);\n", None, ["delete-no-where"]),
    ("php escaped quote", "x.php", "<?php\n$db->query('UPDATE t SET a = \\'x;y\\' WHERE id = 1');\n", None, []),
    ("php dq interp", "x.php", "<?php\n$db->query(\"DELETE FROM t WHERE id = {$x->id}\");\n", None, []),
    ("php dq interp no where", "x.php", "<?php\n$db->query(\"DELETE FROM {$table}\");\n", None, ["delete-no-where"]),
    ("php comment", "x.php", "<?php\n// DROP TABLE t\n/* TRUNCATE TABLE t */\n", None, []),
    ("php concat where", "x.php", "<?php\n$db->query('DELETE FROM `' . $table . '` WHERE id = ' . $id);\n", None, []),
    ("php concat no where", "x.php", "<?php\n$db->query('DELETE FROM ' . $table);\n", None, ["delete-no-where"]),
    ("php bare words", "x.php", "<?php\n$a = 'update'; $b = 'delete';\n", None, []),
    ("php prepared", "x.php", "<?php\n$stmt = $db->prepare('DELETE FROM t WHERE id = ?');\n", None, []),
    ("prepare set ddl", "x.sql", "SET @s = IF(@n = 1, 'ALTER TABLE t DROP COLUMN c', 'SELECT 1');\nPREPARE st FROM @s;\nEXECUTE st;\nDEALLOCATE PREPARE st;\n", None, ["drop-column"]),
    ("prepare literal form", "x.sql", "PREPARE st FROM 'ALTER TABLE t DROP COLUMN c';\n", None, ["drop-column"]),
    ("prepare doubled quote", "x.sql", "SET @s = 'ALTER TABLE t ADD COLUMN d INT DEFAULT ''0'', DROP COLUMN c';\nPREPARE st FROM @s;\n", None, ["drop-column"]),
    ("prepare two on one line", "x.sql", "SET @a = 'ALTER TABLE t DROP COLUMN c'; SET @b = 'SELECT 1'; PREPARE s1 FROM @a; PREPARE s2 FROM @b;\n", None, ["drop-column"]),
    ("prepare select branch only", "x.sql", "SET @s = IF(@n = 1, 'SELECT 1', 'SELECT 2');\nPREPARE st FROM @s;\n", None, []),
    ("prepare non-ddl literal", "x.sql", "SET @s = 'SHOW TABLES';\nPREPARE st FROM @s;\n", None, []),
    ("prepare var never set", "x.sql", "PREPARE st FROM @missing;\nEXECUTE st;\n", None, []),
    ("set ddl without prepare", "x.sql", "SET @s = 'ALTER TABLE t DROP COLUMN c';\nSELECT @s;\n", None, []),
    ("prepare concat unresolved", "x.sql", "SET @s = CONCAT('ALTER TABLE t DROP COLUMN c', @x);\nPREPARE st FROM @s;\n", None, []),
    ("prepare select into unresolved", "x.sql", "SET @s = 'ALTER TABLE t DROP COLUMN c';\nSELECT 'SELECT 1' INTO @s;\nPREPARE st FROM @s;\n", None, []),
    ("prepare range covers set", "x.sql", "SET @s = IF(@n = 1,\n 'ALTER TABLE t DROP COLUMN c',\n 'SELECT 1');\nPREPARE st FROM @s;\n", [(2, 2)], ["drop-column"]),
    ("prepare range only prepare line", "x.sql", "SET @s = IF(@n = 1,\n 'ALTER TABLE t DROP COLUMN c',\n 'SELECT 1');\nPREPARE st FROM @s;\n", [(4, 4)], []),
    ("php prepare set ddl", "x.php", "<?php\n$db->query(\"SET @s = 'ALTER TABLE t DROP COLUMN c'\");\n$db->query('PREPARE st FROM @s');\n", None, ["drop-column"]),
]

SCHEMA_CASES = [
    # (name, schema, sql, expected_tags)
    ("schema narrow", "CREATE TABLE t (c varchar(64) NOT NULL);", "ALTER TABLE t MODIFY c VARCHAR(32) NULL;\n", ["narrow-type"]),
    ("schema widen", "CREATE TABLE t (c varchar(32) NOT NULL);", "ALTER TABLE t MODIFY c VARCHAR(64) NULL;\n", []),
    ("schema unresolved", "CREATE TABLE other (c int);", "ALTER TABLE t MODIFY c VARCHAR(32) NULL;\n", ["narrow-type"]),
    ("schema recreate existing", "CREATE TABLE t (id int);", "DROP TABLE IF EXISTS t;\nCREATE TABLE t (id INT);\n", ["drop-recreate-existing"]),
    ("schema narrow prepared", "CREATE TABLE t (c varchar(255) NOT NULL);", "SET @s = IF(@n = 1, 'ALTER TABLE t MODIFY COLUMN c varchar(35) NULL', 'SELECT 1');\nPREPARE st FROM @s;\n", ["narrow-type"]),
    ("schema widen prepared", "CREATE TABLE t (c varchar(35) NOT NULL);", "SET @s = IF(@n = 1, 'ALTER TABLE t MODIFY COLUMN c varchar(255) NULL', 'SELECT 1');\nPREPARE st FROM @s;\n", []),
]

NARROW_CASES = [
    ("varchar(64)", "varchar(32)", True),
    ("varchar(32)", "varchar(64)", False),
    ("int(11)", "int", False),
    ("int", "bigint", False),
    ("bigint", "int", True),
    ("int", "int unsigned", True),
    ("text", "varchar(255)", True),
    ("varchar(255)", "text", False),
    ("decimal(10,2)", "decimal(8,2)", True),
    ("enum('a','b')", "enum('a')", True),
    ("enum('a')", "enum('a','b')", False),
    ("datetime", "date", True),
    ("varchar(10)", "int", True),
    ("date", "datetime", False),
    ("datetime", "timestamp", True),
    ("float", "double", False),
    ("double", "float", True),
    ("tinyint(3) unsigned", "tinyint unsigned", False),
    ("mediumtext", "text", True),
]

MARKER_CASES = [
    # (name, sql, policy, pr_body, expected_exit)
    ("tagged inline", "-- destructive-migration[drop-column]: removing an unused legacy column\nALTER TABLE t DROP COLUMN c;\n", "strict", None, 0),
    ("tagged wrong tag", "-- destructive-migration[truncate]: removing an unused legacy column\nALTER TABLE t DROP COLUMN c;\n", "strict", None, 1),
    ("untagged strict", "-- destructive-migration: removing an unused legacy column\nALTER TABLE t DROP COLUMN c;\n", "strict", None, 1),
    ("untagged legacy", "-- destructive-migration: removing an unused legacy column\nALTER TABLE t DROP COLUMN c;\n", "legacy", None, 0),
    ("unknown tag", "-- destructive-migration[drop-colum]: removing an unused legacy column\nSELECT 1;\n", "strict", None, 1),
    ("short reason", "-- destructive-migration[drop-column]: too short\nALTER TABLE t DROP COLUMN c;\n", "strict", None, 1),
    ("pr body tagged", "ALTER TABLE t DROP COLUMN c;\n", "strict", "<!-- destructive-migration[drop-column]: removing an unused legacy column -->", 0),
    ("pr body untagged", "ALTER TABLE t DROP COLUMN c;\n", "strict", "<!-- destructive-migration: removing an unused legacy column -->", 1),
    ("marker inside prepared literal ignored", "SET @s = 'ALTER TABLE t /* destructive-migration[drop-column]: reason text long enough to pass */ DROP COLUMN c';\nPREPARE st FROM @s;\n", "strict", None, 1),
]


def self_test():
    cases = 0
    failures = 0
    for name, path, sql, ranges, expected in SELF_TEST_CASES:
        cases += 1
        statements, _ = extract_sql(path, sql)
        selected = [
            k
            for k, st in enumerate(statements)
            if ranges is None or any(st.start_line <= b and a <= st.end_line for a, b in ranges)
        ]
        got = sorted(set(h[0] for h in evaluate(statements, selected, Lookups(None, None, path))))
        if got != sorted(expected):
            failures += 1
            print("FAIL %s: expected %s, got %s" % (name, sorted(expected), got))
    for name, schema, sql, expected in SCHEMA_CASES:
        cases += 1
        statements, _ = extract_sql("x.sql", sql)
        lookups = Lookups(None, None, "x.sql", schema_text=schema)
        got = sorted(set(h[0] for h in evaluate(statements, list(range(len(statements))), lookups)))
        if got != sorted(expected):
            failures += 1
            print("FAIL %s: expected %s, got %s" % (name, sorted(expected), got))
    for old, new, expected in NARROW_CASES:
        cases += 1
        if is_narrower(old, new) != expected:
            failures += 1
            print("FAIL is_narrower(%s, %s) != %s" % (old, new, expected))
    for name, sql, policy, body, expected in MARKER_CASES:
        cases += 1
        code, _ = run_scan("x.sql", sql, None, policy, body, Lookups(None, None, "x.sql"))
        if code != expected:
            failures += 1
            print("FAIL marker %s: expected exit %d, got %d" % (name, expected, code))
    print("%d cases, %d failures" % (cases, failures))
    return 1 if failures else 0


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------


def main(argv):
    if argv[:1] == ["--self-test"]:
        return self_test()
    parser = argparse.ArgumentParser(prog="destructive-migration-scan.py")
    parser.add_argument("--self-test", action="store_true")
    sub = parser.add_subparsers(dest="command")
    scan = sub.add_parser("scan")
    scan.add_argument("--file", required=True)
    group = scan.add_mutually_exclusive_group(required=True)
    group.add_argument("--added-ranges")
    group.add_argument("--all-lines", action="store_true")
    scan.add_argument("--marker-policy", choices=["strict", "legacy"], default="strict")
    scan.add_argument("--pr-body-file")
    scan.add_argument("--schema")
    scan.add_argument("--migrations-dir")
    split = sub.add_parser("split")
    split.add_argument("--file", required=True)
    args = parser.parse_args(argv)
    if args.self_test:
        return self_test()
    if args.command == "scan":
        return cmd_scan(args)
    if args.command == "split":
        return cmd_split(args)
    parser.print_usage(sys.stderr)
    return 2


if __name__ == "__main__":
    try:
        sys.exit(main(sys.argv[1:]))
    except SystemExit:
        raise
    except Exception as exc:  # internal error must fail closed with exit 2
        sys.stderr.write("destructive-migration-scan: internal error: %r\n" % (exc,))
        sys.exit(2)
