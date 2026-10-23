"""Corpus miners for the reviewer eval: print candidates, never write a file.

`mine bugs` finds fix commits whose removed lines blame back to an introducing PR.
`mine clean` finds settled PRs that no later fix blamed. Both print one JSON object
per line for a curator to paste into the corpus after review.
"""
from __future__ import annotations

import datetime
import json
import re
from pathlib import Path
from typing import Iterable, TextIO

from .reviewer_eval import (
    EvalUsageError,
    _gitt,
    blame_records,
    blamed_by_fix_within,
    case_input,
    commit_time,
    diff_line_sets,
    subject,
)

MAX_FIX_LINES = 80
MAX_BUG_DIFF_BYTES = 60_000
MAX_CLEAN_DIFF_BYTES = 40_000
SKIP_TYPES = {"chore", "docs", "test", "ci", "style"}
PHP_SCOPE_RE = re.compile(r"^ibl5/(classes|modules)/.*\.php$")

_FIX_SUBJECT_RE = re.compile(r"^fix(\(|:|!)")
_PR_SUFFIX_RE = re.compile(r"\(#(\d+)\)$")
_TYPE_RE = re.compile(r"^[^(:!]*")


def in_scope(path: str) -> bool:
    if not PHP_SCOPE_RE.match(path):
        return False
    return "/tests/" not in path and not path.endswith("Test.php")


def conventional_type(subj: str) -> str:
    return _TYPE_RE.match(subj).group(0).strip()  # type: ignore[union-attr]


def pr_number(subj: str) -> int | None:
    m = _PR_SUFFIX_RE.search(subj.strip())
    return int(m.group(1)) if m else None


def _changed_in_scope(root: Path, sha: str) -> list[str]:
    r = _gitt(root, "diff", "--name-only", f"{sha}^", sha)
    if r.returncode != 0:
        return []
    return [p for p in r.stdout.splitlines() if p and in_scope(p)]


def _runs(lines: Iterable[int]) -> list[tuple[int, int]]:
    runs: list[tuple[int, int]] = []
    for n in sorted(set(lines)):
        if runs and n == runs[-1][1] + 1:
            runs[-1] = (runs[-1][0], n)
        else:
            runs.append((n, n))
    return runs


def _emit(obj: dict, out: TextIO) -> None:
    print(json.dumps(obj, sort_keys=False), file=out)


def mine_bugs(root: Path, *, ref: str, since: str, limit: int, out: TextIO) -> int:
    r = _gitt(root, "log", "--format=%H%x09%s", f"--since={since}", ref)
    if r.returncode != 0:
        raise EvalUsageError(f"git log failed: {r.stderr.strip()}")
    seen: set[tuple[str, str]] = set()
    emitted = 0
    for row in r.stdout.splitlines():
        if emitted >= limit:
            break
        fix, _, fix_subj = row.partition("\t")
        fix_pr = pr_number(fix_subj)
        if not _FIX_SUBJECT_RE.match(fix_subj) or fix_pr is None:
            continue
        files = _changed_in_scope(root, fix)
        removed_by_file: dict[str, set[int]] = {}
        total = 0
        for f in files:
            removed, added = diff_line_sets(root, fix, f)
            removed_by_file[f] = removed
            total += len(removed) + len(added)
        if total == 0 or total > MAX_FIX_LINES:
            continue
        groups: dict[tuple[str, str], set[int]] = {}
        for f in files:
            for sha, orig, orig_path in blame_records(root, f"{fix}^", f, removed_by_file[f]):
                groups.setdefault((sha, orig_path), set()).add(orig)
        for (sha, orig_path), origs in groups.items():
            if emitted >= limit:
                break
            if sha == fix or (sha, fix) in seen:
                continue
            intro_subj = subject(root, sha)
            intro_pr = pr_number(intro_subj)
            if intro_pr is None or conventional_type(intro_subj) in SKIP_TYPES:
                continue
            try:
                diff_bytes = len(case_input(root, sha)[1].filtered_diff.encode())
            except EvalUsageError:
                continue
            if diff_bytes > MAX_BUG_DIFF_BYTES:
                continue
            _, intro_added = diff_line_sets(root, sha, orig_path)
            hit = origs & intro_added
            if not hit:
                continue
            seen.add((sha, fix))
            stem = re.sub(r"[^a-z0-9]+", "-", Path(orig_path).stem.lower()).strip("-") or "file"
            _emit({
                "id": f"bug-pr{intro_pr}-{stem}",
                "kind": "bug",
                "status": "unconfirmed",
                "pr": intro_pr,
                "commit": sha,
                "fix_pr": fix_pr,
                "fix_commit": fix,
                "expected": [{"path": orig_path, "start": a, "end": b} for a, b in _runs(hit)],
                "description": "",
                "_gap_days": int((commit_time(root, fix) - commit_time(root, sha)) // 86400),
                "_diff_bytes": diff_bytes,
                "_fix_subject": fix_subj,
                "_intro_subject": intro_subj,
            }, out)
            emitted += 1
    return 0


def mine_clean(root: Path, *, ref: str, window_days: int, limit: int, now: float,
               out: TextIO) -> int:
    r = _gitt(root, "log", "--format=%H%x09%s%x09%ct", ref)
    if r.returncode != 0:
        raise EvalUsageError(f"git log failed: {r.stderr.strip()}")
    today = datetime.datetime.fromtimestamp(now, datetime.timezone.utc).date().isoformat()
    emitted = 0
    for row in r.stdout.splitlines():
        if emitted >= limit:
            break
        head, _, ct = row.rpartition("\t")
        sha, _, subj = head.partition("\t")
        n = pr_number(subj)
        if n is None or conventional_type(subj) in SKIP_TYPES:
            continue
        if int(ct) + window_days * 86400 > now:
            continue
        if not _changed_in_scope(root, sha):
            continue
        try:
            diff_bytes = len(case_input(root, sha)[1].filtered_diff.encode())
        except EvalUsageError:
            continue
        if diff_bytes > MAX_CLEAN_DIFF_BYTES:
            continue
        if blamed_by_fix_within(root, sha, window_days, ref):
            continue
        _emit({"id": f"clean-pr{n}", "pr": n, "commit": sha, "checked_through": today}, out)
        emitted += 1
    return 0
