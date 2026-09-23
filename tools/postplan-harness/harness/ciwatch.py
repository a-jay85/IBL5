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

from harness import gitutil

FAIL_STATE = re.compile(r'"state"\s*:\s*"FAILURE"')
FAIL_TEXT = re.compile(r"\bfail(ing|ed)?\b", re.I)
PASS_TEXT = re.compile(r"\ball checks (have )?pass|successful\b", re.I)

# Checks that are red by design and say nothing about the code. `human-signoff`
# (ADR-0062) fails every feat: PR until a human applies the `human-approved`
# label; armable.py already reports that as a hold. Counting it here made every
# feat: run report "CI failed" before any real check had settled. GitHub's
# required-check rule still blocks the merge; this only stops the CI verdict
# from claiming a code failure.
IGNORED_CHECKS = frozenset({"human-signoff"})

HEAD_CHECK_INTERVAL = 300  # seconds between head checks during the background watch


class _HeadChanged(Exception):
    """Raised by _communicate_watching_head when the remote head has moved mid-watch."""
    def __init__(self, remote_sha: str):
        self.remote_sha = remote_sha
        super().__init__(remote_sha)


def _communicate_watching_head(bg: "BackgroundWatch", proc, budget: float):
    """communicate() wrapper that polls the remote head on a short interval."""
    end = time.time() + budget
    while True:
        left = max(end - time.time(), 0.1)
        chunk = min(HEAD_CHECK_INTERVAL, left) if bg.verify_head else left
        try:
            return proc.communicate(timeout=chunk)
        except subprocess.TimeoutExpired:
            if time.time() >= end or not bg.verify_head:
                raise
            ok, remote = gitutil.remote_head_matches(bg.pr, bg.sha, gh_cmd=bg.gh_cmd)
            if not ok:
                raise _HeadChanged(remote)


def _head_moved(bg: "BackgroundWatch") -> str:
    """Return the new remote sha if verify_head and remote head changed, else ""."""
    if not bg.verify_head:
        return ""
    ok, remote = gitutil.remote_head_matches(bg.pr, bg.sha, gh_cmd=bg.gh_cmd)
    if not ok:
        return remote
    return ""


def real_failures(names: list[str]) -> list[str]:
    """Failed check names minus the by-design ones in IGNORED_CHECKS."""
    return sorted({n for n in names if n not in IGNORED_CHECKS})


def _parse_fail_lines(out: str) -> list[str]:
    """Names in the `fail` column of `gh pr checks` tab-separated output."""
    failed = []
    for line in (out or "").splitlines():
        cols = line.split("\t")
        if len(cols) >= 2 and cols[1].strip() == "fail":
            failed.append(cols[0].strip())
    return failed


def _json_failures(s: str) -> list[str] | None:
    """FAILURE-state names in a recorded `--json` snapshot; None if unparseable."""
    try:
        rows = json.loads(s[s.index("["):s.rindex("]") + 1])
    except (ValueError, json.JSONDecodeError):
        return None
    if not isinstance(rows, list):
        return None
    return [r.get("name", "?") for r in rows
            if isinstance(r, dict) and r.get("state") == "FAILURE"]


@dataclass
class CiOutcome:
    exit_code: int                    # 0 green | 8 failures | -1 indeterminate
    failed: list[str] = field(default_factory=list)
    evidence: str = ""
    head_sha: str = ""
    diverged: bool = False


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
    verify_head: bool = False
    gh_cmd: "list[str] | None" = None
    remote_sha: str = ""


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
    for s in [*snaps, tail]:
        if not isinstance(s, str) or not FAIL_STATE.search(s):
            continue
        names = _json_failures(s)
        if names is None:             # unparseable FAILURE text: count it, fail-closed
            saw_failure = True
        elif real_failures(names):
            saw_failure = True
            failed.extend(real_failures(names))
    if saw_failure:
        return CiOutcome(8, sorted(set(failed)), "recorded FAILURE check states")
    if snaps or tail:
        return CiOutcome(0, [], "recorded checks with no code-failing FAILURE states")
    return CiOutcome(-1, [], "no CI watch output recorded in trace")


