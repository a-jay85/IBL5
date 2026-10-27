"""Verifier vote reuse keyed on the exact chunk prompt and a clean worktree tree id.

The verifier reads the whole worktree with Read, Grep and Glob, so a verdict can rest on
any file in it. The only safe key is the chunk prompt plus the `HEAD^{tree}` id of a fully
clean worktree (no modified or untracked files), plus model, purposes and tool list.

One entry holds all seats for one chunk. Callers store it only when every seat returned a
vote for every finding, and `load` returns it only when it covers every seat and index.

Takes every constant as an argument so it never imports `harness.review`.
"""
from __future__ import annotations

import hashlib
import json
import os
import subprocess

CACHE_VERSION = 1       # bump when the verdict contract text or the vote tuple shape changes
SCHEMA_VERSION = 1
_VERDICTS = ("real", "not-real")


def enabled() -> bool:
    return os.environ.get("POSTPLAN_VERDICT_CACHE", "1") != "0"


def _git(worktree: str, *args: str) -> str | None:
    try:
        r = subprocess.run(["git", "-C", worktree, *args], capture_output=True, text=True, timeout=30)
    except (OSError, subprocess.SubprocessError):
        return None
    if r.returncode != 0:
        return None
    return r.stdout


def tree_state(worktree: str) -> str | None:
    """`HEAD^{tree}` of a fully clean worktree, else None (dirty, untracked files, or git failure)."""
    status = _git(worktree, "status", "--porcelain", "--untracked-files=all")
    if status is None or status.strip():
        return None
    tree = _git(worktree, "rev-parse", "HEAD^{tree}")
    return tree.strip() if tree and tree.strip() else None


def cache_dir(worktree: str) -> str | None:
    git_dir = _git(worktree, "rev-parse", "--absolute-git-dir")
    if not git_dir or not git_dir.strip():
        return None
    return os.path.join(git_dir.strip(), "postplan-verdict-cache")


def key(*, prompt: str, tree: str, model: str, purposes: tuple, tools: tuple) -> str:
    payload = json.dumps([CACHE_VERSION, model, list(purposes), list(tools), tree, prompt])
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def _valid_vote(v) -> bool:
    return (isinstance(v, list) and len(v) == 3 and v[0] in _VERDICTS
            and isinstance(v[1], str) and isinstance(v[2], bool))


def load(dirpath: str, k: str, n: int, seats: int) -> list[dict] | None:
    """The seat vote sets for chunk key `k`, or None unless every seat covers every index 1..n."""
    try:
        with open(os.path.join(dirpath, f"{k}.json")) as fh:
            data = json.load(fh)
    except (OSError, ValueError):
        return None
    if not isinstance(data, dict) or data.get("schema_version") != SCHEMA_VERSION:
        return None
    entries = data.get("seats")
    if not isinstance(entries, list) or len(entries) != seats:
        return None
    for entry in entries:
        if not isinstance(entry, dict):
            return None
        for p in range(1, n + 1):
            if not _valid_vote(entry.get(str(p))):
                return None
    return entries


def store(dirpath: str, k: str, seat_local_votes: list[dict]) -> None:
    """Write one chunk entry atomically. Never raises: a cache write failure must not fail a review."""
    final = os.path.join(dirpath, f"{k}.json")
    tmp = f"{final}.tmp"
    try:
        os.makedirs(dirpath, exist_ok=True)
        with open(tmp, "w") as fh:
            json.dump({"schema_version": SCHEMA_VERSION, "seats": seat_local_votes}, fh, sort_keys=True)
        os.replace(tmp, final)
    except OSError:
        pass
