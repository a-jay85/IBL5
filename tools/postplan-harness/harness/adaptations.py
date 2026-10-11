"""Deterministic adaptation of lost-work proof lines.

When master rewrites a neighbouring line (for example `/tmp/` to `"$RL"/`), the conflict
resolver carries the same rewrite onto the branch's own added line, and the change-level
lost-work proof then reports that adapted line as LOST. This module recognises that shape
without a model: it parses the proof's LOST lines, derives the edit transforms master made
between the merge-base and its tip, and finds the HEAD line that is the original line with
those transforms applied.

Pure functions over strings plus one stub-able `run` callable shaped like the helper in
`conflict.py` (`run(*args, check=True) -> str`, returning stdout of a git command). This
module never imports `conflict.py`: that module imports this one, so a reverse import
would cycle.
"""
from __future__ import annotations

import re
from dataclasses import dataclass, replace
from typing import Callable

# A reviewer confirms each adapted line in a later phase; 50 is the most one read-only
# call can judge line by line.
MAX_ADAPTED_LINES = 50
DIVERGED_VERDICT = "TREE DIVERGED — inspect before pushing"
CHECKED_RE = re.compile(r"^CHECKED: files=(\d+) added=(\d+) deleted=(\d+)$")

_ALNUM_RE = re.compile(r"[A-Za-z0-9]")


@dataclass(frozen=True)
class LostLine:
    path: str
    original: str


@dataclass(frozen=True)
class Transform:
    old: str
    new: str


@dataclass(frozen=True)
class NearMatch:
    path: str
    original: str
    adapted: str
    transforms: tuple[Transform, ...]


def _norm(line: str) -> str | None:
    """Strip one trailing CR; None for a line with control chars or no alphanumeric."""
    if line.endswith("\r"):
        line = line[:-1]
    for ch in line:
        if ord(ch) < 32 and ch != "\t":
            return None
    if not _ALNUM_RE.search(line):
        return None
    return line


def parse_lost_lines(
    proof_out: str, resolved_files: tuple[str, ...] | list[str] | frozenset[str]
) -> tuple[tuple[LostLine, ...], str]:
    """Parse lostwork.sh output into `(lines, "")`, or `((), reason)` when not adaptable."""
    out_lines = proof_out.split("\n")
    checked = None
    for line in out_lines:
        m = CHECKED_RE.match(line.rstrip("\r"))
        if m:
            checked = m
            break
    if checked is None:
        return (), "no CHECKED line in the proof output"
    if DIVERGED_VERDICT not in (ln.rstrip("\r") for ln in out_lines):
        return (), "proof verdict is not a plain TREE DIVERGED"

    added = int(checked.group(2))
    ordered = sorted(set(resolved_files), key=len, reverse=True)
    lost_count = 0
    parsed: list[LostLine] = []
    for line in out_lines:
        if not line.startswith("LOST: "):
            continue
        lost_count += 1
        for f in ordered:
            prefix = f"LOST: {f}: +"
            if not line.startswith(prefix):
                continue
            body = _norm(line[len(prefix):])
            if body is not None:
                parsed.append(LostLine(f, body))
            break
    if lost_count != len(parsed):
        return (), "a LOST line is not an added line in a resolved file"
    if not parsed:
        return (), "no LOST lines to adapt"
    if len(parsed) > added:
        return (), f"{len(parsed)} LOST lines exceed the {added} added lines checked"
    if len(parsed) > MAX_ADAPTED_LINES:
        return (), f"{len(parsed)} LOST lines exceed the cap of {MAX_ADAPTED_LINES}"
    return tuple(parsed), ""


def cross_check_pre_patch(pre_patch: bytes, lost: tuple[LostLine, ...]) -> str:
    """Return a reason naming the first LostLine the PRE patch did not add, else ''."""
    added: dict[str, set[str]] = {}
    current: str | None = None
    for line in pre_patch.decode("utf-8", errors="replace").splitlines():
        if line.startswith("+++ "):
            target = line[4:]
            current = target[2:] if target.startswith("b/") else None
            continue
        if line.startswith("+") and current is not None:
            body = _norm(line[1:])
            if body is not None:
                added.setdefault(current, set()).add(body)
    for item in lost:
        if item.original not in added.get(item.path, set()):
            return f"LOST line not added by the pre-rebase patch: {item.path}: +{item.original}"
    return ""


