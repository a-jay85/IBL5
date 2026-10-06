from __future__ import annotations

import os
import re
from dataclasses import dataclass
from typing import Callable

from harness.classify import (FILES_CHANGED_BEGIN, FILES_CHANGED_END,
                              MERGE_DIGEST_BEGIN, MERGE_DIGEST_END,
                              is_test_path, name_status_from_diff)


@dataclass(frozen=True)
class BodyNumberFacts:
    files_changed: int
    lines_added: int
    lines_deleted: int
    migration_numbers: tuple[str, ...]   # zero-padded as they appear in the filename
    adr_numbers: tuple[str, ...]         # 4-digit, as they appear in the filename


_MIGRATION_PATH = re.compile(r"ibl5/migrations/")
_ADR_PATH = re.compile(r"ibl5/docs/decisions/ADR-(\d{4})-")


def body_number_facts(name_status: str, numstat: str) -> BodyNumberFacts:
    """Extract facts from raw git diff --name-status and --numstat output."""
    # files_changed: count non-blank lines (rename lines count once)
    ns_lines = [ln for ln in name_status.splitlines() if ln.strip()]
    files_changed = len(ns_lines)

    # lines_added / lines_deleted: sum columns 1 and 2 of numstat
    lines_added = 0
    lines_deleted = 0
    for line in numstat.splitlines():
        if not line.strip():
            continue
        parts = line.split("\t", 2)
        if len(parts) >= 2:
            try:
                lines_added += int(parts[0])
            except ValueError:
                pass
            try:
                lines_deleted += int(parts[1])
            except ValueError:
                pass

    # migration_numbers and adr_numbers: added paths only
    migration_numbers: list[str] = []
    adr_numbers: list[str] = []

    for line in ns_lines:
        parts = line.split("\t")
        status = parts[0] if parts else ""
        if not status.startswith("A"):
            continue
        path = parts[1] if len(parts) > 1 else ""
        if _MIGRATION_PATH.search(path):
            basename = path.rsplit("/", 1)[-1]
            m = re.match(r"(\d+)", basename)
            if m:
                migration_numbers.append(m.group(1))
        m = _ADR_PATH.search(path)
        if m:
            adr_numbers.append(m.group(1))

    return BodyNumberFacts(
        files_changed=files_changed,
        lines_added=lines_added,
        lines_deleted=lines_deleted,
        migration_numbers=tuple(sorted(set(migration_numbers))),
        adr_numbers=tuple(sorted(set(adr_numbers))),
    )


def _protected_spans(body: str) -> list[tuple[int, int]]:
    """Return half-open char ranges that must not be rewritten."""
    spans: list[tuple[int, int]] = []

    # 1. Files-changed block
    begin = body.find(FILES_CHANGED_BEGIN)
    if begin != -1:
        end = body.find(FILES_CHANGED_END, begin + len(FILES_CHANGED_BEGIN))
        if end != -1 and end > begin:
            spans.append((begin, end + len(FILES_CHANGED_END)))

    # 1b. Merge-digest block (runner-written from the sticky verdict)
    begin = body.find(MERGE_DIGEST_BEGIN)
    if begin != -1:
        end = body.find(MERGE_DIGEST_END, begin + len(MERGE_DIGEST_BEGIN))
        if end != -1 and end > begin:
            spans.append((begin, end + len(MERGE_DIGEST_END)))

    # 2. ## Manual Testing section
    mt_match = re.search(r"^## Manual Testing\b", body, re.MULTILINE)
    if mt_match:
        section_start = mt_match.start()
        next_heading = re.search(r"^## ", body[mt_match.end():], re.MULTILINE)
        if next_heading:
            section_end = mt_match.end() + next_heading.start()
        else:
            section_end = len(body)
        spans.append((section_start, section_end))

    return spans


def _replace_in_match(m: re.Match, group_index: int, new_value: str) -> str:
    """Replace group `group_index` within m.group(0) with `new_value`."""
    s = m.start(group_index) - m.start(0)
    e = m.end(group_index) - m.start(0)
    full = m.group(0)
    return full[:s] + new_value + full[e:]


