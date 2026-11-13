"""Offline half of the second-reviewer eval: corpus schema, digest, surface hash, score line."""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable, Optional

from harness import second_review  # noqa: F401  (CONDITION_NAMES used by later phases)
from harness.armable import SECOND_REVIEW_CLEARABLE
from harness.reviewer_eval import (
    EvalUsageError,
    _STATUSES,
    _last_line,  # noqa: F401  (used by later phases)
    _repo_root,
    read_git_ref,
    read_worktree,
)

SCORE_PREFIX = "second-review-eval"
ID_RE = re.compile(r"^[a-z][a-z0-9-]{2,63}$")
SHA_RE = re.compile(r"^[0-9a-f]{40}$")
SCORE_RE = re.compile(
    re.escape(SCORE_PREFIX) + r" v=([0-9a-f]{12}) c=([0-9a-f]{12})"
    r" missed_holds=(\d+)/(\d+) extra_holds=(\d+)/(\d+) degraded=(\d+)"
)
SURFACE_PATHS = ("tools/postplan-harness/harness/second_review.py", ".claude/agents/pr-second-reviewer.md")
FIDELITY_PATH = "tools/postplan-harness/harness/fidelity.py"
FIDELITY_LINE_RE = re.compile(r"^REMEDIATION_DIFF_INLINE_CAP\s*=.*$", re.MULTILINE)
DEFAULT_CORPUS = "tools/postplan-harness/fixtures/second-review-eval/corpus.json"

CASE_KEYS = {
    "id", "pr", "status", "expected", "label_basis", "base_sha", "replay_sha",
    "holds", "reason", "reason_source", "evidence",
}
EXPECTED = {"hold", "clear"}
LABEL_BASES = {"approved-as-is", "changed-after-hold", "needs-a-human", "reverted",
               "seat-cleared-clean"}
REASON_SOURCES = {"plan", "pr-body"}


def validate_schema(corpus: dict) -> list[str]:
    errors: list[str] = []
    if not isinstance(corpus, dict):
        return ["corpus must be an object"]
    if corpus.get("version") != 1:
        errors.append("corpus: version must be 1")
    cases = corpus.get("cases")
    if not isinstance(cases, list):
        errors.append("corpus: cases must be a list")
        return errors
    seen: set = set()
    for i, case in enumerate(cases):
        cid = case.get("id") if isinstance(case, dict) else None
        pre = f"case {i} ({cid}):"
        if not isinstance(case, dict):
            errors.append(f"{pre} must be an object")
            continue
        for k in sorted(set(case) - CASE_KEYS):
            errors.append(f"{pre} unknown key {k}")
        for k in sorted(CASE_KEYS - set(case)):
            errors.append(f"{pre} missing key {k}")
        if "id" in case:
            if not isinstance(cid, str) or not ID_RE.fullmatch(cid):
                errors.append(f"{pre} id must match {ID_RE.pattern}")
            elif cid in seen:
                errors.append(f"{pre} duplicate id")
            else:
                seen.add(cid)
        if "pr" in case:
            pr = case["pr"]
            if isinstance(pr, bool) or not isinstance(pr, int) or pr <= 0:
                errors.append(f"{pre} pr must be a positive int")
        if case.get("status") not in _STATUSES:
            errors.append(f"{pre} status must be one of {sorted(_STATUSES)}")
        if "expected" in case and case["expected"] not in EXPECTED:
            errors.append(f"{pre} expected must be hold or clear")
        if "label_basis" in case and case["label_basis"] not in LABEL_BASES:
            errors.append(f"{pre} label_basis must be one of {sorted(LABEL_BASES)}")
        for k in ("base_sha", "replay_sha"):
            if k in case and not (isinstance(case[k], str) and SHA_RE.fullmatch(case[k])):
                errors.append(f"{pre} {k} must be 40 hex")
        if "holds" in case:
            holds = case["holds"]
            if not isinstance(holds, dict) or not holds:
                errors.append(f"{pre} holds must be a non-empty object")
            else:
                for key, hv in holds.items():
                    if not str(key).isdigit() or int(key) not in SECOND_REVIEW_CLEARABLE:
                        errors.append(f"{pre} holds key {key!r} is not a clearable condition")
                    if not isinstance(hv, str) or not hv.strip():
                        errors.append(f"{pre} holds value for {key!r} must be a non-empty string")
        if "reason" in case and not (isinstance(case["reason"], str) and case["reason"].strip()):
            errors.append(f"{pre} reason must be non-empty")
        if "reason_source" in case and case["reason_source"] not in REASON_SOURCES:
            errors.append(f"{pre} reason_source must be plan or pr-body")
        if "evidence" in case and not isinstance(case["evidence"], str):
            errors.append(f"{pre} evidence must be a string")
    return errors


