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

import json
import re
import shutil
from dataclasses import dataclass, replace
from pathlib import Path
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


# -- Phase 2: resolver justifications --
ADAPTED_PREFIX = "ADAPTED-LINE: "

RESOLVER_ADAPTATION_INSTRUCTIONS = (
    "If a line the branch ADDED must change only to follow a rewrite master itself made "
    "elsewhere in this same file (a renamed path, variable, or helper), you may adapt it. "
    "For each adapted line add, on its own line before the final verdict line, exactly: "
    'ADAPTED-LINE: {"from": "<branch line as added>", "to": "<line you wrote>", '
    '"old": "<substring master replaced>", "new": "<master\'s replacement>"} '
    "as one valid JSON object. Adapt nothing master did not rewrite in this file; any "
    "other change to a branch-added line fails the tree proof and the whole run."
)

_JUSTIFICATION_KEYS = frozenset({"from", "to", "old", "new"})


@dataclass(frozen=True)
class Justification:
    path: str
    original: str
    adapted: str
    old: str
    new: str


def _violation(line: str, why: str) -> str:
    return f"{why}: {line}"[:200]


def parse_justifications(
    reply: str, path: str
) -> tuple[tuple[Justification, ...], str]:
    """Parse the resolver's `ADAPTED-LINE:` JSON lines. Returns (records, reason).

    The first malformed line returns `((), reason)`; no such lines returns `((), "")`."""
    found: list[Justification] = []
    for raw in reply.splitlines():
        line = raw.strip()
        if not line.startswith(ADAPTED_PREFIX):
            continue
        try:
            obj = json.loads(line[len(ADAPTED_PREFIX):])
        except ValueError as exc:
            return (), _violation(line, f"invalid JSON ({exc})")
        if not isinstance(obj, dict) or set(obj) != _JUSTIFICATION_KEYS:
            return (), _violation(
                line, "expected a JSON object with exactly the keys from, to, old, new"
            )
        if not all(isinstance(v, str) and v for v in obj.values()):
            return (), _violation(line, "every value must be a non-empty string")
        if obj["from"] == obj["to"] or obj["old"] == obj["new"]:
            return (), _violation(line, "from must differ from to and old from new")
        record = Justification(path, obj["from"], obj["to"], obj["old"], obj["new"])
        if record not in found:
            found.append(record)
    return tuple(found), ""


# -- Phase 3: adaptation reviewer --
ADAPT_CONFIRMED = "CONFIRMED"
ADAPT_DENIED = "DENIED"
ADAPT_ABSENT = "ABSENT"
ADAPT_TOKEN_RE = re.compile(r"^ADAPTED-LINE-(\d+)=(CONFIRMED|DENIED)$")

ADAPT_REVIEW_PROMPT_HEAD = (
    "You are reviewing adapted lines from an automated conflict resolution. "
    "adaptations.txt numbers each line the branch added that the resolver rewrote. "
    "For each number CONFIRM only when all three hold: (1) the adapted line equals the "
    "original with only the named old -> new substring rewrite applied to every "
    "occurrence and nothing else changed; (2) master-side.diff shows master itself "
    "rewrote old to new in that same file; (3) the rewrite does not change what the "
    "line does beyond following master's rename. Otherwise DENY. Read the worktree file "
    "and master-side.diff before judging. Reply with one line per number that is exactly "
    "ADAPTED-LINE-<n>=CONFIRMED or exactly ADAPTED-LINE-<n>=DENIED, one token per line, "
    "nothing else on those lines, each number exactly once. Reasoning goes after the "
    "tokens."
)


def parse_adaptation_verdicts(reply: str, n: int) -> dict[int, str]:
    """Per-index verdicts for 1..n from exact-token reply lines.

    A line counts only when its stripped text is the token in full. One distinct token
    yields it, both tokens for one index yield DENIED, none yields ABSENT."""
    seen: dict[int, set[str]] = {}
    for raw in reply.splitlines():
        m = ADAPT_TOKEN_RE.match(raw.strip())
        if m is None:
            continue
        idx = int(m.group(1))
        if 1 <= idx <= n:
            seen.setdefault(idx, set()).add(m.group(2))
    out: dict[int, str] = {}
    for idx in range(1, n + 1):
        tokens = seen.get(idx, set())
        if len(tokens) == 1:
            out[idx] = next(iter(tokens))
        elif tokens:
            out[idx] = ADAPT_DENIED
        else:
            out[idx] = ADAPT_ABSENT
    return out


def _render_adaptation_blocks(matches: tuple[NearMatch, ...]) -> str:
    blocks: list[str] = []
    for n, m in enumerate(matches, start=1):
        lines = [
            f"[{n}] file: {m.path}",
            f"    original: {m.original}",
            f"    adapted:  {m.adapted}",
        ]
        for t in m.transforms:
            lines.append(f"    rewrite:  {t.old} -> {t.new}")
        blocks.append("\n".join(lines) + "\n")
    return "".join(blocks)