def _apply_corrections(text: str, facts: BodyNumberFacts, body_adr_count: int) -> str:
    """Apply all correction rules to a single unprotected text slice."""

    # File count — always correct
    def fix_file_count(m: re.Match) -> str:
        if int(m.group(1)) == facts.files_changed:
            return m.group(0)
        return _replace_in_match(m, 1, str(facts.files_changed))

    text = re.sub(
        r"\b(\d+)\s+files?\s+(?:changed|touched|modified|added)\b",
        fix_file_count,
        text,
        flags=re.IGNORECASE,
    )

    # Insertions — only when lines_added > 0
    if facts.lines_added > 0:
        def fix_insertions(m: re.Match) -> str:
            if int(m.group(1)) == facts.lines_added:
                return m.group(0)
            return _replace_in_match(m, 1, str(facts.lines_added))

        text = re.sub(
            r"\b(?:\+)?(\d+)\s+(?:lines?\s+)?(?:added|insertions?)\b",
            fix_insertions,
            text,
            flags=re.IGNORECASE,
        )

    # Deletions — only when lines_deleted > 0
    if facts.lines_deleted > 0:
        def fix_deletions(m: re.Match) -> str:
            if int(m.group(1)) == facts.lines_deleted:
                return m.group(0)
            return _replace_in_match(m, 1, str(facts.lines_deleted))

        text = re.sub(
            r"\b(?:-)?(\d+)\s+(?:lines?\s+)?(?:deleted|removed|deletions?)\b",
            fix_deletions,
            text,
            flags=re.IGNORECASE,
        )

    # Combined delta — when either side is non-zero
    if facts.lines_added > 0 or facts.lines_deleted > 0:
        def fix_combined(m: re.Match) -> str:
            if int(m.group(1)) == facts.lines_added and int(m.group(2)) == facts.lines_deleted:
                return m.group(0)
            # Replace right-to-left to keep earlier positions valid
            s1 = m.start(1) - m.start(0)
            e1 = m.end(1) - m.start(0)
            s2 = m.start(2) - m.start(0)
            e2 = m.end(2) - m.start(0)
            full = m.group(0)
            full = full[:s2] + str(facts.lines_deleted) + full[e2:]
            full = full[:s1] + str(facts.lines_added) + full[e1:]
            return full

        text = re.sub(r"\+(\d+)\s*/\s*-(\d+)", fix_combined, text)

    # Migration — only when exactly one migration was added
    if len(facts.migration_numbers) == 1:
        target = facts.migration_numbers[0]

        def fix_migration(m: re.Match) -> str:
            if m.group(1) == target:
                return m.group(0)
            return _replace_in_match(m, 1, target)

        text = re.sub(
            r"\bmigration\s+#?(\d+)\b",
            fix_migration,
            text,
            flags=re.IGNORECASE,
        )

    # ADR — only when exactly one ADR was added AND body cites exactly one distinct ADR number
    if len(facts.adr_numbers) == 1 and body_adr_count == 1:
        target = facts.adr_numbers[0]

        def fix_adr(m: re.Match) -> str:
            if m.group(1) == target:
                return m.group(0)
            return _replace_in_match(m, 1, target)

        text = re.sub(r"\bADR-(\d{4})\b", fix_adr, text)

    return text


def body_prose_for_check(body: str) -> str:
    """Return body with both protected spans deleted and the result stripped.

    Feeds the LLM body-check prompt: the model sees prose only, never the
    machine-generated files-changed block or the runner-owned Manual Testing section.
    Empty or whitespace-only input returns "".
    """
    if not body or not body.strip():
        return ""
    spans = sorted(_protected_spans(body))
    parts: list[str] = []
    pos = 0
    for start, end in spans:
        if pos < start:
            parts.append(body[pos:start])
        pos = end
    if pos < len(body):
        parts.append(body[pos:])
    return "".join(parts).strip()


