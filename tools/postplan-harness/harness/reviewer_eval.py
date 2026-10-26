"""Reviewer eval: score the post-plan PR reviewer against a corpus of known bugs.

Drives the unchanged `ReviewPhase` over each corpus case offline and prints a
one-line score (recall on confirmed bug cases, false alarms on clean cases).
See ibl5/docs/reviewer-eval.md.
"""
from __future__ import annotations

import argparse
import contextlib
import datetime
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable, Optional, Sequence, TextIO

from . import schemas
from .adapters.llm import ClaudeCli, FixtureLlm
from .classify import classify, files_from_diff, modified_files_from_diff
from .review import VERIFIER_COUNT, ReviewPhase
from .state import Classification, Finding, HarnessError, PlanInfo, UsageLedger

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


def accepted_ranges(case: dict) -> list[ExpectedRange]:
    """Ranges a finding may land near to score a hit: `expected` plus optional `also_accept`."""
    return [ExpectedRange(e["path"], e["start"], e["end"])
            for e in [*case["expected"], *case.get("also_accept", [])]]


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


def _digest_case(c: dict) -> dict:
    d = {"id": c["id"], "commit": c["commit"],
         "expected": sorted([[e["path"], e["start"], e["end"]] for e in c["expected"]])}
    if "also_accept" in c:
        d["also_accept"] = sorted([[e["path"], e["start"], e["end"]] for e in c["also_accept"]])
    return d


