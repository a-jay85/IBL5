"""Local ledger of post-plan's own fix commits, keyed by diff fingerprint (ADR-0062 Update 2026-11-27)."""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

LEDGER_DIR = "ibl5-approval-ledger"
KINDS = ("fix", "fix-first-scope", "fix-first-finish")
_FP_RE = re.compile(r"[0-9a-f]{40}")


@dataclass(frozen=True)
class Link:
    kind: str
    from_fp: str
    to_fp: str
    sha: str
    files: tuple
    ts: str


def _is_hex(v) -> bool:
    return isinstance(v, str) and bool(_FP_RE.fullmatch(v))


def ledger_path(worktree: str, branch: str) -> Path:
    common = subprocess.run(
        ["git", "-C", worktree, "rev-parse", "--path-format=absolute", "--git-common-dir"],
        capture_output=True, text=True, timeout=30, check=True).stdout.strip()
    safe = branch.replace("/", "__")
    return Path(common) / LEDGER_DIR / f"{safe}.jsonl"


def append_link(worktree, branch, kind, from_fp, to_fp, sha, files) -> bool:
    """Append one link. False, and nothing written, on any bad field or I/O error."""
    if kind not in KINDS or not all(_is_hex(v) for v in (from_fp, to_fp, sha)):
        return False
    files = sorted({f for f in (files or []) if isinstance(f, str) and f})
    try:
        path = ledger_path(worktree, branch)
        path.parent.mkdir(parents=True, exist_ok=True)
        rec = {"v": 1, "kind": kind, "from": from_fp, "to": to_fp, "sha": sha, "files": files,
               "ts": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")}
        with path.open("a", encoding="utf-8") as fh:
            fh.write(json.dumps(rec, sort_keys=True) + "\n")
        return True
    except (OSError, subprocess.SubprocessError, ValueError):
        return False


def read_links(worktree, branch) -> Optional[list]:
    """[] when no ledger exists; None when it cannot be read or any line is malformed."""
    try:
        path = ledger_path(worktree, branch)
        if not path.exists():
            return []
        lines = path.read_text(encoding="utf-8").splitlines()
    except (OSError, subprocess.SubprocessError, ValueError):
        return None
    links = []
    for raw in lines:
        try:
            rec = json.loads(raw)
            link = Link(rec["kind"], rec["from"], rec["to"], rec["sha"], tuple(rec["files"]), str(rec["ts"]))
        except (ValueError, KeyError, TypeError):
            return None  # malformed line: fail closed
        if (link.kind not in KINDS or not all(_is_hex(v) for v in (link.from_fp, link.to_fp, link.sha))
                or not all(isinstance(f, str) and f for f in link.files)):
            return None  # bad field: fail closed
        links.append(link)
    return links


def main(argv=None) -> int:
    p = argparse.ArgumentParser(prog="approval_ledger")
    sub = p.add_subparsers(dest="cmd", required=True)
    a = sub.add_parser("append")
    for flag in ("--worktree", "--branch", "--kind", "--from", "--to", "--sha", "--files-file"):
        a.add_argument(flag, required=True)
    args = p.parse_args(argv)
    try:
        files = Path(args.files_file).read_text(encoding="utf-8").splitlines()
    except OSError:
        return 1
    ok = append_link(args.worktree, args.branch, args.kind, getattr(args, "from"), args.to, args.sha, files)
