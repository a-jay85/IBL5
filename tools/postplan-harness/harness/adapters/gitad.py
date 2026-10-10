"""Git adapter. LiveGit runs real git against a worktree (reads + local commits;
push only to an explicitly provided remote, e.g. a local bare repo in isolated
mode). ReplayGit serves recorded point-in-time state."""
from __future__ import annotations

import os
import subprocess
import time
from dataclasses import dataclass, field, replace as _dc_replace
from pathlib import Path
from typing import Optional

from ..state import SUBPROCESS_TIMEOUT, HarnessError
from ..conflict import classify, parse_unmerged
from .llm import run_bounded

COMMIT_HOOK_TIMEOUT = 300   # seconds for `git commit` incl. bin/pre-commit-hook
FETCH_LOCK_RETRIES = 3      # fetch_base retries after a concurrent-fetch ref-lock race
FETCH_LOCK_BACKOFF = 1.0    # seconds; attempt n sleeps n * this
# One retry after a short pause for a network blip on the fetch. Only stderr text
# that names a timeout or reset counts. "Could not read from remote repository" and
# "Could not resolve host" alone are auth/config/DNS failures and are never retried.
FETCH_TRANSIENT_RETRIES = 1
FETCH_TRANSIENT_DELAY = 3.0
FETCH_TRANSIENT_MARKERS = (
    "kex_exchange_identification",
    "Operation timed out",
    "Connection timed out",
    "Connection reset by peer",
)

# Local gate denials (bin/pre-commit-hook, bin/pre-push-adr-hook) are deterministic:
# re-running the FULL /post-plan skill hits the identical hook and cannot clear it
# without a human writing an ADR, refreshing a doc, or trimming a rule file. A distinct
# kind lets exit_code_for() emit the fail-closed 3 sentinel, so bin/post-plan-now skips
# the ~1M-token skill fallback instead of burning it on a guaranteed re-denial.
# Markers are the hooks' own output: bin/pre-push-adr-hook's prefix and its base-check
# line, the two guidance lines bin/pre-commit-hook echoes, and
# bin/check-rules-byte-budget's summary line.
_ZERO_SHA = "0" * 40

# ERE passed to `git grep -E` for the whole-tree conflict-marker sweep.
# Tightened to avoid false positives from separator lines like 80-char `====`:
#   `<<<<<<<` and `>>>>>>>` must be followed by a space or EOL (they always
#   precede a branch name or nothing).
#   `=======` must stand alone on the line (no heading or trailing text).
#   `|||||||` (diff3 base) follows the same space-or-EOL rule.
_CONFLICT_MARKER_RE = r"^(<{7}( |$)|={7}$|>{7}( |$)|\|{7}( |$))"

# bin/pre-push-adr-hook's FIRST arm: the branch does not contain origin/master. It fires
# before the ADR check and shares the hook's prefix, so without its own discriminator it
# reads as an ADR denial -- which is exactly how PR #2314's remediation push was
# misfiled (2026-09-20): master moved during the 35-minute review + fix span, the hook
# said "does not contain origin/master", the harness logged class=adr and gave up.
_STALE_BASE_MARKER = "does not contain origin/master"

_LOCAL_GATE_MARKERS = (
    "pre-push-adr-hook:",
    "pre-commit-adr-gate:",
    _STALE_BASE_MARKER,
    "One or more checks failed:",
    "Bump last_verified",
    "Trim the rule(s) above",
)

# Sub-classes of a local-gate denial, in SAFETY order (not frequency order). Only
# "stale-base" and "doc-staleness" are mechanically remediable. "stale-base" sits first
# because its message ALSO carries the ADR hook's prefix, and the ADR arm never runs
# when the base check fails, so the blob cannot mean both. Everything else must
# resolve to the class a human has to clear. Each discriminator is a member of
# _LOCAL_GATE_MARKERS above or the hook's own base-check wording: this reuses the hook
# protocol already declared there and adds no new hook/harness contract.
_GATE_CLASSES = (
    ("stale-base", _STALE_BASE_MARKER),
    ("adr", "pre-push-adr-hook:"),
    ("adr", "pre-commit-adr-gate:"),
    ("byte-budget", "Trim the rule(s) above"),
    ("doc-staleness", "Bump last_verified"),
)


# Every harness rebase runs with rerere off. This repo enables rerere + autoupdate, so a
# recorded resolution can silently stage a conflicted path: the rebase still stops
# (rc != 0) but `ls-files --unmerged` is empty, inventory_conflicts() reports
# "no unmerged paths", and the run dies at exit 3 (preseason-stats-retag, 2026-09-26).
# A replayed resolution is also unreviewed; with rerere off the conflict surfaces as
# real unmerged paths and goes through the resolver + TREE-EQUIVALENT proof instead.
_NO_RERERE = ("-c", "rerere.enabled=false")

_STALE_LEASE_MARKERS = ("stale info", "stale-lease:", "cannot lock ref",
                        "fetch first", "non-fast-forward")


def is_stale_lease(err: "HarnessError") -> bool:
    """True when a Phase-1 push-failed is a lease/fast-forward rejection a fetch+rebase
    can clear. detached HEAD and lease read failures stay terminal."""
    if err.kind != "push-failed":
        return False
    return any(m in (err.detail or "").lower() for m in _STALE_LEASE_MARKERS)


def is_stale_base(err: "HarnessError") -> bool:
    """True when a local-gate denial is bin/pre-push-adr-hook's base check: HEAD does not
    contain origin/master. A fetch + clean rebase clears it, the same recovery a stale
    lease gets. The hook's ADR arm is a different denial and stays terminal."""
    if err.kind != "local-gate":
        return False
    return _STALE_BASE_MARKER in (err.detail or "")


def classify_local_gate_denial(detail: str) -> str:
    """Sub-classify a HarnessError("local-gate", detail) by which hook denied it.

    Returns "stale-base", "adr", "byte-budget", "doc-staleness", or "unknown".

    "One or more checks failed:" is deliberately NOT a discriminator. It is a generic
    summary line that names no remediable cause, and bin/pre-commit-hook's other
    failure arms ("Fix the above doc issues before committing.", the gofmt arm) carry
    no marker at all -- commit_all() raises "local-gate" on ANY non-zero commit exit,
    so those reach here too and must land in "unknown", which is fail-closed.
    """
    blob = detail or ""
    for name, marker in _GATE_CLASSES:
        if marker in blob:
            return name
    return "unknown"


@dataclass(frozen=True)
class StackedRebaseResult:
    resolved: bool
    reason: str                 # "" on success; why it failed otherwise
    post_resolution_sha: str = ""
    base_sha: str = ""
    manifest_path: str = ""
    notes_path: str = ""
    collapse_warn: str = ""
    auto_resolved: bool = False
    resolved_files: tuple = ()
    squash_note: str = ""


