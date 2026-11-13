"""Offline half of the second-reviewer eval: corpus schema, digest, surface hash, score line."""
from __future__ import annotations

import argparse
import datetime
import hashlib
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile
import time
from dataclasses import dataclass
from pathlib import Path
from types import SimpleNamespace
from typing import Callable, Iterable, Optional

from harness import fidelity, second_review
from harness.armable import SECOND_REVIEW_CLEARABLE
from harness.fidelity import REMEDIATION_DIFF_INLINE_CAP
from harness.reviewer_eval import (
    EvalUsageError,
    _STATUSES,
    _gitt,
    _last_line,  # noqa: F401  (used by compare)
    _repo_root,
    blamed_by_fix_within,
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


# ---------------------------------------------------------------------------
# Phase 2: history miner. Reads gh and git only; writes the gitignored candidates file.
# ---------------------------------------------------------------------------

GH_REPO = "a-jay85/IBL5"
DEFAULT_SINCE = "2026-10-13"
CANDIDATES_REL = "tools/postplan-harness/out/second-review-eval/candidates.jsonl"
FIX_WINDOW_DAYS = 14
LABEL_APPROVED = "human-approved"
LABEL_NEEDS_HUMAN = "needs-a-human"
# fidelity.py renders the held block as the exact `Auto-merge held:` line followed by
# `- (N) <plain words>` bullets, so a held bullet always opens with this prefix.
HELD_HEADER = "Auto-merge held:"
HELD_BULLET_PREFIX = "- ("
_HELD_NUM_RE = re.compile(r"\((\d+)\)\s*(.*)")
_SEAT_CLEARED_RE = re.compile(r"Second reviewer cleared \((\d+)\) [^:\s]+: (.+)$")
_REVIEWED_TREE_RE = re.compile(r"\*\*Reviewed tree:\*\* ([0-9a-f]{40})")
_SAFE_REF_RE = re.compile(r"^[A-Za-z0-9._-]+$")
WRITE_FLAGS = frozenset({"-X", "--method", "-f", "-F", "--field", "--raw-field", "--input"})
_FIELD_FLAGS = ("-f", "-F", "--field", "--raw-field")
_PR_JSON = "number,title,body,labels,headRefName,headRefOid,mergeCommit,mergedAt"
_EDITS_QUERY = ("query($id: ID!) { node(id: $id) { ... on IssueComment { "
                "userContentEdits(first: 100) { nodes { editedAt diff } } } } }")


def _flag_name(arg: str) -> str:
    """`--method=POST` -> `--method`, `-XPOST` -> `-X`; other args unchanged."""
    if arg.startswith("--"):
        return arg.split("=", 1)[0]
    if arg.startswith("-") and len(arg) > 2:
        return arg[:2]
    return arg


def _field_values(raw: list[str]) -> list[str]:
    values = []
    for i, a in enumerate(raw):
        if a in _FIELD_FLAGS:
            values.append(raw[i + 1] if i + 1 < len(raw) else "")
        elif a.startswith("--") and _flag_name(a) in _FIELD_FLAGS:
            values.append(a.split("=", 1)[1])
        elif a[:2] in ("-f", "-F") and len(a) > 2:
            values.append(a[2:])
    return values


def _assert_read_only(argv: list[str]) -> None:
    """Raise unless argv is a read-only gh call: pr list/view, a GET on the repo, or a query."""
    raw = list(argv)
    argv = [_flag_name(a) for a in raw]
    if argv[:2] == ["api", "graphql"]:
        if any(a in ("-X", "--method", "--input") for a in argv):
            raise EvalUsageError(f"gh argv is not read-only: {raw}")
        queries = [v[len("query="):] for v in _field_values(raw) if v.startswith("query=")]
        if len(queries) != 1 or not queries[0].lstrip().startswith("query"):
            raise EvalUsageError(f"gh graphql argv is not a query: {raw}")
        return
    if any(a in WRITE_FLAGS for a in argv):
        raise EvalUsageError(f"gh argv is not read-only: {raw}")
    if argv[:2] in (["pr", "list"], ["pr", "view"]):
        return
    if argv[:1] == ["api"] and any(a.startswith(f"repos/{GH_REPO}/") for a in argv[1:]):
        return
    raise EvalUsageError(f"gh argv is not an allowed read: {raw}")


def live_gh_json(argv: list[str]):
    from .gate_backtest_replay import live_gh_json as _live
    _assert_read_only(argv)
    return _live(argv)


def make_git(repo: str):
    """git(argv, stdin=None) -> stdout. Raises CalledProcessError on failure."""
    from .gate_backtest_replay import make_git as _make_git
    plain = _make_git(repo)

    def git(argv: list[str], stdin: Optional[str] = None) -> str:
        if stdin is None:
            return plain(argv)
        proc = subprocess.run(["git", "-C", repo, *argv], input=stdin, capture_output=True,
                              text=True, check=True)
        return proc.stdout
    return git


def _flatten(pages) -> list:
    """`gh api --paginate --slurp` wraps each page's array in an outer array."""
    out: list = []
    for page in pages or []:
        out.extend(page if isinstance(page, list) else [page])
    return out


def parse_held_block(text: str) -> dict[int, str]:
    """{condition number: bullet text} from the bullets under `Auto-merge held:`."""
    lines = (text or "").splitlines()
    start = next((i for i, ln in enumerate(lines) if ln.strip() == HELD_HEADER), None)
    if start is None:
        return {}
    holds: dict[int, str] = {}
    for line in lines[start + 1:]:
        if not line.strip():
            break
        if not line.startswith(HELD_BULLET_PREFIX):
            continue
        m = _HELD_NUM_RE.search(line)
        if m:
            holds[int(m.group(1))] = m.group(2).strip()
    return holds


def parse_seat_cleared(text: str) -> dict[int, str]:
    """{condition number: reviewer reason} from `Second reviewer cleared (n) name: reason`."""
    holds: dict[int, str] = {}
    for line in (text or "").splitlines():
        m = _SEAT_CLEARED_RE.search(line)
        if m:
            holds[int(m.group(1))] = m.group(2).strip()
    return holds


def reviewed_tree(text: str) -> Optional[str]:
    m = _REVIEWED_TREE_RE.search(text or "")
    return m.group(1) if m else None


def pick_version(versions: list[tuple[Optional[str], dict[int, str]]]):
    """The newest (tree, holds) whose holds are non-empty and all seat-clearable."""
    for tree, held in versions:
        if held and all(n in SECOND_REVIEW_CLEARABLE for n in held):
            return tree, held
    return None


def classify(*, labels, replay_patch_id, final_patch_id, changes_requested,
             fix_shas) -> tuple[str, str]:
    """(expected, label_basis) for one seat case. The first matching rule wins."""
    if fix_shas:
        return ("hold", "reverted")
    if "needs-a-human" in labels:
        return ("hold", "needs-a-human")
    if LABEL_APPROVED in labels:
        if changes_requested or replay_patch_id != final_patch_id:
            return ("hold", "changed-after-hold")
        return ("clear", "approved-as-is")
    return ("clear", "seat-cleared-clean")


def _patch_id(git, base: str, sha: str) -> str:
    diff = git(["diff", f"{base}...{sha}"])
    if not diff.strip():
        return ""
    out = git(["patch-id", "--stable"], stdin=diff)
    return out.split()[0] if out.split() else ""


def _sticky_versions(gh_json, number: int):
    """(final sticky body or None, [version bodies newest first])."""
    comments = _flatten(gh_json(["api", f"repos/{GH_REPO}/issues/{number}/comments",
                                 "--paginate", "--slurp"]))
    stickies = [c for c in comments if fidelity.STICKY_MARKER in (c.get("body") or "")]
    if not stickies:
        return None, []
    sticky = stickies[-1]
    bodies = [sticky.get("body") or ""]
    node_id = sticky.get("node_id")
    if node_id:
        data = gh_json(["api", "graphql", "-f", f"query={_EDITS_QUERY}", "-f", f"id={node_id}"])
        nodes = ((((data or {}).get("data") or {}).get("node") or {})
                 .get("userContentEdits") or {}).get("nodes") or []
        nodes = sorted(nodes, key=lambda n: n.get("editedAt") or "", reverse=True)
        bodies.extend(n.get("diff") or "" for n in nodes if n.get("diff"))
    return bodies[0], bodies


def _changes_requested(gh_json, number: int) -> bool:
    reviews = _flatten(gh_json(["api", f"repos/{GH_REPO}/pulls/{number}/reviews",
                                "--paginate", "--slurp"]))
    return any(r.get("state") == "CHANGES_REQUESTED" for r in reviews)


def _reason(pr: dict, plans_dir: Path) -> tuple[str, str, Optional[str]]:
    head = pr.get("headRefName") or ""
    plan = plans_dir / f"{head}.md" if _SAFE_REF_RE.match(head) else None
    if plan is not None and plan.is_file():
        text = plan.read_text(encoding="utf-8", errors="replace")
        note = None
        merged = pr.get("mergedAt")
        if merged:
            merged_ts = datetime.datetime.fromisoformat(merged.replace("Z", "+00:00")).timestamp()
            if plan.stat().st_mtime > merged_ts:
                note = "plan file may have changed since arming"
        return second_review.reason_text(text, pr.get("title") or "", pr.get("body")), "plan", note
    return second_review.reason_text(None, pr.get("title") or "", pr.get("body")), "pr-body", None


def _replay_commit(git, head: str, tree: Optional[str]) -> Optional[str]:
    if not tree or not head:
        return None
    try:
        log = git(["log", "--first-parent", "--format=%H %T", head])
    except subprocess.CalledProcessError:
        return None
    for line in log.splitlines():
        parts = line.split()
        if len(parts) == 2 and parts[1] == tree:
            return parts[0]
    return None


def suggested_id(number: int, expected: Optional[str], holds: dict[int, str]) -> str:
    nums = "-".join(str(n) for n in sorted(holds))
    return f"pr{number}-{expected or 'skip'}{nums}"


def _mine_row(root: Path, pr: dict, *, gh_json, git, plans_dir: Path, fixes) -> dict:
    number = pr["number"]
    labels = {lab.get("name") for lab in pr.get("labels") or []}
    row: dict = {"pr": number, "title": pr.get("title") or "", "holds": {}, "base_sha": None,
                 "replay_sha": None, "expected": None, "label_basis": None,
                 "seat_eligible": False, "skip_reason": None}
    final, bodies = _sticky_versions(gh_json, number)
    seat_holds = parse_seat_cleared(final or "")
    versions = [(reviewed_tree(b), parse_held_block(b)) for b in bodies]
    if seat_holds and not (labels & {LABEL_APPROVED, LABEL_NEEDS_HUMAN}):
        versions = [(reviewed_tree(final or ""), seat_holds)]
    picked = pick_version(versions)
    if picked is None:
        row["skip_reason"] = "non-clearable-hold"
        return row
    tree, held = picked
    row["holds"] = {str(n): held[n] for n in sorted(held)}
    head = pr.get("headRefOid") or ""
    replay = _replay_commit(git, head, tree)
    if replay is None:
        row["skip_reason"] = "reviewed-tree-not-in-history"
        return row
    base = git(["merge-base", replay, "origin/master"]).strip()
    row["replay_sha"], row["base_sha"] = replay, base
    diff_bytes = git(["diff", f"{base}...{replay}"]).encode()
    if len(diff_bytes) > REMEDIATION_DIFF_INLINE_CAP:
        row["skip_reason"] = "diff-over-cap"
        return row
    merge_oid = (pr.get("mergeCommit") or {}).get("oid")
    fix_shas = list(fixes(merge_oid)) if merge_oid else []
    final_base = git(["merge-base", head, "origin/master"]).strip()
    expected, basis = classify(labels=labels, replay_patch_id=_patch_id(git, base, replay),
                               final_patch_id=_patch_id(git, final_base, head),
                               changes_requested=_changes_requested(gh_json, number),
                               fix_shas=fix_shas)
    reason, source, note = _reason(pr, plans_dir)
    row.update({"expected": expected, "label_basis": basis, "seat_eligible": True,
                "reason": reason, "reason_source": source, "fix_shas": fix_shas,
                "evidence": _evidence(basis, labels, fix_shas)})
    if note:
        row["reason_note"] = note
    return row


def _evidence(basis: str, labels: set, fix_shas: list[str]) -> str:
    if basis == "reverted":
        return f"later fix {fix_shas[0]} within {FIX_WINDOW_DAYS} days of merge"
    if basis in ("approved-as-is", "changed-after-hold", "needs-a-human"):
        return f"label {sorted(labels & {LABEL_APPROVED, LABEL_NEEDS_HUMAN})}; basis {basis}"
    return "seat cleared every hold and the PR auto-merged"


def _prior_verdict(row: dict) -> None:
    path = second_review.verdict_path(row["pr"])
    if not os.path.exists(path):
        return
    word, _ = second_review.parse_verdict(path)
    row["prior_verdict"] = word
    row["prior_tree"] = fidelity.read_reviewed_tree(path)


def mine(root: Path, *, gh_json, git, plans_dir, since: str, out_path, now: float,
         fixes=None, ensure=None, seat_since: Optional[str] = None) -> tuple[int, int]:
    """Write one candidate row per kept merged PR. Returns (rows, seat-eligible rows)."""
    root = Path(root)
    if fixes is None:
        def fixes(commit):
            return blamed_by_fix_within(root, commit, FIX_WINDOW_DAYS, "origin/master")
    prs = gh_json(["pr", "list", "-R", GH_REPO, "--state", "merged", "--search",
                   f"merged:>={since}", "--limit", "1000", "--json", _PR_JSON]) or []
    seat_floor = seat_since or since
    kept = []
    for pr in sorted(prs, key=lambda p: p["number"]):
        labels = {lab.get("name") for lab in pr.get("labels") or []}
        if labels & {LABEL_APPROVED, LABEL_NEEDS_HUMAN}:
            kept.append(pr)
        elif (pr.get("mergedAt") or "")[:10] >= seat_floor:
            final, _ = _sticky_versions(gh_json, pr["number"])
            if parse_seat_cleared(final or ""):
                kept.append(pr)
    if ensure is not None:
        ensure([SimpleNamespace(number=p["number"], head_oid=p.get("headRefOid")) for p in kept])
    rows = []
    for pr in kept:
        row = _mine_row(root, pr, gh_json=gh_json, git=git, plans_dir=Path(plans_dir),
                        fixes=fixes)
        _prior_verdict(row)
        row["id"] = suggested_id(row["pr"], row["expected"], {int(k): v for k, v in
                                                               row["holds"].items()})
        row["mined_at"] = int(now)
        rows.append(row)
    out = Path(out_path)
    out.parent.mkdir(parents=True, exist_ok=True)
    tmp = out.with_name(out.name + ".tmp")
    with open(tmp, "w", encoding="utf-8") as fh:
        for row in rows:
            fh.write(json.dumps(row, sort_keys=True) + "\n")
    os.replace(tmp, out)
    return len(rows), sum(1 for r in rows if r["seat_eligible"])


def _seat_since(root: Path) -> Optional[str]:
    """The date second_review.py landed: no sticky before it can name a seat clear."""
    r = _gitt(root, "log", "--diff-filter=A", "--format=%cs", "--",
              "tools/postplan-harness/harness/second_review.py")
    dates = [d for d in r.stdout.split() if d] if r.returncode == 0 else []
    return dates[-1] if dates else None


def _cmd_mine(args: argparse.Namespace) -> int:
    root = _repo_root(args.repo)
    out = Path(args.out) if args.out else root / CANDIDATES_REL
    from .gate_backtest_replay import ensure_pr_heads
    try:
        n, eligible = mine(root, gh_json=live_gh_json, git=make_git(str(root)),
                           plans_dir=Path(args.plans_dir).expanduser(), since=args.since,
                           out_path=out, now=time.time(),
                           ensure=lambda prs: ensure_pr_heads(str(root), prs),
                           seat_since=_seat_since(root))
    except subprocess.CalledProcessError as e:
        print(f"command failed: {e.cmd}: {(e.stderr or '').strip()}", file=sys.stderr)
        return 1
    print(f"mined {n} candidates ({eligible} seat-eligible) -> {out}")
    return 0


# ---------------------------------------------------------------------------
# Phase 3: run mode. Replays the unchanged seat per confirmed case.
# ---------------------------------------------------------------------------

DEFAULT_CACHE_REL = "tools/postplan-harness/out/second-review-eval/cache"


def eval_key(case: dict) -> str:
    """The verdict-file key: never a bare PR number, so a live PR's verdict is never touched."""
    return f"eval-{case['id']}"


def _remove_worktree(root: Path, wt: str) -> None:
    _gitt(root, "worktree", "remove", "--force", wt)
    _gitt(root, "worktree", "prune")


def _has_commit(root: Path, sha: str) -> bool:
    return _gitt(root, "cat-file", "-e", f"{sha}^{{commit}}").returncode == 0


def run_case(root: Path, case: dict, *, spawn_factory) -> tuple[Optional[str], dict[int, str]]:
    """(CLEAN|REJECT, clears), or (None, {}) when the replay is degraded."""
    root = Path(root)
    key = eval_key(case)
    if str(key).lstrip("-").isdigit():
        raise EvalUsageError(f"verdict key {key!r} is a bare PR number")
    replay = case["replay_sha"]
    if not _has_commit(root, replay):
        from .gate_backtest_replay import ensure_pr_heads
        ensure_pr_heads(str(root), [SimpleNamespace(number=case["pr"], head_oid=replay)])
        if not _has_commit(root, replay):
            return None, {}
    tmp = tempfile.mkdtemp(prefix="second-review-eval-")
    wt = str(Path(tmp) / "wt")
    try:
        if _gitt(root, "worktree", "add", "-q", "--detach", wt, replay).returncode != 0:
            return None, {}
        replay_tree = _gitt(root, "rev-parse", f"{replay}^{{tree}}").stdout.strip()
        word, tree, clears = second_review.run_seat(
            key,
            head_tree=lambda: replay_tree,
            diff=lambda: _gitt(root, "diff", f"{case['base_sha']}...{replay}").stdout,
            holds={int(k): v for k, v in case["holds"].items()},
            reason=case["reason"],
            spawn=spawn_factory(wt))
        if word is None or tree != replay_tree:
            return None, {}
        return word, clears
    finally:
        try:
            os.unlink(second_review.verdict_path(eval_key(case)))
        except OSError:
            pass
        _remove_worktree(root, wt)
        shutil.rmtree(tmp, ignore_errors=True)


def _cache_path(cache_dir: Path, case: dict, v: str) -> Path:
    key = hashlib.sha256((json.dumps(case, sort_keys=True) + v).encode()).hexdigest()[:16]
    return Path(cache_dir) / f"{case['id']}-{key}.json"


def run_eval(root: Path, corpus: dict, *, spawn_factory, cache_dir, case_ids=None,
             log: Optional[Callable[[str], None]] = None) -> SecondScore:
    """Replay every confirmed case (or the `case_ids` subset) and score the outcomes."""
    v = surface_hash_worktree(Path(root))
    c = corpus_digest(corpus)
    cases = sorted((x for x in corpus["cases"] if x.get("status") == "confirmed"
                    and (case_ids is None or x["id"] in case_ids)), key=lambda x: x["id"])
    outcomes = []
    for case in cases:
        cache_file = _cache_path(Path(cache_dir), case, v)
        if cache_file.exists():
            data = json.loads(cache_file.read_text(encoding="utf-8"))
            word, clears = data["word"], {int(k): t for k, t in data["clears"].items()}
        else:
            word, clears = run_case(root, case, spawn_factory=spawn_factory)
            if word is not None:
                cache_file.parent.mkdir(parents=True, exist_ok=True)
                cache_file.write_text(json.dumps({"word": word, "clears": clears},
                                                 sort_keys=True), encoding="utf-8")
        outcomes.append((case, word, clears))
        if log is not None:
            cleared = word is not None and seat_cleared(word, clears, case["holds"])
            log(f"{case['id']} expected={case['expected']} seat={word or 'degraded'} "
                f"cleared={'yes' if cleared else 'no'}")
    return compute_score(outcomes, v=v, c=c)


def _load_corpus(root: Path, arg: Optional[str]) -> dict:
    path = Path(arg) if arg else root / DEFAULT_CORPUS
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as e:
        raise EvalUsageError(f"cannot read corpus {path}: {e}") from e


def _live_spawn_factory():
    from .adapters.llm import ClaudeCli
    from .state import UsageLedger
    llm = ClaudeCli(UsageLedger())
    return lambda wt: second_review.spawner(llm, wt)


def _cmd_run(args: argparse.Namespace, spawn_factory=None) -> int:
    root = _repo_root(args.repo)
    corpus = _load_corpus(root, args.corpus)
    errors = validate_schema(corpus)
    if errors:
        raise EvalUsageError("; ".join(errors))
    case_ids = set(args.case) if args.case else None
    if not any(x["status"] == "confirmed" and (case_ids is None or x["id"] in case_ids)
               for x in corpus["cases"]):
        print("no confirmed cases", file=sys.stderr)
        return 2
    cache_dir = Path(args.cache_dir) if args.cache_dir else root / DEFAULT_CACHE_REL
    score = run_eval(root, corpus, spawn_factory=spawn_factory or _live_spawn_factory(),
                     cache_dir=cache_dir, case_ids=case_ids,
                     log=lambda s: print(s, file=sys.stderr))
    line = format_score_line(score)
    if getattr(args, "score_out", None):
        Path(args.score_out).parent.mkdir(parents=True, exist_ok=True)
        Path(args.score_out).write_text(line + "\n", encoding="utf-8")
    print(line)
    return 3 if score.degraded else 0


def _read_score(path: str) -> SecondScore:
    try:
        text = Path(path).read_text(encoding="utf-8")
    except OSError as e:
        raise EvalUsageError(f"cannot read score file {path}: {e}") from e
    return parse_score_line(_last_line(text))


def _cmd_compare(args: argparse.Namespace) -> int:
    before = _read_score(args.before)
    after = _read_score(args.after)
    before_line, after_line = format_score_line(before), format_score_line(after)
    if (before.c, before.should_hold, before.should_clear) != (after.c, after.should_hold, after.should_clear):
        print(f"denominators differ: {before_line} vs {after_line}", file=sys.stderr)
        return 2
    print(f"before: {before_line}")
    print(f"after:  {after_line}")
    if is_worse(before, after):
        print(f"worse: missed_holds {before.missed}->{after.missed} "
              f"extra_holds {before.extra}->{after.extra}")
        return 1
    return 0


class _FakeLlm:
    """Records seat calls and replies with a canned clearing verdict; never imports the client."""

    REPLY = "CLEARS 7: the hold is safe to clear for this diff\nVERDICT: CLEAN"

    def __init__(self) -> None:
        self.calls: list[tuple] = []

    def call_tooled(self, label, model, prompt, **kw):
        self.calls.append((label, model, prompt, kw))
        return self.REPLY


def _cmd_smoke(args: argparse.Namespace) -> int:
    lines = _FakeLlm.REPLY.splitlines()
    if not (second_review._CLEARS_RE.match(lines[0]) and second_review._VERDICT_RE.match(lines[-1])):
        print("smoke reply does not match the seat's verdict shapes", file=sys.stderr)
        return 1
    tmp = tempfile.mkdtemp(prefix="second-review-smoke-")
    try:
        repo = Path(tmp) / "repo"
        repo.mkdir()

        def run_git(*argv: str) -> str:
            r = subprocess.run(["git", "-C", str(repo), *argv], capture_output=True, text=True)
            if r.returncode != 0:
                raise EvalUsageError(f"smoke git {argv[0]} failed: {r.stderr.strip()}")
            return r.stdout.strip()

        run_git("init", "-q")
        run_git("config", "user.email", "smoke@example.invalid")
        run_git("config", "user.name", "smoke")
        run_git("config", "commit.gpgsign", "false")
        (repo / "a.txt").write_text("one\n", encoding="utf-8")
        run_git("add", "a.txt")
        run_git("commit", "-qm", "base")
        base = run_git("rev-parse", "HEAD")
        (repo / "a.txt").write_text("one\ntwo\n", encoding="utf-8")
        run_git("commit", "-qam", "change")
        head = run_git("rev-parse", "HEAD")
        corpus = {"version": 1, "cases": [{
            "id": "smoke-clear7", "pr": 999999, "status": "confirmed", "expected": "clear",
            "label_basis": "seat-cleared-clean", "base_sha": base, "replay_sha": head,
            "holds": {"7": "smoke hold"}, "reason": "smoke reason",
            "reason_source": "plan", "evidence": "smoke"}]}
        errors = validate_schema(corpus)
        if errors:
            print("smoke corpus invalid: " + "; ".join(errors), file=sys.stderr)
            return 1
        fake = _FakeLlm()
        score = run_eval(repo, corpus, spawn_factory=lambda wt: second_review.spawner(fake, wt),
                         cache_dir=Path(tmp) / "cache")
        line = format_score_line(score)
        print(line)
        problems = []
        if not line.endswith("missed_holds=0/0 extra_holds=0/1 degraded=0"):
            problems.append("unexpected score line")
        if len(fake.calls) != 1:
            problems.append(f"fake saw {len(fake.calls)} calls, expected 1")
        else:
            prompt = fake.calls[0][2]
            if "## Holds" not in prompt or "- (7)" not in prompt:
                problems.append("prompt lacks '## Holds' or '- (7)'")
        if problems:
            print("smoke mismatch: " + "; ".join(problems), file=sys.stderr)
            return 1
        return 0
    finally:
        shutil.rmtree(tmp, ignore_errors=True)


def main(argv: Optional[list[str]] = None) -> int:
    p = argparse.ArgumentParser(prog="bin/reviewer-eval second")
    sub = p.add_subparsers(dest="cmd", required=True)

    sp = sub.add_parser("mine", help="propose cases from merged-PR history")
    sp.add_argument("--repo", default=None)
    sp.add_argument("--since", default=DEFAULT_SINCE)
    sp.add_argument("--plans-dir", default="~/claude-plans")
    sp.add_argument("--out", default=None)
    sp.set_defaults(func=_cmd_mine)

    sp = sub.add_parser("validate", help="check the corpus schema")
    sp.add_argument("--repo", default=None)
    sp.add_argument("--corpus", default=None)
    sp.set_defaults(func=_cmd_validate)

    sp = sub.add_parser("run", help="replay the seat over confirmed cases")
    sp.add_argument("--repo", default=None)
    sp.add_argument("--corpus", default=None)
    sp.add_argument("--case", action="append", default=None)
    sp.add_argument("--cache-dir", default=None)
    sp.add_argument("--score-out", default=None)
    sp.set_defaults(func=_cmd_run)

    sp = sub.add_parser("compare", help="compare two score files")
    sp.add_argument("--before", required=True)
    sp.add_argument("--after", required=True)
    sp.set_defaults(func=_cmd_compare)

    sp = sub.add_parser("smoke", help="run the seat path with a fake model")
    sp.set_defaults(func=_cmd_smoke)

    args = p.parse_args(argv)
    try:
        return args.func(args)
    except EvalUsageError as e:
        print(f"error: {e}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
