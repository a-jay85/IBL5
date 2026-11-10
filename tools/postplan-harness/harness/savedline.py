"""The Merge danger line the harness last wrote, per branch slug, plus a sticky human tombstone.

A later post-plan phase compares the line the PR carried at round start with the saved line.
A match means the harness owns the line, so the phase may re-judge the newest LLM verdict with
the Phase 2 raise bar and lower the door back to the floor. A mismatch means a human edited the
line: the caller writes a tombstone, and the phase stays raise-only for the rest of the PR's life.

Records: `{"owner": "harness", "line": "<rendered line>"}` or `{"owner": "human"}`. Every read
failure returns None or False, which callers treat as human-owned (fail closed). A lost write
reads back as missing state, which also fails closed. This module only reads and writes files
under `state_dir`.
"""
from __future__ import annotations

import json
import os
import tempfile

from harness.statefile import safe_slug

SUFFIX = ".merge-danger-line.json"


def path_for(state_dir: str, slug: str) -> str:
    return os.path.join(state_dir, f"{safe_slug(slug)}{SUFFIX}")


def _read(state_dir: str, slug: str):
    try:
        with open(path_for(state_dir, slug), encoding="utf-8") as fh:
            payload = json.load(fh)
    except (OSError, ValueError):
        return None
    return payload if isinstance(payload, dict) else None


def load_line(state_dir: str, slug: str) -> str | None:
    """The harness-owned saved line, or None on any failure, a tombstone or a foreign record."""
    try:
        payload = _read(state_dir, slug)
        if payload is None:
            return None
        if payload.get("owner") != "harness":
            return None
        line = payload.get("line")
        if not isinstance(line, str) or not line:
            return None
        return line
    except Exception:  # noqa: BLE001 - fail closed: unknown state reads as human-owned
        return None


def is_human_owned(state_dir: str, slug: str) -> bool:
    """True only for a readable tombstone."""
    try:
        payload = _read(state_dir, slug)
        return payload is not None and payload.get("owner") == "human"
    except Exception:  # noqa: BLE001
        return False


def _write(state_dir: str, slug: str, record: dict) -> bool:
    tmp = None
    try:
        os.makedirs(state_dir, exist_ok=True)
        fd, tmp = tempfile.mkstemp(dir=state_dir, prefix=".mdline-", suffix=".tmp")
        with os.fdopen(fd, "w", encoding="utf-8") as fh:
            json.dump(record, fh)
        os.replace(tmp, path_for(state_dir, slug))
        return True
    except OSError:
        if tmp is not None:
            try:
                os.unlink(tmp)
            except OSError:
                pass
        return False


def save_line(state_dir: str, slug: str, line: str) -> bool:
    """Save the harness's rendered line. False, with no write, over a tombstone or on failure."""
    if is_human_owned(state_dir, slug):
        return False
    return _write(state_dir, slug, {"owner": "harness", "line": line})


def mark_human(state_dir: str, slug: str) -> None:
    """Write the tombstone: the line was edited outside the harness."""
    _write(state_dir, slug, {"owner": "human"})
