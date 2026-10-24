"""Regenerate a conflicted lockfile from its already-resolved manifest.

A lockfile is a derived artifact. The manifest goes through the normal resolver, then the
lockfile is rebuilt from master's copy plus the resolved manifest. No model ever edits
lockfile text. Lifecycle scripts never run. The bound below is local to this module.
"""
from __future__ import annotations

import os
import shlex
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional

from .conflict import UNRESOLVABLE_LOCKFILE, _CONFLICT_MARKER_PAT

LOCKFILE_REGEN_TIMEOUT = 600  # seconds; bun/composer resolve over the network


@dataclass(frozen=True)
class RegenLockfile:
    lock: str                    # repo-relative lockfile path, exact match
    manifest: str                # repo-relative manifest the lock is derived from
    argv: tuple[str, ...]        # regen command; must not run lifecycle scripts
    cwd: str                     # repo-relative directory the command runs in


REGENERABLE_LOCKFILES: tuple[RegenLockfile, ...] = (
    RegenLockfile("ibl5/bun.lock", "ibl5/package.json",
                  ("bun", "install", "--lockfile-only", "--ignore-scripts"), "ibl5"),
    RegenLockfile("ibl5/composer.lock", "ibl5/composer.json",
                  ("composer", "update", "--minimal-changes", "--no-install",
                   "--no-scripts", "--no-plugins", "--no-interaction"), "ibl5"),
)


class LockfileRegenError(RuntimeError):
    """Message is the full `lockfile: <path> (...)` decline reason."""


def regenerable(path: str) -> Optional[RegenLockfile]:
    return next((e for e in REGENERABLE_LOCKFILES if e.lock == path), None)


def command_text(entry: RegenLockfile) -> str:
    return shlex.join(entry.argv)


def regen_lockfile(
    entry: RegenLockfile,
    *,
    run: Callable[..., str],
    worktree: str,
    master_sha: str,
    timeout: Optional[int] = None,
    runner: Callable[..., "subprocess.CompletedProcess[str]"] = subprocess.run,
) -> str:
    """Rebuild `entry.lock` from master's copy plus the resolved manifest and stage it.
    Returns the command text. Every failure raises LockfileRegenError."""
    if timeout is None:
        timeout = LOCKFILE_REGEN_TIMEOUT
    cmd = command_text(entry)
    prefix = f"{UNRESOLVABLE_LOCKFILE}: {entry.lock} ("

    # Master's resolution is the starting point. This also clears the path's unmerged
    # stages, so bun and composer never see a file full of conflict markers.
    run("checkout", master_sha, "--", entry.lock)
    untracked_before = set(run("ls-files", "--others", "--exclude-standard").split())

    try:
        proc = runner(
            list(entry.argv), cwd=str(Path(worktree) / entry.cwd),
            capture_output=True, text=True, errors="replace", timeout=timeout,
            env={**os.environ, "COMPOSER_NO_INTERACTION": "1", "CI": "1"})
    except subprocess.TimeoutExpired:
        raise LockfileRegenError(f"{prefix}regen timed out after {timeout}s: {cmd})")
    except FileNotFoundError:
        raise LockfileRegenError(f"{prefix}regen failed: {cmd}: command not found)")
    if proc.returncode != 0:
        tail = (proc.stderr or proc.stdout or "").strip()[-300:]
        raise LockfileRegenError(
            f"{prefix}regen failed: {cmd} exit {proc.returncode}: {tail})")

    lock_path = Path(worktree) / entry.lock
    text = lock_path.read_text(errors="replace") if lock_path.is_file() else ""
    if not text.strip():
        raise LockfileRegenError(f"{prefix}regen failed: {cmd} wrote no {entry.lock})")
    if _CONFLICT_MARKER_PAT.search(text):
        raise LockfileRegenError(f"{prefix}regen failed: {cmd} left conflict markers)")

    changed = (set(run("diff", "--name-only").split())
               | (set(run("ls-files", "--others", "--exclude-standard").split())
                  - untracked_before))
    stray = sorted(changed - {entry.lock})
    if stray:
        raise LockfileRegenError(
            f"{prefix}regen touched unexpected paths: {', '.join(stray)[:300]})")

    run("add", "--", entry.lock)
    return cmd