def correct_body_numbers(body: str, name_status: str, numstat: str) -> str:
    """Rewrite wrong numbers in *body* to match ground-truth facts from the git outputs.

    Protected spans (files-changed block, Manual Testing section) are copied verbatim.
    """
    facts = body_number_facts(name_status, numstat)

    spans = sorted(_protected_spans(body))

    # Count distinct ADR numbers in unprotected text only (used by ADR correction guard)
    unprotected: list[str] = []
    _pos = 0
    for start, end in spans:
        if _pos < start:
            unprotected.append(body[_pos:start])
        _pos = max(_pos, end)
    if _pos < len(body):
        unprotected.append(body[_pos:])
    body_adr_count = len(set(re.findall(r"\bADR-(\d{4})\b", "\n".join(unprotected))))

    result_parts: list[str] = []
    pos = 0
    for start, end in spans:
        if pos < start:
            result_parts.append(_apply_corrections(body[pos:start], facts, body_adr_count))
        result_parts.append(body[start:end])  # protected: verbatim
        pos = end
    if pos < len(body):
        result_parts.append(_apply_corrections(body[pos:], facts, body_adr_count))

    return "".join(result_parts)


@dataclass(frozen=True)
class TestCountCheck:
    __test__ = False  # not a pytest class

    path: str
    claimed: int
    added: int
    total: int | None
    verdict: str   # "match" | "mismatch" | "unverifiable"
    start: int     # char offsets of the claim match in the body
    end: int


_CLAIM = re.compile(
    r"\b(\d+)\s+(?:new\s+|added\s+)?"
    r"(?:test\s+cases?|test\s+methods?|tests?|cases?)\b"
    r"(?!\s*/|\s+(?:files?|suites?|runs?|scripts?|harness(?:es)?|fixtures?)\b)",
    re.IGNORECASE,
)
_BACKTICK = re.compile(r"`([^`\n]+)`")
_PHP_FUNC = re.compile(
    r"^\s*(?:(?:public|protected|private|static|final|abstract)\s+)*function\s+(\w+)\s*\(")
_PY_DEF = re.compile(r"^\s*(?:async\s+)?def\s+test\w*\s*\(")
_JS_CASE = re.compile(r"^\s*(?:it|test)(?:\.(?:only|skip|fixme|concurrent))?\s*\(")
_GO_TEST = re.compile(r"^func\s+Test\w*\s*\(")
_BASH_CASE = re.compile(r"^\s*(?:function\s+)?case_\w+\s*\(\s*\)")
_DIFF_HEADER = re.compile(r"^diff --git a/.* b/(.*)$")
_NOTE_MARK = " [harness: measured"


def _php_has_test_attribute(lines: list[str], i: int) -> bool:
    """True when line *i* carries ``#[Test]`` or sits under a stack of attribute lines that do."""
    if "#[Test]" in lines[i].split("function", 1)[0]:
        return True
    j = i - 1
    while j >= 0 and (not lines[j].strip() or lines[j].lstrip().startswith("#[")):
        if "#[Test]" in lines[j]:
            return True
        j -= 1
    return False


def _count_declarations(path: str, lines: list[str]) -> int | None:
    """Count test declarations in the language's own unit; None when the unit is unknown."""
    if path.endswith(".php"):
        count = 0
        for i, line in enumerate(lines):
            m = _PHP_FUNC.match(line)
            if m and (m.group(1).startswith("test") or _php_has_test_attribute(lines, i)):
                count += 1
        return count
    if path.endswith(".py"):
        return sum(1 for ln in lines if _PY_DEF.match(ln))
    if path.endswith((".ts", ".js")):
        return sum(1 for ln in lines if _JS_CASE.match(ln))
    if path.endswith(".go"):
        return sum(1 for ln in lines if _GO_TEST.match(ln))
    if path.startswith("bin/test-"):
        return sum(1 for ln in lines if _BASH_CASE.match(ln)) or None
    return None


