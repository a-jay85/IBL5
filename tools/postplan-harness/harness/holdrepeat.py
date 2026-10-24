"""Hold-repeat detection: stop re-running a post-plan that holds on the same structural reason.

Pure logic shared by the runner (record), the notifier (DM once), and the shell wrapper
(decline before spending a run). The record is advisory: every write failure is swallowed
and a fingerprint failure fails open (an empty fingerprint never matches).

Only STRUCTURAL conditions key a repeat. Environment-dependent conditions (review state,
CI, LLM verdicts) can clear on a re-run, so they never count toward a repeat.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
from dataclasses import dataclass
from pathlib import Path

from harness.statefile import safe_slug

SCHEMA_VERSION = 1
DECLINE_EXIT = 10

# 3  unresolved-MISSING-items  (conformance of plan vs diff)
# 7  plan-auto-merge-hold      (plan frontmatter)
# 8  feat-commit-type-floor    (PR title)
# 13 plan-slug-drift           (plan path vs branch)
# Excluded: 1 manual-testing state, 2/11 review state, 4 phase-5 verify,
# 5 golden snapshot, 6 dependency merge order, 9 pr-time safety verdict
# (may carry LLM-verdict text), 10 pipeline-authored floor (bug-pipeline
# PRs are held by design and fired once), 12 fidelity verdict (LLM),
# 15 red CI, 16 meta-checks. All of these can clear on a re-run.
STRUCTURAL_CONDITIONS = frozenset({3, 7, 8, 13})
# Conditions only a human can clear (3 = plan files missing, 7 = plan
# auto_merge: false, 8 = feat signoff floor). A DIRTY PR whose last hold is
# a non-empty subset of these only needs a rebase, never a fresh review.
REBASE_ONLY_CONDITIONS = frozenset({3, 7, 8})
ELIGIBLE_EXIT = 10
FIX_ELIGIBLE_EXIT = 10
FIX_GIVEUP_EXIT = 11
FIX_ATTEMPTS_PER_PR = 2   # hard ceiling across ALL fingerprints for one branch
FIX_SCHEMA_VERSION = 1
FIX_RECHECK_FAIL_EXIT = 12

_RUN_DIR = re.compile(r"live-\S*?-\d{8}-\d{6}-\d+")
_WT_PATH = re.compile(r"/(?:[^\s/]+/)*IBL5-worktrees/[^\s/]+/")
_ROOT_PATH = re.compile(r"/(?:[^\s/]+/)*GitHub/IBL5/")
_TMP_PATH = re.compile(r"/(?:private/)?tmp/\S+")
_ISO_TS = re.compile(
    r"\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2})?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?"
)
_CLOCK = re.compile(r"\b\d{2}:\d{2}:\d{2}\b")
_COMPACT_TS = re.compile(r"\b\d{8}-\d{6}\b")
_SHA = re.compile(r"\b(?=[0-9a-f]*\d)[0-9a-f]{7,40}\b")
_PR_NUM = re.compile(r"\bPR #?\d+\b")
_WS = re.compile(r"\s+")


def normalize_reason(text: str) -> str:
    """Strip run-to-run volatile tokens so the same hold reason compares equal."""
    s = text or ""
    s = _RUN_DIR.sub("<run-dir>", s)
    s = _WT_PATH.sub("<wt>/", s)
    s = _ROOT_PATH.sub("<root>/", s)
    s = _TMP_PATH.sub("<tmp>", s)
    s = _ISO_TS.sub("<ts>", s)
    s = _CLOCK.sub("<ts>", s)
    s = _COMPACT_TS.sub("<ts>", s)
    s = _SHA.sub("<sha>", s)
    s = _PR_NUM.sub("PR <n>", s)
    parts = [_WS.sub(" ", p.strip()) for p in s.split(";")]
    return "; ".join(sorted(p for p in parts if p))


def _field(cond, name):
    """Read a condition field from a ConditionResult-like object or a result.json dict."""
    try:
        return getattr(cond, name)
    except AttributeError:
        pass
    if isinstance(cond, dict):
        return cond.get(name)
    return None


def _blocked(conditions):
    out = []
    for c in conditions or []:
        if _field(c, "blocked"):
            out.append(c)
    out.sort(key=lambda c: _field(c, "number"))
    return out


def _triple(c) -> list:
    return [_field(c, "number"), _field(c, "name"), normalize_reason(_field(c, "reason") or "")]


def hold_set(conditions) -> list:
    """Every blocked condition as [number, name, normalized reason], sorted by number."""
    return [_triple(c) for c in _blocked(conditions)]


def _structural(conditions) -> list:
    return [t for t in hold_set(conditions) if t[0] in STRUCTURAL_CONDITIONS]


def structural_key(conditions) -> str:
    """Stable key over blocked structural conditions; "" when none is blocked."""
    return "|".join(f"{n}:{reason}" for n, _name, reason in _structural(conditions))


def record_path(state_dir: str, slug: str) -> str:
    return os.path.join(state_dir, f"{safe_slug(slug)}.holdrepeat.json")


def load_record(state_dir, slug):
    try:
        with open(record_path(state_dir, slug)) as fh:
            doc = json.load(fh)
    except (OSError, ValueError):
        return None
    if not isinstance(doc, dict) or doc.get("schema_version") != SCHEMA_VERSION:
        return None
    return doc


def clear_record(state_dir, slug) -> None:
    try:
        os.remove(record_path(state_dir, slug))
    except OSError:
        pass


def _save_record(state_dir, slug, doc) -> None:
    path = record_path(state_dir, slug)
    tmp = f"{path}.{os.getpid()}.tmp"
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(tmp, "w") as fh:
            fh.write(json.dumps(doc, indent=1, default=str))
        os.replace(tmp, path)
    except OSError as e:
        print(f"holdrepeat: record write failed ({e!r}); continuing", file=sys.stderr)
        try:
            os.remove(tmp)
        except OSError:
            pass


@dataclass
class Observation:
    action: str  # "recorded" | "repeat-dm" | "repeat-silent" | "cleared"
    key: str
    repeat_count: int
    reasons: list  # structural [[n, name, reason]] for the DM / RESULT text


def observe(state_dir, slug, *, armed: bool, conditions, fingerprint: str,
            pr, now: str) -> Observation:
    """Advance the repeat state machine. Never sends anything."""
    key = structural_key(conditions)
    structural = _structural(conditions)
    if armed or key == "":
        clear_record(state_dir, slug)
        return Observation("cleared", key, 0, [])

    record = load_record(state_dir, slug)
    full_set = hold_set(conditions)
    if record is None or record.get("structural_key") != key:
        _save_record(state_dir, slug, {
            "schema_version": SCHEMA_VERSION,
            "slug": slug,
            "structural_key": key,
            "hold_set": full_set,
            "structural": structural,
            "repeat_count": 1,
            "dm_sent_key": None,
            "fingerprint": fingerprint,
            "pr": pr,
            "updated_at": now,
        })
        return Observation("recorded", key, 1, structural)

    record["repeat_count"] = int(record.get("repeat_count") or 1) + 1
    record["fingerprint"] = fingerprint
    record["hold_set"] = full_set
    record["structural"] = structural
    record["pr"] = pr
    record["updated_at"] = now
    _save_record(state_dir, slug, record)
    action = "repeat-dm" if record.get("dm_sent_key") != key else "repeat-silent"
    return Observation(action, key, record["repeat_count"], structural)


def mark_dm_sent(state_dir, slug, key: str) -> None:
    record = load_record(state_dir, slug)
    if record is None:
        return
    record["dm_sent_key"] = key
    _save_record(state_dir, slug, record)


def refresh_fingerprint(state_dir, slug, fp: str, now: str) -> bool:
    """Replace only `fingerprint` (and `updated_at`) on an existing record.

    Used after a rebase-only run whose net diff (patch-id) did not change, so the
    next cycle still sees "same hold". Never creates a record, never touches
    hold_set, structural, structural_key, repeat_count or dm_sent_key.
    """
    if not fp:
        return False
    record = load_record(state_dir, slug)
    if record is None:
        return False
    record["fingerprint"] = fp
    record["updated_at"] = now
    _save_record(state_dir, slug, record)
    return True


def _sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def _git(worktree: str, *args: str) -> bytes:
    return subprocess.run(
        ["git", "-C", worktree, *args],
        check=True, capture_output=True, timeout=120,
    ).stdout


def fingerprint(plan_path: str, worktree: str, harness_root=None, repo_root=None) -> str:
    """sha256 over plan bytes, the worktree diff vs merge-base, and the gate code.

    Returns "" on any subprocess/OS failure. An empty fingerprint never matches.
    """
    try:
        hroot = Path(harness_root) if harness_root else Path(__file__).resolve().parents[1]
        rroot = Path(repo_root) if repo_root else hroot.parents[1]

        try:
            plan_part = _sha256(Path(plan_path).read_bytes())
        except FileNotFoundError:
            plan_part = "absent"

        mb = _git(worktree, "merge-base", "origin/master", "HEAD").decode().strip()
        diff = _git(worktree, "diff", "--binary", mb)
        untracked = _git(worktree, "ls-files", "--others", "--exclude-standard", "-z")
        diff_h = hashlib.sha256()
        diff_h.update(diff)
        for rel in sorted(p for p in untracked.decode().split("\0") if p):
            data = (Path(worktree) / rel).read_bytes()
            diff_h.update(f"\n{rel}\0{_sha256(data)}".encode())

        files = []
        for p in (hroot / "harness").rglob("*.py"):
            if "__pycache__" not in p.parts:
                files.append(p)
        files.append(hroot / "runner.py")
        files.append(rroot / "bin" / "lib" / "plan-matrix-assertions")
        harness_h = hashlib.sha256()
        entries = []
        for p in files:
            base = hroot if hroot in p.parents else rroot
            try:
                entries.append((p.relative_to(base).as_posix(), p.read_bytes()))
            except FileNotFoundError:
                entries.append((p.relative_to(base).as_posix(), b"<absent>"))
        for rel, data in sorted(entries):
            harness_h.update(f"{rel}\0{_sha256(data)}\n".encode())

        final = hashlib.sha256()
        final.update(f"plan:{plan_part}\n".encode())
        final.update(f"diff:{diff_h.hexdigest()}\n".encode())
        final.update(f"harness:{harness_h.hexdigest()}\n".encode())
        return final.hexdigest()
    except (subprocess.SubprocessError, OSError, ValueError):
        return ""


_MISSING_PREFIXES = ("MISSING:", "MISSING-FILE:")


def missing_parts(reason: str) -> frozenset[str]:
    """Normalized `MISSING:` / `MISSING-FILE:` parts of a condition-3 reason."""
    norm = normalize_reason(reason or "")
    return frozenset(p for p in norm.split("; ")
                     if p.startswith(_MISSING_PREFIXES))


# Positive allowlist: the fail-closed `UNREALISED-ASSERTION: matrix-assertion check
# unavailable (...)` item does not start with `row `, so it never becomes a repeatable part.
UNREALISED_ROW_PREFIX = "UNREALISED-ASSERTION: row "


def unrealised_parts(reason: str) -> frozenset[str]:
    """Normalized `UNREALISED-ASSERTION: row ...` parts of a condition-3 reason."""
    norm = normalize_reason(reason or "")
    return frozenset(p for p in norm.split("; ")
                     if p.startswith(UNREALISED_ROW_PREFIX))


def hold_parts(reason: str) -> frozenset[str]:
    """Every repeatable condition-3 part: MISSING items plus UNREALISED rows."""
    return missing_parts(reason) | unrealised_parts(reason)


def _condition3_reason(rec) -> str:
    for entry in (rec or {}).get("structural") or []:
        if isinstance(entry, list) and len(entry) == 3 and entry[0] == 3:
            return entry[2] or ""
    return ""


def unrealised_only_parts(state_dir, slug) -> frozenset[str]:
    """UNREALISED row parts of the last condition-3 hold, only when it has no MISSING item.

    A mixed hold stays on the MISSING path and never reaches the UNREALISED path.
    """
    reason = _condition3_reason(load_record(state_dir, slug))
    if missing_parts(reason):
        return frozenset()
    return unrealised_parts(reason)


@dataclass(frozen=True)
class UnrealisedEval:
    items: tuple[str, ...]
    tokens: frozenset[str]


def evaluate_unrealised(plan_path, diff_body, root, *, script=None,
                        timeout=60) -> UnrealisedEval | None:
    """Run the matrix script and return its UNREALISED row items and missing tokens.

    None (proceed) when the check cannot be trusted: no plan, an unrunnable script,
    a fail-closed `unavailable` line, or rows with no tokens to guard master with.
    """
    if not plan_path:
        return None
    import tempfile

    from harness import conformance
    script = script or conformance._MATRIX_ASSERTIONS_SCRIPT
    try:
        with tempfile.TemporaryDirectory(prefix="early-unrealised-") as td:
            diff_path = os.path.join(td, "diff.patch")
            body_path = os.path.join(td, "pr-body.md")
            with open(diff_path, "w") as fh:
                fh.write(diff_body or "")
            with open(body_path, "w") as fh:
                fh.write("")
            proc = subprocess.run(
                conformance._matrix_assertion_argv(script, plan_path, diff_path, body_path),
                capture_output=True, text=True, check=False, timeout=timeout,
                env={**os.environ, "MATRIX_ASSERT_ROOT": root})
    except (OSError, subprocess.SubprocessError):
        return None
    if proc.returncode < 0 or proc.returncode >= 2:
        return None
    items = []
    for ln in proc.stdout.splitlines():
        ln = ln.strip()
        if not ln.startswith("UNREALISED-ASSERTION:"):
            continue
        if not ln.startswith(UNREALISED_ROW_PREFIX):
            return None
        items.append(ln)
    tokens: set[str] = set()
    for ln in proc.stderr.splitlines():
        ln = ln.lstrip()
        if ln.startswith("missing:"):
            tokens.update(ln[len("missing:"):].split())
    if items and not tokens:
        return None
    return UnrealisedEval(tuple(items), frozenset(tokens))


def _git_out(repo: str, *args: str):
    """(returncode, stdout) of a git probe, or None on any OS/timeout error."""
    try:
        proc = subprocess.run(["git", "-C", repo, *args], capture_output=True,
                              text=True, check=False, timeout=120)
    except (OSError, subprocess.SubprocessError):
        return None
    return proc.returncode, proc.stdout


def tokens_on_ref(repo, ref, tokens) -> bool | None:
    """True when any token appears in `ref`'s file paths or file contents.

    None for no tokens, an unknown ref, or a git error. Substring matching
    over-approximates the matrix script's arms, so an error only makes callers proceed.
    """
    if not tokens:
        return None
    ok = _git_out(repo, "rev-parse", "--verify", "-q", f"{ref}^{{commit}}")
    if ok is None or ok[0] != 0:
        return None
    paths = _git_out(repo, "ls-tree", "-r", "--name-only", ref)
    if paths is None or paths[0] != 0:
        return None
    if any(tok in path for tok in tokens for path in paths[1].splitlines()):
        return True
    args = ["grep", "-F", "-q"]
    for tok in sorted(tokens):
        args += ["-e", tok]
    grep = _git_out(repo, *args, ref, "--")
    if grep is None or grep[0] not in (0, 1):
        return None
    return grep[0] == 0


def worktree_clean(worktree) -> bool | None:
    out = _git_out(worktree, "status", "--porcelain", "--untracked-files=normal")
    if out is None or out[0] != 0:
        return None
    return out[1].strip() == ""


def head_sha(worktree) -> str:
    out = _git_out(worktree, "rev-parse", "HEAD")
    if out is None or out[0] != 0:
        return ""
    return out[1].strip()


def early_unrealised_repeat(state_dir, slug, missing_items, *, plan_path, diff_body,
                            worktree, master_ref="origin/master", script=None):
    """Decide whether an UNREALISED-only hold repeats. Returns (reason, note, tokens).

    `reason` is None to proceed. Every check fails open; MISSING-only records spawn nothing.
    """
    prior = unrealised_only_parts(state_dir, slug)
    if not prior:
        return None, "no unrealised hold", frozenset()
    if missing_parts("; ".join(missing_items or [])):
        return None, "missing items present", frozenset()
    if worktree_clean(worktree) is not True:
        return None, "worktree dirty or unreadable", frozenset()
    ev = evaluate_unrealised(plan_path, diff_body, worktree, script=script)
    if ev is None:
        return None, "matrix check unavailable", frozenset()
    if unrealised_parts("; ".join(ev.items)) != prior:
        return None, "unrealised set changed", frozenset()
    if tokens_on_ref(worktree, master_ref, ev.tokens) is not False:
        return None, "master carries a missing token or check unavailable", frozenset()
    return "; ".join(sorted(prior)), "repeat", ev.tokens


def early_repeat_reason(state_dir, slug, items) -> str | None:
    """Return the repeated reason when `items` equal the stored condition-3 MISSING set.

    `items` is the list from `conformance.early_missing_items`. Returns the
    sorted, `"; "`-joined normalized parts when both sides are non-empty and
    equal as sets. Returns None for no record, a record with no blocked
    condition 3, an empty side, a strict subset or a strict superset.
    """
    current = missing_parts("; ".join(items or []))
    if not current:
        return None
    rec = load_record(state_dir, slug)
    if not rec:
        return None
    prior: frozenset[str] = frozenset()
    for entry in rec.get("structural") or []:
        if isinstance(entry, list) and len(entry) == 3 and entry[0] == 3:
            prior = missing_parts(entry[2] or "")
            break
    if not prior or prior != current:
        return None
    return "; ".join(sorted(current))


def should_decline(state_dir, slug, current_fp: str):
    record = load_record(state_dir, slug)
    if record is None or not current_fp:
        return False, ""
    dm_key = record.get("dm_sent_key")
    key = record.get("structural_key")
    if not dm_key or dm_key != key:
        return False, ""
    if current_fp != record.get("fingerprint"):
        return False, ""
    return True, f"unchanged plan+diff+harness; held again on {key} (DM already sent)"


def rebase_only_eligible(state_dir, slug) -> bool:
    """True when the stored hold set is non-empty and every number is in {3,7,8}.

    Reads the record's full `hold_set` (every blocked condition, structural or
    not), so a hold that also carried 12, 14, 15 or 16 is NOT eligible. Any
    absent, unreadable or malformed record returns False (fail toward the
    normal full path).
    """
    rec = load_record(state_dir, slug)
    if not rec:
        return False
    entries = rec.get("hold_set")
    if not isinstance(entries, list) or not entries:
        return False
    nums = set()
    for e in entries:
        if not isinstance(e, list) or not e or not isinstance(e[0], int):
            return False
        nums.add(e[0])
    return nums <= REBASE_ONLY_CONDITIONS


def hold_fingerprint(reason: str) -> str:
    """16-hex hash of the sorted condition-3 parts; "" when there are none.

    Covers the parts only, never the plan hash, so a plan edit that leaves the same
    parts buys no new fix attempt.
    """
    parts = sorted(hold_parts(reason))
    if not parts:
        return ""
    return hashlib.sha256("\n".join(parts).encode()).hexdigest()[:16]


def fix_attempts_path(state_dir: str, slug: str) -> str:
    """Attempt ledger, separate from the hold record so a cleared hold keeps its ceiling."""
    return os.path.join(state_dir, f"{safe_slug(slug)}.holdrepeat-fix.json")


def fix_eligibility(state_dir, slug) -> tuple[str, str, list[str]]:
    """(verdict_word, fingerprint, sorted_parts) for the last recorded hold.

    Eligible only when the hold set is exactly condition 3 and every normalized
    condition-3 part is a known `hold_parts` item. Anything else fails toward the owner.
    """
    rec = load_record(state_dir, slug)
    if rec is None:
        return "no-record", "", []
    entries = rec.get("hold_set")
    if not isinstance(entries, list) or not entries:
        return "other-conditions", "", []
    for e in entries:
        if not isinstance(e, list) or not e or e[0] != 3:
            return "other-conditions", "", []
    reason = _condition3_reason(rec)
    parts = hold_parts(reason)
    if not parts:
        return "no-parts", "", []
    for item in normalize_reason(reason).split("; "):
        if item and item not in parts:
            return "foreign-part", "", []
    return "eligible", hold_fingerprint(reason), sorted(parts)


def _empty_fix_ledger() -> dict:
    return {"schema_version": FIX_SCHEMA_VERSION, "attempts": [], "giveup_dm": []}


def _load_fix_ledger(state_dir, slug) -> dict:
    try:
        with open(fix_attempts_path(state_dir, slug)) as fh:
            doc = json.load(fh)
    except (OSError, ValueError):
        return _empty_fix_ledger()
    if (not isinstance(doc, dict) or doc.get("schema_version") != FIX_SCHEMA_VERSION
            or not isinstance(doc.get("attempts"), list)
            or not isinstance(doc.get("giveup_dm"), list)):
        return _empty_fix_ledger()
    return doc


def _save_fix_ledger(state_dir, slug, doc) -> None:
    path = fix_attempts_path(state_dir, slug)
    tmp = f"{path}.{os.getpid()}.tmp"
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(tmp, "w") as fh:
            fh.write(json.dumps(doc, indent=1, default=str))
        os.replace(tmp, path)
    except OSError as e:
        print(f"holdrepeat: fix ledger write failed ({e!r}); continuing", file=sys.stderr)
        try:
            os.remove(tmp)
        except OSError:
            pass


def fix_attempt_verdict(state_dir, slug, fp: str) -> str:
    """"repeat" | "ceiling" | "launch". The repeat check runs first so the DM names it."""
    attempts = _load_fix_ledger(state_dir, slug)["attempts"]
    for a in attempts:
        if isinstance(a, dict) and a.get("fingerprint") == fp:
            return "repeat"
    if len(attempts) >= FIX_ATTEMPTS_PER_PR:
        return "ceiling"
    return "launch"


def record_fix_attempt(state_dir, slug, *, fp, pr, parts, now) -> None:
    """Append a `started` attempt. Called before the session so a crash still consumes it."""
    doc = _load_fix_ledger(state_dir, slug)
    doc["attempts"].append({"fingerprint": fp, "pr": pr, "started": now,
                            "parts": list(parts), "outcome": "started"})
    _save_fix_ledger(state_dir, slug, doc)


def record_fix_outcome(state_dir, slug, *, fp, outcome) -> None:
    """Set `outcome` on the last attempt with fingerprint `fp`; no match writes nothing."""
    doc = _load_fix_ledger(state_dir, slug)
    for a in reversed(doc["attempts"]):
        if isinstance(a, dict) and a.get("fingerprint") == fp:
            a["outcome"] = outcome
            _save_fix_ledger(state_dir, slug, doc)
            return


def mark_fix_giveup_dm(state_dir, slug, fp: str) -> bool:
    """True the first time for `fp` (and records it); False afterwards, writing nothing."""
    doc = _load_fix_ledger(state_dir, slug)
    if fp in doc["giveup_dm"]:
        return False
    doc["giveup_dm"].append(fp)
    _save_fix_ledger(state_dir, slug, doc)
    return True


def _recheck_items(slug, worktree, plan_path) -> list[str]:
    """Current MISSING / MISSING-FILE items, derived the way the runner derives them."""
    from harness import conformance
    from harness.adapters.gitad import LiveGit
    from harness.planfile import locate_plan
    plan = locate_plan(slug, explicit_path=plan_path or None)
    conf_files = LiveGit(worktree).conformance_files()
    return conformance.early_missing_items(plan, conf_files)


def fix_recheck(state_dir, slug, worktree, plan_path) -> list[str]:
    """Recorded MISSING parts that still survive in the worktree, sorted."""
    reason = _condition3_reason(load_record(state_dir, slug))
    recorded = missing_parts(reason)
    current = missing_parts("; ".join(_recheck_items(slug, worktree, plan_path)))
    return sorted(recorded & current)


def _build_parser() -> argparse.ArgumentParser:
    ap = argparse.ArgumentParser(prog="harness.holdrepeat")
    sub = ap.add_subparsers(dest="cmd", required=True)
    chk = sub.add_parser("check", help="decline a run that would repeat a notified hold")
    chk.add_argument("--state-dir", required=True)
    chk.add_argument("--slug", required=True)
    chk.add_argument("--plan", default="")  # explicit override path only; "" = resolve via locate_plan
    chk.add_argument("--worktree", required=True)
    elig = sub.add_parser("rebase-eligible",
                          help="exit 10 when the last hold is a subset of {3,7,8}")
    elig.add_argument("--state-dir", required=True)
    elig.add_argument("--slug", required=True)
    fe = sub.add_parser("fix-eligible",
                        help="exit 10 when the last hold is a fixable condition-3 hold")
    fe.add_argument("--state-dir", required=True)
    fe.add_argument("--slug", required=True)
    fe.add_argument("--no-mark", action="store_true",
                    help="do not consume the one give-up DM for this fingerprint")
    fr = sub.add_parser("fix-record", help="record a fix attempt or its outcome")
    fr.add_argument("--state-dir", required=True)
    fr.add_argument("--slug", required=True)
    fr.add_argument("--fingerprint", required=True)
    fr.add_argument("--pr", default="")
    fr.add_argument("--outcome", required=True)
    rc = sub.add_parser("fix-recheck",
                        help="exit 12 when a recorded MISSING part survives in the worktree")
    rc.add_argument("--state-dir", required=True)
    rc.add_argument("--slug", required=True)
    rc.add_argument("--worktree", required=True)
    rc.add_argument("--plan", default="")
    return ap


def main(argv=None) -> int:
    args = _build_parser().parse_args(argv)
    if args.cmd == "fix-eligible":
        try:
            word, fp, parts = fix_eligibility(args.state_dir, args.slug)
            if word != "eligible":
                print(f"holdrepeat: FIX ineligible reason={word}")
                return 0
            verdict = fix_attempt_verdict(args.state_dir, args.slug, fp)
            if verdict != "launch":
                dm = "no"
                if not args.no_mark and mark_fix_giveup_dm(args.state_dir, args.slug, fp):
                    dm = "yes"
                print(f"holdrepeat: FIX give-up reason={verdict} fingerprint={fp} dm={dm}")
                return FIX_GIVEUP_EXIT
            print(f"holdrepeat: FIX eligible fingerprint={fp} parts={len(parts)}")
            for p in parts:
                print(f"part: {p}")
            return FIX_ELIGIBLE_EXIT
        except Exception as e:  # noqa: BLE001 - every error path ends as "no session"
            print(f"holdrepeat: FIX ineligible reason=error ({e})")
            return 0
    if args.cmd == "fix-recheck":
        # Fails CLOSED, the opposite of `check`: an unreadable recheck must stop a push.
        try:
            left = fix_recheck(args.state_dir, args.slug, args.worktree, args.plan)
        except Exception as e:  # noqa: BLE001
            print(f"holdrepeat: fix-recheck error ({e})")
            return 1
        for p in left:
            print(f"part: {p}")
        return FIX_RECHECK_FAIL_EXIT if left else 0
    if args.cmd == "fix-record":
        if args.outcome == "started":
            from datetime import datetime, timezone
            _w, _fp, parts = fix_eligibility(args.state_dir, args.slug)
            record_fix_attempt(args.state_dir, args.slug, fp=args.fingerprint,
                               pr=args.pr, parts=parts,
                               now=datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"))
        else:
            record_fix_outcome(args.state_dir, args.slug, fp=args.fingerprint,
                               outcome=args.outcome)
        return 0
    if args.cmd == "rebase-eligible":
        try:
            ok = rebase_only_eligible(args.state_dir, args.slug)
        except Exception as e:  # noqa: BLE001 - fail open to "not eligible"
            print(f"holdrepeat: rebase-eligible failed ({e}); not eligible",
                  file=sys.stderr)
            return 0
        if ok:
            print("holdrepeat: REBASE-ONLY eligible")
            return ELIGIBLE_EXIT
        return 0
    try:
        from harness.planfile import locate_plan
        plan_info = locate_plan(args.slug, explicit_path=args.plan or None)
        fp = fingerprint(plan_info.path or "", args.worktree)
        decline, reason = should_decline(args.state_dir, args.slug, fp)
    except Exception as e:  # noqa: BLE001 - fail open by design
        print(f"holdrepeat: check failed ({e}); proceeding", file=sys.stderr)
        return 0
    if decline:
        print(f"holdrepeat: DECLINE {reason}")
        return DECLINE_EXIT
    return 0


if __name__ == "__main__":
    sys.exit(main())