def corpus_digest(corpus: dict) -> str:
    picked = sorted((c for c in corpus["cases"] if c.get("status") == "confirmed"), key=lambda c: c["id"])
    payload = json.dumps(picked, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()[:12]


def surface_hash(reader: Callable[[str], Optional[bytes]]) -> str:
    h = hashlib.sha256()
    for path in SURFACE_PATHS:
        data = reader(path)
        h.update(data if data is not None else f"MISSING:{path}".encode())
    fid = reader(FIDELITY_PATH)
    if fid is None:
        h.update(f"MISSING:{FIDELITY_PATH}".encode())
    else:
        m = FIDELITY_LINE_RE.search(fid.decode("utf-8", errors="replace"))
        h.update(m.group(0).encode() if m else f"MISSING:{FIDELITY_PATH}".encode())
    return h.hexdigest()[:12]


def surface_hash_worktree(root: Path) -> str:
    return surface_hash(lambda rel: read_worktree(Path(root), rel))


def surface_hash_ref(root: Path, ref: str) -> str:
    return surface_hash(lambda rel: read_git_ref(Path(root), ref, rel))


@dataclass(frozen=True)
class SecondScore:
    v: str
    c: str
    missed: int
    should_hold: int
    extra: int
    should_clear: int
    degraded: int


def seat_cleared(word, clears, holds) -> bool:
    return word == "CLEAN" and set(clears) >= {int(k) for k in holds}


def compute_score(outcomes: Iterable, *, v: str, c: str) -> SecondScore:
    outcomes = list(outcomes)
    live = [o for o in outcomes if o[1] is not None]
    degraded = sum(1 for o in outcomes if o[1] is None)
    missed = should_hold = extra = should_clear = 0
    for case, word, clears in live:
        cleared = seat_cleared(word, clears, case["holds"])
        if case["expected"] == "hold":
            should_hold += 1
            if cleared:
                missed += 1
        else:
            should_clear += 1
            if not cleared:
                extra += 1
    return SecondScore(v, c, missed, should_hold, extra, should_clear, degraded)


def format_score_line(s: SecondScore) -> str:
    return (f"{SCORE_PREFIX} v={s.v} c={s.c} missed_holds={s.missed}/{s.should_hold} "
            f"extra_holds={s.extra}/{s.should_clear} degraded={s.degraded}")


def parse_score_line(text: str) -> SecondScore:
    m = SCORE_RE.fullmatch(text.strip())
    if not m:
        raise EvalUsageError(f"not a score line: {text!r}")
    v, c, a, b, d, e, g = m.groups()
    return SecondScore(v, c, int(a), int(b), int(d), int(e), int(g))


def is_worse(before: SecondScore, after: SecondScore) -> bool:
    return after.missed > before.missed or after.extra > before.extra


def _cmd_validate(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    path = Path(args.corpus) if args.corpus else root / DEFAULT_CORPUS
    try:
        corpus = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as e:
        print(f"cannot read corpus {path}: {e}", file=sys.stderr)
        return 2
    errors = validate_schema(corpus)
    if errors:
        for e in errors:
            print(e, file=sys.stderr)
        return 1
    cases = corpus["cases"]
    confirmed = sum(1 for c in cases if c["status"] == "confirmed")
    print(f"ok cases={len(cases)} confirmed={confirmed} c={corpus_digest(corpus)}")
    return 0


def _not_implemented(args: argparse.Namespace) -> int:
    print("not implemented", file=sys.stderr)
    return 2


def main(argv: Optional[list[str]] = None) -> int:
    p = argparse.ArgumentParser(prog="second_review_eval")
    sub = p.add_subparsers(dest="cmd", required=True)
    for name in ("mine", "validate", "run", "compare", "smoke"):
        sp = sub.add_parser(name)
        sp.add_argument("--repo", default=None)
        sp.add_argument("--corpus", default=None)
        sp.set_defaults(func=_cmd_validate if name == "validate" else _not_implemented)
    args = p.parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    raise SystemExit(main())
