"""Does an owner approval carry across post-plan's own commits? (ADR-0062 Update 2026-11-27)"""
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from collections import deque
from dataclasses import dataclass
from pathlib import Path

from harness import approval_ledger, unplanned_scope
from harness.fidelity import denied_local_gate_edits

CARRY_MARKER_PREFIX = "<!-- owner-approve-carry "


@dataclass(frozen=True)
class CarryDecision:
    ok: bool
    reason: str
    src_id: str = ""
    src_mode: str = ""
    src_diff: str = ""
    from_fp: str = ""
    from_head: str = ""
    files: tuple = ()
    fix_files: tuple = ()
    fix_ts: str = ""


def genuine_approval(comments, me=None):
    """(created_at, comment, match) of the newest authentic owner-approve marker. Carry comments never count."""
    found = []
    for c in comments or []:
        if not isinstance(c, dict) or not unplanned_scope._authentic(c):
            continue
        body = (c.get("body") or "").replace("\r", "")
        if CARRY_MARKER_PREFIX in body:
            continue
        if me is not None and (c.get("user") or {}).get("login") != me:
            continue
        lines = [ln.strip() for ln in body.split("\n") if ln.strip()]
        m = unplanned_scope.APPROVAL_MARKER_RE.match(lines[-1]) if lines else None
        if m:
            found.append((c.get("created_at") or "", c, m))
    return max(found, key=lambda x: x[0]) if found else None


def _chain_files(links, start, goal, since):
    """Files of the fix links on a path start -> goal recorded at or after `since`; None when no path exists."""
    prev = {start: None}
    queue = deque([start])
    while queue:
        node = queue.popleft()
        if node == goal:
            break
        for link in links:
            if link.kind != "fix":
                continue
            if link.from_fp != node or link.ts < since:
                continue
            if link.to_fp not in prev:
                prev[link.to_fp] = link
                queue.append(link.to_fp)
    if goal not in prev:
        return None
    files, node = set(), goal
    while prev[node] is not None:
        files.update(prev[node].files)
        node = prev[node].from_fp
    return tuple(sorted(files))


def evaluate(comments, links, current_fp, me=None) -> CarryDecision:
    if not approval_ledger._is_hex(current_fp):
        return CarryDecision(False, "delta-error")
    if links is None:
        return CarryDecision(False, "delta-error")
    hit = genuine_approval(comments, me)
    if hit is None or hit[2].group("diff") == "unread":
        return CarryDecision(False, "no-approval")
    created, c, m = hit
    src = dict(src_id=str(c.get("id") or ""), src_mode=m.group("mode"), src_diff=m.group("diff"))
    start, start_head, fix_files, fix_ts = m.group("diff"), m.group("head"), (), ""
    if m.group("mode") == "fix-first":
        fin = [k for k in links if k.kind == "fix-first-finish" and k.from_fp == start and k.ts >= created]
        if not fin:
            return CarryDecision(False, "no-finish", **src)
        last = max(fin, key=lambda k: k.ts)
        start, start_head, fix_files, fix_ts = last.to_fp, last.sha, last.files, last.ts
    common = dict(src, from_fp=start, from_head=start_head, fix_files=fix_files, fix_ts=fix_ts)
    if start == current_fp:
        return CarryDecision(False, "unchanged", **common)
    files = _chain_files(links, start, current_fp, created)
    if files is None:
        return CarryDecision(False, "outside-commit", **common)
    if denied_local_gate_edits(files):
        return CarryDecision(False, "gate-file", files=files, **common)
    return CarryDecision(True, "eligible", files=files, **common)


def harness_evidence(comments, worktree, branch, base_ref, current_fp, pr_body):
    """OwnerApproval for the kept-scope downgrade when the approval reached this head by carry; else None."""
    links = approval_ledger.read_links(worktree, branch)
    d = evaluate(comments, links, current_fp)
    if d.ok and d.src_mode == "approve":
        return unplanned_scope.approval_from_comments(comments, d.src_diff)
    return None  # fix-first scope: Phase 6


def main(argv=None) -> int:
    p = argparse.ArgumentParser(prog="approval_carry")
    sub = p.add_subparsers(dest="cmd", required=True)
    d = sub.add_parser("check")
    for flag in ("--comments", "--worktree", "--branch", "--current-fp", "--me", "--files-out"):
        d.add_argument(flag, required=True)
    args = p.parse_args(argv)
    try:
        comments = json.loads(Path(args.comments).read_text(encoding="utf-8"))
        links = approval_ledger.read_links(args.worktree, args.branch)
        dec = evaluate(comments, links, args.current_fp, args.me)
        if dec.ok:
            Path(args.files_out).write_text("".join(f + "\n" for f in dec.files), encoding="utf-8")
    except Exception as exc:  # fail closed: any crash is a delta error
        print(f"CARRY no reason=delta-error detail={type(exc).__name__}")
        return 1
    if dec.ok:
        print(f"CARRY ok src={dec.src_id} from={dec.from_fp} head={dec.from_head}")
        return 0
    print(f"CARRY no reason={dec.reason}")
    return 1


if __name__ == "__main__":
    sys.exit(main())
