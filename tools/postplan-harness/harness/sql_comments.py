"""Blank SQL comments on the added lines of a diff, so merge-danger trigger 1 matches statements only.

Pure functions only. Comments are replaced by spaces of the same length and newlines are kept, so
line count, columns and the diff's `+` prefix all survive and `DESTRUCTIVE_SQL` (anchored on `^\\+`)
runs unchanged over the result. Strings and identifiers pass through verbatim: a `DROP` inside a
literal still matches exactly as before. The quote states exist only so a `--` or `/*` inside a
literal cannot open a comment and hide the statement after it.

Fail closed: a scan that ends inside a block comment or a quote returns its text raw, so text the
tokenizer cannot vouch for is matched as code. `--` opens a comment only when followed by
whitespace or end of line (the MySQL rule), and `/*! ... */` bodies stay code because MySQL
executes them.

The CI twin is the `Splitter` in `bin/lib/destructive-migration-scan.py`, which has its own
tokenizer and its own comment self-test rows. The two are kept independent on purpose.

Motivating inputs, both held one-way by comment text alone:
    PR #3513: `+-- G1: a target longer than the column (would truncate or error on write)`
    PR #3495: `+-- Rollback (lossless for all other data): ALTER TABLE `ibl_team_info` DROP COLUMN `trade_mode`;`
"""
from __future__ import annotations

_CODE, _LINE, _BLOCK, _SQUOTE, _DQUOTE, _BACKTICK = range(6)
_QUOTE_STATE = {"'": _SQUOTE, '"': _DQUOTE, "`": _BACKTICK}
_STATE_QUOTE = {v: k for k, v in _QUOTE_STATE.items()}


def _blank(ch: str) -> str:
    return ch if ch == "\n" else " "


def _scan(text: str) -> tuple[str, bool]:
    """(text with comments blanked, True when the scan ended outside a block comment and quote)."""
    out: list[str] = []
    state = _CODE
    i, n = 0, len(text)
    while i < n:
        ch = text[i]
        nxt = text[i + 1] if i + 1 < n else ""
        if state == _CODE:
            if ch == "-" and nxt == "-" and (i + 2 >= n or text[i + 2] in " \t\r\n"):
                state = _LINE
                out.append("  ")
                i += 2
                continue
            if ch == "#":
                state = _LINE
                out.append(" ")
            elif ch == "/" and nxt == "*" and text[i + 2:i + 3] != "!":
                state = _BLOCK
                out.append("  ")
                i += 2
                continue
            else:
                if ch in _QUOTE_STATE:
                    state = _QUOTE_STATE[ch]
                out.append(ch)
        elif state == _LINE:
            if ch == "\n":
                state = _CODE
            out.append(_blank(ch))
        elif state == _BLOCK:
            if ch == "*" and nxt == "/":
                state = _CODE
                out.append("  ")
                i += 2
                continue
            out.append(_blank(ch))
        else:
            quote = _STATE_QUOTE[state]
            if ch == "\\" and state != _BACKTICK and i + 1 < n:
                out.append(text[i:i + 2])
                i += 2
                continue
            if ch == quote:
                if nxt == quote:
                    out.append(ch + nxt)
                    i += 2
                    continue
                state = _CODE
            out.append(ch)
        i += 1
    return "".join(out), state in (_CODE, _LINE)


def strip_sql_comments(text: str) -> str:
    """`text` with every SQL comment blanked to spaces; `text` unchanged when the scan is unclean."""
    out, clean = _scan(text)
    return out if clean else text


def strip_added_sql_comments(diff_text: str) -> str:
    """Diff text of identical line count where only added-line bodies have comments blanked.

    Each run of consecutive added lines inside one hunk is tokenized on its own, so an
    unterminated construct only un-strips its own run. A diff with no `@@` hunk is treated as one
    bare hunk (the `_migration_sections` no-header case); `+++ ` is never an added line.
    """
    lines = diff_text.split("\n")
    has_hunks = any(ln.startswith("@@") for ln in lines)
    in_hunk = not has_hunks
    out: list[str] = []
    group: list[str] = []

    def flush() -> None:
        if group:
            stripped = strip_sql_comments("\n".join(group)).split("\n")
            out.extend("+" + body for body in stripped)
            group.clear()

    for ln in lines:
        if ln.startswith("diff --git"):
            flush()
            in_hunk = False
            out.append(ln)
        elif ln.startswith("@@"):
            flush()
            in_hunk = True
            out.append(ln)
        elif in_hunk and ln.startswith("+") and not ln.startswith("+++ "):
            group.append(ln[1:])
        else:
            flush()
            out.append(ln)
    flush()
    return "\n".join(out)