def probe_failed_checks(worktree: str, pr: int, timeout: int = 60) -> list[str]:
    """Names of checks on the PR's current head already in gh's `fail` bucket.

    `bucket` is a documented `gh pr checks --json` field with values
    pass|fail|pending|skipping|cancel. Strictly additive and best-effort: any
    failure to run, exit, or parse returns [] so the caller falls through to the
    blocking watch. An empty list means "no failure proven", never "green" — this
    function can only ever ADD a failure verdict, never manufacture a pass.
    IGNORED_CHECKS names are dropped, so a PR whose only red check is
    `human-signoff` falls through to the watch instead of short-circuiting.
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
    return real_failures([str(r.get("name") or "?") for r in rows
                          if isinstance(r, dict) and r.get("bucket") == "fail"])


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
            # No --fail-fast: human-signoff goes red within seconds on every
            # feat: PR, so fail-fast would stop there and never see real checks.
            proc = subprocess.Popen(
                ["gh", "pr", "checks", str(bg.pr), "--watch"],
                cwd=bg.worktree, stdout=subprocess.PIPE,
                stderr=subprocess.PIPE, text=True)
        except OSError as e:
            _write_outcome(bg, "indeterminate", [], f"gh unavailable: {e}")
            return
        with bg.lock:
            bg.proc = proc
        try:
            out, err = _communicate_watching_head(bg, proc, max(remaining, 60))
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.communicate()
            moved = _head_moved(bg)
            if moved:
                bg.remote_sha = moved
                _write_outcome(bg, "head-changed", [],
                               f"timeout after remote head moved {bg.sha[:8]} -> {moved[:8]}")
            else:
                _write_outcome(bg, "timeout", [],
                               f"background gh pr checks --watch exceeded {timeout}s")
            return
        except _HeadChanged as hc:
            proc.kill()
            proc.communicate()
            bg.remote_sha = hc.remote_sha
            _write_outcome(bg, "head-changed", [],
                           f"remote head moved {bg.sha[:8]} -> {hc.remote_sha[:8]} mid-watch")
            return
        if bg.stop.is_set():
            break
        if proc.returncode == 0:
            _write_outcome(bg, "success", [], "gh pr checks --watch exit 0")
            return
        if proc.returncode == 8:
            parsed = _parse_fail_lines(out)
            # The probe runs on EVERY exit 8, including the ignored-only case: a
            # text parse must never be the sole basis for a green verdict, because
            # SKILL.md Phase 7.0 skips the real watch entirely on "success".
            probe = probe_failed_checks(bg.worktree, bg.pr)
            failed = real_failures(parsed) + probe
            if parsed and not failed:
                _write_outcome(bg, "success", [],
                               "gh pr checks --watch exit 8, only ignored checks "
                               "failed: " + ", ".join(sorted(set(parsed))),
                               probe=probe)
                return
            _write_outcome(bg, "failure", failed,
                           "gh pr checks --watch exit 8", probe=probe)
            return
        last_rc, last_stderr = str(proc.returncode), (err or "").strip()[:200]
        if time.time() >= deadline or bg.stop.wait(settle_wait):
            break
    moved = _head_moved(bg)
    if moved:
        bg.remote_sha = moved
        _write_outcome(bg, "head-changed", [],
                       f"timeout after remote head moved {bg.sha[:8]} -> {moved[:8]}")
    else:
        _write_outcome(bg, "timeout", [],
                       f"checks never settled; last gh exit {last_rc}: {last_stderr}")


def start_background_watch(worktree: str, pr: int | None, sha: str | None,
                           out_dir: str, timeout: int = 5400,
                           settle_tries: int = 10,
                           settle_wait: int = 30, *,
                           verify_head: bool = False,
                           gh_cmd=None, run_git=None) -> BackgroundWatch | None:
    """Fire-and-forget CI watch for the just-pushed head. Never raises."""
    if not pr or not sha or not out_dir:
        return None                      # clean tree / no PR: nothing to key on
    remote_sha = ""
    if verify_head:
        r = gitutil.reconcile_remote_head(pr, sha, sha, None, worktree,
                                          gh_cmd=gh_cmd, run_git=run_git)
        if r.action == "diverged":
            bg = BackgroundWatch(sha=sha, pr=int(pr), worktree=worktree,
                                 path=outcome_path(out_dir, sha), started=time.time(),
                                 remote_sha=r.remote_sha)
            _write_outcome(bg, "diverged", [], r.evidence)
            return bg
        if r.action == "synced":
            sha, remote_sha = r.remote_sha, r.remote_sha
    bg = BackgroundWatch(sha=sha, pr=int(pr), worktree=worktree,
                         path=outcome_path(out_dir, sha), started=time.time(),
                         verify_head=verify_head, gh_cmd=gh_cmd, remote_sha=remote_sha)
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
                   settle_tries: int = 10, settle_wait: int = 30, *,
                   verify_head: bool = False,
                   gh_cmd=None, run_git=None) -> CiOutcome:
    """Phase 7 entry point: reuse the Phase-2 background watch when it applies.

    Ceiling contract: this function's total wall clock is bounded by `timeout`,
    the same ceiling `watch_live` had before. Time spent waiting on the
    background watch is DEDUCTED from the budget handed to the fallback.
    """
    started = time.time()

    if verify_head and sha:
        r = gitutil.reconcile_remote_head(pr, sha, sha, None, worktree,
                                          gh_cmd=gh_cmd, run_git=run_git)
        if r.action == "diverged":
            return CiOutcome(-1, [], r.evidence, head_sha=r.remote_sha, diverged=True)
        if r.action == "synced":
            sha = r.remote_sha      # stale bg no longer matches; falls through to restart

    if sha and out_dir and bg is not None and bg.sha == sha and not bg.done.is_set():
        bg.done.wait(timeout=max(timeout - (time.time() - started), 0))

    got = read_outcome(out_dir or "", sha or "")
    if got is not None:
        return CiOutcome(got.exit_code, got.failed, got.evidence, head_sha=sha or "")

    # One restart if the background watch timed out or detected a head change
    if (verify_head and sha and out_dir and bg is not None
            and bg.sha == sha and bg.status in ("head-changed", "timeout")):
        r2 = gitutil.reconcile_remote_head(pr, sha, sha, None, worktree,
                                           gh_cmd=gh_cmd, run_git=run_git)
        if r2.action == "diverged":
            return CiOutcome(-1, [], r2.evidence, head_sha=r2.remote_sha, diverged=True)
        new_sha = r2.remote_sha if r2.remote_sha else sha
        remaining2 = int(timeout - (time.time() - started))
        bg2 = start_background_watch(worktree, pr, new_sha, out_dir,
                                     timeout=max(remaining2, 60),
                                     verify_head=True, gh_cmd=gh_cmd, run_git=run_git)
        if bg2 is not None:
            bg2.done.wait(timeout=max(remaining2, 0))
            reap_background_watch(bg2)
            got2 = read_outcome(out_dir, new_sha)
            if got2 is not None:
                return CiOutcome(got2.exit_code, got2.failed, got2.evidence,
                                 head_sha=new_sha)

    remaining = int(timeout - (time.time() - started))
    outcome = watch_live(worktree, pr, timeout=max(remaining, 60),
                         settle_tries=settle_tries, settle_wait=settle_wait)
    return CiOutcome(outcome.exit_code, outcome.failed, outcome.evidence,
                     head_sha=sha or "")


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
            parsed = _parse_fail_lines(proc.stdout)
            failed = real_failures(parsed)
            if parsed and not failed:
                # Corroborate with a fresh --json probe: the pre-watch probe ran
                # before these checks settled, so it cannot stand in for one here.
                probe = probe_failed_checks(worktree, pr)
                if not probe:
                    return CiOutcome(0, [], "gh pr checks --watch exit 8, only ignored "
                                            "checks failed: "
                                            + ", ".join(sorted(set(parsed))))
                failed = probe
            return CiOutcome(8, failed, "gh pr checks --watch exit 8")
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
