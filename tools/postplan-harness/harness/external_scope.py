"""Changed-hunk test for the non-workflow external paths of merge-danger trigger 2.

`bin/db-sync-prod` fires only when a changed line names a prod connection or a variable that
shapes the table lists the dump reads. `bin/discord-dm`, `.github/actions/notify-discord/` and
`ibl5/classes/Mail/` fire on any changed code line. Comment, blank and indentation-only edits,
and the pipefail-safe rewrite of `echo "$v" | grep ...` into `grep ... <<< "$v"`, stay quiet.
Any shape the parser cannot read returns a reason, so the trigger fires (fail closed), the same
contract as `workflow_scope.workflow_send_change`.
"""
from __future__ import annotations

import re

from harness.workflow_scope import _HUNK_RE, _R_BAD_HUNK, _R_BAD_SECTION, _R_TRUNCATED, _SECTION_META

SYNC_PATH = "bin/db-sync-prod"
_R_MISSING = "missing-section"
_R_CONTINUATION = "truncated-continuation"
_R_SYNC = "sync-change"
_R_SEND = "send-change"

# Reading PRESERVE_TABLES (e.g. passing it to a local replay) is quiet; assigning it fires.
_SYNC_TOKEN_RE = re.compile(
    r"REMOTE_|\bremote_(?:query|dump)\b|mariadb-dump|mysqldump|\b(?:ssh|scp|rsync|curl|wget)\b"
    r"|--ignore-table|--host\b|(?:^|\s)-h\s|db-sync-preserve-tables"
    r"|\b(?:GEN_RE|PRESERVE_RE|ALL_TABLES|REGULAR_TABLES|GENERATED_TABLES|IGNORE_ARGS"
    r"|PRESERVE_EXISTING|_kept_gen|_preserve_name_ok)\b|\bPRESERVE_TABLES\+?="
)
_ECHO_GREP_RE = re.compile(r'echo ("\$\{?\w+\}?") \| (grep(?: -[A-Za-z]+)*) ("[^"]*")')
_HERESTRING_RE = re.compile(r"<<<\s*")
_SHELL_COMMENT_RE = re.compile(r"#(?!!)")
_PHP_COMMENT_RE = re.compile(r"(?://|#(?!\[)|/\*|\*/|\*(?:\s|$))")
_YAML_COMMENT_RE = re.compile(r"#")


def _comment_re(path: str) -> re.Pattern[str] | None:
    """The comment opener for the file type; None means every non-blank line is code."""
    name = path.rsplit("/", 1)[-1]
    if name.endswith(".php"):
        return _PHP_COMMENT_RE
    if name.endswith((".yml", ".yaml")):
        return _YAML_COMMENT_RE
    if name.endswith(".sh") or "." not in name:
        return _SHELL_COMMENT_RE
    return None


def _norm(line: str) -> str:
    """Indentation-free form. The here-string rewrite of `echo "$v" | grep` maps to one form."""
    line = line.strip()
    line = _HERESTRING_RE.sub("<<< ", line)
    line = _ECHO_GREP_RE.sub(r"\2 \3 <<< \1", line)
    return line


def _hunks(section: str) -> list[tuple[int, int, list[str]]] | str:
    """(old_start, new_start, tagged body lines) per hunk, or a fail-closed reason."""
    lines = section.split("\n")
    if lines and lines[-1] == "":
        lines.pop()
    start = next((k for k, ln in enumerate(lines) if ln.startswith("@@")), None)
    if start is None or not all(ln.startswith(_SECTION_META) for ln in lines[:start]):
        return _R_BAD_SECTION
    hunks: list[tuple[int, int, list[str]]] = []
    i = start
    while i < len(lines):
        m = _HUNK_RE.match(lines[i])
        if m is None:
            return _R_BAD_HUNK
        old_left, new_left = int(m.group(2) or "1"), int(m.group(4) or "1")
        body: list[str] = []
        i += 1
        while old_left > 0 or new_left > 0:
            if i == len(lines):
                return _R_TRUNCATED
            tag = lines[i][:1]
            if tag != "\\":
                if tag not in (" ", "-", "+"):
                    return _R_BAD_HUNK
                old_left -= 0 if tag == "+" else 1
                new_left -= 0 if tag == "-" else 1
                if old_left < 0 or new_left < 0:
                    return _R_BAD_HUNK
                body.append(lines[i])
            i += 1
        while i < len(lines) and lines[i].startswith("\\"):
            i += 1
        hunks.append((int(m.group(1)), int(m.group(3)), body))
    return hunks


def _live_changes(body: list[str]) -> list[int]:
    """Changed-line indexes left after aligned cancellation. In a run of removed lines followed by
    a run of added lines of the same length, the i-th pair cancels when both normalize equal.
    A reorder or a length change cancels nothing."""
    live: list[int] = []
    i = 0
    while i < len(body):
        if body[i][:1] == " ":
            i += 1
            continue
        minus: list[int] = []
        while i < len(body) and body[i][:1] == "-":
            minus.append(i)
            i += 1
        plus: list[int] = []
        while i < len(body) and body[i][:1] == "+":
            plus.append(i)
            i += 1
        if len(minus) == len(plus):
            for a, b in zip(minus, plus):
                if _norm(body[a][1:]) != _norm(body[b][1:]):
                    live += [a, b]
        else:
            live += minus + plus
    return live


def _sync_reason(body: list[str], idx: int, start: int) -> str | None:
    """Join the backslash-continued command holding body[idx] over its own side of the hunk."""
    tag = body[idx][:1]
    side = [k for k, ln in enumerate(body) if ln[:1] in (" ", tag)]
    pos = side.index(idx)
    lo = pos
    while lo > 0 and body[side[lo - 1]].rstrip().endswith("\\"):
        lo -= 1
    if lo == 0 and start > 1:
        return _R_CONTINUATION
    hi = pos
    while body[side[hi]].rstrip().endswith("\\"):
        hi += 1
        if hi == len(side):
            return _R_CONTINUATION
    joined = " ".join(body[side[k]][1:] for k in range(lo, hi + 1))
    if _SYNC_TOKEN_RE.search(joined):
        return _R_SYNC
    return None


def external_send_change(path: str, section: str | None) -> str | None:
    """A reason when `path`'s changed lines can change what is synced or sent, else None."""
    if path.endswith(".md"):
        return None
    if section is None:
        return _R_MISSING
    hunks = _hunks(section)
    if isinstance(hunks, str):
        return hunks
    comment_re = _comment_re(path)
    for old_start, new_start, body in hunks:
        for idx in _live_changes(body):
            text = body[idx][1:].strip()
            if not text or (comment_re is not None and comment_re.match(text)):
                continue
            if path != SYNC_PATH:
                return _R_SEND
            reason = _sync_reason(body, idx, old_start if body[idx][:1] == "-" else new_start)
            if reason is not None:
                return reason
    return None
