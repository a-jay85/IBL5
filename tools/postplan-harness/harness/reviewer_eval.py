"""Reviewer eval: score the post-plan PR reviewer against a corpus of known bugs.

Drives the unchanged `ReviewPhase` over each corpus case offline and prints a
one-line score (recall on confirmed bug cases, false alarms on clean cases).
See ibl5/docs/reviewer-eval.md.
"""
from __future__ import annotations

import argparse
import datetime
import hashlib
import json
import re
import subprocess
import sys
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable, Optional, Sequence

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


# ---------------------------------------------------------------------------
# Phase 3: corpus schema, loader, git-backed validation, `validate` subcommand
# ---------------------------------------------------------------------------

SCHEMA_VERSION = 1
DEFAULT_CORPUS = "tools/postplan-harness/fixtures/reviewer-eval/corpus.json"

_TOP_KEYS = {"schema_version", "window_days", "cases", "clean"}
_BUG_KEYS = {"id", "kind", "status", "pr", "commit", "fix_pr", "fix_commit",
             "expected", "description"}
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


def _key_error(label: str, obj: dict, keys: set) -> Optional[str]:
    if set(obj) == keys:
        return None
    missing = sorted(keys - set(obj))
    extra = sorted(set(obj) - keys)
    return f"{label}: bad keys (missing={missing}, unknown={extra})"


def _check_expected(label: str, expected: object, errors: list[str]) -> None:
    if not isinstance(expected, list) or not expected:
        errors.append(f"{label}: expected must be a non-empty list")
        return
    for i, r in enumerate(expected):
        rl = f"{label}: expected[{i}]"
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
        ke = _key_error(label, c, _BUG_KEYS)
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
                          capture_output=True, text=True, check=False)


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
        for r in c["expected"]:
            if r["path"] not in added_cache:
                added_cache[r["path"]] = diff_line_sets(root, commit, r["path"])[1]
            if not set(range(r["start"], r["end"] + 1)) <= added_cache[r["path"]]:
                errors.append(f"{cid}: expected range {r['path']}:{r['start']}-{r['end']} "
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
    if arg:
        return Path(arg)
    r = _gitt(Path.cwd(), "rev-parse", "--show-toplevel")
    if r.returncode != 0:
        raise EvalUsageError("not inside a git repository (pass --repo)")
    return Path(r.stdout.strip())


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


def main(argv: Optional[list[str]] = None) -> int:
    parser = argparse.ArgumentParser(prog="reviewer_eval")
    sub = parser.add_subparsers(dest="cmd", required=True)
    v = sub.add_parser("validate", help="validate the corpus offline against git history")
    v.add_argument("--corpus", metavar="PATH", default=None)
    v.add_argument("--repo", metavar="DIR", default=None)
    v.add_argument("--ref", metavar="REF", default="HEAD")
    v.set_defaults(func=_cmd_validate)
    args = parser.parse_args(argv)
    try:
        return args.func(args)
    except (EvalUsageError, SurfaceError) as e:
        print(f"error: {e}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