def _strip_affixes(old: str, new: str) -> tuple[str, str]:
    n = min(len(old), len(new))
    p = 0
    while p < n and old[p] == new[p]:
        p += 1
    old, new = old[p:], new[p:]
    n = min(len(old), len(new))
    s = 0
    while s < n and old[len(old) - 1 - s] == new[len(new) - 1 - s]:
        s += 1
    if s:
        old, new = old[: len(old) - s], new[: len(new) - s]
    return old, new


def _body(line: str) -> str:
    body = line[1:]
    return body[:-1] if body.endswith("\r") else body


def derive_master_transforms(
    run: Callable[..., str], *, path: str, merge_base: str, master_sha: str
) -> tuple[Transform, ...]:
    """Edit transforms master made to `path` between the merge-base and its tip."""
    text = run("diff", "-U0", "--no-color", merge_base, master_sha, "--", path)
    hunks: list[tuple[list[str], list[str]]] = []
    for line in text.split("\n"):
        if line.startswith("@@"):
            hunks.append(([], []))
            continue
        if not hunks or line.startswith("\\"):
            continue
        if line.startswith("-"):
            hunks[-1][0].append(_body(line))
        elif line.startswith("+"):
            hunks[-1][1].append(_body(line))
    out: list[Transform] = []
    for minus, plus in hunks:
        if len(minus) != len(plus):
            continue
        for old_line, new_line in zip(minus, plus):
            old, new = _strip_affixes(old_line, new_line)
            if not old or not new or old == new or not _ALNUM_RE.search(old):
                continue
            t = Transform(old, new)
            if t not in out:
                out.append(t)
    return tuple(out)


def near_match(
    original: str, transforms: tuple[Transform, ...], head_lines: frozenset[str]
) -> NearMatch | None:
    """First master-transformed variant of `original` that exists in `head_lines`.

    `str.replace` rewrites every occurrence, so a HEAD line where only some occurrences
    changed does not match: partial rewrites stay LOST.
    """
    candidates: list[tuple[str, tuple[Transform, ...]]] = []
    for t in transforms:
        if t.old in original:
            candidates.append((original.replace(t.old, t.new), (t,)))
    cur = original
    applied: list[Transform] = []
    for t in transforms:
        if t.old in cur:
            cur = cur.replace(t.old, t.new)
            applied.append(t)
    if applied:
        candidates.append((cur, tuple(applied)))
    for adapted, used in candidates:
        if adapted != original and adapted in head_lines:
            return NearMatch("", original, adapted, used)
    return None


def _lines_of(text: str) -> list[str]:
    out: list[str] = []
    for line in text.split("\n"):
        n = _norm(line)
        if n is not None:
            out.append(n)
    return out


def accept_deterministic(
    run: Callable[..., str],
    *,
    lost: tuple[LostLine, ...],
    merge_base: str,
    master_sha: str,
) -> tuple[tuple[NearMatch, ...], str]:
    """Match every LostLine to its master-adapted HEAD line, or reject the whole set."""
    paths = list(dict.fromkeys(item.path for item in lost))
    head_lines: dict[str, list[str]] = {}
    master_lines: dict[str, list[str]] = {}
    transforms: dict[str, tuple[Transform, ...]] = {}
    for path in paths:
        head_lines[path] = _lines_of(run("show", f"HEAD:{path}"))
        master_lines[path] = _lines_of(run("show", f"{master_sha}:{path}", check=False))
        transforms[path] = derive_master_transforms(
            run, path=path, merge_base=merge_base, master_sha=master_sha
        )

    matches: list[NearMatch] = []
    claims: dict[tuple[str, str], int] = {}
    for item in lost:
        found = near_match(
            item.original, transforms[item.path], frozenset(head_lines[item.path])
        )
        if found is None:
            return (), f"no near-match for {item.path}: +{item.original}"
        found = replace(found, path=item.path)
        matches.append(found)
        key = (item.path, found.adapted)
        claims[key] = claims.get(key, 0) + 1

    for (path, adapted), count in claims.items():
        if head_lines[path].count(adapted) - master_lines[path].count(adapted) < count:
            return (), f"adapted line is not the branch's own edit in {path}: +{adapted}"
    return tuple(matches), ""
