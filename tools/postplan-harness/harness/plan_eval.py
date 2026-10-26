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
import math
import os
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import time
import uuid
from dataclasses import dataclass, field
from pathlib import Path
from typing import Callable, Optional, TextIO

from .reviewer_eval import (
    EvalUsageError,
    SurfaceError,
    blame_origins,
    diff_line_sets,
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


def _names_path(text: str, path: str) -> bool:
    """True when text names path as a whole path, so bin/check-plan-staleness does not name bin/check-plan."""
    return re.search(r"(?<![\w./-])" + re.escape(path) + r"(?![\w/-]|\.[\w/-])", text) is not None


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
        if isinstance(f, str) and f and _names_path(text, f):
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
        if not re.match(r"^(fix|Revert)", subject):
            continue
        # A follow-up must change a line the PR wrote (reviewer-eval's "blamed" rule);
        # an unrelated fix that only shares a busy file does not count.
        for f in case["expected_files"]:
            removed, _ = diff_line_sets(repo, sha, f)
            if any(o == case["merge_sha"] for o, _ in blame_origins(repo, f"{sha}^", f, removed)):
                out.append(f"{case['id']}: follow-up {sha[:12]} {subject}")
                break
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
        r = _git(root, "ls-files", "--cached", "--others", "--exclude-standard", "--", d)
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


# ---------------------------------------------------------------------------
# Phase 2: `mine` (deterministic case selection from the frozen prompt set)
# ---------------------------------------------------------------------------

QUOTA = (("plan-architect", 5), ("plan-architect-sonnet", 2), ("plan-architect-xhigh", 3))
CORPUS_SIZE = sum(n for _, n in QUOTA)
_TIER_RE = re.compile(r"plan-architect(?:-sonnet|-xhigh)?")
_LAST_VERIFIED_RE = re.compile(r"^[+-]last_verified:")


class MineFailure(RuntimeError):
    """The prompt set cannot fill a corpus; CLI exit 1."""


def parse_tier(first_line: str) -> str:
    m = _TIER_RE.search(first_line)
    return m.group(0) if m else "plan-architect"


def expected_files_for(repo: Path, base_sha: str, merge_sha: str) -> list[str]:
    names = _git(repo, "diff", "--name-only", base_sha, merge_sha).stdout.splitlines()
    out: list[str] = []
    for name in names:
        diff = _git(repo, "diff", "-U0", base_sha, merge_sha, "--", name).stdout
        changed = [ln for ln in diff.splitlines()
                   if ln[:1] in ("+", "-") and not ln.startswith(("+++", "---"))]
        if changed and all(_LAST_VERIFIED_RE.match(ln) for ln in changed):
            continue
        out.append(name)
    return out


def select_cases(rows: list[dict], lookup: Callable[[str], Optional[tuple]],
                 repo: Path, window_days: int, out: TextIO = sys.stdout) -> list[dict]:
    """Rows carry slug, tier_line, prompt (text). Returns cases with prompt_text attached."""
    eligible: list[dict] = []
    for row in rows:
        slug = row["slug"]
        if not _ID_RE.match(slug):
            print(f"skip {slug}: bad slug", file=out)
            continue
        found = lookup(slug)
        if found is None:
            print(f"skip {slug}: no merged PR", file=out)
            continue
        pr, merge_sha = found
        parent = _git(repo, "rev-parse", f"{merge_sha}^1")
        if parent.returncode != 0:
            print(f"skip {slug}: merge {merge_sha[:12]} not in local history", file=out)
            continue
        base_sha = parent.stdout.strip()
        # Files the prompt already names are the human's input, not something the plan
        # found, so recall is scored only on the rest.
        files = [f for f in expected_files_for(repo, base_sha, merge_sha)
                 if not _names_path(row["prompt"], f)]
        if not (MIN_EXPECTED <= len(files) <= MAX_EXPECTED):
            print(f"skip {slug}: {len(files)} expected files", file=out)
            continue
        case = {"id": slug, "pr": pr, "base_sha": base_sha, "merge_sha": merge_sha,
                "tier": parse_tier(row["tier_line"]), "core": False,
                "prompt": f"prompts/{slug}.txt", "expected_files": sorted(files)}
        if find_followups(repo, case, window_days):
            print(f"skip {slug}: has a follow-up fix", file=out)
            continue
        eligible.append({**case, "prompt_text": row["prompt"]})
    eligible.sort(key=lambda c: c["pr"])
    by_tier = {t: [c for c in eligible if c["tier"] == t] for t, _ in QUOTA}
    chosen: list[dict] = []
    for tier, want in QUOTA:
        have = by_tier[tier]
        if not have:
            raise MineFailure(f"no eligible {tier} case")
        if len(have) < want:
            print(f"shortfall {tier} {len(have)}/{want}", file=out)
        chosen.extend(have[:want])
    spare = [c for c in by_tier["plan-architect"] if c not in chosen]
    while len(chosen) < CORPUS_SIZE and spare:
        chosen.append(spare.pop(0))
    _mark_core(chosen)
    return sorted(chosen, key=lambda c: c["id"])


def _mark_core(cases: list[dict]) -> None:
    def size_key(c: dict) -> tuple:
        return (len(c["prompt_text"].encode("utf-8")), c["pr"])
    for tier in TIERS:
        ranked = sorted((c for c in cases if c["tier"] == tier), key=size_key)
        take = 2 if tier == "plan-architect" else 1
        for c in ranked[:take]:
            c["core"] = True


def read_index(prompts_dir: Path) -> list[dict]:
    rows: list[dict] = []
    for line in (prompts_dir / "INDEX.tsv").read_text(encoding="utf-8").splitlines():
        parts = line.split("\t")
        if len(parts) < 5:
            continue
        slug = parts[0]
        try:
            prompt = (prompts_dir / f"{slug}.txt").read_text(encoding="utf-8")
        except OSError:
            continue
        rows.append({"slug": slug, "tier_line": parts[4], "prompt": prompt})
    return rows


def gh_lookup(slug: str) -> Optional[tuple]:
    r = subprocess.run(["gh", "pr", "list", "-R", "a-jay85/IBL5", "--state", "merged",
                        "--head", slug, "--json", "number,mergeCommit", "--limit", "1"],
                       capture_output=True, text=True)
    if r.returncode != 0:
        raise EvalUsageError(f"gh pr list failed for {slug}: {r.stderr.strip()}")
    items = json.loads(r.stdout or "[]")
    if not items or not items[0].get("mergeCommit"):
        return None
    return int(items[0]["number"]), items[0]["mergeCommit"]["oid"]


def _cmd_mine(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    root = _repo_root(args.repo)
    prompts_dir = Path(args.prompts_dir)
    out_dir = Path(args.out_dir) if args.out_dir else root / Path(DEFAULT_CORPUS).parent
    window_days = 14
    try:
        cases = select_cases(read_index(prompts_dir), gh_lookup, root, window_days, out)
    except MineFailure as e:
        print(f"ERROR {e}", file=out)
        return 1
    for c in cases:
        print(f"{c['id']} {c['pr']} {c['tier']} {'core' if c['core'] else '-'} "
              f"{len(c['expected_files'])} {len(c['prompt_text'].encode('utf-8'))}", file=out)
    if args.dry_run:
        return 0
    (out_dir / "prompts").mkdir(parents=True, exist_ok=True)
    for c in cases:
        (out_dir / c["prompt"]).write_bytes(c["prompt_text"].encode("utf-8"))
    corpus = {"schema_version": SCHEMA_VERSION, "window_days": window_days,
              "cases": [{k: v for k, v in c.items() if k != "prompt_text"} for c in cases]}
    (out_dir / "corpus.json").write_text(
        json.dumps(corpus, indent=2, sort_keys=True) + "\n", encoding="utf-8")
    errors = validate_schema(corpus, out_dir)
    for e in errors:
        print(f"ERROR {e}", file=out)
    return 1 if errors else 0


# ---------------------------------------------------------------------------
# Phase 3: scorer
# ---------------------------------------------------------------------------

SCORE_LINE_PREFIX = "plan-eval"
STATUSES = ("ok", "no_plan", "degraded", "leaked")
_PHASE_HEADING_RE = re.compile(r"^## (Phase|Step) \d+", re.M)


@dataclass(frozen=True)
class RunScore:
    mech: bool
    file_hits: int
    file_total: int
    tier_ok: bool
    scored: bool


@dataclass(frozen=True)
class PlanScore:
    v: str
    c: str
    runs: int
    mech: int
    files: int
    files_total: int
    tier: int
    no_plan: int
    degraded: int
    leaked: int
    cost_usd: float


def has_required_sections(plan_text: str) -> bool:
    return (re.search(r"^## Verification Matrix\b", plan_text, re.M) is not None
            and re.search(r"^## Critical Files\b", plan_text, re.M) is not None
            and _PHASE_HEADING_RE.search(plan_text) is not None)


def score_run(record: dict, case: dict) -> RunScore:
    status = record.get("status")
    total = len(case["expected_files"])
    if status in ("degraded", "leaked"):
        return RunScore(mech=False, file_hits=0, file_total=total, tier_ok=False, scored=False)
    ok = status == "ok"
    mech = (ok and record.get("check_plan_rc") == 0 and record.get("staleness_rc") == 0
            and has_required_sections(record.get("plan_text") or ""))
    hits = 0
    if ok:
        expected = {normalize_path(p) for p in case["expected_files"]}
        got = {normalize_path(p) for p in record.get("critical_files") or []}
        hits = len(expected & got)
    tier_ok = bool(record.get("tier_chosen")) and record.get("tier_chosen") == case["tier"]
    return RunScore(mech=mech, file_hits=hits, file_total=total, tier_ok=tier_ok, scored=True)


def critical_files_of(plan_path: Path, check_plan_bin: Path) -> list[str]:
    r = subprocess.run([str(check_plan_bin), "--print-critical-files", str(plan_path)],
                       capture_output=True, text=True)
    if r.returncode != 0:
        return []
    # Lines are `MUST_APPEAR:<path>` or `EXEMPT:<path>` (reference or conditional rows);
    # both name a file the plan identified.
    out: list[str] = []
    for ln in r.stdout.splitlines():
        kind, sep, path = ln.strip().partition(":")
        if sep and kind in ("MUST_APPEAR", "EXEMPT") and path:
            out.append(path)
    return out


def compute_score(scores: list[RunScore], records: list[dict], *, v: str, c: str) -> PlanScore:
    scored = [s for s in scores if s.scored]
    if not scored:
        raise EvalUsageError("no scored runs")
    return PlanScore(
        v=v, c=c, runs=len(scored),
        mech=sum(1 for s in scored if s.mech),
        files=sum(s.file_hits for s in scored),
        files_total=sum(s.file_total for s in scored),
        tier=sum(1 for s in scored if s.tier_ok),
        no_plan=sum(1 for r in records if r.get("status") == "no_plan"),
        degraded=sum(1 for r in records if r.get("status") == "degraded"),
        leaked=sum(1 for r in records if r.get("status") == "leaked"),
        cost_usd=round(sum(float(r.get("cost_usd") or 0.0) for r in records), 2),
    )


def format_score_line(s: PlanScore) -> str:
    return (f"{SCORE_LINE_PREFIX} v={s.v} c={s.c} runs={s.runs} mech={s.mech}/{s.runs} "
            f"files={s.files}/{s.files_total} tier={s.tier}/{s.runs} no_plan={s.no_plan} "
            f"degraded={s.degraded} leaked={s.leaked} cost_usd={s.cost_usd:.2f}")


SCORE_LINE_RE = re.compile(
    r"^plan-eval v=([0-9a-f]{12}) c=([0-9a-f]{12}) runs=(\d+) mech=(\d+)/(\d+) "
    r"files=(\d+)/(\d+) tier=(\d+)/(\d+) no_plan=(\d+) degraded=(\d+) leaked=(\d+) "
    r"cost_usd=(\d+\.\d{2})$")


def parse_score_line(text: str) -> PlanScore:
    m = SCORE_LINE_RE.fullmatch(text.strip())
    if not m:
        raise EvalUsageError(f"malformed score line: {text!r}")
    n, a, an, h, e, t, tn, x, d, lk = (int(m.group(i)) for i in range(3, 13))
    if n == 0 or an != n or tn != n or a > n or h > e or t > n:
        raise EvalUsageError(f"malformed score line: {text!r}")
    return PlanScore(v=m.group(1), c=m.group(2), runs=n, mech=a, files=h, files_total=e,
                     tier=t, no_plan=x, degraded=d, leaked=lk, cost_usd=float(m.group(13)))


def is_worse(before: PlanScore, after: PlanScore) -> bool:
    if before.runs != after.runs or before.files_total != after.files_total:
        raise EvalUsageError("scores not comparable: denominators differ")
    return (after.mech < before.mech or after.files < before.files
            or after.tier < before.tier)


# ---------------------------------------------------------------------------
# Phase 4: replay clone, surface overlay, stub gh, eval coda
# ---------------------------------------------------------------------------

CANNED_PLAN = "tools/postplan-harness/fixtures/plan-eval/canned-plan.md"

# Copied from bin/plan-now's headless coda (PLAN_FILE/NO_PLAN markers, no human,
# fork rule, self-check, foreground sub-agents); the queue disposition bullet is dropped.
EVAL_CODA = """

---

**Headless run constraints (appended by `bin/plan-eval` — these override any
conflicting default in the skill):**

- **Announce your plan's path — in your FINAL message.** Unless you halt with no
  plan (next bullet), the **last line** of your final message must be exactly this line, on its own, with nothing after it and no
  backticks, bullet, bold or fence around it:
  ```
  PLAN_FILE: {plans_dir}/{slug}.md
  ```
  (absolute path). This job attributes the check-plan verdict to your run by that
  line. **The final message is the only placement that works**: under
  `claude -p` only your final message reaches this job, so an earlier print is
  discarded (printing it earlier as well is harmless; the last one wins). This
  overrides the skill's Step 5.5 and Step 6: skip the queue step, write the Step 6
  report, then end with this line.
- **Halting with no plan is legal — end it with `NO_PLAN:` instead.** If you stop
  without writing any plan (the skill's Step 2.1 found the work fully done), the
  last line of your final message must be exactly this, bare, on its own:
  ```
  NO_PLAN: <one line: why, with the evidence — PR #, commit SHA or file path>
  ```
  Use it only when no plan file was written. A run that wrote a plan always ends
  with `PLAN_FILE:`. Without either line the run is reported `unconfirmed`.
- **No human is present.** Never call `AskUserQuestion`, never pause for input,
  never end a turn waiting for an answer. The forks a human could answer were
  already resolved by the session that drafted this prompt; they appear above as
  hard constraints.
- If you hit a genuine `needs-user-input` fork that the constraints above do not
  settle: pick the safest option, **record the fork and your choice explicitly in
  the plan's Approach section**, and set `auto_merge: false` in the line-1
  frontmatter. `auto_merge: false` requires an `## Automouse Hold Justification`
  section naming the unresolved fork, or `bin/check-plan` gate [H] fails.
- Before finishing, run `bin/check-plan {plans_dir}/{slug}.md` and then
  `bin/check-plan-staleness` on the same file, and fix everything they report.
  This job re-runs both after you exit and logs the verdict. Step 5 of the skill
  has the remediation table.
- **Run every sub-agent in the FOREGROUND.** Never spawn the plan-architect (or any
  other `Agent`) with `run_in_background: true`, and never start a long task, end
  your turn, and expect to collect it afterwards. Under `claude -p` the turn you
  end is the run, so a background result has no next turn to land on and is
  discarded. If you have already started one, collect it before you finish.
- Evaluation run. The branch slug is `{slug}`; it overrides any slug named above for
  the plan file, the draft and any shared-context file.
- Plans directory override. Everywhere the skill or a rule says `$HOME/claude-plans`
  or `~/claude-plans` (plans, `.drafts/`, shared-context files you write), use
  `{plans_dir}` instead. Write nothing under `~/claude-plans`.
- Do not read `~/claude-plans/{orig_slug}.md`, any `{orig_slug}-<n>.md` variant, or
  `.drafts/{orig_slug}.draft.md`. A shared-context file the task text names may be read.
- Never run `bin/automouse/queue`, `bin/plan-now`, `bin/post-plan-now`, `bin/wt-new`,
  or `gh`; never push a branch; never edit a tracked file.
"""

_SLUG_LINE_RE = re.compile(r"^- Branch slug: .*$", re.M)


def run_slug(case_id: str, k: int) -> str:
    return f"eval-{case_id}-r{k}"


def build_prompt(case_prompt: str, slug: str, plans_dir: Path, orig_slug: str) -> str:
    text = _SLUG_LINE_RE.sub(f"- Branch slug: {slug}", case_prompt)
    if not text.lstrip().startswith("/plan"):
        text = "/plan " + text.lstrip()
    return text.rstrip("\n") + EVAL_CODA.format(plans_dir=str(plans_dir), slug=slug,
                                                orig_slug=orig_slug)


def _git_check(repo: Path, *args: str) -> str:
    r = _git(repo, *args)
    if r.returncode != 0:
        raise SurfaceError(f"git {' '.join(args)} failed: {r.stderr.strip()[-300:]}")
    return r.stdout


class ReplayClone:
    """A history-truncated local clone at base_sha in system temp."""

    def __init__(self, repo: Path, base_sha: str, keep: bool = False) -> None:
        self.repo = Path(repo)
        self.base_sha = base_sha
        self.keep = keep
        self.tmp: Optional[Path] = None

    @property
    def root(self) -> Path:
        assert self.tmp is not None
        return self.tmp / "repo"

    @property
    def plans_dir(self) -> Path:
        assert self.tmp is not None
        return self.tmp / "plans"

    @property
    def stub_bin(self) -> Path:
        assert self.tmp is not None
        return self.tmp / "stubbin"

    def __enter__(self) -> "ReplayClone":
        self.tmp = Path(tempfile.mkdtemp(prefix="plan-eval-")).resolve()
        ok = False
        try:
            r = subprocess.run(["git", "clone", "-q", "--local", "--no-checkout",
                                str(self.repo), str(self.root)],
                               capture_output=True, text=True)
            if r.returncode != 0:
                raise SurfaceError(f"git clone failed: {r.stderr.strip()[-300:]}")
            _git_check(self.root, "checkout", "-q", "--detach", self.base_sha)
            for ref in _git_check(self.root, "for-each-ref", "--format=%(refname)").split():
                _git_check(self.root, "update-ref", "-d", ref)
            if _git(self.root, "remote").stdout.strip():
                _git_check(self.root, "remote", "remove", "origin")
            packed = self.root / ".git" / "packed-refs"
            if packed.exists():
                packed.unlink()
            _git_check(self.root, "reflog", "expire", "--expire=now", "--all")
            # --local hardlinks every object, so the merged answer stays readable until pruned.
            _git_check(self.root, "gc", "-q", "--prune=now")
            (self.plans_dir / ".drafts").mkdir(parents=True)
            self.stub_bin.mkdir()
            ok = True
        finally:
            if not ok:
                self._cleanup()
        return self

    def _cleanup(self) -> None:
        if self.tmp is not None and not self.keep:
            shutil.rmtree(self.tmp, ignore_errors=True)

    def __exit__(self, *exc: object) -> None:
        self._cleanup()


def overlay_surface(source_root: Path, clone_root: Path) -> list[str]:
    files = expand_surface(worktree_lister(source_root))
    for entry in PLAN_SURFACE_ENTRIES:
        if entry.endswith("/") or entry.endswith("-"):
            d = entry.rstrip("/") if entry.endswith("/") else entry.rsplit("/", 1)[0]
            base = clone_root / d
            if base.is_dir():
                for p in sorted(base.rglob("*")):
                    rel = p.relative_to(clone_root).as_posix()
                    if p.is_file() and rel.startswith(entry):
                        p.unlink()
        else:
            target = clone_root / entry
            if target.is_file():
                target.unlink()
    copied: list[str] = []
    for rel in files:
        src = Path(source_root) / rel
        if not src.is_file():
            continue
        dst = clone_root / rel
        dst.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(src, dst)
        copied.append(rel)
    return copied


def write_stub_gh(stub_bin: Path) -> Path:
    stub_bin.mkdir(parents=True, exist_ok=True)
    gh = stub_bin / "gh"
    gh.write_text("#!/bin/sh\nexit 0\n")
    gh.chmod(0o755)
    return gh


def _tail(text: str, n: int = 5) -> str:
    return " | ".join(text.strip().splitlines()[-n:])


def overlay_probe(root: Path, corpus: dict) -> list[str]:
    """Clone at the first case's base, overlay today's surface, run both gates on a canned plan."""
    if not corpus["cases"]:
        return []
    first = sorted(corpus["cases"], key=lambda c: c["id"])[0]
    errors: list[str] = []
    with ReplayClone(root, first["base_sha"]) as rc:
        overlay_surface(root, rc.root)
        plan = rc.plans_dir / "canned-plan.md"
        shutil.copy2(root / CANNED_PLAN, plan)
        for tool in ("bin/check-plan", "bin/check-plan-staleness"):
            r = subprocess.run([str(rc.root / tool), str(plan)], cwd=str(rc.root),
                               capture_output=True, text=True)
            if r.returncode != 0:
                errors.append(f"overlay probe: {tool} rc={r.returncode}: "
                              f"{_tail(r.stderr or r.stdout)}")
    return errors


# ---------------------------------------------------------------------------
# Phase 5: single-case worker, stream parse, leak sweep
# ---------------------------------------------------------------------------

DEFAULT_MODEL = "claude-sonnet-5-5"
DEFAULT_TIMEOUT = 10800
GATE_TIMEOUT = 300
CACHE_ROOT = "tools/postplan-harness/out/plan-eval/cache"
LEAK_ANY_RE = re.compile(r"^eval-[a-z0-9-]+-r\d+([.-]|$)")
_MARKER_RE = re.compile(r"^(PLAN_FILE|NO_PLAN):\s*(.*)$")


def claude_bin() -> str:
    """PLAN_EVAL_CLAUDE overrides the binary; unset and empty both mean `claude`."""
    return os.environ.get("PLAN_EVAL_CLAUDE") or "claude"


@dataclass
class StreamFacts:
    final_text: str = ""
    has_result: bool = False
    cost_usd: float = 0.0
    output_tokens: int = 0
    cache_read_tokens: int = 0
    cache_write_tokens: int = 0
    tier_chosen: str = ""
    contaminated: str = ""
    bad_lines: int = 0


def _answer_key_re(orig_slug: str) -> "re.Pattern[str]":
    return re.compile(r"claude-plans/(\.drafts/)?" + re.escape(orig_slug)
                      + r"(-\d+)?(\.draft)?\.md")


def _int(v: object) -> int:
    return v if isinstance(v, int) and not isinstance(v, bool) else 0


def parse_stream(lines: list[str], orig_slug: str) -> StreamFacts:
    facts = StreamFacts()
    seen: set = set()
    key_re = _answer_key_re(orig_slug)
    result_usage: list = []
    for raw in lines:
        raw = raw.strip()
        if not raw:
            continue
        try:
            ev = json.loads(raw)
        except ValueError:
            facts.bad_lines += 1
            continue
        if not isinstance(ev, dict):
            facts.bad_lines += 1
            continue
        if ev.get("type") == "result":
            facts.has_result = True
            facts.final_text = str(ev.get("result") or "")
            try:
                facts.cost_usd = float(ev.get("total_cost_usd") or 0.0)
            except (TypeError, ValueError):
                facts.cost_usd = 0.0
            model_usage = ev.get("modelUsage")
            if isinstance(model_usage, dict) and model_usage:
                result_usage = [u for u in model_usage.values() if isinstance(u, dict)]
            continue
        if ev.get("type") != "assistant":
            continue
        msg = ev.get("message")
        if not isinstance(msg, dict):
            continue
        mid = msg.get("id")
        # The stream repeats one message once per content block; count its usage once.
        if mid is None or mid not in seen:
            if mid is not None:
                seen.add(mid)
            usage = msg.get("usage") if isinstance(msg.get("usage"), dict) else {}
            facts.output_tokens += _int(usage.get("output_tokens"))
            facts.cache_read_tokens += _int(usage.get("cache_read_input_tokens"))
            facts.cache_write_tokens += _int(usage.get("cache_creation_input_tokens"))
        content = msg.get("content")
        for block in content if isinstance(content, list) else []:
            if not isinstance(block, dict) or block.get("type") != "tool_use":
                continue
            inp = block.get("input")
            if (not facts.tier_chosen and block.get("name") in ("Agent", "Task")
                    and isinstance(inp, dict)):
                st = inp.get("subagent_type")
                if isinstance(st, str) and st.startswith("plan-architect"):
                    facts.tier_chosen = st
            if not facts.contaminated:
                m = key_re.search(json.dumps(inp))
                if m:
                    facts.contaminated = m.group(0)
    if result_usage:
        # The result event's per-model totals include sub-agent turns the stream omits.
        facts.output_tokens = sum(_int(u.get("outputTokens")) for u in result_usage)
        facts.cache_read_tokens = sum(_int(u.get("cacheReadInputTokens")) for u in result_usage)
        facts.cache_write_tokens = sum(_int(u.get("cacheCreationInputTokens"))
                                       for u in result_usage)
    return facts


def _strip_decoration(line: str) -> str:
    s = re.sub(r"^[-*]\s+", "", line.strip())
    return s.replace("**", "").replace("`", "").strip()


def _newest_slug_plan(plans_dir: Path, slug: str) -> Optional[Path]:
    rx = re.compile("^" + re.escape(slug) + r"(-\d+)?\.md$")
    found = [p for p in plans_dir.iterdir() if p.is_file() and rx.match(p.name)] \
        if plans_dir.is_dir() else []
    return max(found, key=lambda p: p.stat().st_mtime) if found else None


def resolve_plan(final_text: str, plans_dir: Path, slug: str) -> tuple:
    """(status, detail, plan path or None) from the final message's last line."""
    lines = [ln for ln in final_text.splitlines() if ln.strip()]
    m = _MARKER_RE.match(_strip_decoration(lines[-1])) if lines else None
    bad_marker = False
    if m and m.group(1) == "NO_PLAN":
        return "no_plan", m.group(2).strip() or "no plan", None
    if m and m.group(1) == "PLAN_FILE":
        path = m.group(2).strip()
        if path:
            real = Path(os.path.realpath(path))
            base = Path(os.path.realpath(plans_dir))
            if real.is_file() and base in real.parents:
                return "ok", "", real
        bad_marker = True
    adopted = _newest_slug_plan(plans_dir, slug)
    if adopted is not None:
        return "ok", f"marker missing; adopted {adopted.name}", adopted
    return "no_plan", "marker outside plans dir" if bad_marker else "no marker", None


def sweep_leaks(plans_home: Path, slug: Optional[str] = None,
                pattern: Optional["re.Pattern[str]"] = None) -> list[str]:
    """Delete regular files in plans_home and its .drafts/ named for this run; return them."""
    rx = pattern or re.compile("^" + re.escape(slug or "") + r"([.-]|$)")
    hits: list[str] = []
    for d in (plans_home, plans_home / ".drafts"):
        if not d.is_dir():
            continue
        for p in sorted(d.iterdir()):
            if p.is_file() and not p.is_symlink() and rx.match(p.name):
                p.unlink()
                hits.append(p.relative_to(plans_home).as_posix())
    return hits


def record_path(cache_dir: Path, case_id: str, k: int, status: str) -> Path:
    suffix = ".degraded.json" if status in ("degraded", "leaked") else ".json"
    return cache_dir / f"{case_id}-r{k}{suffix}"


def write_record(cache_dir: Path, record: dict) -> Path:
    path = record_path(cache_dir, record["case_id"], record["run"], record["status"])
    tmp = path.with_name(path.name + ".tmp")
    tmp.write_text(json.dumps(record, indent=2, sort_keys=True) + "\n")
    os.replace(tmp, path)
    return path


def _gate_rc(argv: list[str], cwd: Path) -> int:
    try:
        return subprocess.run(argv, cwd=str(cwd), capture_output=True, text=True,
                              timeout=GATE_TIMEOUT).returncode
    except subprocess.TimeoutExpired:
        return 124


def _invoke_claude(argv: list[str], cwd: Path, env: dict, timeout: int) -> tuple:
    """(stdout, returncode, timed_out) with the whole process group reaped."""
    from .adapters.llm import _run_reaped  # lazy: keeps `validate` import-light
    try:
        proc = _run_reaped(argv, "", timeout, str(cwd), env)
        return proc.stdout or "", proc.returncode, False
    except subprocess.TimeoutExpired as e:
        out = e.output
        text = out.decode("utf-8", "replace") if isinstance(out, bytes) else (out or "")
        return text, None, True


def run_case(case: dict, k: int, *, repo: Path, source_root: Path, corpus_dir: Path,
             cache_dir: Path, claude: Optional[str] = None, model: str = DEFAULT_MODEL,
             timeout: int = DEFAULT_TIMEOUT, plans_home: Optional[Path] = None) -> dict:
    slug = run_slug(case["id"], k)
    home = Path(plans_home) if plans_home else Path.home() / "claude-plans"
    cache_dir = Path(cache_dir)
    cache_dir.mkdir(parents=True, exist_ok=True)
    record: dict = {
        "case_id": case["id"], "run": k, "slug": slug, "model": model,
        "status": "degraded", "detail": "", "tier_chosen": "", "cost_usd": 0.0,
        "output_tokens": 0, "cache_read_tokens": 0, "cache_write_tokens": 0,
        "bad_lines": 0, "exit_code": None, "check_plan_rc": None, "staleness_rc": None,
        "critical_files": [], "plan_text": "", "started": int(time.time()),
    }
    try:
        prompt_text = (Path(corpus_dir) / case["prompt"]).read_text()
        with ReplayClone(Path(repo), case["base_sha"]) as rc:
            overlay_surface(Path(source_root), rc.root)
            write_stub_gh(rc.stub_bin)
            prompt = build_prompt(prompt_text, slug, rc.plans_dir, case["id"])
            argv = [claude or claude_bin(), "-p", prompt, "--dangerously-skip-permissions",
                    "--model", model, "--effort", "high", "--output-format", "stream-json",
                    "--verbose", "--session-id", str(uuid.uuid4()),
                    "--name", f"plan-eval {slug}"]
            env = dict(os.environ)
            env["CLAUDE_HEADLESS"] = "1"
            env["CLAUDE_CODE_PRINT_BG_WAIT_CEILING_MS"] = "0"
            env["PATH"] = f"{rc.stub_bin}{os.pathsep}{os.environ.get('PATH', '')}"
            out, exit_code, timed_out = _invoke_claude(argv, rc.root, env, timeout)
            (cache_dir / f"{slug}.stream.jsonl").write_text(out)
            facts = parse_stream(out.splitlines(), case["id"])
            record.update(tier_chosen=facts.tier_chosen, cost_usd=facts.cost_usd,
                          output_tokens=facts.output_tokens,
                          cache_read_tokens=facts.cache_read_tokens,
                          cache_write_tokens=facts.cache_write_tokens,
                          bad_lines=facts.bad_lines, exit_code=exit_code)
            if timed_out:
                record["detail"] = f"timeout after {timeout}s"
            elif not facts.has_result:
                record["detail"] = f"no result event (exit {exit_code})"
            elif facts.contaminated:
                record["detail"] = f"contaminated: {facts.contaminated}"
            else:
                status, detail, plan = resolve_plan(facts.final_text, rc.plans_dir, slug)
                record.update(status=status, detail=detail)
                if plan is not None:
                    record["check_plan_rc"] = _gate_rc(
                        [str(rc.root / "bin/check-plan"), str(plan)], rc.root)
                    record["staleness_rc"] = _gate_rc(
                        [str(rc.root / "bin/check-plan-staleness"), str(plan)], rc.root)
                    record["critical_files"] = critical_files_of(plan, rc.root / "bin/check-plan")
                    record["plan_text"] = plan.read_text(errors="replace")
    except (SurfaceError, OSError) as e:
        record.update(status="degraded", detail=f"setup failed: {e}")
    leaks = sweep_leaks(home, slug)
    if leaks:
        record.update(status="leaked", detail="leaked: " + ", ".join(leaks))
    write_record(cache_dir, record)
    return record


def _load_case(corpus_path: Path, case_id: str) -> dict:
    corpus = load_corpus(corpus_path)
    for c in corpus["cases"]:
        if c["id"] == case_id:
            return c
    raise EvalUsageError(f"unknown case: {case_id}")


def _cmd_worker(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    root = _repo_root(args.repo)
    corpus_path = _corpus_path(root, args.corpus)
    cache_dir = Path(args.cache_dir)
    pending = cache_dir / f"{args.case}-r{args.run}.pending"
    case = _load_case(corpus_path, args.case)
    kw = dict(repo=root, corpus_dir=corpus_path.parent, cache_dir=cache_dir,
              model=args.model, timeout=args.timeout,
              plans_home=Path(args.plans_home) if args.plans_home else None)
    if args.source_ref:
        from .gate_backtest_replay import ScratchTree  # lazy, as above
        with ScratchTree(str(root), args.source_ref) as st:
            run_case(case, args.run, source_root=Path(st.dir), **kw)
    else:
        run_case(case, args.run, source_root=Path(args.source_root), **kw)
    # run_case wrote the record. On a crash the .pending stays, so key_state
    # reports the documented degraded "worker died" record.
    try:
        pending.unlink()
    except FileNotFoundError:
        pass
    return 0


# ---------------------------------------------------------------------------
# Phase 6: detached `run`, cache, estimate, `compare`, `smoke`
# ---------------------------------------------------------------------------

HARNESS_DIR = Path(__file__).resolve().parents[1]
FAKE_CLAUDE = HARNESS_DIR / "fixtures" / "plan-eval" / "fake-claude.py"
# Frozen decision 6: measured per-run medians of past bin/plan-now runs.
EST_OUTPUT = 73500
EST_CACHE_READ = 7960000
EST_CACHE_WRITE = 356000
EST_WALL_MIN = 20.3
POLL_SECONDS = 15
# Past the claude timeout a worker still clones, gc-prunes and runs two gates.
DEAD_GRACE = 2 * GATE_TIMEOUT + 1200


def select_run_cases(corpus: dict, spec: str) -> list[dict]:
    cases = corpus.get("cases") or []
    if not cases:
        raise EvalUsageError("empty corpus")
    if spec == "core":
        sel = [c for c in cases if c.get("core")]
    elif spec == "all":
        sel = list(cases)
    else:
        by_id = {c["id"]: c for c in cases}
        ids = [s for s in spec.split(",") if s]
        unknown = [s for s in ids if s not in by_id]
        if unknown or not ids:
            raise EvalUsageError(f"unknown case id: {', '.join(unknown) or spec!r}")
        sel = [by_id[s] for s in ids]
    return sorted(sel, key=lambda c: c["id"])


def _pid_alive(pid: int) -> bool:
    try:
        done, _ = os.waitpid(pid, os.WNOHANG)  # reaps our own exited child
        if done == pid:
            return False
    except ChildProcessError:
        pass
    except OSError:
        return False
    try:
        os.kill(pid, 0)
    except ProcessLookupError:
        return False
    except PermissionError:
        return True
    return True


def _terminal(cache_dir: Path, key: str) -> Optional[Path]:
    for suffix in (".json", ".degraded.json"):
        p = cache_dir / f"{key}{suffix}"
        if p.exists():
            return p
    return None


def _read_pending(path: Path) -> tuple:
    try:
        info = json.loads(path.read_text())
        return int(info["pid"]), float(info["started"]), info.get("timeout")
    except (OSError, ValueError, KeyError, TypeError, AttributeError):
        return 0, 0.0, None


def key_state(cache_dir: Path, case_id: str, k: int, timeout: int, now: float,
              mutate: bool = True) -> str:
    """terminal | pending | missing. With mutate, a dead worker becomes a degraded record."""
    key = f"{case_id}-r{k}"
    pending = cache_dir / f"{key}.pending"
    if _terminal(cache_dir, key):
        return "terminal"
    if pending.exists():
        pid, started, worker_timeout = _read_pending(pending)
        # Judge the worker by the timeout it was launched with, not this call's.
        if isinstance(worker_timeout, (int, float)) and worker_timeout > 0:
            timeout = worker_timeout
        if pid and _pid_alive(pid) and now - started < timeout + DEAD_GRACE:
            return "pending"
        if not mutate:
            return "missing"
        if _terminal(cache_dir, key):  # finished between the two checks
            return "terminal"
        write_record(cache_dir, {"case_id": case_id, "run": k, "slug": run_slug(case_id, k),
                                 "status": "degraded", "detail": "worker died",
                                 "cost_usd": 0.0, "tier_chosen": ""})
        pending.unlink()
        return "terminal"
    return "terminal" if _terminal(cache_dir, key) else "missing"


def launch_worker(spec: dict) -> int:
    """Start a detached `_worker` (own session, so it outlives this process); return its pid."""
    cache_dir = Path(spec["cache_dir"])
    key = f"{spec['case_id']}-r{spec['run']}"
    env = dict(os.environ)
    prior = env.get("PYTHONPATH")
    env["PYTHONPATH"] = str(HARNESS_DIR) + (os.pathsep + prior if prior else "")
    env.update(spec.get("env") or {})
    with open(cache_dir / f"{key}.worker.log", "ab") as log:
        proc = subprocess.Popen(worker_argv(spec), start_new_session=True,
                                stdin=subprocess.DEVNULL, stdout=log,
                                stderr=subprocess.STDOUT, cwd=str(spec["repo"]), env=env)
    return proc.pid


def worker_argv(spec: dict) -> list[str]:
    argv = [sys.executable, "-m", "harness.plan_eval", "_worker",
            "--case", spec["case_id"], "--run", str(spec["run"]),
            "--cache-dir", str(spec["cache_dir"]), "--corpus", str(spec["corpus"]),
            "--repo", str(spec["repo"]), "--model", spec["model"],
            "--timeout", str(spec["timeout"])]
    if spec.get("source_ref"):
        argv += ["--source-ref", spec["source_ref"]]
    else:
        argv += ["--source-root", str(spec["source_root"])]
    if spec.get("plans_home"):
        argv += ["--plans-home", str(spec["plans_home"])]
    return argv


def _write_pending(cache_dir: Path, key: str, pid: int, timeout: Optional[int] = None) -> None:
    tmp = cache_dir / f"{key}.pending.tmp"
    info = {"pid": pid, "started": int(time.time())}
    if timeout:
        info["timeout"] = timeout
    tmp.write_text(json.dumps(info) + "\n")
    os.replace(tmp, cache_dir / f"{key}.pending")


def estimate_line(n: int, parallel: int) -> str:
    return (f"estimate launch={n} output_tokens={n * EST_OUTPUT} "
            f"cache_read={n * EST_CACHE_READ} cache_write={n * EST_CACHE_WRITE} "
            f"wall_serial_min={n * EST_WALL_MIN:.1f} "
            f"wall_parallel_min={math.ceil(n / parallel) * EST_WALL_MIN:.1f}")


def _check_run_args(args: argparse.Namespace) -> None:
    if not 1 <= args.runs <= 3:
        raise EvalUsageError("--runs must be 1 to 3")
    if args.wait < 0:
        raise EvalUsageError("--wait must be >= 0")
    if not 1 <= args.parallel <= 4:
        raise EvalUsageError("--parallel must be 1 to 4")
    if args.timeout < 1:
        raise EvalUsageError("--timeout must be >= 1")


def _cache_key(root: Path, corpus: dict, corpus_path: Path, base: Optional[str]) -> tuple:
    c = corpus_digest(corpus, corpus_path.parent)
    if base:
        r = _git(root, "rev-parse", "--verify", "-q", base + "^{commit}")
        sha = r.stdout.strip()
        if r.returncode != 0 or not sha:
            raise EvalUsageError(f"unknown --base ref: {base}")
        return surface_hash_ref(root, sha), c, sha
    return surface_hash_worktree(root), c, None


def _cmd_run(args: argparse.Namespace, out: TextIO = sys.stdout, err: TextIO = sys.stderr,
             launcher: Optional[Callable[[dict], int]] = None,
             sleep: Callable[[float], None] = time.sleep) -> int:
    _check_run_args(args)
    root = _repo_root(args.repo)
    corpus_path = _corpus_path(root, args.corpus)
    corpus = load_corpus(corpus_path)
    selected = select_run_cases(corpus, args.cases)
    v, c, source_ref = _cache_key(root, corpus, corpus_path, args.base)
    model_tag = ""
    if args.model != DEFAULT_MODEL:  # default keeps the bare key so old records still hit
        model_tag = "-" + re.sub(r"[^A-Za-z0-9._]+", "_", args.model)
    cache_dir = root / CACHE_ROOT / f"{v}-{c}{model_tag}"
    keys = [(case, k) for case in selected for k in range(1, args.runs + 1)]
    plans_home = Path(args.plans_home) if args.plans_home else Path.home() / "claude-plans"

    if args.dry_run:
        n = 0
        for case, k in keys:
            st = (key_state(cache_dir, case["id"], k, args.timeout, time.time(), mutate=False)
                  if cache_dir.is_dir() else "missing")
            if args.retry_degraded and (cache_dir / f"{case['id']}-r{k}.degraded.json").exists():
                st = "missing"
            word = {"terminal": "cached", "pending": "pending", "missing": "to-launch"}[st]
            print(f"{word} {case['id']}-r{k}", file=out)
            n += st == "missing"
        print(estimate_line(n, args.parallel), file=out)
        return 0

    cache_dir.mkdir(parents=True, exist_ok=True)
    if args.retry_degraded:
        for case, k in keys:
            p = cache_dir / f"{case['id']}-r{k}.degraded.json"
            if p.exists():
                p.unlink()
    launch = launcher or launch_worker
    deadline = time.time() + args.wait
    while True:
        states = {(case["id"], k): key_state(cache_dir, case["id"], k, args.timeout, time.time())
                  for case, k in keys}
        alive = sum(1 for s in states.values() if s == "pending")
        for case, k in keys:
            if states[(case["id"], k)] != "missing" or alive >= args.parallel:
                continue
            pid = launch({"case_id": case["id"], "run": k, "cache_dir": cache_dir,
                          "corpus": corpus_path, "repo": root, "model": args.model,
                          "timeout": args.timeout, "source_ref": source_ref,
                          "source_root": root, "plans_home": args.plans_home})
            if not _terminal(cache_dir, f"{case['id']}-r{k}"):
                _write_pending(cache_dir, f"{case['id']}-r{k}", pid, args.timeout)
            states[(case["id"], k)] = "pending"
            alive += 1
        waiting = sum(1 for s in states.values() if s != "terminal")
        if not waiting:
            break
        remaining = deadline - time.time()
        if remaining <= 0:
            print(f"pending={waiting} cache={cache_dir}", file=out)
            return 4
        sleep(min(POLL_SECONDS, remaining))

    extra_leaks = sweep_leaks(plans_home, pattern=LEAK_ANY_RE)
    records, scores = [], []
    for case, k in keys:
        rec = json.loads(_terminal(cache_dir, f"{case['id']}-r{k}").read_text())  # type: ignore[union-attr]
        s = score_run(rec, case)
        records.append(rec)
        scores.append(s)
        print(f"case {case['id']} r{k} {rec.get('status')} mech={int(s.mech)} "
              f"files={s.file_hits}/{s.file_total} "
              f"tier={rec.get('tier_chosen') or '-'}/{case['tier']} "
              f"cost_usd={float(rec.get('cost_usd') or 0.0):.2f}", file=err)
    if extra_leaks:
        print(f"leaked after run: {', '.join(extra_leaks)}", file=err)
    if not any(s.scored for s in scores):
        # Every run degraded or leaked: no score line exists, but the documented exit is 3.
        print(f"no scored runs: degraded={sum(1 for r in records if r.get('status') == 'degraded')} "
              f"leaked={sum(1 for r in records if r.get('status') == 'leaked') + len(extra_leaks)}",
              file=err)
        return 3
    score = compute_score(scores, records, v=v, c=c)
    if extra_leaks:
        from dataclasses import replace
        score = replace(score, leaked=score.leaked + 1)
    print(format_score_line(score), file=out)
    return 3 if score.degraded + score.leaked > 0 else 0


def _cmd_compare(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    worse = is_worse(parse_score_line(args.before), parse_score_line(args.after))
    print("worse" if worse else "not-worse", file=out)
    return 1 if worse else 0


def _fake_claude_shim(where: Path) -> Path:
    shim = where / "fake-claude"
    shim.write_text(f'#!/bin/sh\nexec "{sys.executable}" "{FAKE_CLAUDE}" "$@"\n')
    shim.chmod(0o755)
    return shim


def _smoke_case(corpus: dict) -> dict:
    core = sorted((c for c in corpus.get("cases") or [] if c.get("core")), key=lambda c: c["id"])
    if not core:
        raise EvalUsageError("smoke needs a core case")
    return core[0]


def _cmd_smoke(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    root = _repo_root(args.repo)
    corpus_path = _corpus_path(root, args.corpus)
    corpus = load_corpus(corpus_path)
    case = _smoke_case(corpus)
    source_root = Path(args.source_root) if args.source_root else root
    tmp = Path(tempfile.mkdtemp(prefix="plan-eval-smoke-")).resolve()
    try:
        shim = _fake_claude_shim(tmp)
        cache_dir = tmp / "cache"
        plans_home = tmp / "plans-home"
        if args.detached:
            return _smoke_detached(root, corpus_path, case, source_root, cache_dir,
                                   plans_home, shim, out)
        rec = run_case(case, 1, repo=root, source_root=source_root,
                       corpus_dir=corpus_path.parent, cache_dir=cache_dir, claude=str(shim),
                       timeout=300, plans_home=plans_home)
        problems = []
        if rec.get("status") != "ok":
            problems.append(f"status {rec.get('status')} ({rec.get('detail')})")
        if not rec.get("critical_files"):
            problems.append("no critical files")
        if rec.get("check_plan_rc") != 0:
            problems.append(f"check_plan_rc={rec.get('check_plan_rc')}")
        if problems:
            print("smoke FAIL " + "; ".join(problems), file=out)
            return 1
        line = format_score_line(compute_score([score_run(rec, case)], [rec],
                                               v="0" * 12, c="0" * 12))
        if format_score_line(parse_score_line(line)) != line:
            print(f"smoke FAIL score line does not round-trip: {line}", file=out)
            return 1
        print(f"smoke ok {line}", file=out)
        return 0
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def _smoke_detached(root: Path, corpus_path: Path, case: dict, source_root: Path,
                    cache_dir: Path, plans_home: Path, shim: Path, out: TextIO) -> int:
    cache_dir.mkdir(parents=True)
    spec = {"case_id": case["id"], "run": 1, "cache_dir": str(cache_dir),
            "corpus": str(corpus_path), "repo": str(root), "model": DEFAULT_MODEL,
            "timeout": 300, "source_ref": None, "source_root": str(source_root),
            "plans_home": str(plans_home),
            "env": {"PLAN_EVAL_CLAUDE": str(shim), "FAKE_CLAUDE_SLEEP": "3"}}
    env = dict(os.environ)
    prior = env.get("PYTHONPATH")
    env["PYTHONPATH"] = str(HARNESS_DIR) + (os.pathsep + prior if prior else "")
    launcher = subprocess.Popen([sys.executable, "-m", "harness.plan_eval", "_smoke_launcher",
                                 json.dumps(spec)], start_new_session=True,
                                stdin=subprocess.DEVNULL, env=env, cwd=str(root))
    launcher.wait()
    record = cache_dir / f"{case['id']}-r1.json"
    deadline = time.time() + 120
    while time.time() < deadline:
        if record.exists():
            break
        time.sleep(0.5)
    if record.exists() and json.loads(record.read_text()).get("status") == "ok":
        print("smoke-detached ok", file=out)
        return 0
    print("smoke-detached FAIL worker did not survive launcher kill", file=out)
    return 1


def _cmd_smoke_launcher(args: argparse.Namespace, out: TextIO = sys.stdout) -> int:
    """Start the worker the way `run` does, then SIGKILL our own process group."""
    spec = json.loads(args.spec)
    pid = launch_worker(spec)
    _write_pending(Path(spec["cache_dir"]), f"{spec['case_id']}-r{spec['run']}", pid)
    os.killpg(os.getpgrp(), signal.SIGKILL)
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="plan_eval")
    sub = parser.add_subparsers(dest="cmd", required=True)
    v = sub.add_parser("validate", help="validate the corpus schema, history, and overlay")
    v.add_argument("--corpus", metavar="PATH", default=None)
    v.add_argument("--repo", metavar="DIR", default=None)
    v.set_defaults(func=_cmd_validate)
    mn = sub.add_parser("mine", help="select the corpus from the frozen prompt set")
    mn.add_argument("--prompts-dir", metavar="DIR", required=True)
    mn.add_argument("--out-dir", metavar="DIR", default=None)
    mn.add_argument("--repo", metavar="DIR", default=None)
    mn.add_argument("--dry-run", action="store_true")
    mn.set_defaults(func=_cmd_mine)
    w = sub.add_parser("_worker")
    w.add_argument("--case", required=True)
    w.add_argument("--run", type=int, required=True)
    w.add_argument("--source-root", default=None)
    w.add_argument("--source-ref", default=None)
    w.add_argument("--cache-dir", required=True)
    w.add_argument("--corpus", default=None)
    w.add_argument("--repo", default=None)
    w.add_argument("--model", default=DEFAULT_MODEL)
    w.add_argument("--timeout", type=int, default=DEFAULT_TIMEOUT)
    w.add_argument("--plans-home", default=None)
    w.set_defaults(func=_cmd_worker)
    r = sub.add_parser("run", help="run the selected cases (detached) and print the score line")
    r.add_argument("--corpus", metavar="PATH", default=None)
    r.add_argument("--repo", metavar="DIR", default=None)
    r.add_argument("--cases", default="core", help="core | all | ID[,ID...]")
    r.add_argument("--runs", type=int, default=1)
    r.add_argument("--base", metavar="REF", default=None)
    r.add_argument("--wait", type=int, default=540)
    r.add_argument("--parallel", type=int, default=2)
    r.add_argument("--model", default=DEFAULT_MODEL)
    r.add_argument("--timeout", type=int, default=DEFAULT_TIMEOUT)
    r.add_argument("--dry-run", action="store_true")
    r.add_argument("--retry-degraded", action="store_true")
    r.add_argument("--plans-home", metavar="DIR", default=None, help=argparse.SUPPRESS)
    r.set_defaults(func=_cmd_run)
    cp = sub.add_parser("compare", help="compare two score lines")
    cp.add_argument("--before", required=True)
    cp.add_argument("--after", required=True)
    cp.set_defaults(func=_cmd_compare)
    sm = sub.add_parser("smoke", help="end-to-end check with a fake model")
    sm.add_argument("--detached", action="store_true")
    sm.add_argument("--corpus", metavar="PATH", default=None)
    sm.add_argument("--repo", metavar="DIR", default=None)
    sm.add_argument("--source-root", metavar="DIR", default=None)
    sm.set_defaults(func=_cmd_smoke)
    sl = sub.add_parser("_smoke_launcher")
    sl.add_argument("spec")
    sl.set_defaults(func=_cmd_smoke_launcher)
    return parser


def main(argv: Optional[list[str]] = None) -> int:
    parser = build_parser()
    try:
        args = parser.parse_args(argv)
    except SystemExit as e:
        return e.code if isinstance(e.code, int) else 2
    try:
        return args.func(args, sys.stdout)  # late-bound, so a redirected stdout is honoured
    except EvalUsageError as e:
        print(f"usage error: {e}", file=sys.stderr)
        return 2
    except SurfaceError as e:
        print(f"surface error: {e}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    sys.exit(main())
