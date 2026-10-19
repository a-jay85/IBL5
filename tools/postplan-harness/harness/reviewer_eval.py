"""Reviewer eval: score the post-plan PR reviewer against a corpus of known bugs.

Drives the unchanged `ReviewPhase` over each corpus case offline and prints a
one-line score (recall on confirmed bug cases, false alarms on clean cases).
See ibl5/docs/reviewer-eval.md.
"""
from __future__ import annotations

import hashlib
import json
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Optional, Sequence

from .state import Finding

# ---------------------------------------------------------------------------
# Phase 1: pure scoring core
# ---------------------------------------------------------------------------

LINE_WINDOW = 5
SCORE_LINE_PREFIX = "reviewer-eval"


class EvalUsageError(ValueError):
    """Bad input or corpus; CLI maps this to exit 2."""


def normalize_path(p: str) -> str:
    p = p.strip()
    while p.startswith("./"):
        p = p[2:]
    return p.lstrip("/")


@dataclass(frozen=True)
class ExpectedRange:
    path: str
    start: int
    end: int


def finding_matches(path: str, line: int, rng: ExpectedRange) -> bool:
    if line <= 0:
        return False
    return (normalize_path(path) == normalize_path(rng.path)
            and rng.start - LINE_WINDOW <= line <= rng.end + LINE_WINDOW)


def case_hit(findings: Sequence[Finding], expected: Sequence[ExpectedRange]) -> bool:
    return any(finding_matches(f.path, f.line, r) for f in findings for r in expected)


def file_only_count(findings: Sequence[Finding], expected: Sequence[ExpectedRange]) -> int:
    paths = {normalize_path(r.path) for r in expected}
    return sum(1 for f in findings if f.line <= 0 and normalize_path(f.path) in paths)


@dataclass(frozen=True)
class Score:
    v: str
    c: str
    hits: int
    scored: int
    false_alarms: int
    clean_flagged: int
    clean_total: int
    degraded: int


@dataclass(frozen=True)
class CaseOutcome:
    id: str
    kind: str                         # "bug" | "clean"
    findings: tuple[Finding, ...]
    degraded_agents: tuple[str, ...]
    hit: Optional[bool]               # None for clean


def compute_score(bug: Sequence[CaseOutcome], clean: Sequence[CaseOutcome], *,
                  v: str, c: str) -> Score:
    if len(bug) == 0:
        raise EvalUsageError("no confirmed bug cases to score")
    return Score(
        v=v, c=c,
        hits=sum(1 for o in bug if o.hit),
        scored=len(bug),
        false_alarms=sum(len(o.findings) for o in clean),
        clean_flagged=sum(1 for o in clean if o.findings),
        clean_total=len(clean),
        degraded=sum(1 for o in (*bug, *clean) if o.degraded_agents),
    )


def format_score_line(s: Score) -> str:
    return (f"{SCORE_LINE_PREFIX} v={s.v} c={s.c} recall={s.hits}/{s.scored} "
            f"false_alarms={s.false_alarms} clean_flagged={s.clean_flagged}/{s.clean_total} "
            f"degraded={s.degraded}")


SCORE_LINE_RE = re.compile(
    r"^reviewer-eval v=([0-9a-f]{12}) c=([0-9a-f]{12}) recall=(\d+)/(\d+) "
    r"false_alarms=(\d+) clean_flagged=(\d+)/(\d+) degraded=(\d+)$")


def parse_score_line(text: str) -> Score:
    m = SCORE_LINE_RE.fullmatch(text.strip())
    if not m:
        raise EvalUsageError(f"malformed score line: {text!r}")
    v, c = m.group(1), m.group(2)
    hits, scored, fa, cf, ct, deg = (int(m.group(i)) for i in range(3, 9))
    if hits > scored or cf > ct or scored == 0:
        raise EvalUsageError(f"malformed score line: {text!r}")
    return Score(v=v, c=c, hits=hits, scored=scored, false_alarms=fa,
                 clean_flagged=cf, clean_total=ct, degraded=deg)


