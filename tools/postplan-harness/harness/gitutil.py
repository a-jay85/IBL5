"""Remote-head reconciliation primitives for the post-plan harness.

Four pure-git/gh helpers plus one composite decision function, reconcile_remote_head,
that returns match | synced | diverged. Every guarded site calls that one function so
the decision tree exists once.

content_equivalent answers "same tree"; patch_series_equivalent answers "same patch series
rebased onto newer master". Both must fail closed.
"""
from __future__ import annotations

import logging
import re
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


# One pairing line of `git range-diff` output: "<n>:  <sha> <marker> <n>:  <sha> <subject>".
# Interdiff body lines under a "!" pairing are indented and never match.
_RANGE_DIFF_LINE = re.compile(r"^\s*(\d+|-):\s+\S+\s+([=!<>])\s+(\d+|-):\s+\S+")


def _parse_range_diff(text: str) -> list[tuple[str, str, str]]:
    """(left_index, marker, right_index) for every pairing line in range-diff output."""
    out = []
    for line in text.splitlines():
        m = _RANGE_DIFF_LINE.match(line)
        if m:
            out.append((m.group(1), m.group(2), m.group(3)))
    return out


def _series_all_equal(pairs, expected_count: int) -> bool:
    """True iff range-diff paired exactly expected_count commits, every pair is '=',
    and pair k pairs left commit k with right commit k (no reordering)."""
    if expected_count < 1 or len(pairs) != expected_count:
        return False
    for pos, (left, marker, right) in enumerate(pairs, start=1):
        if marker != "=" or left != str(pos) or right != str(pos):
            return False
    return True


def patch_series_equivalent(local_sha, remote_sha, worktree, *, run_git=None,
                            master_ref="origin/master") -> bool:
    """True iff remote_sha is the same patch series as local_sha rebased onto a newer
    master. Every arm fails closed: any git rc != 0, unparseable output, timeout or
    OSError returns False.

    Arms, in order:
      1. bl = merge-base(local, master), br = merge-base(remote, master) both resolve.
      2. bl and br are ancestors of master, and bl is an ancestor of br (remote base
         is at or after the local base — a rebase onto *newer* master, never older).
      3. rev-list --count bl..local == rev-list --count br..remote, and >= 1.
      4. git range-diff bl..local br..remote pairs every commit '=' in position
         (same patch, same message, same order). '!', '<', '>' or a cross-position
         pairing all fail.
    """
    if not local_sha or not remote_sha:
        return False
    _run = run_git or _default_run_git

    def _text(r):
        return r.stdout.strip() if r.returncode == 0 and r.stdout.strip() else ""

    try:
        if _run(["fetch", "origin"], worktree).returncode != 0:
            log.warning("patch_series_equivalent: git fetch failed — treating as not equivalent")
            return False
        bl = _text(_run(["merge-base", local_sha, master_ref], worktree))
        br = _text(_run(["merge-base", remote_sha, master_ref], worktree))
        if not bl or not br:
            return False
        for anc, desc in ((bl, master_ref), (br, master_ref), (bl, br)):
            if _run(["merge-base", "--is-ancestor", anc, desc], worktree).returncode != 0:
                return False
        n_l = _text(_run(["rev-list", "--count", f"{bl}..{local_sha}"], worktree))
        n_r = _text(_run(["rev-list", "--count", f"{br}..{remote_sha}"], worktree))
        try:
            n, nr = int(n_l), int(n_r)
        except ValueError:
            return False
        if n < 1 or n != nr:
            return False
        rd = _run(["range-diff", "--no-color", f"{bl}..{local_sha}", f"{br}..{remote_sha}"],
                  worktree)
        if rd.returncode != 0:
            return False
        return _series_all_equal(_parse_range_diff(rd.stdout), n)
    except (subprocess.TimeoutExpired, OSError):
        return False


def sync_to_remote(branch, worktree, *, run_git=None) -> str:
    """Reset the worktree HEAD to origin/<branch>. Precondition: content_equivalent or patch_series_equivalent is True."""
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
    synced  — remote moved but is tree-equivalent, or is the same patch series rebased onto newer master; worktree reset to remote head.
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

    def _adopt(kind: str) -> Reconcile:
        _b = _branch or _run(["rev-parse", "--abbrev-ref", "HEAD"], worktree).stdout.strip()
        new_sha = sync_to_remote(_b, worktree, run_git=run_git)
        return Reconcile(
            "synced", new_sha,
            f"remote head moved {expected_sha[:8]} -> {remote[:8]}; {kind}; synced"
        )

    if content_equivalent(local_sha, remote, worktree, run_git=run_git):
        return _adopt("tree-equivalent")
    if patch_series_equivalent(local_sha, remote, worktree, run_git=run_git):
        log.info("reconcile_remote_head: remote %s is the same patch series as %s rebased "
                 "onto newer master; adopting", remote[:8], local_sha[:8])
        return _adopt("patch-series-equivalent (rebased onto newer master)")
    log.error(
        "reconcile_remote_head: remote head %s diverged from %s with different content",
        remote[:8] if remote else "?", expected_sha[:8] if expected_sha else "?"
    )
    return Reconcile(
        "diverged", remote,
        f"remote head {remote[:8]} diverged from {expected_sha[:8]} with different content"
    )
