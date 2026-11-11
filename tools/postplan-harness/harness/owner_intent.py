"""The owner's request, read from the intent file bin/capture-intent writes.

This module is the only reader of `~/claude-plans/_intent/<branch>.md`. The file holds a
header line with a SHA-256 over the payload, then one line of JSON with the owner's typed
turns. Every failure (missing, unreadable, altered, wrong branch, over the byte cap) makes
`load` return None and the section builders return "", so every caller falls back to the
behavior it had before the file existed.

The PR body gets `pr_section` (a quoted copy with a provenance line). The second-reviewer
seat gets `seat_section`, read from the file and never from the PR body, so a hand-written
body section cannot reach the seat.
"""
from __future__ import annotations

import hashlib
import json
import os
import re

OWNER_REQUEST_CAP = 4_000
HEADER_RE = re.compile(r"^<!-- capture-intent v1 sha256=([0-9a-f]{64}) -->$")
BRANCH_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._-]*$")
PR_MARKER = "<!-- owner-request: harness-written -->"
HEADING = "## Owner request"

_HEADING_RE = re.compile(r"^## Owner request[ \t]*$", re.M)
_SUMMARY_RE = re.compile(r"^## Summary[ \t]*$", re.M)
_NEXT_SECTION_RE = re.compile(r"^## ", re.M)


def intent_path(branch: str) -> str:
    return os.path.expanduser(f"~/claude-plans/_intent/{branch}.md")


def _valid_turn(turn) -> bool:
    if not isinstance(turn, dict):
        return False
    index, redactions, stripped = turn.get("index"), turn.get("redactions"), turn.get("stripped")
    return (isinstance(index, int) and not isinstance(index, bool)
            and isinstance(turn.get("timestamp"), str)
            and isinstance(turn.get("text"), str) and bool(turn["text"])
            and isinstance(redactions, int) and not isinstance(redactions, bool)
            and redactions >= 0
            and isinstance(stripped, list) and all(isinstance(s, str) for s in stripped))


def load(branch: str) -> dict | None:
    """The verified payload for `branch`, or None on any failure."""
    if not isinstance(branch, str) or not BRANCH_RE.match(branch) or ".." in branch:
        return None
    try:
        with open(intent_path(branch), encoding="utf-8") as fh:
            text = fh.read()
    except (OSError, UnicodeDecodeError):
        return None
    lines = text.split("\n")
    if len(lines) < 2:
        return None
    m = HEADER_RE.match(lines[0])
    if not m:
        return None
    payload = lines[1]
    if hashlib.sha256(payload.encode()).hexdigest() != m.group(1):
        return None
    try:
        data = json.loads(payload)
    except ValueError:
        return None
    if not isinstance(data, dict) or data.get("version") != 1:
        return None
    if data.get("branch") != branch:
        return None
    if not isinstance(data.get("session"), str):
        return None
    turns = data.get("turns")
    if not isinstance(turns, list) or not turns or not all(_valid_turn(t) for t in turns):
        return None
    total = sum(len(t["text"].encode()) for t in turns)
    if total > OWNER_REQUEST_CAP:
        return None
    return data


def fence_for(text: str) -> str:
    """A backtick fence longer than any backtick run inside `text`."""
    runs = re.findall(r"`+", text)
    return "`" * max(3, max((len(r) for r in runs), default=0) + 1)


def seat_section(branch: str) -> str:
    """The `## Owner request` block for the seat prompt; "" when there is no valid file."""
    data = load(branch)
    if data is None:
        return ""
    parts = []
    for turn in data["turns"]:
        fence = fence_for(turn["text"])
        parts.append(f"Message at {turn['timestamp']}:\n\n{fence}text\n{turn['text']}\n{fence}")
    return "## Owner request\n\n" + "\n\n".join(parts)


def _changes_sentences(turns) -> list[str]:
    tags = []
    for turn in turns:
        for tag in turn["stripped"]:
            if tag not in tags:
                tags.append(tag)
    redactions = sum(t["redactions"] for t in turns)
    out = []
    if tags:
        out.append("It removed " + " and ".join(f"`{t}`" for t in tags) + " blocks.")
    if redactions:
        out.append(f"It replaced {redactions} spans with [REDACTED] (an email address or a token).")
    return out


def pr_section(branch: str) -> str:
    """The marked `## Owner request` section for the PR body; "" when there is no valid file."""
    data = load(branch)
    if data is None:
        return ""
    turns = data["turns"]
    provenance = (f"The harness copied these messages from session `{data['session']}` "
                  "with `bin/capture-intent`.")
    changes = _changes_sentences(turns)
    if changes:
        provenance += (" The text below is the owner's typed text, byte for byte, "
                       "except for these changes. " + " ".join(changes))
    else:
        provenance += " The text below is the owner's typed text, byte for byte."
    quoted = []
    for turn in turns:
        lines = [f"> **Message at {turn['timestamp']}**", ">"]
        lines += [f"> {ln}" if ln else ">" for ln in turn["text"].split("\n")]
        quoted.append("\n".join(lines))
    return f"{HEADING}\n\n{PR_MARKER}\n\n{provenance}\n\n" + "\n\n".join(quoted) + "\n"


def upsert_owner_request(body: str, section: str) -> str:
    """Place `section` at the end of `## Summary`, replacing any harness-written copy.

    With an empty `section`, a marked (harness-written) section is removed and an unmarked
    one is kept as it is. A second call with the same arguments returns the same body.
    """
    m = _HEADING_RE.search(body)
    if m:
        nxt = _NEXT_SECTION_RE.search(body, m.end())
        end = nxt.start() if nxt else len(body)
        existing = body[m.start():end]
        if section or PR_MARKER in existing:
            if nxt:
                body = body[:m.start()] + body[end:]
            else:
                body = body[:m.start()].rstrip("\n") + "\n"
    if not section:
        return body
    s = _SUMMARY_RE.search(body)
    nxt = _NEXT_SECTION_RE.search(body, s.end()) if s else None
    cut = nxt.start() if nxt else len(body)
    pre = body[:cut].rstrip("\n") + "\n\n" if body[:cut].strip() else ""
    body = pre + body[cut:]
    cut = len(pre)
    return body[:cut] + section.rstrip("\n") + "\n\n" + body[cut:]