class LiveGit:
    def __init__(self, worktree: str, push_remote: str | None = None, llm=None):
        self.worktree = worktree
        self.push_remote = push_remote  # None = pushing disabled (typed failure)
        self.llm = llm
        self.last_conflict_resolution: Optional["StackedRebaseResult"] = None
        # Unmerged paths captured at the moment a rebase stopped, BEFORE any abort clears
        # the index. Reset at the start of each rebase method; read by runner.py for the
        # audit log so a fail-closed exit 3 names the files that conflicted.
        self.last_conflict_files: tuple[str, ...] = ()
        # Probe-only state (set by predict_rebase_conflict, read by resolver_refusal_reason).
        # Stage sets per conflicted path from the merge-tree probe, and whether the probe's
        # net merge is guaranteed to match the per-commit rebase (see predict_rebase_conflict).
        self.last_conflict_stages: dict[str, frozenset[int]] = {}
        self.last_probe_exact: bool = False
        # HEAD before the current rebase; None outside one. The public rebase methods
        # clear it in `finally`. The SIGTERM handler reads it to abort and restore.
        self._pre_rebase_sha: Optional[str] = None
        self._squashed_from: Optional[str] = None   # pre-squash HEAD while a squash is live
        self._last_squash_note: str = ""

    def emergency_abort(self) -> None:
        """Called from the SIGTERM signal handler.

        Aborts any in-progress merge or rebase and hard-resets to the pre-rebase HEAD.
        Acts only when `_pre_rebase_sha` is set AND a rebase directory or MERGE_HEAD is
        present; otherwise it is a no-op to avoid destroying committed work during
        unrelated phases.
        """
        pre = self._pre_rebase_sha
        if pre is None:
            return
        # Only act when git has a stopped rebase (the directory that marks mid-rebase state).
        # --git-path can print a path relative to the worktree, so join it (a no-op
        # when git already printed an absolute one).
        def _git_path(name: str) -> str:
            out = subprocess.run(
                ["git", "-C", self.worktree, "rev-parse", "--git-path", name],
                capture_output=True, text=True,
            ).stdout.strip()
            return os.path.join(self.worktree, out) if out else ""
        merging = subprocess.run(
            ["git", "-C", self.worktree, "rev-parse", "-q", "--verify", "MERGE_HEAD"],
            capture_output=True, text=True,
        ).stdout.strip()
        if not merging and not any(
                p and os.path.exists(p)
                for p in (_git_path("rebase-merge"), _git_path("rebase-apply"))):
            if getattr(self, "_squashed_from", None) is not None:
                try:
                    self._restore_pre_squash()
                except Exception:  # signal handler: never raise
                    pass
            return
        subprocess.run(["git", "-C", self.worktree, "merge", "--abort"],
                       capture_output=True)
        subprocess.run(["git", "-C", self.worktree, "rebase", "--abort"],
                       capture_output=True)
        subprocess.run(["git", "-C", self.worktree, "reset", "--hard", pre],
                       capture_output=True)
        import sys as _sys
        _sys.stderr.write(
            f"post-plan harness: SIGTERM — aborted merge/rebase in {self.worktree},"
            f" restored to {pre[:12]}\n"
        )
        _sys.stderr.flush()

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
                raise HarnessError("local-gate", f"git {' '.join(args)}: {detail[:600]}",
                                   cmd=f"git {' '.join(args)}", output=detail)
            raise HarnessError("git", f"git {' '.join(args)}: {proc.stderr.strip()[:400]}",
                               cmd=f"git {' '.join(args)}", output=proc.stderr.strip())
        return proc.stdout

    def _run_out(self, *args: str) -> tuple[int, str]:
        proc = subprocess.run(["git", "-C", self.worktree, *args],
                              capture_output=True, text=True, errors="replace")
        return proc.returncode, f"{proc.stdout}\n{proc.stderr}".strip()

    def _run_bytes(self, *args: str, check: bool = True) -> bytes:
        """`_run` without text-mode translation. Use only where the bytes are
        written back out verbatim (the lostwork PRE patch): text mode strips
        the CR from CRLF files and replaces non-UTF-8 bytes with U+FFFD, which
        makes lostwork.sh report every CRLF line as LOST (13 blocked runs,
        2026-10-09/10)."""
        proc = subprocess.run(["git", "-C", self.worktree, *args],
                              capture_output=True)
        if check and proc.returncode != 0:
            stdout = proc.stdout.decode("utf-8", errors="replace")
            stderr = proc.stderr.decode("utf-8", errors="replace")
            blob = f"{stdout}\n{stderr}"
            if any(m in blob for m in _LOCAL_GATE_MARKERS):
                detail = "\n".join(s for s in (stderr.strip(), stdout.strip()) if s)
                raise HarnessError("local-gate", f"git {' '.join(args)}: {detail[:600]}",
                                   cmd=f"git {' '.join(args)}", output=detail)
            raise HarnessError("git", f"git {' '.join(args)}: {stderr.strip()[:400]}",
                               cmd=f"git {' '.join(args)}", output=stderr.strip())
        return proc.stdout

    def _snapshot_conflicted_paths(self) -> tuple[str, ...]:
        """Record the unmerged paths of a stopped rebase on self.last_conflict_files.

        Independent of conflict.inventory_conflicts(): that helper returns files=() when it
        classifies a conflict unresolvable (delete/modify, binary), and this snapshot must
        name the paths in exactly those cases. Never raises: a diagnostic must not mask the
        rebase failure it is describing.
        """
        try:
            out = self._run("diff", "--name-only", "--diff-filter=U", check=False)
            files = tuple(sorted(p for p in out.splitlines() if p.strip()))
        except Exception:
            files = ()
        self.last_conflict_files = files
        return files

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

    def has_changes_to_commit(self) -> bool:
        """True when the index differs from HEAD. Call after stage_all(); this is the
        exact question commit_all() asks before it decides to return ""."""
        return bool(self._run("diff", "--cached", "--name-only").strip())

    def branch_head_subject(self, base: str = "origin/master") -> str:
        """Subject of the newest commit this branch owns, or "" when HEAD is still the
        base's commit. A dirty, never-committed worktree must not borrow master's subject."""
        return self._run("log", "-1", "--format=%s", f"{self._merge_base(base)}..HEAD").strip()

    def changed_files(self, base: str = "origin/master") -> list[str]:
        vs_base = self._run("diff", "--name-only", self._merge_base(base)).strip()
        untracked = self._run("ls-files", "--others", "--exclude-standard").strip()
        out: list[str] = []
        for chunk in (vs_base, untracked):
            for f in chunk.splitlines():
                if f and f not in out:
                    out.append(f)
        return out


    def conformance_files(self, base: str = "origin/master") -> list[str]:
        """Every path this branch touched, INCLUDING the old path of a rename.

        `--no-renames` makes git report a rename as a delete of the old path plus an
        add of the new one, so one diff call yields both sides with no extra parsing
        (PR #2514: `R067 .claude/agents/sonnet-4-6.md -> .claude/agents/sonnet-5-5.md`
        held arming condition (3) because only the new path was listed). This is a
        strict superset of `changed_files`, and it is read ONLY by the conformance
        check (runner conformance.check / phase_omission_items); classify(), scope
        conformance and denied_gate_edits keep reading `changed_files`, because an
        old path showing up there would look like an unplanned file and create a
        new hold. Same two git calls as `changed_files`: no third call.
        """
        vs_base = self._run("diff", "--no-renames", "--name-only",
                            self._merge_base(base)).strip()
        untracked = self._run("ls-files", "--others", "--exclude-standard").strip()
        out: list[str] = []
        for chunk in (vs_base, untracked):
            for f in chunk.splitlines():
                if f and f not in out:
                    out.append(f)
        return out

    def read_worktree_file(self, path: str) -> str | None:
        """Text of `path` in the WORKING TREE, or None when it cannot be read.

        The working tree is what diff_vs_base() diffs against, so this is the text the
        hunks were cut from. Read ONLY by conformance.check's MISSING-METHOD fallback,
        which passes paths from conformance_files(). A path that is absolute, carries
        `..`, or resolves outside the worktree (symlink) is refused with None, as is a
        missing or non-UTF-8 file; the caller treats None as "not declared here".
        """
        if not path or os.path.isabs(path) or ".." in path.split("/"):
            return None
        root = os.path.realpath(self.worktree)
        full = os.path.realpath(os.path.join(root, path))
        if full != root and not full.startswith(root + os.sep):
            return None
        try:
            with open(full, encoding="utf-8") as fh:
                return fh.read()
        except (OSError, UnicodeDecodeError):
            return None

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
        try:
            proc = run_bounded(["git", "-C", self.worktree, "commit", "-m", message],
                               step="commit (pre-commit hook)",
                               timeout=COMMIT_HOOK_TIMEOUT, errors="replace")
        except HarnessError as e:
            if e.kind == SUBPROCESS_TIMEOUT:
                self._clear_stale_index_lock()
            raise
        if proc.returncode != 0:
            detail = "\n".join(s for s in (proc.stderr.strip(), proc.stdout.strip()) if s)
            raise HarnessError("local-gate",
                               detail[:800] or f"git commit exited {proc.returncode}",
                               cmd="git commit", output=detail)
        return self._run("rev-parse", "HEAD").strip()

    def _clear_stale_index_lock(self) -> None:
        """After a reaped commit timeout no git process holds the lock; drop it."""
        try:
            rel = self._run("rev-parse", "--git-path", "index.lock").strip()
        except HarnessError:
            return
        lock = rel if os.path.isabs(rel) else os.path.join(self.worktree, rel)
        try:
            os.remove(lock)
        except FileNotFoundError:
            pass

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
        if not ref:
            return
        # Worktrees share refs/remotes, so two runs fetching at once race on the
        # origin/master ref lock: the loser dies with "cannot lock ref ... is at X but
        # expected Y". The winner already moved the ref, so a retry succeeds. The
        # usage-gate coordinator resumes paused runs in one wave, which hit this
        # (hot-files-base-ref-validate, 2026-10-09).
        # A network blip (ssh kex timeout, connection reset) gets its own single retry
        # after a short pause (fix-and-prevent-skill, 2026-07-26). Real ssh failures
        # also print "fatal: Could not read from remote repository." after the kex
        # line, so any transient marker in the detail qualifies the retry.
        lock_attempt = 0
        transient_left = FETCH_TRANSIENT_RETRIES
        while True:
            try:
                self._run("fetch", remote, ref)
                return
            except HarnessError as e:
                detail = e.detail or ""
                if "cannot lock ref" in detail and lock_attempt < FETCH_LOCK_RETRIES:
                    time.sleep(FETCH_LOCK_BACKOFF * (lock_attempt + 1))
                    lock_attempt += 1
                    continue
                if transient_left and any(m in detail for m in FETCH_TRANSIENT_MARKERS):
                    transient_left -= 1
                    time.sleep(FETCH_TRANSIENT_DELAY)
                    continue
                raise

    def branch_base(self, branch: str | None = None) -> str | None:
        """Stacked-branch parent tip SHA, recorded by bin/wt-new as
        `git config branch.<name>.iblBase`. master is squash/rebase-merged, so once
        the parent merges its SHAs never appear in origin/master and merge-base cannot
        recover this value; the config entry is the only record. Returns None when the
        key is unset (an ordinary non-stacked branch) or when the recorded SHA no
        longer resolves to a commit in this worktree (pruned or rewritten history).
        None is the caller's signal that no auto-resolution is possible."""
        name = branch or self.branch()
        if not name or name == "HEAD":
            return None
        sha = self._run("config", "--get", f"branch.{name}.iblBase",
                        check=False).strip()
        if not sha:
            return None
        # A recorded SHA that no longer resolves would make `rebase --onto` fail with
        # a bad-revision error rather than a conflict, which would surface as a
        # confusing "git" HarnessError. Verify first and degrade to None instead.
        resolved = self._run("rev-parse", "--verify", "--quiet", f"{sha}^{{commit}}",
                             check=False).strip()
        return resolved or None

    def _merge_tree_conflicts(self, merge_base: str, base: str,
                              tree: str) -> dict[str, frozenset[int]]:
        """One `git merge-tree --write-tree` probe. Returns {path: stages} for each conflicted
        path; {} means a clean merge. Exit 1 is git's only "conflicts" code, so any other
        non-zero exit raises HarnessError("git") and the caller fails open.

        Without --name-only, stdout is the merged tree OID followed by one
        `mode oid stage<TAB>path` line per conflicted index entry, the exact shape of
        `git ls-files --unmerged`, so conflict.parse_unmerged reads it verbatim. The stage
        sets match what a real rebase leaves (both-modified {1,2,3}, delete/modify two
        stages). Unparseable output raises HarnessError("git"): same fail-open path."""
        proc = subprocess.run(
            ["git", "-C", self.worktree, "merge-tree", "--write-tree",
             "--no-messages", f"--merge-base={merge_base}", base, tree],
            capture_output=True, text=True, errors="replace")
        if proc.returncode == 0:
            return {}
        if proc.returncode != 1:
            raise HarnessError(
                "git", f"git merge-tree (rc={proc.returncode}): {proc.stderr.strip()[:400]}")
        # Line 1 is the merged tree OID; skip it.
        body = "\n".join(proc.stdout.splitlines()[1:])
        try:
            parsed = parse_unmerged(body)
        except ValueError as exc:
            raise HarnessError("git", f"git merge-tree: unparseable conflict output: {exc}")
        if not parsed:
            raise HarnessError("git", "git merge-tree exited 1 but listed no conflicted entries")
        return {path: frozenset(stages) for path, stages in parsed.items()}

    def predict_rebase_conflict(self, base: str = "origin/master") -> tuple[str, ...]:
        """Predict, without touching refs, index, or worktree, whether a rebase onto `base`
        will stop on a conflict. Returns the conflicted paths as a sorted tuple, or () if clean.

        Merges the INDEX tree (staged diff), not HEAD. Uses a plain merge-base probe first;
        if that conflicts and branch.<name>.iblBase is set, retries with the iblBase.
        Returns () unless every applicable probe conflicts.

        Raises HarnessError("git") on any git error; the caller treats that as "no prediction"
        and falls through. Does not raise HarnessError("rebase-conflict") — gating on a
        predicted conflict is the caller's responsibility. On a predicted conflict it also
        sets last_conflict_stages and last_probe_exact for resolver_refusal_reason()."""
        self.last_conflict_files = ()
        self.last_conflict_stages = {}
        self.last_probe_exact = False
        tree =self._run("write-tree").strip()
        fork_point = self._run("merge-base", "HEAD", base).strip()
        if not tree or not fork_point:
            raise HarnessError("git", f"conflict probe: empty write-tree/merge-base output "
                                      f"(tree={tree!r}, fork_point={fork_point!r})")
        conflicted = self._merge_tree_conflicts(fork_point, base, tree)
        if not conflicted:
            return ()
        ibl_base = self.branch_base()
        if ibl_base is not None and ibl_base != fork_point:
            onto_conflicted = self._merge_tree_conflicts(ibl_base, base, tree)
            if not onto_conflicted:
                return ()
            conflicted = onto_conflicted
        # Exactness: the probe merges the NET index tree; the rebase replays per commit, and
        # the two can disagree once the branch has >= 1 commit past the fork point. They are
        # identical only when the staged work becomes the single replayed commit. A recorded
        # iblBase gives autoresolve_stacked_rebase a second chance, so the plain-arm verdict
        # is not terminal there either.
        count_out = self._run("rev-list", "--count", f"{fork_point}..HEAD").strip()
        try:
            branch_commits = int(count_out)
        except ValueError:
            raise HarnessError("git", f"conflict probe: bad rev-list --count output {count_out!r}")
        self.last_conflict_files = tuple(sorted(conflicted))
        self.last_conflict_stages = dict(conflicted)
        self.last_probe_exact = branch_commits == 0 and ibl_base is None
        return self.last_conflict_files

    def resolver_refusal_reason(self) -> Optional[str]:
        """Reason the LLM resolver is certain to refuse the conflict the last
        predict_rebase_conflict() call predicted, or None when it might resolve it.

        Decidable before the rebase only through conflict.classify (migration .sql,
        lockfile, stages != {1, 2, 3}); binary files, LLM decline, marker survival, and the
        lost-work / tree-equivalence proofs are post-LLM and never predicted here. Returns
        None unless the probe was exact (no branch commits past the fork point and no
        iblBase), because only then does the probe's net merge equal the rebase that the
        resolver will see. Paths are checked in sorted order; the first reason wins."""
        if not self.last_probe_exact:
            return None
        for path in sorted(self.last_conflict_stages):
            reason = classify(path, set(self.last_conflict_stages[path]))
            if reason is not None:
                return reason
        return None

    def _load_lostwork(self, master_sha: str, key: str) -> Optional[Path]:
        """Load lostwork.sh from a pinned master SHA. Returns the path, or None if absent."""
        lostwork_path = Path(f"/tmp/postplan-lostwork-{key}.sh")
        lostwork_content = self._run(
            "show", f"{master_sha}:.claude/review-shared/scripts/lostwork.sh",
            check=False,
        )
        if not lostwork_content.strip():
            return None
        lostwork_path.write_text(lostwork_content)
        lostwork_path.chmod(0o755)
        return lostwork_path

    def _run_proof(self, lostwork_path: Path, key: str) -> tuple[bool, str]:
        """Run lostwork.sh once. Returns (ok, FULL stdout). Gate is conjunctive:
        stdout contains TREE-EQUIVALENT AND rc==0. A diverged tree exits 0 with
        TREE DIVERGED, so weakening to either operator alone admits lost work."""
        proof_proc = subprocess.run(
            ["bash", str(lostwork_path), key],
            capture_output=True, text=True, errors="replace",
            cwd=self.worktree,
        )
        proof_out = proof_proc.stdout
        return ("TREE-EQUIVALENT" in proof_out and proof_proc.returncode == 0), proof_out

    def _prove_tree_equivalent(self, lostwork_path: Path, key: str) -> tuple[bool, str]:
        """Wrapper kept for existing callers: on failure returns the 400-char
        `tree proof failed: ...` reason that rebase_cause.py parses."""
        ok, proof_out = self._run_proof(lostwork_path, key)
        if not ok:
            return False, f"tree proof failed: {proof_out.strip()[:400]}"
        return True, proof_out

    def _run_conflict_ladder(self, *, key, branch, master_sha, pre_rebase_sha,
                             lostwork_path, conflicted, proof_out):
        from ..conflict_ladder import LadderContext, default_rungs, run_ladder
        ctx = LadderContext(
            llm=self.llm, run=self._run, run_out=self._run_out,
            prove=lambda: self._run_proof(lostwork_path, key),
            worktree=self.worktree, key=key, master_sha=master_sha,
            pre_rebase_sha=pre_rebase_sha, conflicted_files=conflicted)
        return run_ladder(ctx, proof_out, rungs=default_rungs(branch))

    def _record_resolution(
        self,
        key: str,
        branch: str,
        master_sha: str,
        resolved_files: tuple,
        collapse_warn: str = "",
        proof_out: str = "",
        base_sha: str = "",
    ) -> tuple[str, str]:
        """Write manifest, notes, condition-(14) flag, and run the conflict reviewer.
        Returns (manifest_path, notes_path)."""
        from ..armable import conflict_flag_path
        from ..conflict import review_resolution

        manifest_content = self._run("diff", "--name-only", f"{master_sha}...HEAD")
        if not manifest_content.strip():
            raise HarnessError("rebase-conflict",
                               "post-resolution diff is empty; nothing to ship")
        manifest_path = f"/tmp/postplan-conflict-files-{key}.txt"
        Path(manifest_path).write_text(manifest_content)

        if resolved_files:
            autoresolved_path = f"/tmp/postplan-conflict-files-{key}-autoresolved.txt"
            # Union with any list an earlier rebase in this run wrote (stale lists from a
            # previous run are cleared once at run start); de-dup, order preserved.
            prior: list[str] = []
            try:
                prior = [l.strip() for l in
                         Path(autoresolved_path).read_text().splitlines() if l.strip()]
            except OSError:
                pass
            merged = list(dict.fromkeys([*prior, *resolved_files]))
            Path(autoresolved_path).write_text("\n".join(merged) + "\n")

        if resolved_files:
            res_list = ", ".join(resolved_files)
            notes_header = (
                f"Resolution: auto-resolved conflict in {res_list} via per-file "
                f"three-way merge onto `{master_sha}`"
                + (f" from `{base_sha}`" if base_sha else "")
                + ".\n\n"
            )
        else:
            notes_header = (
                f"Resolution: mechanical `--onto` replay onto `{master_sha}`"
                + (f" from `{base_sha}`" if base_sha else "")
                + " with no per-hunk content merge.\n\n"
            )
        file_list = "\n".join(f"- {f}" for f in manifest_content.strip().splitlines())
        notes = (
            f"# Conflict resolution — {branch}\n\n"
            f"{notes_header}"
            f"## Changed files\n\n{file_list}\n\n"
            f"## Proof\n\nTREE-EQUIVALENT\n"
        )
        if collapse_warn:
            notes += f"\n## Collapse guard\n\n{collapse_warn}\n"
        notes_path = f"/tmp/postplan-conflict-resolution-{key}.md"
        Path(notes_path).write_text(notes)

        # Write condition-(14) flag only when a model actually touched files.
        # The mechanical --onto replay (resolved_files=()) proves TREE-EQUIVALENT without
        # any model work, so no review is needed and the hold should not fire.
        if resolved_files:
            Path(conflict_flag_path(branch)).touch()

        if self.llm is not None and resolved_files:
            review_resolution(
                self.llm, self._run,
                worktree=self.worktree, key=key,
                resolved_files=resolved_files, proof_out=proof_out,
            )

        return manifest_path, notes_path

    def rebase_onto(self, base: str = "origin/master") -> None:
        # Every exit (return or raise) disarms the SIGTERM restore point.
        try:
            self._rebase_onto(base)
        finally:
            self._pre_rebase_sha = None

    def _rebase_onto(self, base: str) -> None:
        """Repo pre-push policy (bin/pre-push-adr-hook) requires origin/master to be an
        ancestor of HEAD. This merges `base` in, so every conflict surfaces at one stop
        and no branch commit is rewritten. Conflict → attempt auto-resolution; if that
        fails or is not applicable, abort the merge, restore the tree, and raise
        HarnessError. Fail closed: exit_code_for() maps this to exit 3, which
        bin/post-plan-now refuses to escalate to the skill fallback."""
        from ..conflict import (
            abort_and_restore, assert_text_only, inventory_conflicts,
            purge_verdict_artifacts, resolve_all,
        )
        from ..conflict_ladder import ladder_failure_reason

        branch = self.branch()
        key = branch.replace("/", "-")
        self.last_conflict_files = ()
        self.last_conflict_rung = ""
        master_sha = self._run("rev-parse", base).strip()

        pre_rebase_sha = self._run("rev-parse", "HEAD").strip()
        self._pre_rebase_sha = pre_rebase_sha  # arm SIGTERM handler
        pre_patch = self._run_bytes("diff", f"{master_sha}...HEAD")
        if pre_patch.strip():
            Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_bytes(pre_patch)

        purge_verdict_artifacts(key)

        proc = subprocess.run(
            ["git", "-C", self.worktree, *_NO_RERERE, "merge", "--no-edit", base],
            capture_output=True, text=True, errors="replace")
        if proc.returncode != 0:
            conflict_detail = (proc.stderr or proc.stdout).strip()[:400]

            if self.llm is None or not pre_patch.strip():
                # No LLM or no pre-patch: immediate abort-and-restore. Snapshot the
                # unmerged set FIRST; the abort clears it.
                files = self._snapshot_conflicted_paths()
                subprocess.run(["git", "-C", self.worktree, "merge", "--abort"],
                               capture_output=True, text=True, errors="replace")
                raise HarnessError(
                    "rebase-conflict",
                    f"{conflict_detail} | conflicted: {', '.join(files) or '?'}",
                )

            try:
                self._snapshot_conflicted_paths()
                inventory = inventory_conflicts(self._run)
                if inventory.unresolvable_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=inventory.unresolvable_reason)

                resolve_result = resolve_all(self.llm, self._run, worktree=self.worktree,
                                            key=key, inventory=inventory,
                                            branch_stage=2, master_sha=master_sha,
                                            pre_sha=pre_rebase_sha)
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                # The merge commit runs bin/pre-commit-hook; a hook denial lands here
                # and restores the tree with the hook's own text in the reason.
                env = {**os.environ, "GIT_EDITOR": "true"}
                cont_proc = subprocess.run(
                    ["git", "-C", self.worktree, "-c", "core.editor=true",
                     "commit", "--no-edit"],
                    capture_output=True, text=True, errors="replace", env=env,
                )
                if cont_proc.returncode != 0:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"merge commit failed: {(cont_proc.stderr or cont_proc.stdout).strip()[:400]}")

                # Whole-tree marker sweep. git grep exits 0 on a match, 1 on no match,
                # and >=2 on its own failure, so branch on rc: reading stdout alone would
                # let a rejected argv pass as "no markers found".
                sweep_rc, sweep_out = self._run_out(
                    "grep", "-n", "-E", _CONFLICT_MARKER_RE, "HEAD", "--", ".")
                if sweep_rc == 0:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"conflict markers survive after resolution: {sweep_out.strip()[:200]}")
                if sweep_rc != 1:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"marker sweep failed rc={sweep_rc}: {sweep_out.strip()[:200]}")

                text_reason = assert_text_only(self.worktree, inventory.files)
                if text_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=text_reason)

                lostwork_path = self._load_lostwork(master_sha, key)
                if lostwork_path is None:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason="lostwork.sh not found at pinned master SHA")

                proof_ok, proof_out = self._run_proof(lostwork_path, key)
                resolved_files = resolve_result.resolved_files
                self.last_conflict_rung = "first-pass"
                if not proof_ok:
                    first_proof = proof_out
                    try:
                        outcome = self._run_conflict_ladder(
                            key=key, branch=branch, master_sha=master_sha,
                            pre_rebase_sha=pre_rebase_sha, lostwork_path=lostwork_path,
                            conflicted=tuple(inventory.files), proof_out=first_proof)
                    except HarnessError as exc:
                        # The outer `except HarnessError: raise` does not restore, so a
                        # HarnessError escaping the ladder restores here.
                        self.last_conflict_rung = "exhausted"
                        abort_and_restore(self._run, worktree=self.worktree,
                                          pre_rebase_sha=pre_rebase_sha,
                                          reason=ladder_failure_reason(
                                              first_proof,
                                              (f"ladder error: {exc.kind}: {exc.detail}",)))
                    if not outcome.rung:
                        self.last_conflict_rung = "exhausted"
                        abort_and_restore(self._run, worktree=self.worktree,
                                          pre_rebase_sha=pre_rebase_sha,
                                          reason=ladder_failure_reason(first_proof, outcome.trail))
                    self.last_conflict_rung = outcome.rung
                    proof_out = outcome.proof_out
                    resolved_files = outcome.resolved_files

                try:
                    manifest_path, notes_path = self._record_resolution(
                        key, branch, master_sha, resolved_files,
                        proof_out=proof_out,
                    )
                except HarnessError as exc:
                    # Phase 3c: a HarnessError from _record_resolution (empty post-resolution
                    # diff) must restore too — the outer `except HarnessError: raise` exists
                    # only so abort_and_restore's own raise is not re-restored.
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=exc.detail)

                self.last_conflict_resolution = StackedRebaseResult(
                    resolved=True, reason="",
                    post_resolution_sha=self.head(),
                    base_sha=master_sha,
                    manifest_path=manifest_path,
                    notes_path=notes_path,
                    auto_resolved=True,
                    resolved_files=resolved_files,
                )
            except HarnessError:
                raise
            except Exception as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"unexpected error during auto-resolve: {exc}")

    def _squash_merge_range_for_replay(
        self, ibl_base: str, branch: str, pre_sha: str,
    ) -> tuple[str, str, bool]:
        """Collapse a merge-carrying `ibl_base..HEAD` range into one tree-identical commit.

        Returns (new_head, note, fatal). A per-commit --onto replay drops merge commits and
        the conflict resolutions they recorded, so a branch commit that an earlier merge
        already reconciled conflicts again. The squash replays the branch's net change
        instead. A linear range replays faithfully and is never rewritten.
        """
        merges = self._run("rev-list", "--merges", "--count", f"{ibl_base}..{pre_sha}").strip()
        if merges in ("", "0"):
            return "", "", False
        mb = self._run("merge-base", ibl_base, pre_sha, check=False).strip()
        if not mb:
            return "", "squash skipped: no merge-base between iblBase and HEAD", False
        count = self._run("rev-list", "--count", f"{mb}..{pre_sha}").strip()
        tree = self._run("rev-parse", f"{pre_sha}^{{tree}}").strip()

        # --first-parent keeps a master commit that arrived via a merge out of the pick.
        src = self._run("rev-list", "--first-parent", "--no-merges", "--reverse",
                        f"{mb}..{pre_sha}").split()
        if src:
            msg = self._run("log", "-1", "--format=%B", src[0])
            name, email, date = self._run(
                "log", "-1", "--format=%an%x00%ae%x00%ad", "--date=raw", src[0],
            ).rstrip("\n").split("\x00")
            env = {**os.environ, "GIT_AUTHOR_NAME": name, "GIT_AUTHOR_EMAIL": email,
                   "GIT_AUTHOR_DATE": date}
        else:
            msg = "chore: squash branch history before replay\n"
            env = dict(os.environ)

        # commit-tree runs no hooks and touches neither the index nor the worktree.
        proc = subprocess.run(
            ["git", "-C", self.worktree, "-c", "commit.gpgsign=false", "commit-tree",
             tree, "-p", mb, "-F", "-"],
            input=msg, env=env, capture_output=True, text=True, errors="replace",
        )
        if proc.returncode != 0:
            return "", f"squash skipped: commit-tree failed: {proc.stderr.strip()[:200]}", False
        new = proc.stdout.strip()

        # The reflog message starts with `postplan:` so collapse-guard.sh, which treats
        # ^(rebase|reset:|filter-branch|amend) entries as rewrites, does not match it.
        rc, out = self._run_out(
            "update-ref", "-m",
            f"postplan: squash {count} commits ({merges} merges) before --onto replay",
            f"refs/heads/{branch}", new, pre_sha,
        )
        if rc != 0:
            return "", f"squash aborted: update-ref compare-and-swap failed: {out.strip()[:200]}", True
        return new, f"squashed {count} commits ({merges} merges) {pre_sha[:12]} -> {new[:12]} onto {mb[:12]}", False

    def _restore_pre_squash(self) -> None:
        pre = self._squashed_from
        if pre is None:
            return
        # A live rebase/merge is abort_and_restore's state to own; never move a ref under it.
        for sub in ("rebase-merge", "rebase-apply"):
            p = self._run("rev-parse", "--git-path", sub, check=False).strip()
            if p and os.path.exists(os.path.join(self.worktree, p)):
                return
        if self._run("rev-parse", "-q", "--verify", "MERGE_HEAD", check=False).strip():
            return
        cur = self._run("rev-parse", "HEAD").strip()
        if cur == pre:
            return
        branch = self._run("rev-parse", "--abbrev-ref", "HEAD").strip()
        if branch == "HEAD":
            return  # detached: not a branch tip this run moved
        # Identical trees need no worktree write and no `reset:` reflog entry (collapse-guard
        # reads that as a rewrite); differing trees use --keep, which refuses to clobber edits.
        same_tree = (self._run("rev-parse", f"{cur}^{{tree}}").strip()
                     == self._run("rev-parse", f"{pre}^{{tree}}").strip())
        rc, out = (self._run_out("update-ref", "-m", "postplan: restore pre-squash HEAD",
                                 f"refs/heads/{branch}", pre, cur)
                   if same_tree else self._run_out("reset", "--keep", pre))
        if rc != 0:
            raise HarnessError("rebase-conflict",
                               f"RESTORE-FAILED: pre-squash HEAD {pre[:12]} not restored: {out.strip()[:300]}")

    def autoresolve_stacked_rebase(self) -> "StackedRebaseResult":
        resolved = False
        try:
            result = self._autoresolve_stacked_rebase()
            resolved = result.resolved
            return _dc_replace(result, squash_note=self._last_squash_note)
        finally:
            if not resolved:
                self._restore_pre_squash()
            self._squashed_from = None
            self._pre_rebase_sha = None

    def _autoresolve_stacked_rebase(self) -> "StackedRebaseResult":
        """Resolve a squash-trap stacked-branch conflict via `git rebase --onto`.
        Returns a StackedRebaseResult; never raises on a decline — the caller owns
        the single decision about exit 3."""
        branch = self.branch()
        key = branch.replace("/", "-")
        self.last_conflict_files = ()
        self._squashed_from = None
        self._last_squash_note = ""

        # Step 2: iblBase (early return before any network/expensive call)
        ibl_base = self.branch_base()
        if ibl_base is None:
            return StackedRebaseResult(False, "no branch.<name>.iblBase recorded; not a known stacked branch")

        # Step 3: refuse dirty tree
        if self.is_dirty():
            return StackedRebaseResult(False, "worktree dirty at resolution entry")

        # Step 4: pre-side capture — must happen before touching history
        pre_patch = self._run_bytes("diff", f"{ibl_base}...HEAD")
        if not pre_patch.strip():
            return StackedRebaseResult(False, "pre-rebase diff vs iblBase is empty")
        Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_bytes(pre_patch)

        # Pin master_sha once so a concurrent fetch cannot split the proof across two bases
        master_sha = self._run("rev-parse", "origin/master").strip()

        # Step 5: extract proof and guard scripts by pinned git show
        lostwork_path = self._load_lostwork(master_sha, key)
        if lostwork_path is None:
            return StackedRebaseResult(False, "lostwork.sh not found at pinned master SHA")

        collapse_guard_path = Path(f"/tmp/postplan-collapse-guard-{key}.sh")
        collapse_content = self._run(
            "show", f"{master_sha}:.claude/review-shared/scripts/collapse-guard.sh",
            check=False,
        )
        if not collapse_content.strip():
            return StackedRebaseResult(False, "collapse-guard.sh not found at pinned master SHA")
        collapse_guard_path.write_text(collapse_content)
        collapse_guard_path.chmod(0o755)

        # Step 6: collapse guard — check then record
        collapse_warn = ""
        check_proc = subprocess.run(
            ["bash", str(collapse_guard_path), "check", key, branch],
            capture_output=True, text=True, errors="replace",
            cwd=self.worktree,
        )
        check_out = check_proc.stdout + check_proc.stderr
        if "STOP: PRIOR-COLLAPSE-DETECTED" in check_out or check_proc.returncode != 0:
            return StackedRebaseResult(False, f"collapse guard check failed: {check_out[:400]}")
        for line in check_out.splitlines():
            if "COLLAPSE-GUARD: WARN" in line:
                collapse_warn = line.strip()
                break

        record_proc = subprocess.run(
            ["bash", str(collapse_guard_path), "record", key, branch],
            capture_output=True, text=True, errors="replace",
            cwd=self.worktree,
        )
        if record_proc.returncode != 0:
            return StackedRebaseResult(
                False,
                f"collapse guard record failed: {(record_proc.stderr or record_proc.stdout).strip()[:400]}",
            )

        # Step 7: the --onto rebase (direct subprocess, same shape as rebase_onto)
        from ..conflict import (
            abort_and_restore, assert_text_only, inventory_conflicts,
            purge_verdict_artifacts, resolve_all,
        )

        pre_rebase_sha = self._run("rev-parse", "HEAD").strip()
        self._pre_rebase_sha = pre_rebase_sha  # arm SIGTERM handler
        purge_verdict_artifacts(key)

        # Step 6.5: collapse a merge-carrying range into one tree-identical commit, so the
        # --onto replay sees the branch's net change, not commits whose conflicts a dropped
        # merge already resolved. Runs AFTER collapse-guard `record` (records the true
        # pre-squash tip) and AFTER pre_rebase_sha is armed (restore point = pre-squash HEAD).
        self._squashed_from = pre_rebase_sha  # armed BEFORE the ref moves; restore is a no-op while HEAD == pre
        squashed_head, squash_note, squash_fatal = self._squash_merge_range_for_replay(
            ibl_base, branch, pre_rebase_sha,
        )
        self._last_squash_note = squash_note
        if not squashed_head:
            self._squashed_from = None
        if squash_fatal:
            return StackedRebaseResult(False, squash_note)

        rebase_proc = subprocess.run(
            ["git", "-C", self.worktree, *_NO_RERERE, "rebase", "--onto", master_sha, ibl_base, branch],
            capture_output=True, text=True, errors="replace",
        )
        auto_resolved_files: tuple = ()
        if rebase_proc.returncode != 0:
            if self.llm is None:
                # No LLM: immediate decline. Snapshot the unmerged set FIRST; the abort
                # clears it.
                files = self._snapshot_conflicted_paths()
                subprocess.run(
                    ["git", "-C", self.worktree, "rebase", "--abort"],
                    capture_output=True, text=True, errors="replace",
                )
                return StackedRebaseResult(
                    False,
                    f"--onto rebase still conflicts: {(rebase_proc.stderr or rebase_proc.stdout).strip()[:400]}"
                    f" | conflicted: {', '.join(files) or '?'}",
                )

            try:
                self._snapshot_conflicted_paths()
                inventory = inventory_conflicts(self._run)
                if inventory.unresolvable_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=inventory.unresolvable_reason)

                resolve_result = resolve_all(self.llm, self._run, worktree=self.worktree,
                                            key=key, inventory=inventory,
                                            branch_stage=3, master_sha=master_sha,
                                            pre_sha=pre_rebase_sha)
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                env = {**os.environ, "GIT_EDITOR": "true"}
                cont_proc = subprocess.run(
                    ["git", "-C", self.worktree, *_NO_RERERE, "rebase", "--continue"],
                    capture_output=True, text=True, errors="replace", env=env,
                )
                if cont_proc.returncode != 0:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"rebase --continue failed: {(cont_proc.stderr or cont_proc.stdout).strip()[:400]}")

                # Whole-tree marker sweep. git grep exits 0 on a match, 1 on no match,
                # and >=2 on its own failure, so branch on rc: reading stdout alone would
                # let a rejected argv pass as "no markers found".
                sweep_rc, sweep_out = self._run_out(
                    "grep", "-n", "-E", _CONFLICT_MARKER_RE, "HEAD", "--", ".")
                if sweep_rc == 0:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"conflict markers survive after resolution: {sweep_out.strip()[:200]}")
                if sweep_rc != 1:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=f"marker sweep failed rc={sweep_rc}: {sweep_out.strip()[:200]}")

                text_reason = assert_text_only(self.worktree, inventory.files)
                if text_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=text_reason)

                auto_resolved_files = resolve_result.resolved_files
            except HarnessError:
                raise
            except Exception as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"unexpected error during auto-resolve: {exc}")

        # Step 8: TREE-EQUIVALENT proof — gate is conjunctive (stdout contains
        # TREE-EQUIVALENT AND rc == 0), because a diverged tree exits 0 with TREE DIVERGED
        proof_ok, proof_out = self._prove_tree_equivalent(lostwork_path, key)
        if not proof_ok:
            if auto_resolved_files:
                # Failed after auto-resolution: abort_and_restore
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=proof_out)
            return StackedRebaseResult(False, proof_out)

        # Step 9: manifest + notes + flag + reviewer via _record_resolution
        try:
            manifest_path, notes_path = self._record_resolution(
                key, branch, master_sha, auto_resolved_files,
                collapse_warn=collapse_warn, proof_out=proof_out, base_sha=ibl_base,
            )
        except HarnessError as exc:
            if auto_resolved_files:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=exc.detail)
            raise
        except Exception as exc:
            if auto_resolved_files:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=str(exc))
            raise HarnessError("rebase-conflict", str(exc))

        return StackedRebaseResult(
            resolved=True,
            reason="",
            post_resolution_sha=self.head(),
            base_sha=ibl_base,
            manifest_path=manifest_path,
            notes_path=notes_path,
            collapse_warn=collapse_warn,
            auto_resolved=bool(auto_resolved_files),
            resolved_files=auto_resolved_files,
        )

    def capture_lostwork_pre(self, key: str) -> bool:
        """Pre-side capture for lostwork.sh. Must run BEFORE fetch/rebase."""
        pre = self._run_bytes("diff", self._merge_base("origin/master"))
        if not pre.strip():
            return False
        Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_bytes(pre)
        return True

    def prove_lostwork(self, key: str) -> tuple[bool, str]:
        master_sha = self._run("rev-parse", "origin/master").strip()
        content = self._run(
            "show", f"{master_sha}:.claude/review-shared/scripts/lostwork.sh",
            check=False)
        if not content.strip():
            return False, "lostwork.sh not found at pinned master SHA"
        script = Path(f"/tmp/postplan-lostwork-{key}.sh")
        script.write_text(content)
        script.chmod(0o755)
        proc = subprocess.run(["bash", str(script), key],
                              capture_output=True, text=True, errors="replace",
                              cwd=self.worktree)
        ok = "TREE-EQUIVALENT" in proc.stdout and proc.returncode == 0
        return ok, (proc.stdout + proc.stderr).strip()[:400]

    def push(self) -> None:
        if not self.push_remote:
            raise HarnessError("push-disabled",
                               "no isolated push remote configured; live push requires install approval")
        branch = self.branch()
        if branch == "HEAD":
            raise HarnessError("push-failed", "detached HEAD: refusing to push without a branch name")
        remote = self.push_remote
        lease = self._run("rev-parse", "--verify", "--quiet",
                          f"refs/remotes/{remote}/{branch}", check=False).strip()
        if not lease:
            rc, out = self._run_out("ls-remote", remote, f"refs/heads/{branch}")
            if rc != 0:
                raise HarnessError("push-failed",
                                   f"lease read failed for {remote}/{branch}: {out[:400]}")
            if out.strip():
                sha = out.strip().split()[0]
                raise HarnessError("push-failed",
                                   f"stale-lease: {remote} holds refs/heads/{branch} ({sha}) "
                                   f"but this worktree has no refs/remotes/{remote}/{branch}")
            lease = _ZERO_SHA
        rc, out = self._run_out("push", f"--force-with-lease={branch}:{lease}",
                                remote, f"HEAD:refs/heads/{branch}")
        if rc != 0:
            if any(m in out for m in _LOCAL_GATE_MARKERS):
                raise HarnessError("local-gate", f"git push: {out[:600]}",
                                   cmd=f"git push --force-with-lease={branch}:{lease} {remote} HEAD:refs/heads/{branch}",
                                   output=out)
            raise HarnessError("push-failed", out[:600],
                               cmd=f"git push --force-with-lease={branch}:{lease} {remote} HEAD:refs/heads/{branch}",
                               output=out)

    def push_ff(self) -> str:
        if not self.push_remote:
            raise HarnessError("push-disabled",
                               "no isolated push remote configured; live push requires install approval")
        branch = self.branch()
        if branch == "HEAD":
            raise HarnessError("push-failed", "detached HEAD: refusing to push without a branch name")
        remote = self.push_remote
        rc, out = self._run_out("push", remote, f"HEAD:refs/heads/{branch}")
        if rc != 0:
            if any(m in out for m in _LOCAL_GATE_MARKERS):
                raise HarnessError("local-gate", f"git push: {out[:600]}",
                                   cmd=f"git push {remote} HEAD:refs/heads/{branch}", output=out)
            raise HarnessError("push-failed", out[:600],
                               cmd=f"git push {remote} HEAD:refs/heads/{branch}", output=out)
        return self.head()


