"""Phase 7 ci-fix shipping helpers: the bun-audit catch-up trigger, the PR-body
proposal filter, and the fresh Meta checks wait after a body edit.

Pure helpers. Never imports runner (circular); redaction and the body signature are
passed in by the caller.
"""
from __future__ import annotations

import os
import re
import subprocess
import time
from dataclasses import dataclass

from . import cifix, classify, gitutil

BUN_AUDIT_CHECK = "JS Dependency Audit (bun)"     # job name in .github/workflows/tests.yml
META_CHECK_NAME = "Meta checks"                     # job name in .github/workflows/pr-meta-checks.yml
DEP_FILES = ("ibl5/package.json", "ibl5/bun.lock")
PROPOSAL_FILENAME = "proposed-pr-body.md"
PROPOSAL_MAX_BYTES = 60_000                         # GitHub caps a PR body at 65,536 chars
QUOTE_LIMIT = 1_500
_OVERSIZE = "\0oversize"
_WAIVER_LINE = re.compile(r"(?i)no[-_ ]?adr|post-merge-recipe-ok")
_MANUAL_SENTINEL = re.compile(r"(?i)no manual testing (is )?needed")
_MANUAL_HEADING = re.compile(r"(?m)^##\s+Manual Testing\s*$")
_GREEN_STATES = {"SUCCESS", "SKIPPED", "NEUTRAL"}
_RED_STATES = {"FAILURE", "ERROR", "TIMED_OUT", "STARTUP_FAILURE", "ACTION_REQUIRED"}


# ---------------------------------------------------------------------------
# Bun-audit trigger
# ---------------------------------------------------------------------------

def bun_audit_failed(names: list[str]) -> bool:
    return BUN_AUDIT_CHECK in names


def master_dep_files_changed(worktree: str | None, *, run_git=None) -> bool:
    """True iff origin/master changed a JS dependency file since this branch's merge
    base. Never fetches (the caller does). Any git failure reads as False, so a broken
    probe means "no catch-up", never a spurious rebase."""
    if not worktree:
        return False
    run = run_git or gitutil._default_run_git
    try:
        base = run(["merge-base", "HEAD", "origin/master"], worktree)
        if base.returncode != 0 or not (base.stdout or "").strip():
            return False
        diff = run(["diff", "--name-only", f"{base.stdout.strip()}..origin/master",
                    "--", *DEP_FILES], worktree)
    except (OSError, subprocess.TimeoutExpired):
        return False
    if diff.returncode != 0:
        return False
    return bool((diff.stdout or "").strip())


# ---------------------------------------------------------------------------
# PR-body proposal filter
# ---------------------------------------------------------------------------

@dataclass(frozen=True)
class BodyVerdict:
    action: str      # "apply" | "refuse" | "noop" | "absent"
    reason: str
    body: str = ""


def read_proposal(fix_dir: str) -> str | None:
    path = os.path.join(fix_dir, PROPOSAL_FILENAME)
    try:
        if os.path.getsize(path) > PROPOSAL_MAX_BYTES:
            return _OVERSIZE
        with open(path, encoding="utf-8", errors="replace") as fh:
            return fh.read()
    except FileNotFoundError:
        return None


def _waiver_lines(body: str) -> list[str]:
    return [ln.strip() for ln in body.splitlines() if _WAIVER_LINE.search(ln)]


def judge_proposal(current: str, proposed: str | None, *,
                   signature=str.strip) -> BodyVerdict:
    """Decide whether the harness may apply an agent-proposed PR body. A proposal that
    adds or alters any waiver or attestation is refused whole: models in the harness
    never write a gate's bypass (the adr_draft precedent)."""
    if proposed is None:
        return BodyVerdict("absent", "no proposal file")
    if proposed == _OVERSIZE or not proposed.strip():
        return BodyVerdict("refuse", "empty or oversize proposal")
    if signature(proposed) == signature(current):
        return BodyVerdict("noop", "proposal matches the live body")
    if _waiver_lines(proposed) != _waiver_lines(current):
        return BodyVerdict("refuse", "waiver change (no-adr / post-merge-recipe-ok)")
    manual = "## Manual Testing change"
    if len(_MANUAL_HEADING.findall(proposed)) != len(_MANUAL_HEADING.findall(current)):
        return BodyVerdict("refuse", manual)
    restored, _ = classify.restore_manual_testing_section(proposed, current)
    if restored != proposed:
        return BodyVerdict("refuse", manual)
    if len(_MANUAL_SENTINEL.findall(proposed)) > len(_MANUAL_SENTINEL.findall(current)):
        return BodyVerdict("refuse", manual)
    return BodyVerdict("apply", "benign body edit", body=proposed)


def quote_proposal(text: str, redact) -> str:
    """Redacted, bounded proposal in a tilde fence (a PR body may hold backtick fences)."""
    body = redact(text or "")
    if len(body) > QUOTE_LIMIT:
        body = body[:QUOTE_LIMIT] + "\n… (truncated)"
    return f"~~~\n{body.rstrip(chr(10))}\n~~~"


# ---------------------------------------------------------------------------
# Fresh Meta checks wait
# ---------------------------------------------------------------------------

def _newest_meta_run(checks: list[dict]) -> tuple[int, str] | None:
    newest = None
    for entry in checks:
        refs = cifix.failed_job_refs([entry], [META_CHECK_NAME])
        if META_CHECK_NAME not in refs:
            continue
        run_id = int(refs[META_CHECK_NAME][0])
        if newest is None or run_id > newest[0]:
            newest = (run_id, (entry.get("state") or "").upper())
    return newest


def wait_for_fresh_meta_run(checks_fn, baseline_run_id: str | None, *, deadline: float,
                            sleep=None, now=None, poll_secs: int = 20) -> str:
    """Wait for a Meta checks run newer than `baseline_run_id` to conclude. A body edit
    keeps the head SHA, so only a higher run id proves the result reflects the edit.
    CANCELLED is a superseded run (cancel-in-progress), never red. Returns "green",
    "red" or "indeterminate"."""
    sleep, now = sleep or time.sleep, now or time.time   # resolved per call, patchable
    baseline = int(baseline_run_id) if baseline_run_id else None
    while True:
        newest = _newest_meta_run(checks_fn() or [])
        if newest and (baseline is None or newest[0] > baseline):
            if newest[1] in _GREEN_STATES:
                return "green"
            if newest[1] in _RED_STATES:
                return "red"
        if now() >= deadline:
            return "indeterminate"
        sleep(poll_secs)


def meta_run_id(checks: list[dict]) -> str | None:
    """Newest Meta checks run id in `gh pr checks` JSON, or None."""
    newest = _newest_meta_run(checks or [])
    return str(newest[0]) if newest else None
