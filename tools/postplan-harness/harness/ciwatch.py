"""Phase 7 — CI watch, compiled.

Live mode runs `gh pr checks <pr> --watch` (exit 0 = green, 8 = failures) — a
single blocking subprocess, no model in the loop. Replay derives the recorded
terminal outcome from the trace's captured snapshots/watch output.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
import threading
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


CI_TERMINAL = ("success", "failure")     # statuses that may be reused at Phase 7


def outcome_path(out_dir: str, sha: str) -> str:
    """Per-head-SHA outcome file. Keyed by SHA so a rebase invalidates it."""
    return os.path.join(out_dir, f"ci-{sha}.json")


@dataclass
class BackgroundWatch:
    sha: str
    pr: int
    worktree: str
    path: str
    started: float
    lock: threading.Lock = field(default_factory=threading.Lock)
    done: threading.Event = field(default_factory=threading.Event)
    stop: threading.Event = field(default_factory=threading.Event)
    thread: "threading.Thread | None" = None
    proc: "subprocess.Popen | None" = None
    written: bool = False
    status: str = ""
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


def probe_failed_checks(worktree: str, pr: int, timeout: int = 60) -> list[str]:
    """Names of checks on the PR's current head already in gh's `fail` bucket.

    `bucket` is a documented `gh pr checks --json` field with values
    pass|fail|pending|skipping|cancel. Strictly additive and best-effort: any
    failure to run, exit, or parse returns [] so the caller falls through to the
    blocking watch. An empty list means "no failure proven", never "green" — this
    function can only ever ADD a failure verdict, never manufacture a pass.
    """
    try:
        proc = subprocess.run(
            ["gh", "pr", "checks", str(pr), "--json", "name,state,bucket"],
            cwd=worktree, capture_output=True, text=True, timeout=timeout)
    except (subprocess.TimeoutExpired, OSError):
        return []
    if proc.returncode not in (0, 8):   # 1 = "no checks reported" yet
        return []
    try:
        rows = json.loads(proc.stdout or "[]")
    except (ValueError, json.JSONDecodeError):
        return []
    if not isinstance(rows, list):
        return []
    return sorted({str(r.get("name") or "?") for r in rows
                   if isinstance(r, dict) and r.get("bucket") == "fail"})


def _write_outcome(bg: BackgroundWatch, status: str, failed: list[str],
                   evidence: str, probe: list[str] | None = None) -> None:
    """Write <run_dir>/ci-<sha>.json exactly once. Never raises."""
    with bg.lock:
        if bg.written:
            return
        bg.written = True
        bg.status, bg.failed, bg.evidence = status, sorted(set(failed)), evidence
        payload = {
            "sha": bg.sha, "pr": bg.pr, "status": status,
            "exit_code": {"success": 0, "failure": 8}.get(status, -1),
            "failed_checks": bg.failed,
            "probe": sorted(set(probe or [])),
            "evidence": evidence,
            "started": bg.started, "finished": time.time(),
        }
        try:
            tmp = bg.path + ".tmp"
            with open(tmp, "w") as fh:
                json.dump(payload, fh, indent=2, sort_keys=True)
            os.replace(tmp, bg.path)
        except OSError:
            pass          # a missing run dir must never break the harness run
    bg.done.set()


def _watch_thread(bg: BackgroundWatch, timeout: int,
                  settle_tries: int, settle_wait: int) -> None:
    deadline = bg.started + timeout
    last_rc, last_stderr = "none", ""
    for _ in range(settle_tries):
        if bg.stop.is_set():
            break
        remaining = deadline - time.time()
        if remaining <= 0:
            break
        try:
            proc = subprocess.Popen(
                ["gh", "pr", "checks", str(bg.pr), "--watch", "--fail-fast"],
                cwd=bg.worktree, stdout=subprocess.PIPE,
                stderr=subprocess.PIPE, text=True)
        except OSError as e:
            _write_outcome(bg, "indeterminate", [], f"gh unavailable: {e}")
            return
        with bg.lock:
            bg.proc = proc
        try:
            out, err = proc.communicate(timeout=max(remaining, 60))
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.communicate()
            _write_outcome(bg, "timeout", [],
                           f"background gh pr checks --watch exceeded {timeout}s")
            return
        if bg.stop.is_set():
            break
        if proc.returncode == 0:
            _write_outcome(bg, "success", [],
                           "gh pr checks --watch --fail-fast exit 0")
            return
        if proc.returncode == 8:
            failed = []
            for line in (out or "").splitlines():
                cols = line.split("\t")
                if len(cols) >= 2 and cols[1].strip() == "fail":
                    failed.append(cols[0].strip())
            probe = probe_failed_checks(bg.worktree, bg.pr)
            _write_outcome(bg, "failure", failed + probe,
                           "gh pr checks --watch --fail-fast exit 8", probe=probe)
            return
        last_rc, last_stderr = str(proc.returncode), (err or "").strip()[:200]
        if time.time() >= deadline or bg.stop.wait(settle_wait):
            break
    _write_outcome(bg, "timeout", [],
                   f"checks never settled; last gh exit {last_rc}: {last_stderr}")


def start_background_watch(worktree: str, pr: int | None, sha: str | None,
                           out_dir: str, timeout: int = 5400,
                           settle_tries: int = 10,
                           settle_wait: int = 30) -> BackgroundWatch | None:
    """Fire-and-forget CI watch for the just-pushed head. Never raises."""
    if not pr or not sha or not out_dir:
        return None                      # clean tree / no PR: nothing to key on
    bg = BackgroundWatch(sha=sha, pr=int(pr), worktree=worktree,
                         path=outcome_path(out_dir, sha), started=time.time())
    bg.thread = threading.Thread(target=_watch_thread,
                                 args=(bg, timeout, settle_tries, settle_wait),
                                 daemon=True, name=f"ciwatch-{sha[:8]}")
    bg.thread.start()
    return bg


def reap_background_watch(bg: BackgroundWatch | None,
                          join_timeout: float = 10.0) -> None:
    """Terminate the child and guarantee an outcome file exists. Never raises."""
    if bg is None:
        return
    try:
        if not bg.done.is_set():
            bg.stop.set()
            with bg.lock:
                proc = bg.proc
            if proc is not None and proc.poll() is None:
                proc.terminate()
                try:
                    proc.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    proc.kill()
        if bg.thread is not None:
            bg.thread.join(timeout=join_timeout)
        _write_outcome(bg, "timeout", [],
                       "background CI watch reaped before CI settled")
    except Exception:                    # teardown must never mask a run's result
        pass


def read_outcome(out_dir: str, sha: str) -> CiOutcome | None:
    """Reusable outcome for this exact head SHA, or None.

    Returns None — never a guess — when the file is missing, unreadable, keyed
    to a different SHA, or carries a non-terminal status. `timeout` and
    `indeterminate` are NOT reusable: they mean "we never learned", and
    reporting them as a Phase 7 verdict would turn an unknown into a claim.
    """
    if not out_dir or not sha:
        return None
    try:
        with open(outcome_path(out_dir, sha)) as fh:
            data = json.load(fh)
    except (OSError, ValueError, json.JSONDecodeError):
        return None
    if not isinstance(data, dict) or data.get("sha") != sha:
        return None
    status = data.get("status")
    if status not in CI_TERMINAL:
        return None
    failed = [str(x) for x in (data.get("failed_checks") or []) if x]
    if status == "failure" and not failed:
        failed = ["(unnamed failing check)"]   # never report exit 8 with an empty list
    return CiOutcome(0 if status == "success" else 8, sorted(set(failed)),
                     f"reused background CI watch for {sha[:8]}: "
                     + (data.get("evidence") or status))


def watch_or_reuse(worktree: str, pr: int, sha: str | None, out_dir: str | None,
                   bg: "BackgroundWatch | None" = None, timeout: int = 5400,
                   settle_tries: int = 10, settle_wait: int = 30) -> CiOutcome:
    """Phase 7 entry point: reuse the Phase-2 background watch when it applies.

    Ceiling contract: this function's total wall clock is bounded by `timeout`,
    the same ceiling `watch_live` had before. Time spent waiting on the
    background watch is DEDUCTED from the budget handed to the fallback.
    """
    started = time.time()
    if sha and out_dir and bg is not None and bg.sha == sha and not bg.done.is_set():
        bg.done.wait(timeout=max(timeout - (time.time() - started), 0))
    got = read_outcome(out_dir or "", sha or "")
    if got is not None:
        return got
    remaining = int(timeout - (time.time() - started))
    return watch_live(worktree, pr, timeout=max(remaining, 60),
                      settle_tries=settle_tries, settle_wait=settle_wait)


def watch_live(worktree: str, pr: int, timeout: int = 5400,
               settle_tries: int = 10, settle_wait: int = 30) -> CiOutcome:
    """Block on `gh pr checks --watch` until CI settles.

    Immediately after pr create, checks may not be reported yet ("no checks
    reported" exit 1) — retry with a short wait before treating as
    indeterminate. Never raises: Phase 7 only decides SHIPPED reporting.
    """
    deadline = time.time() + timeout
    last_rc = "none"          # str, so the message renders cleanly when the loop never ran
    last_stderr = ""
    for _ in range(settle_tries):
        already_failed = probe_failed_checks(worktree, pr)
        if already_failed:
            return CiOutcome(8, already_failed,
                             "gh pr checks --json: fail bucket on HEAD: "
                             + ", ".join(already_failed))
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
        last_rc, last_stderr = str(proc.returncode), (proc.stderr or "").strip()[:200]
        if time.time() < deadline:
            time.sleep(settle_wait)
            continue
        return CiOutcome(-1, [], f"gh pr checks exit {proc.returncode}: "
                                 f"{(proc.stderr or '').strip()[:200]}")
    return CiOutcome(-1, [], f"checks never settled after {settle_tries} tries; "
                             f"last gh exit {last_rc}: {last_stderr}")


if __name__ == "__main__":             # one-shot live seam: see plan Phase 1.6
    import argparse
    ap = argparse.ArgumentParser(description="one-shot live CI-watch smoke")
    ap.add_argument("--smoke", action="store_true", required=True)
    ap.add_argument("--worktree", required=True)
    ap.add_argument("--pr", type=int, required=True)
    ap.add_argument("--sha", required=True)
    ap.add_argument("--out-dir", required=True)
    ap.add_argument("--timeout", type=int, default=300)
    a = ap.parse_args()
    _bg = start_background_watch(a.worktree, a.pr, a.sha, a.out_dir,
                                 timeout=a.timeout, settle_tries=3, settle_wait=5)
    if _bg is None:
        raise SystemExit("start_background_watch declined (missing pr/sha/out-dir)")
    _bg.done.wait(a.timeout + 15)
    reap_background_watch(_bg)
    with open(_bg.path) as _fh:
        print(_fh.read())