class ReplayGit:
    """Point-in-time state reconstructed from a historical trace fixture."""

    def __init__(self, fixture: dict):
        self.fx = fixture
        self.commit_messages: list[str] = []
        self.pushes = 0
        self.ff_pushes = 0
        self.meta_checks_calls: list[tuple[str, int]] = []

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

    def has_changes_to_commit(self) -> bool:
        # Historical fixtures all model a run that commits, so absent means True.
        return not self.fx.get("clean_tree", False)

    def branch_head_subject(self, base: str = "origin/master") -> str:
        return self.fx.get("head_subject", "")

    def changed_files(self, base: str = "origin/master") -> list[str]:
        from ..classify import files_from_diff
        return files_from_diff(self.diff_vs_base())

    def conformance_files(self, base: str = "origin/master") -> list[str]:
        """`changed_files(base)` plus the OLD path of every rename in the fixture diff.

        Built ON TOP of `changed_files` on purpose: replay test fakes override
        `changed_files` to inject a touched-path list (test_runner_replay._TwoCallGit,
        test_fidelity_remediation._GateEditGit, test_fidelity_rounds._CountingGit), and
        this delegation keeps their injected list flowing into the conformance check
        without each fake having to learn a second method. Deleted files are already
        in `changed_files` (b-side of the header) and stay counted.
        """
        from ..classify import rename_sources_from_diff
        out = list(self.changed_files(base))
        for src in rename_sources_from_diff(self.diff_vs_base()):
            if src not in out:
                out.append(src)
        return out

    def read_worktree_file(self, path: str) -> str | None:
        """Replay has no tree to read; fail closed so a replayed MISSING-METHOD never clears."""
        return None

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

    def push_ff(self) -> str:  # replay: recorded as a count; no remote, so return ""
        self.ff_pushes += 1
        return ""

    def predict_rebase_conflict(self, base: str = "origin/master") -> tuple:
        # Replay mode has no live repo to probe; report clean so the probe is a no-op.
        return ()
