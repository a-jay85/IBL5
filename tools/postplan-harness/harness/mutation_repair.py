"""Deterministic checks for the fix round's Executable Mutations repairs.

No LLM import. The model may propose one new `match` for a stale row; this module writes
that one line into a copy of the plan, compares the old and new rows, and judges the proof.
Row parsing goes through bin/lib/plan-exec-mutations, the one grammar owner (ADR-0199).
"""
from __future__ import annotations

import difflib
import posixpath
import re
import shutil
import subprocess
from dataclasses import dataclass
from typing import Callable, Iterable, Optional

from . import mutations
from .state import HarnessError

MAX_ROWS = 3
MATCH_SIMILARITY_MIN = 0.6
SYNTAX_TIMEOUT_S = 30

ELIGIBLE_RE = re.compile(r"^row (?P<row>[0-9]+(?:-[A-Za-z0-9]+)*) (?P<kind>HELD|NOT-APPLIED) — (?P<detail>.*)$")
NEW_MATCH_RE = re.compile(r"^NEW-MATCH: (.*)$", re.M)
_IDENT_RE = re.compile(r"[A-Za-z_]\w*")
_ROW_PREFIX_RE =re.compile(r"^row ([0-9A-Za-z_-]+) ")
_FENCE_RE = re.compile(r"^ {0,3}(`{3,}|~{3,})(.*)$")
_ROW_LINE_RE = re.compile(r"^row:(?: (.*))?$")
_MATCH_LINE_RE = re.compile(r"^match:( .*)?$")
_SECTION_TITLE = "Executable Mutations"


@dataclass(frozen=True)
class Row:
    row: str
    file: str
    check: str
    match: str
    replace: str


def parse_rows(plan_path, root, *, lib=mutations.DEFAULT_LIB) -> Optional[tuple[Row, ...]]:
    """The plan's rows as the grammar owner prints them, or None when it cannot say."""
    try:
        proc = mutations.run_bounded([str(lib), "parse", "--root", str(root), str(plan_path)],
                                     step="fix-round-row-parse", timeout=60, cwd=str(root),
                                     errors="surrogateescape")
    except (OSError, HarnessError):
        return None
    if proc.returncode != 0:
        return None
    rows: list[Row] = []
    for line in proc.stdout.split("\n"):
        if not line:
            continue
        fields = line.split("\0")
        if len(fields) != 5:
            return None
        rows.append(Row(*fields))
    return tuple(rows)


def eligible_rows(holding: Iterable[str]) -> tuple[list[str], list[str]]:
    """(held_ids, stale_ids) the round may work on, each capped at MAX_ROWS.

    A NOT-APPLIED row counts only when its match occurs 0 times. Every other holding line
    (BASELINE-FAILED, UNDECIDED, REVERT-FAILED, an executor failure) stays out of the round.
    """
    held: list[str] = []
    stale: list[str] = []
    for line in holding or ():
        m = ELIGIBLE_RE.match(line)
        if not m:
            continue
        if m.group("kind") == "HELD":
            held.append(m.group("row"))
        elif "match occurs 0 times" in m.group("detail"):
            stale.append(m.group("row"))
    return held[:MAX_ROWS], stale[:MAX_ROWS]


def parse_new_match(reply: str) -> Optional[str]:
    found = NEW_MATCH_RE.findall(reply or "")
    if len(found) == 1 and found[0] and not any(c in found[0] for c in "\0\r\n"):
        return found[0]
    return None


def _mutation_blocks(lines: list[str]) -> list[tuple[int, int]]:
    """(open, close) line indexes of each mutation fence inside the section."""
    blocks: list[tuple[int, int]] = []
    in_section = False
    other_fence = None
    start = None
    for i, line in enumerate(lines):
        if start is not None:
            if line.rstrip(" \t\r") == "```":
                blocks.append((start, i))
                start = None
            continue
        m = _FENCE_RE.match(line)
        if other_fence is not None:
            if (m and not m.group(2).strip() and m.group(1)[0] == other_fence[0]
                    and len(m.group(1)) >= len(other_fence)):
                other_fence = None
            continue
        if m:
            if in_section and m.group(1) == "```" and m.group(2).strip() == "mutation":
                start = i
            else:
                other_fence = m.group(1)
            continue
        if line.startswith("## "):
            in_section = line[3:].strip() == _SECTION_TITLE
    return blocks


