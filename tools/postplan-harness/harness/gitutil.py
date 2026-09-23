"""Remote-head reconciliation primitives for the post-plan harness.

Three pure-git/gh helpers plus one composite decision function, reconcile_remote_head,
that returns match | synced | diverged. Every guarded site calls that one function so
the decision tree exists once.
"""
from __future__ import annotations

import logging
import subprocess
import time
from dataclasses import dataclass

from harness.state import HarnessError

log = logging.getLogger("harness.gitutil")

GH_TIMEOUT = 30
GIT_TIMEOUT = 120


def _default_run_git(args: list[str], cwd: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", *args], cwd=cwd, capture_output=True,
                          text=True, timeout=GIT_TIMEOUT)


def remote_head_matches(pr_number, local_sha, *, gh_cmd=None,
                        confirm_tries=3, confirm_wait=5.0,
                        sleep=None) -> tuple[bool, str]:
    """Check whether the GitHub PR's head SHA matches local_sha.

    Returns (True, remote_sha) when they match (or on API errors — fail safe).
    Returns (False, remote_sha) when a mismatch persists across confirm_tries.
    """
    if not pr_number or not local_sha:
        return (True, "")
    argv = list(gh_cmd or ["gh"]) + [
        "pr", "view", str(pr_number), "--json", "headRefOid", "--jq", ".headRefOid"
    ]
    remote = ""
    for attempt in range(confirm_tries):
        try:
            result = subprocess.run(argv, capture_output=True, text=True,
                                    timeout=GH_TIMEOUT)
            if result.returncode != 0 or not result.stdout.strip():
                log.warning("remote_head_matches: gh pr view failed (rc=%s) — treating as match",
                            result.returncode)
                return (True, "")
            remote = result.stdout.strip()
        except (subprocess.TimeoutExpired, OSError) as e:
            log.warning("remote_head_matches: gh pr view error (%s) — treating as match", e)
            return (True, "")
        if remote == local_sha:
            return (True, remote)
        if attempt < confirm_tries - 1:
            (sleep or time.sleep)(confirm_wait)
    return (False, remote)


def content_equivalent(local_sha, remote_sha, worktree, *, run_git=None) -> bool:
    """True iff the two commits have identical tree hashes (exact tree equality)."""
    if not local_sha or not remote_sha:
        return False
    if local_sha == remote_sha:
        return True
    _run = run_git or _default_run_git
    try:
        fetch_r = _run(["fetch", "origin"], worktree)
        if fetch_r.returncode != 0:
            log.warning("content_equivalent: git fetch failed — treating as not equivalent")
            return False
        t_l = _run(["rev-parse", "--verify", f"{local_sha}^{{tree}}"], worktree)
        t_r = _run(["rev-parse", "--verify", f"{remote_sha}^{{tree}}"], worktree)
        if (t_l.returncode != 0 or not t_l.stdout.strip() or
                t_r.returncode != 0 or not t_r.stdout.strip()):
            return False
        return t_l.stdout.strip() == t_r.stdout.strip()
    except (subprocess.TimeoutExpired, OSError):
        return False


def sync_to_remote(branch, worktree, *, run_git=None) -> str:
    """Reset the worktree HEAD to origin/<branch>. Precondition: content_equivalent is True."""
    _run = run_git or _default_run_git
    try:
        _run(["fetch", "origin"], worktree)
        reset_r = _run(["reset", "--hard", f"origin/{branch}"], worktree)
        if reset_r.returncode != 0:
            raise HarnessError("remote-head-diverged",
                                f"sync failed: reset --hard origin/{branch}: "
                                + (reset_r.stderr or "").strip()[:200])
        head_r = _run(["rev-parse", "HEAD"], worktree)
        return head_r.stdout.strip()
    except HarnessError:
        raise
    except (subprocess.TimeoutExpired, OSError) as e:
        raise HarnessError("remote-head-diverged", f"sync failed: {e}") from e


def tracking_sha(branch, worktree, *, run_git=None) -> str:
    """SHA of refs/remotes/origin/<branch>, or "" (first push: no tracking ref yet)."""
    _run = run_git or _default_run_git
    try:
        r = _run(["rev-parse", "--verify", "--quiet",
                  f"refs/remotes/origin/{branch}"], worktree)
        if r.returncode != 0:
            return ""
        return r.stdout.strip()
    except (subprocess.TimeoutExpired, OSError):
        return ""


@dataclass
class Reconcile:
    action: str          # "match" | "synced" | "diverged"
    remote_sha: str
    evidence: str


def reconcile_remote_head(pr, expected_sha, local_sha, branch, worktree, *,
                          gh_cmd=None, run_git=None, sleep=None) -> Reconcile:
    """Single decision function. Every guarded site calls this one function.

    match   — remote head equals expected_sha; no mutation.
    synced  — remote moved but tree is equivalent; worktree reset to remote head.
    diverged — remote head has different content; caller must fail closed.
    """
    ok, remote = remote_head_matches(pr, expected_sha, gh_cmd=gh_cmd, sleep=sleep)
    if ok:
        return Reconcile("match", remote or expected_sha, "")

    # Remote head has moved. Check the ls-remote tie-breaker first: gh pr view can
    # lag right after a push and briefly report the previous head. If ls-remote says
    # the branch still points at expected_sha, treat it as a match.
    _run = run_git or _default_run_git
    _branch = branch
    if not _branch:
        try:
            br = _run(["rev-parse", "--abbrev-ref", "HEAD"], worktree)
            _branch = br.stdout.strip() if br.returncode == 0 else ""
        except (subprocess.TimeoutExpired, OSError):
            _branch = ""
    if _branch:
        try:
            ls = _run(["ls-remote", "origin", f"refs/heads/{_branch}"], worktree)
            if ls.returncode == 0 and ls.stdout.strip():
                ls_sha = ls.stdout.strip().split()[0]
                if ls_sha == expected_sha:
                    return Reconcile("match", expected_sha, "")
        except (subprocess.TimeoutExpired, OSError):
            pass

    if content_equivalent(local_sha, remote, worktree, run_git=run_git):
        _b = _branch or (run_git or _default_run_git)(
            ["rev-parse", "--abbrev-ref", "HEAD"], worktree).stdout.strip()
        new_sha = sync_to_remote(_b, worktree, run_git=run_git)
        return Reconcile(
            "synced", new_sha,
            f"remote head moved {expected_sha[:8]} -> {remote[:8]}; "
            "tree-equivalent; synced"
        )
    log.error(
        "reconcile_remote_head: remote head %s diverged from %s with different content",
        remote[:8] if remote else "?", expected_sha[:8] if expected_sha else "?"
    )
    return Reconcile(
        "diverged", remote,
        f"remote head {remote[:8]} diverged from {expected_sha[:8]} with different content"
    )