def _added_lines_by_path(diff_text: str) -> dict[str, list[str]]:
    """Lines each file section of a unified diff adds, without the leading ``+``."""
    out: dict[str, list[str]] = {}
    cur: str | None = None
    in_hunk = False
    for line in diff_text.splitlines():
        m = _DIFF_HEADER.match(line)
        if m:
            cur = m.group(1)
            out.setdefault(cur, [])
            in_hunk = False
            continue
        if cur is None:
            continue
        if line.startswith("@@"):
            in_hunk = True
            continue
        if in_hunk and line.startswith("+") and not line.startswith("+++"):
            out[cur].append(line[1:])
    return out


def read_worktree_file(worktree: str, path: str) -> str | None:
    """Read *path* from the working tree; None when unsafe or unreadable.

    The working tree is the head here: ``diff_vs_base`` diffs merge-base against it,
    and the body check runs before the commit, so a read of the committed HEAD
    would miss uncommitted test edits.
    """
    if os.path.isabs(path) or ".." in path.split("/"):
        return None
    try:
        with open(os.path.join(worktree, path), encoding="utf-8", errors="replace") as fh:
            return fh.read()
    except OSError:
        return None


def _normalize_test_path(token: str) -> str | None:
    token = token.strip().split("::", 1)[0]
    token = re.sub(r":\d+$", "", token)
    return token if is_test_path(token) else None


def check_test_count_claims(
    body: str,
    diff_text: str,
    read_file: Callable[[str], str | None] | None = None,
) -> list[TestCountCheck]:
    """Ground each hand-written test-count claim against the diff and the head file."""
    spans = _protected_spans(body)
    added_by_path = _added_lines_by_path(diff_text)
    status_by_path = {p: st for st, p in name_status_from_diff(diff_text)}
    checks: list[TestCountCheck] = []
    in_fence = False
    offset = 0
    for line in body.splitlines(keepends=True):
        line_start = offset
        offset += len(line)
        if line.strip().startswith("```"):
            in_fence = not in_fence
            continue
        if in_fence or any(s <= line_start < e for s, e in spans):
            continue
        claims = list(_CLAIM.finditer(line))
        if not claims:
            continue
        paths = {p for tok in _BACKTICK.findall(line) if (p := _normalize_test_path(tok))}
        for m in claims:
            claimed = int(m.group(1))
            start, end = line_start + m.start(), line_start + m.end()
            if len(paths) != 1:
                checks.append(TestCountCheck("", claimed, 0, None, "unverifiable", start, end))
                continue
            path = next(iter(paths))
            status = status_by_path.get(path)
            if path not in added_by_path or status is None or status == "D":
                checks.append(TestCountCheck(path, claimed, 0, None, "unverifiable", start, end))
                continue
            added_raw = _count_declarations(path, added_by_path[path])
            total: int | None
            if status == "A":
                total = added_raw
            else:
                head = read_file(path) if read_file is not None else None
                total = _count_declarations(path, head.splitlines()) if head is not None else None
            if total is None and added_raw is None:
                checks.append(TestCountCheck(path, claimed, 0, None, "unverifiable", start, end))
                continue
            added = added_raw or 0
            if claimed == added or claimed == total:
                verdict = "match"
            elif total is not None:
                verdict = "mismatch"
            else:
                verdict = "unverifiable"
            checks.append(TestCountCheck(path, claimed, added, total, verdict, start, end))
    return checks


def annotate_test_count_mismatches(
    body: str,
    diff_text: str,
    read_file: Callable[[str], str | None] | None = None,
) -> tuple[str, list[str]]:
    """Insert a measured-counts note after each mismatched claim; never rewrite the claim."""
    mismatches = [c for c in check_test_count_claims(body, diff_text, read_file)
                  if c.verdict == "mismatch"]
    findings: list[str] = []
    for c in sorted(mismatches, key=lambda c: c.end, reverse=True):
        if body[c.end:].startswith(_NOTE_MARK):
            continue
        note = f"{_NOTE_MARK} {c.added} added / {c.total} total in `{c.path}`]"
        body = body[:c.end] + note + body[c.end:]
        findings.append(
            f"test-count claim mismatch: body says {c.claimed} for {c.path}; "
            f"diff adds {c.added}, file has {c.total} at head")
    findings.reverse()
    return body, findings
