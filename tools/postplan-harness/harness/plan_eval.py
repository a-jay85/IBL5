"""Plan eval: score the /plan skill against a corpus of past prompts whose PRs merged clean.

Each case replays a real /plan prompt against a history-truncated clone at the PR's base
SHA with today's plan surface copied over it, then scores the produced plan on mechanical
pass, recall of the realized PR's files, and architect-tier match.
See ibl5/docs/plan-eval.md.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import re
import subprocess
import sys
from pathlib import Path
from typing import Callable, Optional, TextIO

from .reviewer_eval import (
    EvalUsageError,
    SurfaceError,
    load_corpus,
    normalize_path,
    read_git_ref,
    read_worktree,
)

# ---------------------------------------------------------------------------
# Phase 1: corpus schema, plan-surface hash, `validate`
# ---------------------------------------------------------------------------

SCHEMA_VERSION = 1
DEFAULT_CORPUS = "tools/postplan-harness/fixtures/plan-eval/corpus.json"
TIERS = ("plan-architect", "plan-architect-sonnet", "plan-architect-xhigh")
MIN_EXPECTED = 2
MAX_EXPECTED = 12
CORE_COUNT = 4
ABSENT = b"<absent>"

_TOP_KEYS = {"schema_version", "window_days", "cases"}
_CASE_KEYS = {"id", "pr", "base_sha", "merge_sha", "tier", "core", "prompt", "expected_files"}
_ID_RE = re.compile(r"^[a-z0-9][a-z0-9-]*$")
_SHA_RE = re.compile(r"^[0-9a-f]{40}$")

PLAN_SURFACE_ENTRIES = (
    ".claude/skills/plan/",
    ".claude/review-shared/_plan-verification.md",
    ".claude/review-shared/_plan-verification-detail.md",
    ".claude/agents/plan-architect.md",
    ".claude/agents/plan-architect-sonnet.md",
    ".claude/agents/plan-architect-xhigh.md",
    "bin/check-plan",
    "bin/check-plan-staleness",
    "bin/plan-tier-hint",
    "bin/lib/plan-",
    "bin/lib/critical-files.sh",
    "bin/lib/hold-check.sh",
    "bin/lib/hold-check-patterns.txt",
)


def _is_int(v: object) -> bool:
    return isinstance(v, int) and not isinstance(v, bool)


def _keys_error(label: str, obj: dict, keys: set) -> Optional[str]:
    present = set(obj)
    missing = sorted(keys - present)
    extra = sorted(present - keys)
    if not missing and not extra:
        return None
    return f"{label}: bad keys (missing={missing}, unknown={extra})"


def _bad_rel_path(p: object) -> bool:
    return (not isinstance(p, str) or not p or p.startswith("/") or p.startswith("./")
            or ".." in p.split("/"))


def _check_case(case: object, i: int, corpus_dir: Path, seen: set,
                errors: list[str]) -> None:
    label = f"cases[{i}]"
    if not isinstance(case, dict):
        errors.append(f"{label} must be an object")
        return
    ke = _keys_error(label, case, _CASE_KEYS)
    if ke:
        errors.append(ke)
        return
    cid = case["id"]
    if not isinstance(cid, str) or not _ID_RE.match(cid):
        errors.append(f"{label}: bad id {cid!r}")
    else:
        label = cid
        if cid in seen:
            errors.append(f"{cid}: duplicate id")
        seen.add(cid)
    if not _is_int(case["pr"]) or case["pr"] < 1:
        errors.append(f"{label}: pr must be an int >= 1")
    for k in ("base_sha", "merge_sha"):
        if not isinstance(case[k], str) or not _SHA_RE.match(case[k]):
            errors.append(f"{label}: {k} must be 40 hex chars")
    if case["tier"] not in TIERS:
        errors.append(f"{label}: tier: {case['tier']!r} not in {list(TIERS)}")
    if not isinstance(case["core"], bool):
        errors.append(f"{label}: core must be a bool")
    files = case["expected_files"]
    if not isinstance(files, list):
        errors.append(f"{label}: expected_files must be a list")
        files = []
    else:
        if not (MIN_EXPECTED <= len(files) <= MAX_EXPECTED):
            errors.append(f"{label}: expected_files has {len(files)} entries "
                          f"(need {MIN_EXPECTED} to {MAX_EXPECTED})")
        if len(set(map(str, files))) != len(files):
            errors.append(f"{label}: expected_files has duplicates")
        for f in files:
            if _bad_rel_path(f):
                errors.append(f"{label}: bad expected path {f!r}")
    prompt = case["prompt"]
    if _bad_rel_path(prompt):
        errors.append(f"{label}: bad prompt path {prompt!r}")
        return
    ppath = corpus_dir / prompt
    try:
        text = ppath.read_text(encoding="utf-8")
    except (OSError, UnicodeDecodeError):
        errors.append(f"{label}: prompt file unreadable: {prompt}")
        return
    if not text.strip():
        errors.append(f"{label}: prompt file is empty: {prompt}")
    for f in files:
        if isinstance(f, str) and f and f in text:
            errors.append(f"{label}: prompt names expected path {f}")


def validate_schema(corpus: object, corpus_dir: Path) -> list[str]:
    errors: list[str] = []
    if not isinstance(corpus, dict):
        return ["corpus must be an object"]
    ke = _keys_error("corpus", corpus, _TOP_KEYS)
    if ke:
        return [ke]
    if corpus["schema_version"] != SCHEMA_VERSION:
        errors.append(f"schema_version must be {SCHEMA_VERSION}")
    if not _is_int(corpus["window_days"]) or corpus["window_days"] < 1:
        errors.append("window_days must be an int >= 1")
    cases = corpus["cases"]
    if not isinstance(cases, list):
        return errors + ["cases must be a list"]
    seen: set = set()
    for i, case in enumerate(cases):
        _check_case(case, i, Path(corpus_dir), seen, errors)
    if not cases:
        return errors
    core = [c for c in cases if isinstance(c, dict) and c.get("core") is True]
    if len(core) != CORE_COUNT:
        errors.append(f"core: need exactly {CORE_COUNT} core cases, found {len(core)}")
    core_tiers = {c.get("tier") for c in core}
    if not set(TIERS) <= core_tiers:
        errors.append(f"core: core cases must cover every tier, missing "
                      f"{sorted(set(TIERS) - core_tiers)}")
    return errors


def _git(repo: Path, *args: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True, text=True)


def find_followups(repo: Path, case: dict, window_days: int) -> list[str]:
    ct = _git(repo, "log", "-1", "--format=%ct", case["merge_sha"])
    if ct.returncode != 0:
        return [f"{case['id']}: cannot read merge commit {case['merge_sha'][:12]}"]
    merged_at = int(ct.stdout.strip())
    log = _git(repo, "log", "--format=%H%x09%ct%x09%s", f"{case['merge_sha']}..HEAD", "--",
               *case["expected_files"])
    out: list[str] = []
    for line in log.stdout.splitlines():
        parts = line.split("\t", 2)
        if len(parts) != 3:
            continue
        sha, when, subject = parts
        if int(when) - merged_at > window_days * 86400:
            continue
        if re.match(r"^(fix|Revert)", subject):
            out.append(f"{case['id']}: follow-up {sha[:12]} {subject}")
    return out


def validate_git(repo: Path, corpus: dict) -> list[str]:
    errors: list[str] = []
    for case in corpus["cases"]:
        cid = case["id"]
        parent = _git(repo, "rev-parse", f"{case['merge_sha']}^1")
        if parent.returncode != 0:
            errors.append(f"{cid}: merge_sha {case['merge_sha'][:12]} not in history")
            continue
        if parent.stdout.strip() != case["base_sha"]:
            errors.append(f"{cid}: base_sha is not the first parent of merge_sha")
        diff = _git(repo, "diff", "--name-only", case["base_sha"], case["merge_sha"])
        touched = set(diff.stdout.splitlines())
        for f in case["expected_files"]:
            if f not in touched:
                errors.append(f"{cid}: expected file {f} not changed by the PR")
        errors.extend(find_followups(repo, case, corpus["window_days"]))
    return errors


def expand_surface(lister: Callable[[str], list[str]]) -> list[str]:
    paths: set = set()
    for entry in PLAN_SURFACE_ENTRIES:
        if entry.endswith("/") or entry.endswith("-"):
            d = entry.rstrip("/") if entry.endswith("/") else entry.rsplit("/", 1)[0]
            paths.update(p for p in lister(d) if p.startswith(entry))
        else:
            paths.add(entry)
    return sorted(paths)


def plan_surface_hash(paths: list[str], reader: Callable[[str], Optional[bytes]]) -> str:
    h = hashlib.sha256()
    for rel in paths:
        content = reader(rel)
        digest = ABSENT if content is None else hashlib.sha256(content).hexdigest().encode()
        h.update(rel.encode() + b"\0" + digest + b"\n")
    return h.hexdigest()[:12]


def worktree_lister(root: Path) -> Callable[[str], list[str]]:
    def lister(d: str) -> list[str]:
        r = _git(root, "ls-files", "--", d)
        return r.stdout.splitlines()
    return lister


def ref_lister(root: Path, ref: str) -> Callable[[str], list[str]]:
    def lister(d: str) -> list[str]:
        r = _git(root, "ls-tree", "-r", "--name-only", ref, "--", d)
        if r.returncode != 0:
            raise SurfaceError(f"bad ref: {ref}")
        return r.stdout.splitlines()
    return lister


def surface_hash_worktree(root: Path) -> str:
    return plan_surface_hash(expand_surface(worktree_lister(root)),
                             lambda r: read_worktree(root, r))


def surface_hash_ref(root: Path, ref: str) -> str:
    return plan_surface_hash(expand_surface(ref_lister(root, ref)),
                             lambda r: read_git_ref(root, ref, r))


def corpus_digest(corpus: dict, corpus_dir: Path) -> str:
    proj = []
    for c in sorted(corpus["cases"], key=lambda c: c["id"]):
        try:
            pbytes = (Path(corpus_dir) / c["prompt"]).read_bytes()
        except OSError:
            pbytes = ABSENT
        proj.append({"id": c["id"], "merge_sha": c["merge_sha"],
                     "expected_files": sorted(c["expected_files"]), "tier": c["tier"],
                     "prompt": hashlib.sha256(pbytes).hexdigest()})
    blob = json.dumps(proj, sort_keys=True, separators=(",", ":")).encode()
    return hashlib.sha256(blob).hexdigest()[:12]


def _repo_root(repo: Optional[str]) -> Path:
    if repo:
        return Path(repo).resolve()
    r = subprocess.run(["git", "rev-parse", "--show-toplevel"], capture_output=True, text=True)
    if r.returncode != 0:
        raise EvalUsageError("not inside a git repository; pass --repo")
    return Path(r.stdout.strip())


def _corpus_path(root: Path, corpus: Optional[str]) -> Path:
    return Path(corpus).resolve() if corpus else root / DEFAULT_CORPUS


def _cmd_validate(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    root = _repo_root(args.repo)
    cpath = _corpus_path(root, args.corpus)
    corpus = load_corpus(cpath)
    errors = validate_schema(corpus, cpath.parent)
    if not errors:
        errors = validate_git(root, corpus)
    if not errors:
        errors = overlay_probe(root, corpus)
    for e in errors:
        print(f"ERROR {e}", file=out)
    if errors:
        return 1
    core = sum(1 for c in corpus["cases"] if c["core"])
    v = surface_hash_worktree(root)
    c = corpus_digest(corpus, cpath.parent)
    print(f"ok cases={len(corpus['cases'])} core={core} v={v} c={c}", file=out)
    return 0


def overlay_probe(root: Path, corpus: dict) -> list[str]:
    """Phase 4 fills this in: clone at the first core base, overlay, run the gates."""
    return []


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="plan_eval")
    sub = parser.add_subparsers(dest="cmd", required=True)
    v = sub.add_parser("validate", help="validate the corpus schema, history, and overlay")
    v.add_argument("--corpus", metavar="PATH", default=None)
    v.add_argument("--repo", metavar="DIR", default=None)
    v.set_defaults(func=_cmd_validate)
    return parser


def main(argv: Optional[list[str]] = None) -> int:
    parser = build_parser()
    try:
        args = parser.parse_args(argv)
    except SystemExit as e:
        return e.code if isinstance(e.code, int) else 2
    try:
        return args.func(args)
    except EvalUsageError as e:
        print(f"usage error: {e}", file=sys.stderr)
        return 2
    except SurfaceError as e:
        print(f"surface error: {e}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