def corpus_digest(corpus: dict) -> str:
    proj = {
        "bugs": sorted(
            [_digest_case(c) for c in corpus["cases"] if c["status"] == "confirmed"],
            key=lambda d: d["id"]),
        "clean": sorted([{"id": c["id"], "commit": c["commit"]} for c in corpus["clean"]],
                        key=lambda d: d["id"]),
    }
    blob = json.dumps(proj, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(blob).hexdigest()[:12]


# ---------------------------------------------------------------------------
# Phase 3: corpus schema, loader, git-backed validation, `validate` subcommand
# ---------------------------------------------------------------------------

SCHEMA_VERSION = 1
DEFAULT_CORPUS = "tools/postplan-harness/fixtures/reviewer-eval/corpus.json"

_TOP_KEYS = {"schema_version", "window_days", "cases", "clean"}
_BUG_KEYS = {"id", "kind", "status", "pr", "commit", "fix_pr", "fix_commit",
             "expected", "description"}
_BUG_OPTIONAL_KEYS = frozenset({"also_accept"})
_RANGE_KEYS = {"path", "start", "end"}
_CLEAN_KEYS = {"id", "pr", "commit", "checked_through"}
_ID_RE = re.compile(r"^[a-z0-9][a-z0-9-]*$")
_SHA_RE = re.compile(r"^[0-9a-f]{40}$")
_DATE_RE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
_STATUSES = {"confirmed", "unconfirmed"}


def load_corpus(path: Path) -> dict:
    try:
        return json.loads(Path(path).read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as e:
        raise EvalUsageError(f"cannot read corpus {path}: {e}") from e


def _is_int(v: object) -> bool:
    return isinstance(v, int) and not isinstance(v, bool)


def _is_pos_int(v: object) -> bool:
    return _is_int(v) and v >= 1  # type: ignore[operator]


def _key_error(label: str, obj: dict, keys: set,
               optional: frozenset = frozenset()) -> Optional[str]:
    present = set(obj)
    missing = sorted(keys - present)
    extra = sorted(present - keys - optional)
    if not missing and not extra:
        return None
    return f"{label}: bad keys (missing={missing}, unknown={extra})"


def _check_expected(label: str, expected: object, errors: list[str],
                    field: str = "expected") -> None:
    if not isinstance(expected, list) or not expected:
        errors.append(f"{label}: {field} must be a non-empty list")
        return
    for i, r in enumerate(expected):
        rl = f"{label}: {field}[{i}]"
        if not isinstance(r, dict):
            errors.append(f"{rl} must be an object")
            continue
        ke = _key_error(rl, r, _RANGE_KEYS)
        if ke:
            errors.append(ke)
            continue
        path = r["path"]
        if (not isinstance(path, str) or not path or path.startswith("/")
                or path.startswith("./") or ".." in path.split("/")):
            errors.append(f"{rl} bad path {path!r}")
        start, end = r["start"], r["end"]
        if not _is_pos_int(start):
            errors.append(f"{rl} start must be an int >= 1")
        if not _is_int(end) or (_is_pos_int(start) and end < start):
            errors.append(f"{rl} end must be an int >= start")


def validate_schema(corpus: dict) -> list[str]:
    if not isinstance(corpus, dict):
        return ["corpus: top level must be an object"]
    ke = _key_error("corpus", corpus, _TOP_KEYS)
    if ke:
        return [ke]
    errors: list[str] = []
    if not _is_int(corpus["schema_version"]) or corpus["schema_version"] != SCHEMA_VERSION:
        errors.append(f"corpus: schema_version must be {SCHEMA_VERSION}")
    if not _is_pos_int(corpus["window_days"]):
        errors.append("corpus: window_days must be an int >= 1")
    if not isinstance(corpus["cases"], list) or not isinstance(corpus["clean"], list):
        errors.append("corpus: cases and clean must be lists")
        return errors

    seen: set[str] = set()

    def check_id(label: str, c: dict) -> None:
        cid = c.get("id")
        if not isinstance(cid, str) or not _ID_RE.match(cid):
            errors.append(f"{label}: bad id {cid!r}")
        elif cid in seen:
            errors.append(f"{label}: duplicate id")
        else:
            seen.add(cid)

    def label_of(prefix: str, i: int, c: object) -> str:
        if isinstance(c, dict) and isinstance(c.get("id"), str):
            return c["id"]
        return f"{prefix}[{i}]"

    for i, c in enumerate(corpus["cases"]):
        label = label_of("cases", i, c)
        if not isinstance(c, dict):
            errors.append(f"{label}: case must be an object")
            continue
        ke = _key_error(label, c, _BUG_KEYS, _BUG_OPTIONAL_KEYS)
        if ke:
            errors.append(ke)
            continue
        check_id(label, c)
        if c["kind"] != "bug":
            errors.append(f"{label}: kind must be 'bug'")
        if c["status"] not in _STATUSES:
            errors.append(f"{label}: status must be one of {sorted(_STATUSES)}")
        for k in ("pr", "fix_pr"):
            if not _is_pos_int(c[k]):
                errors.append(f"{label}: {k} must be a positive int")
        for k in ("commit", "fix_commit"):
            if not isinstance(c[k], str) or not _SHA_RE.match(c[k]):
                errors.append(f"{label}: {k} must be 40 lowercase hex chars")
        _check_expected(label, c["expected"], errors)
        if "also_accept" in c:
            _check_expected(label, c["also_accept"], errors, field="also_accept")
        d = c["description"]
        if not isinstance(d, str) or not d or len(d) > 300:
            errors.append(f"{label}: description must be a non-empty str of at most 300 chars")
        elif "@" in d:
            errors.append(f"{label}: description must not contain '@'")

    for i, c in enumerate(corpus["clean"]):
        label = label_of("clean", i, c)
        if not isinstance(c, dict):
            errors.append(f"{label}: clean case must be an object")
            continue
        ke = _key_error(label, c, _CLEAN_KEYS)
        if ke:
            errors.append(ke)
            continue
        check_id(label, c)
        if not _is_pos_int(c["pr"]):
            errors.append(f"{label}: pr must be a positive int")
        if not isinstance(c["commit"], str) or not _SHA_RE.match(c["commit"]):
            errors.append(f"{label}: commit must be 40 lowercase hex chars")
        ct = c["checked_through"]
        ok = isinstance(ct, str) and bool(_DATE_RE.match(ct))
        if ok:
            try:
                datetime.date.fromisoformat(ct)
            except ValueError:
                ok = False
        if not ok:
            errors.append(f"{label}: checked_through must be a YYYY-MM-DD date")
    return errors


# --- git helpers -----------------------------------------------------------

HUNK_RE = re.compile(r"^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@")
_BLAME_HDR_RE = re.compile(r"^([0-9a-f]{40}) (\d+) (\d+)")
_FIX_SUBJECT_RE = re.compile(r"^(fix|revert|Revert)")


def _gitt(root: Path, *argv: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", "-C", str(root), *argv],
                          capture_output=True, text=True, errors="replace", check=False)


def diff_line_sets(root: Path, commit: str, path: str) -> tuple[set[int], set[int]]:
    """(removed_old_lines, added_new_lines) of `commit` for `path`."""
    r = _gitt(root, "diff", "-U0", "--no-color", "--no-ext-diff",
              f"{commit}^", commit, "--", path)
    removed: set[int] = set()
    added: set[int] = set()
    if r.returncode != 0:
        return removed, added
    for line in r.stdout.splitlines():
        m = HUNK_RE.match(line)
        if not m:
            continue
        old_start = int(m.group(1))
        old_count = 1 if m.group(2) is None else int(m.group(2))
        new_start = int(m.group(3))
        new_count = 1 if m.group(4) is None else int(m.group(4))
        removed.update(range(old_start, old_start + old_count))
        added.update(range(new_start, new_start + new_count))
    return removed, added


def blame_origins(root: Path, rev: str, path: str,
                  lines: Iterable[int]) -> list[tuple[str, int]]:
    """(origin sha, line number in the origin commit) for each blamed line."""
    out: list[tuple[str, int]] = []
    runs: list[tuple[int, int]] = []
    for n in sorted(set(lines)):
        if runs and n == runs[-1][1] + 1:
            runs[-1] = (runs[-1][0], n)
        else:
            runs.append((n, n))
    for a, b in runs:
        r = _gitt(root, "blame", "--porcelain", "-L", f"{a},{b}", rev, "--", path)
        if r.returncode != 0:
            continue
        for line in r.stdout.splitlines():
            m = _BLAME_HDR_RE.match(line)
            if m:
                out.append((m.group(1), int(m.group(2))))
    return out


def blame_records(root: Path, rev: str, path: str,
                  lines: Iterable[int]) -> list[tuple[str, int, str]]:
    """(origin sha, line number in the origin commit, path in the origin commit).

    Same porcelain parse as `blame_origins`; the `filename` line maps a renamed
    file to its path in the introducing commit.
    """
    out: list[tuple[str, int, str]] = []
    runs: list[tuple[int, int]] = []
    for n in sorted(set(lines)):
        if runs and n == runs[-1][1] + 1:
            runs[-1] = (runs[-1][0], n)
        else:
            runs.append((n, n))
    for a, b in runs:
        r = _gitt(root, "blame", "--porcelain", "-L", f"{a},{b}", rev, "--", path)
        if r.returncode != 0:
            continue
        names: dict[str, str] = {}
        pending: list[tuple[str, int]] = []
        for line in r.stdout.splitlines():
            m = _BLAME_HDR_RE.match(line)
            if m:
                pending.append((m.group(1), int(m.group(2))))
            elif line.startswith("filename ") and pending:
                names[pending[-1][0]] = line[len("filename "):]
        out.extend((sha, orig, names.get(sha, path)) for sha, orig in pending)
    return out


def subject(root: Path, sha: str) -> str:
    r = _gitt(root, "log", "-1", "--format=%s", sha)
    if r.returncode != 0:
        raise EvalUsageError(f"git log failed for {sha}: {r.stderr.strip()}")
    return r.stdout.strip()


def commit_time(root: Path, sha: str) -> int:
    r = _gitt(root, "log", "-1", "--format=%ct", sha)
    if r.returncode != 0:
        raise EvalUsageError(f"git log failed for {sha}: {r.stderr.strip()}")
    return int(r.stdout.strip())


def is_ancestor(root: Path, a: str, b: str) -> bool:
    return _gitt(root, "merge-base", "--is-ancestor", a, b).returncode == 0


def fix_commits_between(root: Path, since_ts: int, until_ts: int, ref: str) -> list[str]:
    r = _gitt(root, "log", "--format=%H%x09%s", f"--since=@{int(since_ts)}",
              f"--until=@{int(until_ts)}", ref)
    if r.returncode != 0:
        return []
    shas: list[str] = []
    for line in r.stdout.splitlines():
        sha, _, subj = line.partition("\t")
        if _FIX_SUBJECT_RE.match(subj):
            shas.append(sha)
    return shas


def blamed_by_fix_within(root: Path, commit: str, window_days: int, ref: str) -> list[str]:
    r = _gitt(root, "diff", "--name-only", f"{commit}^", commit)
    touched = [p for p in r.stdout.splitlines() if p] if r.returncode == 0 else []
    t0 = commit_time(root, commit)
    blamed: list[str] = []
    for fix in fix_commits_between(root, t0, t0 + window_days * 86400, ref):
        for f in touched:
            removed, _ = diff_line_sets(root, fix, f)
            if any(sha == commit for sha, _ in blame_origins(root, f"{fix}^", f, removed)):
                blamed.append(fix)
                break
    return blamed


def validate_git(root: Path, corpus: dict, *, ref: str = "HEAD", now: float) -> list[str]:
    errors: list[str] = []
    window_days = corpus["window_days"]
    confirmed = [c for c in corpus["cases"] if c["status"] == "confirmed"]
    if not confirmed:
        errors.append("no confirmed bug cases")
    for c in confirmed:
        cid, commit, fix = c["id"], c["commit"], c["fix_commit"]
        anc_ok = True
        for k, sha in (("commit", commit), ("fix_commit", fix)):
            if not is_ancestor(root, sha, ref):
                errors.append(f"{cid}: {k} is not an ancestor of {ref}")
                anc_ok = False
        if not anc_ok:
            continue
        if not subject(root, commit).endswith(f"(#{c['pr']})"):
            errors.append(f"{cid}: commit subject does not end with (#{c['pr']})")
        if not subject(root, fix).endswith(f"(#{c['fix_pr']})"):
            errors.append(f"{cid}: fix_commit subject does not end with (#{c['fix_pr']})")
        if commit == fix or not is_ancestor(root, commit, fix):
            errors.append(f"{cid}: fix_commit must be later than commit")
        added_cache: dict[str, set[int]] = {}
        for field in ("expected", "also_accept"):
            for r in c.get(field, []):
                if r["path"] not in added_cache:
                    added_cache[r["path"]] = diff_line_sets(root, commit, r["path"])[1]
                if not set(range(r["start"], r["end"] + 1)) <= added_cache[r["path"]]:
                    errors.append(f"{cid}: {field} range {r['path']}:{r['start']}-{r['end']} "
                                  f"is outside the lines added by commit")
        corroborated = False
        for path in sorted({r["path"] for r in c["expected"]}):
            removed, _ = diff_line_sets(root, fix, path)
            for sha, orig in blame_origins(root, f"{fix}^", path, removed):
                if sha == commit and any(r["path"] == path and r["start"] <= orig <= r["end"]
                                         for r in c["expected"]):
                    corroborated = True
        if not corroborated:
            errors.append(f"{cid}: blame does not corroborate: fix_commit removes no line "
                          f"originating from commit inside the expected ranges")
    for c in corpus["clean"]:
        cid, commit = c["id"], c["commit"]
        if not is_ancestor(root, commit, ref):
            errors.append(f"{cid}: commit is not an ancestor of {ref}")
            continue
        if not subject(root, commit).endswith(f"(#{c['pr']})"):
            errors.append(f"{cid}: commit subject does not end with (#{c['pr']})")
        if commit_time(root, commit) + window_days * 86400 > now:
            errors.append(f"{cid}: too young: {window_days}-day window has not elapsed")
            continue
        for fix in blamed_by_fix_within(root, commit, window_days, ref):
            errors.append(f"{cid}: blamed by fix {fix[:12]}")
    return errors


# --- CLI -------------------------------------------------------------------

def _repo_root(arg: Optional[str]) -> Path:
    # Module-relative, so a base tree's own copy of this module resolves to that tree.
    return Path(arg) if arg else Path(__file__).resolve().parents[3]


def _cmd_validate(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    corpus_path = Path(args.corpus) if args.corpus else root / DEFAULT_CORPUS
    corpus = load_corpus(corpus_path)
    errors = validate_schema(corpus)
    if not errors:
        if _gitt(root, "rev-parse", "--verify", "--quiet", f"{args.ref}^{{commit}}").returncode != 0:
            raise EvalUsageError(f"bad ref: {args.ref}")
        for c in corpus["cases"]:
            if c["status"] == "unconfirmed":
                print(f"skip {c['id']} (unconfirmed)")
        errors = validate_git(root, corpus, ref=args.ref, now=time.time())
    if errors:
        for e in errors:
            print(f"FAIL {e}")
        return 1
    n_conf = sum(1 for c in corpus["cases"] if c["status"] == "confirmed")
    n_unconf = len(corpus["cases"]) - n_conf
    print(f"validate ok: {n_conf} confirmed, {n_unconf} unconfirmed, "
          f"{len(corpus['clean'])} clean")
    return 0


# ---------------------------------------------------------------------------
# Phase 4: run and smoke
# ---------------------------------------------------------------------------

DEFAULT_CACHE_REL = "tools/postplan-harness/out/reviewer-eval/cache"
_TRAILING_PR_RE = re.compile(r"\s*\(#(\d+)\)\s*$")
_AGENT_GATES = ("A", "D", "security")


class NullGh:
    """Swallows the two posting calls the reviewer makes; any other gh call raises."""

    def post_review_findings(self, *a, **k):
        return None

    def post_review_summary(self, *a, **k):
        return None


def case_input(root: Path, commit: str) -> tuple[dict, Classification]:
    r = _gitt(root, "diff", "--no-color", "--no-ext-diff", f"{commit}^", commit)
    if r.returncode != 0:
        raise EvalUsageError(f"git diff failed for {commit}: {r.stderr.strip()}")
    diff = r.stdout
    if not diff.strip():
        raise EvalUsageError(f"empty diff for {commit}")
    cls = classify(files_from_diff(diff), diff, modified_files_from_diff(diff))
    subj = subject(root, commit)
    m = _TRAILING_PR_RE.search(subj)
    meta = {"number": int(m.group(1)) if m else 0,
            "title": _TRAILING_PR_RE.sub("", subj),
            "headRefOid": commit}
    return meta, cls


# The cache key covers what review_case reads. Accepted ranges (`also_accept`) only
# affect scoring after the fact, so editing them must reuse cached findings.
def case_digest(case: dict, kind: str) -> str:
    blob = json.dumps({"kind": kind, "commit": case["commit"],
                       "expected": case.get("expected", [])}, sort_keys=True)
    return hashlib.sha256(blob.encode()).hexdigest()[:12]


class _CountingLlm:
    """Counts the calls a case actually makes through the wrapped llm."""

    def __init__(self, inner: object):
        self._inner = inner
        self._lock = threading.Lock()
        self.calls = 0

    def call(self, *a, **k):
        with self._lock:
            self.calls += 1
        return self._inner.call(*a, **k)  # type: ignore[attr-defined]

    def call_tooled(self, *a, **k):
        with self._lock:
            self.calls += 1
        return self._inner.call_tooled(*a, **k)  # type: ignore[attr-defined]


def _load_cached(path: Path) -> Optional[tuple[list[Finding], list[dict], list[str]]]:
    """An entry without `scored` predates the verifier checkout and kept every finding
    unverified, so it reads as a miss."""
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
        findings = [Finding(source=f["source"], agent=f["agent"], path=f["path"],
                            line=int(f["line"]), body="", score=f["score"])
                    for f in data["findings"]]
        scored = [dict(s) for s in data["scored"]]
        degraded = [str(d) for d in data["degraded"]]
        return findings, scored, degraded
    except (OSError, ValueError, KeyError, TypeError):
        return None


def _write_cache(path: Path, findings: Sequence[Finding], scored: Sequence[dict]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = {"findings": [{"source": f.source, "agent": f.agent, "path": f.path,
                             "line": f.line, "score": f.score} for f in findings],
               "scored": list(scored),
               "degraded": []}
    tmp = path.with_name(path.name + ".tmp")
    tmp.write_text(json.dumps(payload, sort_keys=True), encoding="utf-8")
    os.replace(tmp, path)


def review_case(root: Path, commit: str, llm: object, meta: dict,
                cls: Classification) -> tuple[list[Finding], list[dict], list[str]]:
    """Run ReviewPhase with its verifiers reading a detached checkout of `commit`, the way
    the live reviewer reads the PR head. A verifier seat that errored abstains, and an
    abstain keeps a finding unverified, so that case counts as degraded."""
    with tempfile.TemporaryDirectory(prefix="reviewer-eval-") as tmp:
        checkout = str(Path(tmp) / "wt")
        r = _gitt(root, "worktree", "add", "-q", "--detach", checkout, commit)
        if r.returncode != 0:
            raise EvalUsageError(f"git worktree add failed for {commit}: {r.stderr.strip()}")
        try:
            phase = ReviewPhase(llm, NullGh(), worktree=checkout)
            surviving, _gates, scored, degraded = phase.run(meta, cls, PlanInfo())
        finally:
            _gitt(root, "worktree", "remove", "--force", checkout)
            _gitt(root, "worktree", "prune")
    degraded = list(degraded)
    if any(v.get("error") for rec in phase.last_votes for v in rec["votes"]):
        degraded.append("verifier")
    return list(surviving), list(scored), degraded


def run_eval(root: Path, corpus: dict, *, kinds: str, case_ids: Optional[set],
             llm_factory: Callable[[str], object], cache_dir: Optional[Path],
             dry_run: bool, out: TextIO, runs: int = 1) -> int:
    """Review each case `runs` times. A bug case hits only when every run hits, and a
    clean case keeps its worst run, so one lucky sample cannot raise the score."""
    if runs < 1:
        raise EvalUsageError("--runs must be at least 1")
    known = {c["id"] for c in corpus["cases"]} | {c["id"] for c in corpus["clean"]}
    if case_ids is not None:
        unknown = sorted(case_ids - known)
        if unknown:
            raise EvalUsageError(f"unknown case id(s): {', '.join(unknown)}")

    def wanted(c: dict) -> bool:
        return case_ids is None or c["id"] in case_ids

    bug_cases = ([c for c in corpus["cases"] if c["status"] == "confirmed" and wanted(c)]
                 if kinds in ("bug", "all") else [])
    clean_cases = ([c for c in corpus["clean"] if wanted(c)]
                   if kinds in ("clean", "all") else [])
    excluded = sum(1 for c in corpus["cases"] if c["status"] != "confirmed")
    v = surface_hash_worktree(root)
    c_digest = corpus_digest({"cases": bug_cases, "clean": clean_cases})

    selected = [(c, "bug") for c in bug_cases] + [(c, "clean") for c in clean_cases]

    if dry_run:
        total = 0
        for case, kind in selected:
            _meta, cls = case_input(root, case["commit"])
            gates = ReviewPhase(None, NullGh()).gates(cls)
            on = [k for k in _AGENT_GATES if gates[k]]
            est = (len(on) + VERIFIER_COUNT) * runs    # one verifier chunk; more findings add chunks
            total += est
            print(f"case {case['id']} {kind} dry gates={','.join(on)} "
                  f"diff_bytes={len(cls.filtered_diff.encode())} est_calls={est}", file=out)
        print(f"excluded_unconfirmed={excluded}", file=out)
        print(f"dry-run total est_calls={total}", file=out)
        return 0

    bug_out: list[CaseOutcome] = []
    clean_out: list[CaseOutcome] = []
    llm_calls = 0
    flaky = 0
    for case, kind in selected:
        cid = case["id"]
        expected: list[ExpectedRange] = []
        if kind == "bug":
            expected = accepted_ranges(case)
        per_run: list[list[Finding]] = []
        run_hits: list[bool] = []
        degraded: list[str] = []
        for k in range(1, runs + 1):
            # Run 1 keeps the old cache name, so a --runs 1 cache stays valid.
            tag = "" if k == 1 else f"-r{k}"
            cache_path = (cache_dir / v / f"{cid}-{case_digest(case, kind)}{tag}.json"
                          if cache_dir is not None else None)
            cached = _load_cached(cache_path) if cache_path is not None else None
            if cached is not None:
                findings, scored, run_degraded = cached
            else:
                meta, cls = case_input(root, case["commit"])
                llm = _CountingLlm(llm_factory(cid))
                findings, scored, run_degraded = review_case(root, case["commit"], llm, meta, cls)
                llm_calls += llm.calls
                if cache_path is not None and not run_degraded:
                    _write_cache(cache_path, findings, scored)
            for s in scored:
                print(f"  raw {s['agent']} {s['path']}:{s['line']} score={s['score']} "
                      f"{' '.join((s.get('body_head') or '').split())[:120]}", file=out)
            per_run.append(findings)
            run_hits.append(case_hit(findings, expected) if kind == "bug" else bool(findings))
            degraded += [d for d in run_degraded if d not in degraded]
        worst = max(per_run, key=len)
        suffix = f" DEGRADED={','.join(degraded)}" if degraded else ""
        if runs > 1:
            n = sum(run_hits)
            suffix = f" runs_{'hit' if kind == 'bug' else 'flagged'}={n}/{runs}{suffix}"
            if 0 < n < runs:
                flaky += 1
                suffix += " FLAKY"
        if kind == "bug":
            hit = all(run_hits)
            bug_out.append(CaseOutcome(cid, "bug", tuple(worst), tuple(degraded), hit))
            print(f"case {cid} bug {'HIT' if hit else 'MISS'} findings={len(worst)} "
                  f"file_only={file_only_count(worst, expected)}{suffix}", file=out)
        else:
            clean_out.append(CaseOutcome(cid, "clean", tuple(worst), tuple(degraded), None))
            print(f"case {cid} clean flagged={len(worst)}{suffix}", file=out)

    score = compute_score(bug_out, clean_out, v=v, c=c_digest)
    print(f"excluded_unconfirmed={excluded}", file=out)
    if runs > 1:
        print(f"runs={runs} flaky={flaky}", file=out)
    print(f"llm_calls={llm_calls}", file=out)
    print(format_score_line(score), file=out)
    return 3 if score.degraded > 0 else 0


_SMOKE_PROMPT = ("Reply with a JSON array of findings for this diff, each {path, line, body}. "
                 "Reply [] when there is nothing to report.\n\n"
                 "--- a/ibl5/classes/Example.php\n+++ b/ibl5/classes/Example.php\n"
                 "@@ -1 +1 @@\n-$a = $row['x'] ?? 0;\n+$a = $row['x'];\n")


def smoke(llm: object) -> int:
    try:
        data = llm.call("review-agent-a", "sonnet", _SMOKE_PROMPT,  # type: ignore[attr-defined]
                        schemas.validate_findings, normalizer=schemas.unwrap_findings_envelope)
    except HarnessError as e:
        print(f"smoke failed: {e.kind}: {e.detail}", file=sys.stderr)
        return 1
    print(f"smoke ok findings={len(data)}")
    return 0


class _Tee:
    def __init__(self, *streams: TextIO):
        self._streams = streams

    def write(self, text: str) -> int:
        for s in self._streams:
            s.write(text)
        return len(text)

    def flush(self) -> None:
        for s in self._streams:
            s.flush()


def _cmd_run(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    corpus_path = Path(args.corpus) if args.corpus else root / DEFAULT_CORPUS
    corpus = load_corpus(corpus_path)
    errors = validate_schema(corpus)
    if errors:
        raise EvalUsageError("; ".join(errors))
    case_ids = {s for s in args.cases.split(",") if s} if args.cases else None
    ledger = UsageLedger()
    cache_dir: Optional[Path] = None
    if not args.no_cache:
        cache_dir = Path(args.cache_dir) if args.cache_dir else root / DEFAULT_CACHE_REL
    if args.canned:
        canned = load_corpus(Path(args.canned))
        cache_dir = None

        def factory(cid: str) -> object:
            if cid not in canned:
                raise EvalUsageError(f"case {cid} missing from canned file")
            return FixtureLlm(ledger, canned[cid])
    else:
        shared = ClaudeCli(ledger)

        def factory(cid: str) -> object:
            return shared
    out_fh = open(args.out, "w", encoding="utf-8") if args.out else None
    try:
        out: TextIO = _Tee(sys.stdout, out_fh) if out_fh else sys.stdout  # type: ignore[assignment]
        return run_eval(root, corpus, kinds=args.kinds, case_ids=case_ids,
                        llm_factory=factory, cache_dir=cache_dir,
                        dry_run=args.dry_run, out=out, runs=args.runs)
    finally:
        if out_fh:
            out_fh.close()


# ---------------------------------------------------------------------------
# Phase 5: compare (the miner lives in reviewer_eval_mine.py)
# ---------------------------------------------------------------------------

Runner = Callable[[Path, list], "tuple[int, str]"]


def _default_runner(path: Path, args: list) -> tuple[int, str]:
    r = subprocess.run([str(path), *args], capture_output=True, text=True, check=False)
    return r.returncode, r.stdout


@contextlib.contextmanager
def _default_make_tree(root: Path, sha: str):
    from .gate_backtest_replay import ScratchTree
    with ScratchTree(str(root), sha) as scratch:
        yield Path(scratch.dir)


def _last_line(text: str) -> str:
    lines = [ln for ln in text.splitlines() if ln.strip()]
    return lines[-1].strip() if lines else ""


def compare(root: Path, base: str, corpus_path: Path, *, runner: Runner,
            make_tree: Callable, out: TextIO, cache_dir: Optional[Path] = None,
            err: Optional[TextIO] = None, runs: int = 1) -> int:
    err = err if err is not None else sys.stderr
    if runs < 1:
        print("error: --runs must be at least 1", file=err)
        return 2
    rv = _gitt(root, "rev-parse", "--verify", "--quiet", f"{base}^{{commit}}")
    if rv.returncode != 0 or not rv.stdout.strip():
        print(f"error: cannot resolve base ref {base}", file=err)
        return 2
    base_sha = rv.stdout.strip()
    if _gitt(root, "cat-file", "-e", f"{base_sha}:bin/reviewer-eval").returncode != 0:
        print(f"error: base {base} predates bin/reviewer-eval; "
              "record the before score from a checkout that has it", file=err)
        return 2
    cache = cache_dir if cache_dir is not None else root / DEFAULT_CACHE_REL
    args = ["run", "--corpus", str(corpus_path.resolve()), "--cache-dir", str(Path(cache).resolve())]
    if runs > 1:
        base_src = _gitt(root, "show", f"{base_sha}:tools/postplan-harness/harness/reviewer_eval.py")
        if '"--runs"' not in base_src.stdout:
            print(f"error: base {base} predates --runs; compare with --runs 1", file=err)
            return 2
        args += ["--runs", str(runs)]
    with make_tree(root, base_sha) as tree:
        base_rc, base_out = runner(Path(tree) / "bin/reviewer-eval", args)
    head_rc, head_out = runner(root / "bin/reviewer-eval", args)
    if 2 in (base_rc, head_rc):
        print("error: reviewer eval run failed with a usage error", file=err)
        return 2
    for rc in (base_rc, head_rc):
        if rc not in (0, 3):
            print(f"error: reviewer eval run exited {rc}", file=err)
            return 1
    before_line, after_line = _last_line(base_out), _last_line(head_out)
    try:
        before, after = parse_score_line(before_line), parse_score_line(after_line)
    except EvalUsageError as e:
        print(f"error: {e}", file=err)
        return 2
    print(f"Reviewer eval before: {before_line}", file=out)
    print(f"Reviewer eval after: {after_line}", file=out)
    try:
        worse = is_worse(before, after)
    except EvalUsageError as e:
        print(f"error: {e}", file=err)
        return 2
    if worse:
        print("note: after is worse than before; the PR body needs "
              "<!-- reviewer-eval-regression-ok: <reason> -->", file=err)
    return 3 if 3 in (base_rc, head_rc) else 0


def _cmd_compare(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    corpus_path = Path(args.corpus) if args.corpus else root / DEFAULT_CORPUS
    cache_dir = Path(args.cache_dir) if args.cache_dir else None
    return compare(root, args.base, corpus_path, runner=_default_runner,
                   make_tree=_default_make_tree, out=sys.stdout, cache_dir=cache_dir,
                   runs=args.runs)


def _cmd_mine(args: argparse.Namespace) -> int:
    from . import reviewer_eval_mine as mine
    root = _repo_root(args.repo)
    now = time.time()
    if args.mine_cmd == "bugs":
        since = args.since or datetime.datetime.fromtimestamp(
            now - 180 * 86400, datetime.timezone.utc).date().isoformat()
        return mine.mine_bugs(root, ref=args.ref, since=since, limit=args.limit,
                              out=sys.stdout)
    return mine.mine_clean(root, ref=args.ref, window_days=args.window_days,
                           limit=args.limit, now=now, out=sys.stdout)


def _cmd_smoke(args: argparse.Namespace) -> int:
    return smoke(ClaudeCli(UsageLedger()))


# ---------------------------------------------------------------------------
# Phase 6: hash and check-claim (the CI binding of a PR-body score to the surface)
# ---------------------------------------------------------------------------

SKIP_RE = re.compile(r"<!--\s*reviewer-eval-skip:\s*(.*?)\s*-->")
REGRESSION_RE = re.compile(r"<!--\s*reviewer-eval-regression-ok:\s*(.*?)\s*-->")
_BEFORE_RE = re.compile(r"^Reviewer eval before:[ \t]*(.+)$", re.M)
_AFTER_RE = re.compile(r"^Reviewer eval after:[ \t]*(.+)$", re.M)
MIN_WAIVER_REASON = 15


def _corpus_at_ref(root: Path, ref: str) -> dict:
    raw = read_git_ref(root, ref, DEFAULT_CORPUS)
    if raw is None:
        raise EvalUsageError(f"corpus absent at {ref}: {DEFAULT_CORPUS}")
    try:
        corpus = json.loads(raw.decode("utf-8"))
        corpus_digest(corpus)
    except (ValueError, KeyError, TypeError) as e:
        raise EvalUsageError(f"corpus at {ref} is not a valid corpus: {e}") from e
    return corpus


def _cmd_hash(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    if args.ref:
        v = surface_hash_ref(root, args.ref)
        c = corpus_digest(_corpus_at_ref(root, args.ref))
    else:
        v = surface_hash_worktree(root)
        c = corpus_digest(load_corpus(root / DEFAULT_CORPUS))
    print(f"v={v} c={c}")
    return 0


BASELINE_DOC = "ibl5/docs/reviewer-eval.md"


def baseline_hits(root: Path, ref: str) -> Optional[int]:
    """Recall hits of the last score line in the baseline doc at `ref`; None when absent."""
    raw = read_git_ref(root, ref, BASELINE_DOC)
    if raw is None:
        return None
    hits = None
    for line in raw.decode("utf-8", errors="replace").splitlines():
        if line.startswith(SCORE_LINE_PREFIX + " v="):
            try:
                hits = parse_score_line(line).hits
            except EvalUsageError:
                continue
    return hits


def check_claim(root: Path, base: str, head: str, body_loader: Callable[[], str], *,
                out: Optional[TextIO] = None, err: Optional[TextIO] = None) -> int:
    """Strict check, softened to a warning while the base ref's baseline recall is 0.
    The baseline is read at base so a PR cannot loosen its own gate."""
    out = out if out is not None else sys.stdout
    err = err if err is not None else sys.stderr
    rc = _check_claim_strict(root, base, head, body_loader, out=out, err=err)
    if rc != 1:
        return rc
    try:
        hits = baseline_hits(root, base)
    except SurfaceError as e:
        print(f"check-reviewer-eval: {e}", file=err)
        return 2
    if hits:
        return 1
    print(f"check-reviewer-eval: WARNING: not blocking, because the baseline recall at {base} "
          f"is {'absent' if hits is None else '0'} ({BASELINE_DOC}); the gate blocks once "
          f"the baseline recall is above 0", file=err)
    return 0


def _check_claim_strict(root: Path, base: str, head: str, body_loader: Callable[[], str], *,
                        out: TextIO, err: TextIO) -> int:
    """Bind a PR body's before/after score lines to the surface at base/head and the corpus at head."""
    try:
        v_base = surface_hash_ref(root, base)
        v_head = surface_hash_ref(root, head)
    except SurfaceError as e:
        print(f"check-reviewer-eval: {e}", file=err)
        return 2
    if v_base == v_head:
        print(f"reviewer-eval: review surface unchanged (v={v_base})", file=out)
        return 0
    try:
        c_head = corpus_digest(_corpus_at_ref(root, head))
        body = body_loader()
    except (EvalUsageError, SurfaceError) as e:
        print(f"check-reviewer-eval: {e}", file=err)
        return 2
    body = body.replace("\r\n", "\n")

    skip = SKIP_RE.search(body)
    if skip:
        reason = skip.group(1)
        if len(reason) >= MIN_WAIVER_REASON:
            print(f"reviewer-eval-skip: {reason}", file=out)
            return 0
        print("check-reviewer-eval: malformed reviewer-eval-skip waiver (reason < 15 chars)",
              file=err)
        return 1

    hint = "produce both lines with `bin/reviewer-eval compare` and paste them into the PR body"
    befores, afters = _BEFORE_RE.findall(body), _AFTER_RE.findall(body)
    if len(befores) != 1 or len(afters) != 1:
        print(f"check-reviewer-eval: review surface changed (v {v_base} -> {v_head}); the PR body "
              f"needs exactly one 'Reviewer eval before:' and one 'Reviewer eval after:' line "
              f"(found {len(befores)} and {len(afters)}); {hint}", file=err)
        return 1
    try:
        before, after = parse_score_line(befores[0]), parse_score_line(afters[0])
    except EvalUsageError as e:
        print(f"check-reviewer-eval: {e}; {hint}", file=err)
        return 1

    bad = False
    for label, claimed, expected in (("before.v", before.v, v_base), ("after.v", after.v, v_head),
                                     ("before.c", before.c, c_head), ("after.c", after.c, c_head)):
        if claimed != expected:
            print(f"check-reviewer-eval: {label} mismatch: expected {expected}, claimed {claimed}",
                  file=err)
            bad = True
    for label, sc in (("before", before), ("after", after)):
        if sc.degraded != 0:
            print(f"check-reviewer-eval: {label} score is degraded (degraded={sc.degraded}); "
                  f"rerun until degraded=0", file=err)
            bad = True
    if bad:
        return 1

    try:
        worse = is_worse(before, after)
    except EvalUsageError as e:
        print(f"check-reviewer-eval: {e}", file=err)
        return 1
    if worse:
        reg = REGRESSION_RE.search(body)
        if reg and len(reg.group(1)) >= MIN_WAIVER_REASON:
            print(f"reviewer-eval-regression-ok: {reg.group(1)}", file=out)
            return 0
        print("check-reviewer-eval: the review got worse (fewer hits or more false alarms) and "
              "the PR body has no valid '<!-- reviewer-eval-regression-ok: <reason, 15+ chars> -->'",
              file=err)
        return 1
    print("reviewer-eval: before/after claim matches base and head", file=out)
    return 0


def _gh_body_loader(gh: str, pr: str) -> Callable[[], str]:
    def load() -> str:
        try:
            r = subprocess.run([gh, "pr", "view", pr, "--json", "body", "-q", ".body"],
                               capture_output=True, text=True, check=False)
        except OSError as e:
            raise EvalUsageError(f"gh failed for PR {pr}: {e}") from e
        if r.returncode != 0:
            raise EvalUsageError(f"gh pr view failed for PR {pr}")
        return r.stdout
    return load


def _file_body_loader(path: str) -> Callable[[], str]:
    def load() -> str:
        try:
            return Path(path).read_text(encoding="utf-8")
        except (OSError, UnicodeDecodeError) as e:
            raise EvalUsageError(f"cannot read body file {path}: {e}") from e
    return load


def _cmd_check_claim(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    loader = _gh_body_loader(args.gh, args.pr) if args.pr else _file_body_loader(args.body_file)
    return check_claim(root, args.base, args.head, loader)


def main(argv: Optional[list[str]] = None) -> int:
    parser = argparse.ArgumentParser(prog="reviewer_eval")
    sub = parser.add_subparsers(dest="cmd", required=True)
    v = sub.add_parser("validate", help="validate the corpus offline against git history")
    v.add_argument("--corpus", metavar="PATH", default=None)
    v.add_argument("--repo", metavar="DIR", default=None)
    v.add_argument("--ref", metavar="REF", default="HEAD")
    v.set_defaults(func=_cmd_validate)
    r = sub.add_parser("run", help="run the reviewer over the corpus and print the score line")
    r.add_argument("--corpus", metavar="PATH", default=None)
    r.add_argument("--repo", metavar="DIR", default=None)
    r.add_argument("--kinds", choices=("bug", "clean", "all"), default="all")
    r.add_argument("--cases", metavar="ID,ID", default=None)
    r.add_argument("--no-cache", action="store_true")
    r.add_argument("--cache-dir", metavar="DIR", default=None)
    r.add_argument("--canned", metavar="FILE", default=None)
    r.add_argument("--dry-run", action="store_true")
    r.add_argument("--out", metavar="FILE", default=None)
    r.add_argument("--runs", type=int, default=1, metavar="N",
                   help="review each case N times; a bug hits only when every run hits")
    r.set_defaults(func=_cmd_run)
    sm = sub.add_parser("smoke", help="one live review-agent call proving the claude -p boundary")
    sm.set_defaults(func=_cmd_smoke)
    c = sub.add_parser("compare", help="score the base tree and this tree on one corpus and cache")
    c.add_argument("--base", metavar="REF", default="origin/master")
    c.add_argument("--corpus", metavar="PATH", default=None)
    c.add_argument("--repo", metavar="DIR", default=None)
    c.add_argument("--cache-dir", metavar="DIR", default=None)
    c.add_argument("--runs", type=int, default=1, metavar="N")
    c.set_defaults(func=_cmd_compare)
    mn = sub.add_parser("mine", help="print corpus candidates from history (writes nothing)")
    msub = mn.add_subparsers(dest="mine_cmd", required=True)
    mb = msub.add_parser("bugs", help="fixes that blame back to an introducing PR")
    mb.add_argument("--since", metavar="YYYY-MM-DD", default=None)
    mb.add_argument("--ref", metavar="REF", default="HEAD")
    mb.add_argument("--limit", type=int, default=40)
    mb.add_argument("--repo", metavar="DIR", default=None)
    mb.set_defaults(func=_cmd_mine)
    mc = msub.add_parser("clean", help="settled PRs no later fix blamed")
    mc.add_argument("--window-days", type=int, default=30)
    mc.add_argument("--ref", metavar="REF", default="HEAD")
    mc.add_argument("--limit", type=int, default=20)
    mc.add_argument("--repo", metavar="DIR", default=None)
    mc.set_defaults(func=_cmd_mine)
    h = sub.add_parser("hash", help="print v=<surface hash> c=<corpus digest> for the tree or a ref")
    h.add_argument("--ref", metavar="REF", default=None)
    h.add_argument("--repo", metavar="DIR", default=None)
    h.set_defaults(func=_cmd_hash)
    cc = sub.add_parser("check-claim", help="check a PR body's before/after claim (CI gate)")
    cc.add_argument("--repo", metavar="DIR", default=None)
    cc.add_argument("--base", metavar="REF", required=True)
    cc.add_argument("--head", metavar="REF", default="HEAD")
    grp = cc.add_mutually_exclusive_group(required=True)
    grp.add_argument("--pr", metavar="N", default=None)
    grp.add_argument("--body-file", metavar="PATH", default=None)
    cc.add_argument("--gh", metavar="CMD", default="gh")
    cc.set_defaults(func=_cmd_check_claim)
    args = parser.parse_args(argv)
    try:
        return args.func(args)
    except (EvalUsageError, SurfaceError) as e:
        print(f"error: {e}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