def is_worse(before: Score, after: Score) -> bool:
    if before.scored != after.scored or before.clean_total != after.clean_total:
        raise EvalUsageError("scores not comparable: denominators differ")
    return after.hits < before.hits or after.false_alarms > before.false_alarms


# ---------------------------------------------------------------------------
# Phase 2: review-surface hash (v=) and corpus digest (c=)
# ---------------------------------------------------------------------------

# Paths are relative to the repo root.
REVIEW_SURFACE_PATHS = (
    "tools/postplan-harness/harness/review.py",
    ".claude/review-shared/_review-agents.md",
    ".claude/review-shared/_security-agents.md",
    ".claude/review-shared/_review-rubric.md",
    ".claude/skills/post-plan/_phase-4-review-audit.md",
)
MODEL_MAP_SOURCE = "tools/postplan-harness/harness/adapters/llm.py"
MODEL_MAP_RE = re.compile(r"^MODEL_MAP = \{\n.*?^\}", re.M | re.S)
ABSENT = b"<absent>"


class SurfaceError(RuntimeError):
    """A surface file is unreadable, a ref is invalid, or MODEL_MAP is missing. CLI exit 2."""


def read_worktree(root: Path, rel: str) -> Optional[bytes]:
    try:
        return (Path(root) / rel).read_bytes()
    except FileNotFoundError:
        return None
    except OSError as e:
        raise SurfaceError(f"unreadable surface file: {rel}") from e


def _git(root: Path, *argv: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", "-C", str(root), *argv], capture_output=True, check=False)


def read_git_ref(root: Path, ref: str, rel: str) -> Optional[bytes]:
    if _git(root, "rev-parse", "--verify", "--quiet", f"{ref}^{{commit}}").returncode != 0:
        raise SurfaceError(f"bad ref: {ref}")
    if _git(root, "cat-file", "-e", f"{ref}:{rel}").returncode != 0:
        return None
    shown = _git(root, "show", f"{ref}:{rel}")
    if shown.returncode != 0:
        raise SurfaceError(f"git show failed: {ref}:{rel}")
    return shown.stdout


def extract_model_map(src: bytes) -> bytes:
    m = MODEL_MAP_RE.search(src.decode("utf-8"))
    if not m:
        raise SurfaceError("MODEL_MAP block not found in llm.py")
    return m.group(0).encode("utf-8")


def surface_hash(reader: Callable[[str], Optional[bytes]]) -> str:
    h = hashlib.sha256()
    entries: list[tuple[str, Optional[bytes]]] = [(rel, reader(rel)) for rel in REVIEW_SURFACE_PATHS]
    llm_src = reader(MODEL_MAP_SOURCE)
    entries.append((MODEL_MAP_SOURCE + "#MODEL_MAP",
                    None if llm_src is None else extract_model_map(llm_src)))
    for rel, content in entries:
        digest = ABSENT if content is None else hashlib.sha256(content).hexdigest().encode()
        h.update(rel.encode() + b"\0" + digest + b"\n")
    return h.hexdigest()[:12]


def surface_hash_worktree(root: Path) -> str:
    return surface_hash(lambda r: read_worktree(root, r))


def surface_hash_ref(root: Path, ref: str) -> str:
    return surface_hash(lambda r: read_git_ref(root, ref, r))


def corpus_digest(corpus: dict) -> str:
    proj = {
        "bugs": sorted(
            [{"id": c["id"], "commit": c["commit"],
              "expected": sorted([[e["path"], e["start"], e["end"]] for e in c["expected"]])}
             for c in corpus["cases"] if c["status"] == "confirmed"],
            key=lambda d: d["id"]),
        "clean": sorted([{"id": c["id"], "commit": c["commit"]} for c in corpus["clean"]],
                        key=lambda d: d["id"]),
    }
    blob = json.dumps(proj, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(blob).hexdigest()[:12]