def review_adaptations(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    matches: tuple[NearMatch, ...],
    merge_base: str,
    master_sha: str,
) -> tuple[bool, str, dict[int, str]]:
    """Read-only per-line review of adapted lines. Fails closed on anything but all CONFIRMED."""
    if not matches:
        return False, "adaptation review: nothing to review", {}

    review_dir = Path(f"/tmp/postplan-adapt-review-{key}")
    shutil.rmtree(review_dir, ignore_errors=True)
    review_dir.mkdir(parents=True, exist_ok=True)

    blocks = _render_adaptation_blocks(matches)
    (review_dir / "adaptations.txt").write_text(blocks)
    diff = run(
        "diff", "-U3", "--no-color", merge_base, master_sha, "--",
        *sorted({m.path for m in matches}),
    )
    (review_dir / "master-side.diff").write_text(diff)
    pre_patch_src = Path(f"/tmp/pr-ready-diff-pre-{key}.patch")
    if pre_patch_src.exists():
        shutil.copy(pre_patch_src, review_dir / "pre-rebase.patch")

    prompt = ADAPT_REVIEW_PROMPT_HEAD + "\n\n" + blocks

    reply = ""
    try:
        reply = llm.call_tooled(
            "conflict-adapt-review",
            "sonnet",
            prompt,
            cwd=worktree,
            allowed_tools=("Read",),
            denied_tools=("Bash", "Agent", "Write", "Edit"),
            add_dirs=(str(review_dir),),
        )
    except Exception:
        reply = ""
    if not isinstance(reply, str):
        reply = ""

    verdicts = parse_adaptation_verdicts(reply, len(matches))
    (review_dir / "verdict.txt").write_text(
        "".join(f"ADAPTED-LINE-{n}={v}\n" for n, v in verdicts.items()) + "\n" + reply
    )
    bad = [(n, v) for n, v in verdicts.items() if v != ADAPT_CONFIRMED]
    if not bad:
        return True, "", verdicts
    return (
        False,
        "adaptation review: " + ", ".join(f"[{n}]={v}" for n, v in bad),
        verdicts,
    )


# -- Phase 4: proof acceptance and audit --
@dataclass(frozen=True)
class AcceptedAdaptation:
    path: str
    original: str
    adapted: str
    old: str
    new: str


def _pair_justifications(
    matches: tuple[NearMatch, ...], justifications: tuple[Justification, ...]
) -> tuple[tuple[AcceptedAdaptation, ...], str]:
    """Pair each near-match with one unused resolver justification naming its rewrite."""
    used: set[int] = set()
    accepted: list[AcceptedAdaptation] = []
    for m in matches:
        for i, j in enumerate(justifications):
            if (i not in used and (j.path, j.original, j.adapted) == (m.path, m.original, m.adapted)
                    and Transform(j.old, j.new) in m.transforms):
                used.add(i)
                accepted.append(AcceptedAdaptation(m.path, m.original, m.adapted, j.old, j.new))
                break
        else:
            return (), f"no resolver justification for {m.path}: +{m.original}"
    return tuple(accepted), ""


def _accept(llm, run, *, worktree, key, proof_out, rc, resolved_files, justifications,
            merge_base, master_sha):
    if rc != 0:
        return False, f"adaptation not applicable: proof exited {rc}", ()
    if llm is None:
        return False, "adaptation not applicable: no LLM", ()
    if not resolved_files:
        return False, "adaptation not applicable: no model-resolved files", ()
    if not merge_base:
        return False, "adaptation not applicable: no merge-base", ()
    if not justifications:
        return False, "LOST lines present but the resolver claimed no adaptation", ()
    lost, reason = parse_lost_lines(proof_out, tuple(resolved_files))
    if reason:
        return False, reason, ()
    pre = Path(f"/tmp/pr-ready-diff-pre-{key}.patch")
    if not pre.exists():
        return False, f"pre-rebase patch missing: {pre}", ()
    reason = cross_check_pre_patch(pre.read_bytes(), lost)
    if reason:
        return False, reason, ()
    matches, reason = accept_deterministic(
        run, lost=lost, merge_base=merge_base, master_sha=master_sha)
    if reason:
        return False, reason, ()
    accepted, reason = _pair_justifications(matches, tuple(justifications))
    if reason:
        return False, reason, ()
    ok, reason, _verdicts = review_adaptations(
        llm, run, worktree=worktree, key=key, matches=matches,
        merge_base=merge_base, master_sha=master_sha)
    if not ok:
        return False, reason, ()
    lines = [proof_out.rstrip("\n"),
             f"ADAPTED-LINES: {len(accepted)} accepted "
             "(near-match + resolver justification + reviewer CONFIRMED)"]
    lines += [f"ADAPTED: {a.path}: +{a.original} => +{a.adapted} [{a.old} -> {a.new}]"
              for a in accepted]
    return True, "\n".join(lines) + "\n", accepted


def accept_adapted_proof(
    llm,
    run: Callable[..., str],
    *,
    worktree: str,
    key: str,
    proof_out: str,
    rc: int,
    resolved_files: tuple,
    justifications: tuple,
    merge_base: str,
    master_sha: str,
) -> tuple[bool, str, tuple[AcceptedAdaptation, ...]]:
    """Accept a diverged proof only when every LOST line is a justified, confirmed adaptation.

    Never appends TREE-EQUIVALENT. Any exception fails closed."""
    try:
        return _accept(llm, run, worktree=worktree, key=key, proof_out=proof_out, rc=rc,
                       resolved_files=resolved_files, justifications=justifications,
                       merge_base=merge_base, master_sha=master_sha)
    except Exception as exc:
        return False, f"adaptation check raised {type(exc).__name__}: {exc}"[:300], ()


def audit_lines_for_adaptations(adapted) -> tuple[str, ...]:
    """audit.log lines for accepted adaptations: one per entry plus a summary; () when none."""
    if not isinstance(adapted, (tuple, list)) or not adapted:
        return ()
    out = [f"adapted line accepted: {a.path}: +{a.original} => +{a.adapted} "
           f"[{a.old} -> {a.new}] (master transformation in same file; resolver justified; "
           "reviewer CONFIRMED)" for a in adapted]
    out.append(f"adapted-lines={len(adapted)} accepted; condition (14) hold stays set")
    return tuple(out)