def rewrite_match(plan_text: str, row_id: str, new_match: str) -> Optional[str]:
    """`plan_text` with one row's `match:` line replaced, or None when that is not one line."""
    lines = plan_text.split("\n")
    hits = []
    for start, end in _mutation_blocks(lines):
        body = range(start + 1, end)
        ids = [(_ROW_LINE_RE.match(lines[j]).group(1) or "").strip()
               for j in body if _ROW_LINE_RE.match(lines[j])]
        if row_id in ids:
            hits.append(body)
    if len(hits) != 1:
        return None
    match_lines = [j for j in hits[0] if _MATCH_LINE_RE.match(lines[j])]
    if len(match_lines) != 1:
        return None
    lines[match_lines[0]] = "match: " + new_match
    return "\n".join(lines)


def _shell_script(path: str, text: str) -> bool:
    if path.endswith(".sh"):
        return True
    first = text.split("\n", 1)[0].rstrip()
    return first.startswith("#!") and first.endswith(("bash", "sh"))


def _stdin_check_ok(argv: list[str], text: str) -> bool:
    try:
        proc = subprocess.run(argv, input=text, capture_output=True, text=True,
                              errors="surrogateescape", timeout=SYNTAX_TIMEOUT_S)
    except (OSError, subprocess.SubprocessError):
        return False
    return proc.returncode == 0


def syntax_ok(path: str, text: str) -> bool:
    """True only when a parser for this file type accepts `text`. Unknown types fail closed."""
    if path.endswith(".py"):
        try:
            compile(text, path, "exec")
        except (SyntaxError, ValueError):
            return False
        return True
    if path.endswith(".php"):
        return bool(shutil.which("php")) and _stdin_check_ok(["php", "-l"], text)
    if _shell_script(path, text):
        return _stdin_check_ok(["bash", "-n"], text)
    return False


def validate_repair(old_rows, new_rows, repaired, read_file: Callable[[str], Optional[str]]) -> list[str]:
    """Why the new rows are refused; an empty list accepts them. Only `match` may move."""
    if len(old_rows) != len(new_rows):
        return ["row-count-changed"]
    if [o.row for o in old_rows] != [n.row for n in new_rows]:
        return ["row-order-changed"]
    reasons: list[str] = []
    for o, n in zip(old_rows, new_rows):
        if o.file != n.file:
            reasons.append(f"file-changed row {o.row}")
        if o.check != n.check:
            reasons.append(f"check-changed row {o.row}")
        if o.replace != n.replace:
            reasons.append(f"replace-changed row {o.row}")
        if o.match == n.match:
            continue
        if o.row not in repaired:
            reasons.append(f"unexpected-match-change row {o.row}")
            continue
        text = read_file(n.file)
        if text is None or text.count(o.match) != 0:
            reasons.append(f"not-stale row {o.row}")
        if text is None or text.count(n.match) != 1:
            reasons.append(f"match-count row {o.row}")
        elif not syntax_ok(n.file, text.replace(n.match, n.replace, 1)):
            reasons.append(f"mutation-breaks-syntax row {o.row}")
        elif _IDENT_RE.findall(n.replace) and (
                (set(_IDENT_RE.findall(n.replace)) & set(_IDENT_RE.findall(o.match)))
                - set(_IDENT_RE.findall(text))):
            # A renamed identifier left in `replace` would trip on a NameError, not on the test.
            reasons.append(f"replace-stale-identifier row {o.row}")
        if difflib.SequenceMatcher(None, o.match, n.match).ratio() < MATCH_SIMILARITY_MIN:
            reasons.append(f"match-dissimilar row {o.row}")
    return reasons


def _row_of(line: str) -> Optional[str]:
    m = _ROW_PREFIX_RE.match(line)
    return m.group(1) if m else None


def holding_row_ids(holding: Iterable[str]) -> set:
    """Row ids of the holding lines. An executor line names no row, so it can never be excused."""
    return {r for r in map(_row_of, holding or ()) if r}


def proof_ok(report, wanted, before_holding) -> bool:
    """Every wanted row tripped, and nothing holds that was not already holding."""
    if report is None or report.tree_suspect or not report.results:
        return False
    tripped = {str(r.row) for r in report.results if r.kind == "TRIPPED"}
    if not set(wanted) <= tripped:
        return False
    still = {_row_of(h) for h in report.holding}
    return still <= (set(before_holding) - set(wanted))


def is_test_path(path: str) -> bool:
    base = posixpath.basename(path)
    if path.startswith(("tools/postplan-harness/tests/", "ibl5/tests/")):
        return True
    if path.startswith("bin/") and base.startswith("test"):
        return True
    return (base.startswith("test_") and base.endswith(".py")) or base.endswith(("Test.php", ".spec.ts"))
