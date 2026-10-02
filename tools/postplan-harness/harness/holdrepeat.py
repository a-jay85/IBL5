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


def _build_parser() -> argparse.ArgumentParser:
    ap = argparse.ArgumentParser(prog="harness.holdrepeat")
    sub = ap.add_subparsers(dest="cmd", required=True)
    chk = sub.add_parser("check", help="decline a run that would repeat a notified hold")
    chk.add_argument("--state-dir", required=True)
    chk.add_argument("--slug", required=True)
    chk.add_argument("--plan", required=True)
    chk.add_argument("--worktree", required=True)
    return ap


def main(argv=None) -> int:
    args = _build_parser().parse_args(argv)
    try:
        fp = fingerprint(args.plan, args.worktree)
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
