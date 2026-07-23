"""Git adapter. LiveGit runs real git against a worktree (reads + local commits;
push only to an explicitly provided remote, e.g. a local bare repo in isolated
mode). ReplayGit serves recorded point-in-time state."""
from __future__ import annotations

import subprocess

from ..state import HarnessError


class LiveGit:
    def __init__(self, worktree: str, push_remote: str | None = None):
        self.worktree = worktree
        self.push_remote = push_remote  # None = pushing disabled (typed failure)

    def _run(self, *args: str, check: bool = True) -> str:
        proc = subprocess.run(["git", "-C", self.worktree, *args],
                              capture_output=True, text=True)
        if check and proc.returncode != 0:
            raise HarnessError("git", f"git {' '.join(args)}: {proc.stderr.strip()[:400]}")
        return proc.stdout

    def branch(self) -> str:
        return self._run("rev-parse", "--abbrev-ref", "HEAD").strip()

    def is_dirty(self) -> bool:
        return bool(self._run("status", "--porcelain").strip())

    def stage_all(self) -> None:
        """post-plan-now fires on a DIRTY worktree by design — stage everything
        so untracked files land in the shippable diff before classification."""
        self._run("add", "-A")

    def _merge_base(self, base: str) -> str:
        mb = self._run("merge-base", base, "HEAD").strip()
        return mb or base

    def diff_vs_base(self, base: str = "origin/master") -> str:
        # merge-base → WORKING TREE: committed + staged + unstaged, and (after
        # stage_all) untracked. `base...HEAD` alone drops the dirty tree, which
        # turns every post-plan-now invocation into a false "nothing to ship".
        return self._run("diff", self._merge_base(base))

    def working_diff(self) -> str:
        # staged + unstaged, vs HEAD (what Phase 2 would commit)
        return self._run("diff", "HEAD")

    def changed_files(self, base: str = "origin/master") -> list[str]:
        vs_base = self._run("diff", "--name-only", self._merge_base(base)).strip()
        untracked = self._run("ls-files", "--others", "--exclude-standard").strip()
        out: list[str] = []
        for chunk in (vs_base, untracked):
            for f in chunk.splitlines():
                if f and f not in out:
                    out.append(f)
        return out

    def modified_files(self, base: str = "origin/master") -> list[str]:
        out = self._run("diff", "--diff-filter=M", "--name-only",
                        self._merge_base(base)).strip()
        return [f for f in out.splitlines() if f]

    def commit_all(self, message: str) -> str:
        self._run("add", "-A")
        if not self._run("diff", "--cached", "--name-only").strip():
            return ""
        self._run("commit", "-m", message)
        return self._run("rev-parse", "HEAD").strip()

    def head(self) -> str:
        return self._run("rev-parse", "HEAD").strip()

    def fetch_base(self, base: str = "origin/master") -> None:
        """Freshen the base ref so diff/classification and the later rebase see
        the real remote tip, not a stale local origin/master."""
        remote, _, ref = base.partition("/")
        if ref:
            self._run("fetch", remote, ref)

    def rebase_onto(self, base: str = "origin/master") -> None:
        """Repo pre-push policy (pre-push-adr-hook) rejects branches not rebased
        onto origin/master. Conflict → abort, restore the tree, typed failure
        (fail closed: the skill fallback owns conflict judgment)."""
        proc = subprocess.run(["git", "-C", self.worktree, "rebase", base],
                              capture_output=True, text=True)
        if proc.returncode != 0:
            subprocess.run(["git", "-C", self.worktree, "rebase", "--abort"],
                           capture_output=True, text=True)
            raise HarnessError("rebase-conflict",
                               (proc.stderr or proc.stdout).strip()[:400])

    def push(self) -> None:
        if not self.push_remote:
            raise HarnessError("push-disabled",
                               "no isolated push remote configured; live push requires install approval")
        self._run("push", self.push_remote, "HEAD")


class ReplayGit:
    """Point-in-time state reconstructed from a historical trace fixture."""

    def __init__(self, fixture: dict):
        self.fx = fixture

    def branch(self) -> str:
        return self.fx.get("slug", "unknown-branch")

    def stage_all(self) -> None:
        return

    def is_dirty(self) -> bool:
        return bool(self.fx.get("worktree_diff"))

    def diff_vs_base(self, base: str = "origin/master") -> str:
        return self.fx.get("diff") or self.fx.get("worktree_diff") or ""

    def working_diff(self) -> str:
        return self.fx.get("worktree_diff") or self.fx.get("diff") or ""

    def changed_files(self, base: str = "origin/master") -> list[str]:
        from ..classify import files_from_diff
        return files_from_diff(self.diff_vs_base())

    def modified_files(self, base: str = "origin/master") -> list[str]:
        from ..classify import modified_files_from_diff
        return modified_files_from_diff(self.diff_vs_base())

    def commit_all(self, message: str) -> str:
        return "replay-sha-" + self.fx.get("slug", "x")[:12]

    def push(self) -> None:  # replay: recorded as a no-op; ghad records PR intents
        return
