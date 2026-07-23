"""Phase 7 — CI watch, compiled.

Live mode runs `gh pr checks <pr> --watch` (exit 0 = green, 8 = failures) — a
single blocking subprocess, no model in the loop. Replay derives the recorded
terminal outcome from the trace's captured snapshots/watch output.
"""
from __future__ import annotations

import json
import re
import subprocess
import time
from dataclasses import dataclass, field

FAIL_STATE = re.compile(r'"state"\s*:\s*"FAILURE"')
FAIL_TEXT = re.compile(r"\bfail(ing|ed)?\b", re.I)
PASS_TEXT = re.compile(r"\ball checks (have )?pass|successful\b", re.I)


@dataclass
class CiOutcome:
    exit_code: int                    # 0 green | 8 failures | -1 indeterminate
    failed: list[str] = field(default_factory=list)
    evidence: str = ""


def derive_from_trace(ci: dict | None) -> CiOutcome:
    """Best-effort read of the recorded `gh pr checks` snapshots.

    Fail-closed for arming purposes is irrelevant here (arming already happened
    in 6.5); this only decides SHIPPED terminal reporting, so indeterminate is
    reported as such rather than guessed.
    """
    ci = ci or {}
    snaps = ci.get("snapshots") or []
    tail = ci.get("watch_tail") or ""
    failed: list[str] = []
    saw_failure = False
    for s in snaps:
        if not isinstance(s, str):
            continue
        if FAIL_STATE.search(s):
            saw_failure = True
            try:
                for row in json.loads(s[s.index("["):s.rindex("]") + 1]):
                    if row.get("state") == "FAILURE":
                        failed.append(row.get("name", "?"))
            except (ValueError, json.JSONDecodeError):
                pass
    if isinstance(tail, str) and FAIL_STATE.search(tail):
        saw_failure = True
    if saw_failure:
        return CiOutcome(8, sorted(set(failed)), "recorded FAILURE check states")
    if snaps or tail:
        return CiOutcome(0, [], "recorded checks with no FAILURE states")
    return CiOutcome(-1, [], "no CI watch output recorded in trace")


def watch_live(worktree: str, pr: int, timeout: int = 5400,
               settle_tries: int = 10, settle_wait: int = 30) -> CiOutcome:
    """Block on `gh pr checks --watch` until CI settles.

    Immediately after pr create, checks may not be reported yet ("no checks
    reported" exit 1) — retry with a short wait before treating as
    indeterminate. Never raises: Phase 7 only decides SHIPPED reporting.
    """
    deadline = time.time() + timeout
    for _ in range(settle_tries):
        try:
            proc = subprocess.run(["gh", "pr", "checks", str(pr), "--watch"],
                                  cwd=worktree, capture_output=True, text=True,
                                  timeout=max(deadline - time.time(), 60))
        except subprocess.TimeoutExpired:
            return CiOutcome(-1, [], f"gh pr checks --watch exceeded {timeout}s")
        except OSError as e:
            return CiOutcome(-1, [], f"gh unavailable: {e}")
        if proc.returncode == 0:
            return CiOutcome(0, [], "gh pr checks --watch exit 0")
        if proc.returncode == 8:
            failed = []
            for line in proc.stdout.splitlines():
                cols = line.split("\t")
                if len(cols) >= 2 and cols[1].strip() == "fail":
                    failed.append(cols[0].strip())
            return CiOutcome(8, sorted(set(failed)), "gh pr checks --watch exit 8")
        # any other exit is treated as "checks not settled yet" (right after pr
        # create, gh exits 1 — sometimes with EMPTY stderr — until checks
        # register), so retry until the settle budget runs out
        if time.time() < deadline:
            time.sleep(settle_wait)
            continue
        return CiOutcome(-1, [], f"gh pr checks exit {proc.returncode}: "
                                 f"{(proc.stderr or '').strip()[:200]}")
    return CiOutcome(-1, [], f"checks never settled after {settle_tries} tries")
