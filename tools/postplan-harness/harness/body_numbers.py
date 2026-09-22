from __future__ import annotations

import re
from dataclasses import dataclass

from harness.classify import FILES_CHANGED_BEGIN, FILES_CHANGED_END


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

    # Count distinct ADR numbers in the full body (used by ADR correction guard)
    body_adr_count = len(set(re.findall(r"\bADR-(\d{4})\b", body)))

    spans = sorted(_protected_spans(body))

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
