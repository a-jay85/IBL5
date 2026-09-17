"""Git adapter. LiveGit runs real git against a worktree (reads + local commits;
push only to an explicitly provided remote, e.g. a local bare repo in isolated
mode). ReplayGit serves recorded point-in-time state."""
from __future__ import annotations

import subprocess

from ..state import HarnessError

# Local gate denials (bin/pre-commit-hook, bin/pre-push-adr-hook) are deterministic:
# re-running the FULL /post-plan skill hits the identical hook and cannot clear it
# without a human writing an ADR, refreshing a doc, or trimming a rule file. A distinct
# kind lets exit_code_for() emit the fail-closed 3 sentinel, so bin/post-plan-now skips
# the ~1M-token skill fallback instead of burning it on a guaranteed re-denial.
# Markers are the hooks' own output: bin/pre-push-adr-hook's prefix, the two guidance
# lines bin/pre-commit-hook echoes, and bin/check-rules-byte-budget's summary line.
_LOCAL_GATE_MARKERS = (
    "pre-push-adr-hook:",
    "One or more checks failed:",
    "Bump last_verified",
    "Trim the rule(s) above",
)


class LiveGit:
    def __init__(self, worktree: str, push_remote: str | None = None):
        self.worktree = worktree
        self.push_remote = push_remote  # None = pushing disabled (typed failure)

    def _run(self, *args: str, check: bool = True) -> str:
        proc = subprocess.run(["git", "-C", self.worktree, *args],
                              capture_output=True, text=True, errors="replace")
        if check and proc.returncode != 0:
            # Scan BOTH streams: bin/pre-commit-hook runs its checks with `2>&1` and
            # echoes guidance to stdout, so stderr-only detection misses every
            # commit-path denial. The generic "git" branch keeps its stderr-only
            # shape unchanged.
            blob = f"{proc.stdout}\n{proc.stderr}"
            if any(m in blob for m in _LOCAL_GATE_MARKERS):
                detail = "\n".join(s for s in (proc.stderr.strip(), proc.stdout.strip()) if s)
                raise HarnessError("local-gate", f"git {' '.join(args)}: {detail[:600]}")
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
        # A rejected `git commit` is a GATE, not a transient git error. _run's
        # _LOCAL_GATE_MARKERS sniff catches the gates whose wording it enumerates, but
        # bin/pre-commit-hook's gofmt leg ("gofmt: unformatted Go files") and its
        # check-docs leg ("Fix the above doc issues before committing.") match none of
        # them -- those rejections still type "git", exit 1, and launch the ~1M-token
        # skill fallback on a tree only a human can fix. On the COMMIT path the return
        # code alone is sufficient signal: no blind /post-plan re-run satisfies a hook
        # that just said no, so there is no message left to enumerate. Kind stays
        # "local-gate" so it lands in runner._FAIL_CLOSED_KINDS and exits 3 -- no new
        # exit code to thread through should_fallback, GATE_CLOSE or the automouse
        # ledger arm, all of which already admit 3. Same direct-subprocess shape as
        # rebase_onto() below; no abort step, because a rejected commit leaves no
        # partial state. BOTH streams are captured: the hook writes its reason to
        # whichever it likes, so rebase_onto's `stderr or stdout` would discard the one
        # line that names the gate.
        proc = subprocess.run(["git", "-C", self.worktree, "commit", "-m", message],
                              capture_output=True, text=True, errors="replace")
        if proc.returncode != 0:
            detail = "\n".join(s for s in (proc.stderr.strip(), proc.stdout.strip()) if s)
            raise HarnessError("local-gate",
                               detail[:800] or f"git commit exited {proc.returncode}")
        return self._run("rev-parse", "HEAD").strip()

    def head(self) -> str:
        return self._run("rev-parse", "HEAD").strip()

    def head_tree(self) -> str:
        # a TREE sha, not a commit sha: condition (12) compares trees so a no-op
        # commit (rebase, empty amend) does not invalidate a still-valid review
        return self._run("rev-parse", "HEAD^{tree}").strip()

    def fetch_base(self, base: str = "origin/master") -> None:
        """Freshen the base ref so diff/classification and the later rebase see
        the real remote tip, not a stale local origin/master."""
        remote, _, ref = base.partition("/")
        if ref:
            self._run("fetch", remote, ref)

    def rebase_onto(self, base: str = "origin/master") -> None:
        """Repo pre-push policy (pre-push-adr-hook) rejects branches not rebased
        onto origin/master. Conflict → abort, restore the tree, typed failure.
        Fail closed: exit_code_for() maps this to exit 3, which bin/post-plan-now
        refuses to escalate to the skill fallback — a human owns conflict judgment."""
        proc = subprocess.run(["git", "-C", self.worktree, "rebase", base],
                              capture_output=True, text=True, errors="replace")
        if proc.returncode != 0:
            subprocess.run(["git", "-C", self.worktree, "rebase", "--abort"],
                           capture_output=True, text=True, errors="replace")
            raise HarnessError("rebase-conflict",
                               (proc.stderr or proc.stdout).strip()[:400])

    def push(self) -> None:
        if not self.push_remote:
            raise HarnessError("push-disabled",
                               "no isolated push remote configured; live push requires install approval")
        # --force-with-lease: rebase_onto() rewrites SHAs, making plain push fail non-fast-forward
        self._run("push", "--force-with-lease", self.push_remote, "HEAD")


class ReplayGit:
    """Point-in-time state reconstructed from a historical trace fixture."""

    def __init__(self, fixture: dict):
        self.fx = fixture
        self.commit_messages: list[str] = []
        self.pushes = 0

    def branch(self) -> str:
        return self.fx.get("slug", "unknown-branch")

    def stage_all(self) -> None:
        return

    def is_dirty(self) -> bool:
        # the Phase 2 commit leaves the replay tree clean, as it does live
        return bool(self.fx.get("worktree_diff")) and not self.commit_messages

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
        self.commit_messages.append(message)
        return "replay-sha-" + self.fx.get("slug", "x")[:12]

    def head(self) -> str:
        return self.fx.get("head_sha") or ""

    def head_tree(self) -> str:
        trees = self.fx.get("head_trees")
        if trees:
            return trees[min(len(self.commit_messages), len(trees) - 1)]
        # no fixture trees: synthesise one that advances with each replay commit, so a
        # replay commit invalidates a prior review exactly as a live commit does
        return format(len(self.commit_messages), "040x")

    def push(self) -> None:  # replay: recorded as a count; ghad records PR intents
        self.pushes += 1
        return
