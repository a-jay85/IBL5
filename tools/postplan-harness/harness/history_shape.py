"""Phase 5.5 history-shape stop: a stacked branch carrying a master merge its parent
never took (PR #3672). PR-body or file fixes cannot remove a merge commit, so the
remediation loop records one decision and stops before the first fixer spawn."""
from __future__ import annotations

import subprocess

HISTORY_SHAPE_REASON = "history-shape"
GIT_TIMEOUT = 30  # seconds per git call


def _git(worktree: str, *args: str) -> subprocess.CompletedProcess:
    return subprocess.run(["git", "-C", worktree, *args], capture_output=True, text=True,
                          stdin=subprocess.DEVNULL, timeout=GIT_TIMEOUT)


def _is_ancestor(worktree: str, a: str, b: str) -> bool:
    return _git(worktree, "merge-base", "--is-ancestor", a, b).returncode == 0


def stray_master_merges(worktree: str, base: str, master: str = "origin/master") -> list[str]:
    """SHAs of merge commits in base..HEAD with a non-first parent on `master` but not on
    `base`. [] for an unstacked branch (base == master), a missing worktree, or any git
    failure. A failure keeps today's loop: bounded, and it still ends NOT READY."""
    if not worktree or base == master:
        return []
    try:
        merges = _git(worktree, "rev-list", "--merges", f"{base}..HEAD")
        if merges.returncode != 0:
            return []
        hits = []
        for sha in merges.stdout.split():
            parents = _git(worktree, "rev-list", "--parents", "-n", "1", sha).stdout.split()[2:]
            if any(_is_ancestor(worktree, p, master) and not _is_ancestor(worktree, p, base)
                   for p in parents):
                hits.append(sha)
        return hits
    except (OSError, subprocess.TimeoutExpired):
        return []


def next_action(branch: str, base: str, merges: list[str]) -> str:
    shas = ", ".join(s[:12] for s in merges)
    return (f"{branch} carries origin/master merge(s) {shas} but its PR base is {base}. "
            f"Rebuild the branch from {base} without the merge and force-push it by hand, "
            f"or retarget the PR to master, then re-run bin/post-plan-now.")
