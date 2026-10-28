"""Git adapter. LiveGit runs real git against a worktree (reads + local commits;
push only to an explicitly provided remote, e.g. a local bare repo in isolated
mode). ReplayGit serves recorded point-in-time state."""
from __future__ import annotations

import os
import re
import subprocess
import time
from dataclasses import dataclass, field, replace as _dc_replace
from pathlib import Path
from typing import Optional

from .. import netretry
from ..mutations import JOURNAL_DIRNAME
from ..state import MUTATION_JOURNAL_PENDING, PRECOMMIT_HANG, SUBPROCESS_TIMEOUT, HarnessError
from ..conflict import classify, parse_unmerged
from .llm import run_bounded

COMMIT_HOOK_TIMEOUT = 300   # seconds for `git commit` incl. bin/pre-commit-hook
GIT_OP_TIMEOUT = 300    # seconds; every other local git call routed through _run/_run_out/_run_bytes
GIT_NET_TIMEOUT = 600   # seconds; fetch, push (runs bin/pre-push-adr-hook), ls-remote, pull
EMERGENCY_GIT_TIMEOUT = 30   # seconds per git call in emergency_abort (SIGTERM cleanup path)
# A hang in any git call fails closed with subprocess-timeout naming `git <subcommand>`.
# Added after the 2026-10-23 run (PR 3352) sat 90 min after "starting commit (pre-commit
# hook)" with no FAILED line: the bounded commit leg was ruled out, which left the then
# unbounded `git add -A` / `git diff --cached` calls at the top of commit_all.
_NET_SUBCOMMANDS = frozenset({"fetch", "push", "ls-remote", "pull"})


def _git_timeout(args: tuple[str, ...]) -> int:
    """Read at call time, so tests can monkeypatch either constant."""
    return GIT_NET_TIMEOUT if args and args[0] in _NET_SUBCOMMANDS else GIT_OP_TIMEOUT


def _git_step(args: tuple[str, ...]) -> str:
    """`git <subcommand>`, skipping leading `-c key=val` pairs (e.g. _NO_RERERE)."""
    rest = list(args)
    while len(rest) >= 2 and rest[0] == "-c":
        rest = rest[2:]
    return f"git {rest[0]}" if rest else "git"
FETCH_LOCK_RETRIES = 3      # fetch_base retries after a concurrent-fetch ref-lock race
FETCH_LOCK_BACKOFF = 1.0    # seconds; attempt n sleeps n * this

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
    adapted_lines: tuple = ()


def lostwork_pre_path(key: str) -> str:
    """The pre-rebase PR patch capture_lostwork_pre writes (lostwork.sh reads it too)."""
    return f"/tmp/pr-ready-diff-pre-{key}.patch"


class LiveGit:
    def __init__(self, worktree: str, push_remote: str | None = None, llm=None,
                 state_dir: str | None = None):
        self.worktree = worktree
        self.state_dir = state_dir or ""  # "" = cost-guard marker dir unavailable
        self.push_remote = push_remote  # None = pushing disabled (typed failure)
        self.llm = llm
        self.last_conflict_resolution: Optional["StackedRebaseResult"] = None
        # Unmerged paths captured at the moment a rebase stopped, BEFORE any abort clears
        # the index. Reset at the start of each rebase method; read by runner.py for the
        # audit log so a fail-closed exit 3 names the files that conflicted.
        self.last_conflict_files: tuple[str, ...] = ()
        # Set by _reconcile_conflict_flag at each rebase start; "" when the marker is absent.
        # The note is for logging only; control flow keys on the FLAG_* state.
        self.last_conflict_flag_note: str = ""
        self.last_conflict_flag_state: str = ""
        self.conflict_review_retries: dict[str, int] = {}   # review_resolution retries_out; runner copies into RunResult.retries
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
        self._decline_retries_used: int = 0   # Opus decline retries spent this run

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
            proc = self._emergency_git("rev-parse", "--git-path", name)
            out = proc.stdout.strip() if proc else ""
            return os.path.join(self.worktree, out) if out else ""
        merge_proc = self._emergency_git("rev-parse", "-q", "--verify", "MERGE_HEAD")
        merging = merge_proc.stdout.strip() if merge_proc else ""
        if not merging and not any(
                p and os.path.exists(p)
                for p in (_git_path("rebase-merge"), _git_path("rebase-apply"))):
            if getattr(self, "_squashed_from", None) is not None:
                try:
                    self._restore_pre_squash()
                except Exception:  # signal handler: never raise
                    pass
            return
        # Each step is best-effort: a hang or failure in one must not skip the next.
        timed_out = [
            args[0] for args in (("merge", "--abort"), ("rebase", "--abort"),
                                 ("reset", "--hard", pre))
            if self._emergency_git(*args) is None
        ]
        import sys as _sys
        _sys.stderr.write(
            f"post-plan harness: SIGTERM — aborted merge/rebase in {self.worktree},"
            f" restored to {pre[:12]}"
            + (f" (timed out or failed to run: {', '.join(timed_out)})" if timed_out else "")
            + "\n"
        )
        _sys.stderr.flush()

    def _emergency_git(self, *args: str):
        """Bounded git call for the SIGTERM cleanup path. Never raises: returns the
        CompletedProcess, or None when git hung (reaped, stale index.lock dropped) or
        could not run. A nonzero rc still returns the process, as the old bare run did."""
        try:
            return run_bounded(["git", "-C", self.worktree, *args], step=_git_step(args),
                               timeout=EMERGENCY_GIT_TIMEOUT, errors="replace")
        except HarnessError as e:
            if e.kind == SUBPROCESS_TIMEOUT:
                self._clear_stale_index_lock()
            return None
        except Exception:  # signal handler: never raise (e.g. OSError)
            return None

    def drop_commit(self, pre: str) -> str:
        """Move HEAD back to `pre`, dropping commits made after it.

        Uses `reset --keep` (refuses to clobber unexpected worktree edits) and names the
        reflog action `postplan` so collapse-guard.sh, which treats
        ^(rebase|reset:|filter-branch|amend) reflog subjects as a history rewrite, does
        not read the dropped commit as lost branch work. Returns "" on success and the
        git error text on failure. Never raises: the caller compares `head()` to `pre`
        and fails closed on any mismatch.
        """
        proc = subprocess.run(
            ["git", "-C", self.worktree, "reset", "--keep", pre],
            env={**os.environ, "GIT_REFLOG_ACTION": "postplan"},
            capture_output=True, text=True, errors="replace",
        )
        if proc.returncode != 0:
            return (proc.stderr.strip() or proc.stdout.strip() or
                    f"reset --keep {pre[:12]} exited {proc.returncode}")[:300]
        return ""

    def _bounded(self, args: tuple[str, ...], **kw):
        """run_bounded for a git call. A killed git leaves .git/index.lock behind, which
        would fail emergency_abort and the human re-run, so clear it on a timeout.
        Network ops (fetch, push, ls-remote) never hold the index lock."""
        try:
            return run_bounded(["git", "-C", self.worktree, *args], step=_git_step(args),
                               timeout=_git_timeout(args), **kw)
        except HarnessError as e:
            if e.kind == SUBPROCESS_TIMEOUT and args and args[0] not in ("fetch", "push", "ls-remote"):
                self._clear_stale_index_lock()
            raise

    def _run(self, *args: str, check: bool = True) -> str:
        proc = self._bounded(args, errors="replace")
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
        """Raises HarnessError(subprocess-timeout) on a hang. Only a completed git returns an rc."""
        proc = self._bounded(args, errors="replace")
        return proc.returncode, f"{proc.stdout}\n{proc.stderr}".strip()

    def _run_bytes(self, *args: str, check: bool = True) -> bytes:
        """`_run` without text-mode translation. Use only where the bytes are
        written back out verbatim (the lostwork PRE patch): text mode strips
        the CR from CRLF files and replaces non-UTF-8 bytes with U+FFFD, which
        makes lostwork.sh report every CRLF line as LOST (13 blocked runs,
        2026-10-09/10)."""
        proc = self._bounded(args, text=False)
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

    def _journal_pending(self) -> bool:
        """True when bin/lib/plan-exec-mutations left an unreverted edit (ADR-0199)."""
        git_dir = Path(self._run("rev-parse", "--git-dir").strip())
        if not git_dir.is_absolute():
            git_dir = Path(self.worktree) / git_dir
        return any((git_dir / JOURNAL_DIRNAME).glob("*.meta"))

    def _refuse_if_journal_pending(self) -> None:
        if self._journal_pending():
            raise HarnessError(MUTATION_JOURNAL_PENDING,
                               "exec-mutation journal pending — tree may be mutated; "
                               "refusing to stage/commit")

    def dirty_paths(self) -> list[str]:
        """Every path `git status` reports, one entry per file (untracked dirs expanded).

        `--untracked-files=all` so a new test in a new directory is listed as its file,
        not as `dir/`; `--no-renames` so each record is one path (the conformance fixer
        compares these against exact allowed paths)."""
        out = self._run("status", "--porcelain", "-z", "--untracked-files=all",
                        "--no-renames")
        return [ent[3:] for ent in out.split("\0") if len(ent) > 3]

    def restore_paths(self, paths) -> None:
        """Put `paths` back to HEAD: tracked files are checked out, untracked removed."""
        paths = [p for p in (paths or []) if p]
        if not paths:
            return
        tracked = set(self._run("ls-files", "-z", "--", *paths).split("\0")) - {""}
        if tracked:
            self._run("checkout", "HEAD", "--", *sorted(tracked))
        for p in paths:
            if p not in tracked:
                try:
                    os.remove(os.path.join(self.worktree, p))
                except OSError:
                    pass

    def unstage_paths(self, paths) -> None:
        """Drop `paths` from the index (back to HEAD's entry); the working files stay.

        A commit the pre-commit hook rejected leaves `git add -A` staged; a caller
        that reverts the files must unstage them too or the tree stays dirty."""
        paths = [p for p in (paths or []) if p]
        if paths:
            self._run("reset", "-q", "--", *paths, check=False)

    def stage_all(self) -> None:
        """post-plan-now fires on a DIRTY worktree by design — stage everything
        so untracked files land in the shippable diff before classification."""
        self._refuse_if_journal_pending()
        self._run("add", "-A")

    def _merge_base(self, base: str) -> str:
        mb = self._run("merge-base", base, "HEAD").strip()
        return mb or base

    def diff_vs_base(self, base: str = "origin/master") -> str:
        # merge-base → WORKING TREE: committed + staged + unstaged, and (after
        # stage_all) untracked. `base...HEAD` alone drops the dirty tree, which
        # turns every post-plan-now invocation into a false "nothing to ship".
        return self._run("diff", self._merge_base(base))

    def own_change_diff(self, base: str = "origin/master") -> str:
        # Same range as diff_vs_base, zero context lines. Feeds the own-change id,
        # which must not move when master edits a line next to a PR hunk.
        return self._run("diff", "-U0", self._merge_base(base))

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
        self._refuse_if_journal_pending()
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
            if e.kind != SUBPROCESS_TIMEOUT:
                raise
            self._clear_stale_index_lock()
            # Distinct kind so the RESULT line and DM say the HOOK hung, not "a subprocess".
            # The add/diff legs above keep subprocess-timeout: their hang is not the hook's.
            raise HarnessError(
                PRECOMMIT_HANG,
                f"pre-commit hook hung past {COMMIT_HOOK_TIMEOUT}s and was killed "
                "(step 'commit (pre-commit hook)')",
                cmd="git commit", output=e.output) from None
        if proc.returncode != 0:
            detail = "\n".join(s for s in (proc.stderr.strip(), proc.stdout.strip()) if s)
            raise HarnessError("local-gate",
                               detail[:800] or f"git commit exited {proc.returncode}",
                               cmd="git commit", output=detail)
        return self._run("rev-parse", "HEAD").strip()

    def _clear_stale_index_lock(self) -> None:
        """After a reaped commit timeout no git process holds the lock; drop it."""
        try:
            # run_bounded directly: going through self._run would recurse on a timeout.
            rel = run_bounded(["git", "-C", self.worktree, "rev-parse", "--git-path", "index.lock"],
                              step="rev-parse index.lock", timeout=30,
                              errors="replace").stdout.strip()
        except HarnessError:
            return
        if not rel:
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
        # Network blips (ssh kex timeout, connection reset) go through netretry.call:
        # three retries after 5, 20 and 60 s. The lock-race loop keeps its own budget.
        lock_attempt = 0

        def _fetch() -> None:
            nonlocal lock_attempt
            while True:
                try:
                    self._run("fetch", remote, ref)
                    return
                except HarnessError as e:
                    if "cannot lock ref" in (e.detail or "") and lock_attempt < FETCH_LOCK_RETRIES:
                        time.sleep(FETCH_LOCK_BACKOFF * (lock_attempt + 1))
                        lock_attempt += 1
                        continue
                    raise

        netretry.call(_fetch, label=f"git fetch {remote} {ref}")

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
        unregistered lockfile, stages != {1, 2, 3}); binary files, LLM decline, marker survival, and the
        lost-work / tree-equivalence proofs are post-LLM and never predicted here. Returns
        None unless the probe was exact (no branch commits past the fork point and no
        iblBase), because only then does the probe's net merge equal the rebase that the
        resolver will see. Paths are checked in sorted order; the first reason wins."""
        if not self.last_probe_exact:
            return None
        for path in sorted(self.last_conflict_stages):
            reason = classify(path, set(self.last_conflict_stages[path]),
                              regen_lockfiles=True)
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

    def _prove_tree_equivalent(self, lostwork_path: Path, key: str) -> tuple[bool, str]:
        """Run the TREE-EQUIVALENT proof. Gate is conjunctive: stdout AND rc==0.
        A diverged tree exits 0 with TREE DIVERGED — weakening to either operator alone
        would silently admit lost work."""
        rc, proof_out = self._run_lostwork(lostwork_path, key)
        if not ("TREE-EQUIVALENT" in proof_out and rc == 0):
            return False, f"tree proof failed: {proof_out.strip()[:400]}"
        return True, proof_out

    def _run_lostwork(self, lostwork_path: Path, key: str) -> tuple[int, str]:
        """Run lostwork.sh and return (rc, untruncated stdout)."""
        proof_proc = subprocess.run(
            ["bash", str(lostwork_path), key],
            capture_output=True, text=True, errors="replace",
            cwd=self.worktree,
        )
        return proof_proc.returncode, proof_proc.stdout

    def _prove_with_adaptations(
        self,
        lostwork_path: Path,
        key: str,
        *,
        resolved_files: tuple,
        adaptations: tuple,
        pre_rebase_sha: str,
        master_sha: str,
    ) -> tuple[bool, str, tuple]:
        """The conjunctive proof, plus one narrow relaxation: a LOST branch-added line
        passes only when it is a deterministic near-match of a rewrite master made in
        the same file, the resolver justified it, and a read-only reviewer CONFIRMED it
        (harness/adaptations.py). A strict pass makes no model call."""
        from ..adaptations import accept_adapted_proof

        rc, out = self._run_lostwork(lostwork_path, key)
        if "TREE-EQUIVALENT" in out and rc == 0:
            return True, out, ()
        merge_base = self._run("merge-base", pre_rebase_sha, master_sha, check=False).strip()
        ok, composed, accepted = accept_adapted_proof(
            self.llm, self._run,
            worktree=self.worktree, key=key, proof_out=out, rc=rc,
            resolved_files=resolved_files, justifications=adaptations,
            merge_base=merge_base, master_sha=master_sha,
        )
        if not ok:
            return (False,
                    f"tree proof failed: {out.strip()[:400]} | adaptation rejected: {composed}",
                    ())
        return True, composed, accepted

    def _run_proof(self, lostwork_path: Path, key: str) -> tuple[bool, str]:
        """Strict proof for the conflict ladder. Returns (ok, FULL stdout). Gate is
        conjunctive: stdout contains TREE-EQUIVALENT AND rc==0. A diverged tree exits 0
        with TREE DIVERGED, so weakening to either operator alone admits lost work."""
        rc, proof_out = self._run_lostwork(lostwork_path, key)
        return ("TREE-EQUIVALENT" in proof_out and rc == 0), proof_out

    def _pre_resolve_generated(self, inventory, *, master_sha, pre_rebase_sha):
        """Split flagged generated files out of `inventory`. Returns
        (remaining_inventory, targets). `targets` is () when nothing qualifies, and the
        caller then runs exactly today's path. Never runs the generator; see
        _regen_generated."""
        from .. import conflict_ladder
        targets = conflict_ladder.pre_resolve_targets(
            inventory.files, worktree=self.worktree, stage_sets=inventory.stage_sets)
        if not targets:
            return inventory, ()
        skip = {t.path for t in targets}
        remaining = _dc_replace(
            inventory,
            files=tuple(p for p in inventory.files if p not in skip),
            stage_sets={p: s for p, s in inventory.stage_sets.items() if p not in skip})
        return remaining, targets

    def _regen_generated(self, targets, *, master_sha, pre_rebase_sha):
        """Regenerate `targets` in the in-progress merge and stage the output. Returns the
        exempt paths (targets plus any sibling output the generator changed). Every
        failure calls abort_and_restore, which raises HarnessError."""
        from .. import conflict_ladder
        from ..conflict import abort_and_restore
        untracked_before = set(self._run("ls-files", "--others", "--exclude-standard").split())
        try:
            conflict_ladder.regenerate(
                self._run, self._run_out, worktree=self.worktree,
                master_sha=master_sha, candidates=targets)
        except (RuntimeError, subprocess.TimeoutExpired) as exc:
            abort_and_restore(self._run, worktree=self.worktree,
                              pre_rebase_sha=pre_rebase_sha,
                              reason=f"resolver declined: {targets[0].path} "
                                     f"(regen failed: {str(exc)[:300]})")
        # Every auto-merged and LLM-resolved file is staged, so the worktree-vs-index
        # diff holds only generator writes.
        changed = (set(self._run("diff", "--name-only").split())
                   | (set(self._run("ls-files", "--others", "--exclude-standard").split())
                      - untracked_before))
        allowed = conflict_ladder.command_outputs(targets)
        stray = sorted(changed - allowed)
        if stray:
            abort_and_restore(self._run, worktree=self.worktree,
                              pre_rebase_sha=pre_rebase_sha,
                              reason=f"resolver declined: {targets[0].path} "
                                     f"(regen touched unexpected paths: {', '.join(stray)[:300]})")
        exempt = tuple(sorted({t.path for t in targets} | changed))
        self._run("add", "-A", "--", *exempt)
        return exempt

    def _regen_lockfiles(self, paths, *, master_sha, pre_rebase_sha):
        """Rebuild each set-aside lockfile from master's copy plus the resolved manifest
        and stage it. Returns the paths, which the proof then treats as derived. A failure
        restores the tree with the module's `lockfile: <path> (...)` reason."""
        from .. import lockfile_regen
        from ..conflict import abort_and_restore
        done = []
        for path in paths:
            entry = lockfile_regen.regenerable(path)
            try:
                cmd = lockfile_regen.regen_lockfile(
                    entry, run=self._run, worktree=self.worktree, master_sha=master_sha,
                    other_locks=tuple(p for p in paths if p != path))
            except lockfile_regen.LockfileRegenError as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha, reason=str(exc))
            self.last_lockfile_regens += ((path, cmd),)
            done.append(path)
        return tuple(done)

    def _rewrite_pre_patch_for_regen(self, key, *, master_sha, pre_rebase_sha, exempt):
        """Rewrite lostwork_pre_path(key) so each exempt path's section is the
        master-to-merged diff of the regenerated file. Every other file's section stays
        byte-identical to the original capture."""
        excludes = [f":(exclude){p}" for p in exempt]
        kept = self._run_bytes("diff", f"{master_sha}...{pre_rebase_sha}", "--", ".", *excludes)
        regen = self._run_bytes("diff", master_sha, "HEAD", "--", *exempt)
        path = Path(lostwork_pre_path(key))
        old = path.read_bytes() if path.exists() else None
        path.write_bytes(kept + regen)

        def undo():
            if old is None:
                path.unlink(missing_ok=True)
            else:
                path.write_bytes(old)
        return undo

    def _run_conflict_ladder(self, *, key, branch, master_sha, pre_rebase_sha,
                             lostwork_path, conflicted, proof_out, regen_exempt=(),
                             stage_sets=None):
        from ..conflict_ladder import LadderContext, default_rungs, run_ladder
        ctx = LadderContext(
            llm=self.llm, run=self._run, run_out=self._run_out,
            prove=lambda: self._run_proof(lostwork_path, key),
            worktree=self.worktree, key=key, master_sha=master_sha,
            pre_rebase_sha=pre_rebase_sha, conflicted_files=conflicted,
            state_dir=self.state_dir, stage_sets=dict(stage_sets or {}),
            extend_pre_patch=lambda paths: self._rewrite_pre_patch_for_regen(
                key, master_sha=master_sha, pre_rebase_sha=pre_rebase_sha,
                exempt=tuple(dict.fromkeys([*regen_exempt, *paths]))))
        return run_ladder(ctx, proof_out, rungs=default_rungs(branch))

    def _run_stacked_lost_retry(self, *, key, master_sha, pre_rebase_sha,
                                lostwork_path, conflicted, proof_out):
        """One Opus rung over the strict proof's LOST list, after an --onto replay. The
        parents are snapshotted now, so a squashed replay (parent = master) and an
        unsquashed one (parent = the previous replayed commit) are both guarded."""
        from ..conflict_ladder import LadderContext, make_opus_rung, run_ladder
        head_parents = tuple(self._run("rev-list", "--parents", "-n", "1", "HEAD").split()[1:])
        ctx = LadderContext(
            llm=self.llm, run=self._run, run_out=self._run_out,
            prove=lambda: self._run_proof(lostwork_path, key),
            worktree=self.worktree, key=key, master_sha=master_sha,
            pre_rebase_sha=pre_rebase_sha, conflicted_files=conflicted,
            state_dir=self.state_dir, extend_pre_patch=None,
            expected_parents=head_parents)
        return run_ladder(ctx, proof_out, rungs=(make_opus_rung(),))

    def _record_resolution(
        self,
        key: str,
        branch: str,
        master_sha: str,
        resolved_files: tuple,
        collapse_warn: str = "",
        proof_out: str = "",
        base_sha: str = "",
        adapted_lines: tuple = (),
    ) -> tuple[str, str]:
        """Write manifest, notes, condition-(14) flag, and run the conflict reviewer.
        Returns (manifest_path, notes_path)."""
        from ..armable import record_conflict_resolution
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
        if adapted_lines:
            notes += "\n## Adapted lines\n\n" + "".join(
                f"- {a.path}: +{a.original} => +{a.adapted} [{a.old} -> {a.new}]\n"
                for a in adapted_lines
            )
        if collapse_warn:
            notes += f"\n## Collapse guard\n\n{collapse_warn}\n"
        notes_path = f"/tmp/postplan-conflict-resolution-{key}.md"
        Path(notes_path).write_text(notes)

        # Write condition-(14) flag only when a model actually touched files.
        # The mechanical --onto replay (resolved_files=()) proves TREE-EQUIVALENT without
        # any model work, so no review is needed and the hold should not fire.
        # The marker records the resolving HEAD so a later run can tell a resolution that
        # is still in history (hold) from one a squash or rebase dropped (stale).
        if resolved_files:
            record_conflict_resolution(branch, self._run("rev-parse", "HEAD").strip())

        if self.llm is not None and resolved_files:
            review_resolution(
                self.llm, self._run,
                worktree=self.worktree, key=key,
                resolved_files=resolved_files, proof_out=proof_out,
                adaptations=adapted_lines,
                retries_out=self.conflict_review_retries,
            )

        return manifest_path, notes_path

    def _reconcile_conflict_flag(self, branch: str, key: str, base: str) -> None:
        """Drop a condition-(14) marker whose resolution is no longer in HEAD's history.

        Runs at the start of every rebase, before purge_verdict_artifacts, so the marker
        Phase 6.5 reads describes THIS branch history. Fail closed: a git error, an odd
        marker, or a legacy empty marker without rewrite proof keeps the file and the hold.
        """
        import sys as _sys
        from ..armable import (FLAG_ABSENT, FLAG_STALE, FLAG_UNKNOWN,
                               classify_conflict_flag, conflict_flag_path)

        flag = conflict_flag_path(branch)
        notes = f"/tmp/postplan-conflict-resolution-{key}.md"

        def _git(*args: str) -> tuple[int, str]:
            proc = subprocess.run(["git", "-C", self.worktree, *args],
                                  capture_output=True, text=True, errors="replace")
            return proc.returncode, proc.stdout

        class _GitError(Exception):
            pass

        def is_ancestor(sha: str) -> bool:
            rc = _git("merge-base", "--is-ancestor", sha, "HEAD")[0]
            if rc in (0, 1):
                return rc == 0
            # Any other exit is a git error, or the recorded object is gone. cat-file -e
            # exits 1 only for a missing object, so a pruned commit still counts as not an
            # ancestor while a broken repo (128) stays unknown and keeps the hold. No
            # ^{commit} peel: a peel of a missing object exits 128, which would never clear.
            if _git("cat-file", "-e", sha)[0] == 1:
                return False
            raise _GitError(f"git merge-base --is-ancestor exited {rc}")

        def legacy_rewritten(floor: float) -> bool:
            # Two independent witnesses must agree before an empty marker is called stale:
            # (1) no merge commit in base..HEAD -- the harness merge path and the skill both
            #     resolve into a merge commit, and the skill touches its flag BEFORE that
            #     commit exists, so mtime alone would wrongly call it rewritten;
            # (2) every commit in base..HEAD has a committer time strictly newer than the
            #     marker -- squash and rebase re-stamp %ct; the stacked --onto path touches
            #     the marker AFTER its resolution commit, so that commit is never newer.
            # An empty range passes both (nothing on the branch can carry a resolution).
            rc, merges = _git("rev-list", "--merges", "--count", f"{base}..HEAD")
            if rc != 0 or merges.strip() != "0":
                return False
            rc, stamps = _git("log", "--format=%ct", f"{base}..HEAD")
            if rc != 0:
                return False
            try:
                return all(int(s) > floor for s in stamps.split())
            except ValueError:
                return False

        try:
            state, detail = classify_conflict_flag(flag, notes, is_ancestor, legacy_rewritten)
        except (_GitError, OSError) as exc:
            state, detail = FLAG_UNKNOWN, f"git error while classifying: {exc}"
        self.last_conflict_flag_state = state
        if state == FLAG_ABSENT:
            self.last_conflict_flag_note = ""
            return
        if state == FLAG_STALE:
            try:
                os.unlink(flag)
            except OSError as exc:
                note = (f"conflict flag stale but could not be removed: {flag} "
                        f"({exc.__class__.__name__}); hold (14) stays")
            else:
                note = f"conflict flag dropped as stale: {flag} ({detail})"
        elif state == FLAG_UNKNOWN:
            note = f"conflict flag kept (unknown): {flag} ({detail}); hold (14) stays"
        else:
            note = f"conflict flag carried: {flag} ({detail}); hold (14) stays until reviewed"
        self.last_conflict_flag_note = note
        _sys.stderr.write(f"postplan: {note}\n")
        _sys.stderr.flush()

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
            DECLINE_RETRY_MODEL, MAX_DECLINE_RETRIES,
            abort_and_restore, assert_text_only, inventory_conflicts,
            purge_verdict_artifacts, resolve_all,
        )
        from ..conflict_ladder import RUNG_REGEN, ladder_failure_reason

        branch = self.branch()
        key = branch.replace("/", "-")
        self.last_conflict_files = ()
        self.last_conflict_resolution = None
        self.last_conflict_rung = ""
        self.last_conflict_fix_summary = ""
        self.last_lockfile_regens: tuple[tuple[str, str], ...] = ()
        self.conflict_review_retries = {}
        master_sha = self._run("rev-parse", base).strip()

        pre_rebase_sha = self._run("rev-parse", "HEAD").strip()
        self._pre_rebase_sha = pre_rebase_sha  # arm SIGTERM handler
        pre_patch = self._run_bytes("diff", f"{master_sha}...HEAD")
        if pre_patch.strip():
            Path(lostwork_pre_path(key)).write_bytes(pre_patch)

        self._reconcile_conflict_flag(branch, key, base)
        purge_verdict_artifacts(key)

        proc = self._bounded((*_NO_RERERE, "merge", "--no-edit", base), errors="replace")
        if proc.returncode != 0:
            conflict_detail = (proc.stderr or proc.stdout).strip()[:400]

            if self.llm is None or not pre_patch.strip():
                # No LLM or no pre-patch: immediate abort-and-restore. Snapshot the
                # unmerged set FIRST; the abort clears it.
                files = self._snapshot_conflicted_paths()
                self._bounded(("merge", "--abort"), errors="replace")
                raise HarnessError(
                    "rebase-conflict",
                    f"{conflict_detail} | conflicted: {', '.join(files) or '?'}",
                )

            try:
                self._snapshot_conflicted_paths()
                inventory = inventory_conflicts(self._run, regen_lockfiles=True)
                if inventory.unresolvable_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=inventory.unresolvable_reason)

                llm_inventory, regen_targets = self._pre_resolve_generated(
                    inventory, master_sha=master_sha, pre_rebase_sha=pre_rebase_sha)
                resolve_result = resolve_all(self.llm, self._run, worktree=self.worktree,
                                            key=key, inventory=llm_inventory,
                                            branch_stage=2, master_sha=master_sha,
                                            pre_sha=pre_rebase_sha,
                                            decline_retry_model=DECLINE_RETRY_MODEL,
                                            max_decline_retries=MAX_DECLINE_RETRIES - self._decline_retries_used)
                self._decline_retries_used += resolve_result.decline_retries
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                regen_exempt = ()
                if regen_targets:
                    regen_exempt = self._regen_generated(
                        regen_targets, master_sha=master_sha, pre_rebase_sha=pre_rebase_sha)
                if inventory.lockfiles:
                    regen_exempt = tuple(sorted({*regen_exempt, *self._regen_lockfiles(
                        inventory.lockfiles, master_sha=master_sha,
                        pre_rebase_sha=pre_rebase_sha)}))

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

                # A copy-only target was taken from master byte-for-byte, never
                # three-way merged, so the text guard does not apply to it. Every
                # LLM-resolved and generator-regenerated path is still checked.
                binary_exempt = {t.path for t in regen_targets if t.command is None}
                text_reason = assert_text_only(
                    self.worktree,
                    [*(p for p in inventory.files if p not in binary_exempt),
                     *inventory.lockfiles])
                if text_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=text_reason)

                if regen_exempt:
                    self._rewrite_pre_patch_for_regen(
                        key, master_sha=master_sha, pre_rebase_sha=pre_rebase_sha,
                        exempt=regen_exempt)
                lostwork_path = self._load_lostwork(master_sha, key)
                if lostwork_path is None:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason="lostwork.sh not found at pinned master SHA")

                proof_ok, proof_out, adapted = self._prove_with_adaptations(
                    lostwork_path, key,
                    resolved_files=resolve_result.resolved_files,
                    adaptations=resolve_result.adaptations,
                    pre_rebase_sha=pre_rebase_sha, master_sha=master_sha,
                )
                resolved_files = tuple(sorted(set(resolve_result.resolved_files) | set(regen_exempt)))
                self.last_conflict_rung = "first-pass"
                if regen_exempt and not llm_inventory.files:
                    self.last_conflict_rung = RUNG_REGEN
                if not proof_ok:
                    # The ladder needs the full LOST list; proof_out is the 400-char
                    # reason string when the adaptation path rejected. Re-run the strict proof.
                    _strict_ok, first_proof = self._run_proof(lostwork_path, key)
                    adapt_trail = tuple(
                        f"adaptation rejected: {p}" for p in proof_out.split(" | adaptation rejected: ", 1)[1:])
                    try:
                        outcome = self._run_conflict_ladder(
                            key=key, branch=branch, master_sha=master_sha,
                            pre_rebase_sha=pre_rebase_sha, lostwork_path=lostwork_path,
                            conflicted=tuple(inventory.files), proof_out=first_proof,
                            regen_exempt=regen_exempt, stage_sets=inventory.stage_sets)
                    except HarnessError as exc:
                        # The outer `except HarnessError: raise` does not restore, so a
                        # HarnessError escaping the ladder restores here.
                        self.last_conflict_rung = "exhausted"
                        abort_and_restore(self._run, worktree=self.worktree,
                                          pre_rebase_sha=pre_rebase_sha,
                                          reason=ladder_failure_reason(
                                              first_proof,
                                              adapt_trail + (f"ladder error: {exc.kind}: {exc.detail}",)))
                    if not outcome.rung:
                        self.last_conflict_rung = "exhausted"
                        # Only entries from a run where the guard reached the proof:
                        # the DM line says the proof still failed.
                        self.last_conflict_fix_summary = next(
                            (t for t in outcome.trail
                             if t == "sonnet-fix: proof still failed"
                             or t.startswith("sonnet-fix: partial (")), "")
                        abort_and_restore(self._run, worktree=self.worktree,
                                          pre_rebase_sha=pre_rebase_sha,
                                          reason=ladder_failure_reason(first_proof, adapt_trail + tuple(outcome.trail)))
                    self.last_conflict_rung = outcome.rung
                    proof_out = outcome.proof_out
                    resolved_files = outcome.resolved_files
                    adapted = ()  # a ladder rung re-proved strictly; no adaptation relaxation applies

                try:
                    manifest_path, notes_path = self._record_resolution(
                        key, branch, master_sha, resolved_files,
                        proof_out=proof_out, adapted_lines=adapted,
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
                    adapted_lines=adapted,
                    resolved_files=resolved_files,
                )
            except HarnessError:
                raise
            except Exception as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"unexpected error during auto-resolve: {exc}")

    def _squash_merge_range_for_replay(
        self, ibl_base: str, branch: str, pre_sha: str, *, include_linear: bool = False,
    ) -> tuple[str, str, bool]:
        """Collapse a merge-carrying `ibl_base..HEAD` range into one tree-identical commit.

        Returns (new_head, note, fatal). A per-commit --onto replay drops merge commits and
        the conflict resolutions they recorded, so a branch commit that an earlier merge
        already reconciled conflicts again. The squash replays the branch's net change
        instead. A linear range is rewritten only when the caller passes
        `include_linear=True`, which the stacked path does after an unsquashed replay
        conflicts; a one-commit range is never rewritten.
        """
        merges = self._run("rev-list", "--merges", "--count", f"{ibl_base}..{pre_sha}").strip()
        linear = merges in ("", "0")
        if linear and not include_linear:
            return "", "", False
        mb = self._run("merge-base", ibl_base, pre_sha, check=False).strip()
        if not mb:
            return "", "squash skipped: no merge-base between iblBase and HEAD", False
        count = self._run("rev-list", "--count", f"{mb}..{pre_sha}").strip()
        if linear and int(count or "0") < 2:
            return "", "", False
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

        # Net-diff identity guard: the squash must not change what the PR ships. Checked
        # before update-ref, so a mismatch moves no ref and needs no restore.
        new_tree = self._run("rev-parse", f"{new}^{{tree}}").strip()
        new_parent = self._run("rev-parse", f"{new}^").strip()
        pre_diff = self._run_bytes("diff", "--binary", ibl_base, pre_sha)
        new_diff = self._run_bytes("diff", "--binary", ibl_base, new)
        if new_tree != tree or new_parent != mb or pre_diff != new_diff:
            return "", (f"squash aborted: net diff vs {ibl_base[:12]} changed "
                        f"({pre_sha[:12]} -> {new[:12]})"), True

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

        # Pin master_sha once, BEFORE the pre-side capture, so the proof's base and the
        # rebase target come from the same fetch (a concurrent fetch cannot split them).
        master_sha = self._run("rev-parse", "origin/master").strip()

        # Step 4: pre-side capture — must happen before touching history.
        # `bin/wt-new` records `iblBase=master` for every ordinary branch, and the local
        # `master` ref can sit behind origin/master after a merge-pull. A stale base makes
        # `{ibl_base}...HEAD` carry master's own commits, so a deletion master made and
        # later reversed reads as a branch deletion the proof then reports as LOST. When
        # ibl_base is an ancestor of the pinned master_sha, `{master_sha}...HEAD` starts at
        # merge-base(master_sha, HEAD) and holds only the branch's net change (same range
        # as the plain path in _rebase_onto). A stacked parent tip that is not an ancestor
        # keeps the iblBase range so the parent's unmerged work is not charged to this branch.
        anc_rc, _ = self._run_out("merge-base", "--is-ancestor", ibl_base, master_sha)
        pre_base = master_sha if anc_rc == 0 else ibl_base
        pre_patch = self._run_bytes("diff", f"{pre_base}...HEAD")
        if not pre_patch.strip():
            return StackedRebaseResult(False, "pre-rebase diff vs iblBase is empty")
        Path(lostwork_pre_path(key)).write_bytes(pre_patch)

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

        # Step 7: the --onto rebase (bounded via _bounded, same shape as rebase_onto)
        from ..conflict import (
            DECLINE_RETRY_MODEL, MAX_DECLINE_RETRIES,
            abort_and_restore, assert_text_only, inventory_conflicts,
            purge_verdict_artifacts, resolve_all,
        )

        pre_rebase_sha = self._run("rev-parse", "HEAD").strip()
        self._pre_rebase_sha = pre_rebase_sha  # arm SIGTERM handler
        self._reconcile_conflict_flag(branch, key, pre_base)
        purge_verdict_artifacts(key)

        # Step 6.5: collapse a merge-carrying range into one tree-identical commit, so the
        # --onto replay sees the branch's net change, not commits whose conflicts a dropped
        # merge already resolved. Runs AFTER collapse-guard `record` (records the true
        # pre-squash tip) and AFTER pre_rebase_sha is armed (restore point = pre-squash HEAD).
        self._squashed_from = pre_rebase_sha  # armed BEFORE the ref moves; restore is a no-op while HEAD == pre
        squashed_head, squash_note, squash_fatal = self._squash_merge_range_for_replay(
            pre_base, branch, pre_rebase_sha,
        )
        self._last_squash_note = squash_note
        if not squashed_head:
            self._squashed_from = None
        if squash_fatal:
            return StackedRebaseResult(False, squash_note)

        rebase_proc = self._bounded(
            (*_NO_RERERE, "rebase", "--onto", master_sha, pre_base, branch),
            errors="replace")
        # Step 7.5: a linear multi-commit range whose replay conflicts is squashed and
        # replayed once, so the resolver sees one stop against the branch's final content.
        if (rebase_proc.returncode != 0 and not squashed_head and self.llm is not None
                and int(self._run("rev-list", "--count",
                                  f"{pre_base}..{pre_rebase_sha}").strip() or "0") >= 2):
            abort_proc = self._bounded(("rebase", "--abort"), errors="replace")
            if (abort_proc.returncode != 0
                    or self._run("rev-parse", "HEAD").strip() != pre_rebase_sha):
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"rebase --abort before linear squash failed: "
                                         f"{(abort_proc.stderr or abort_proc.stdout).strip()[:300]}")
            self._squashed_from = pre_rebase_sha  # armed BEFORE the ref moves
            squashed_head, squash_note, squash_fatal = self._squash_merge_range_for_replay(
                pre_base, branch, pre_rebase_sha, include_linear=True,
            )
            self._last_squash_note = squash_note
            if not squashed_head:
                self._squashed_from = None
            if squash_fatal:
                return StackedRebaseResult(False, squash_note)
            rebase_proc = self._bounded(
                (*_NO_RERERE, "rebase", "--onto", master_sha, pre_base, branch),
                errors="replace")
        auto_resolved_files: tuple = ()
        adaptations: tuple = ()
        if rebase_proc.returncode != 0:
            if self.llm is None:
                # No LLM: immediate decline. Snapshot the unmerged set FIRST; the abort
                # clears it.
                files = self._snapshot_conflicted_paths()
                self._bounded(("rebase", "--abort"), errors="replace")
                return StackedRebaseResult(
                    False,
                    f"--onto rebase still conflicts: {(rebase_proc.stderr or rebase_proc.stdout).strip()[:400]}"
                    f" | conflicted: {', '.join(files) or '?'}",
                )

            try:
                self._snapshot_conflicted_paths()
                # regen_lockfiles stays off: this path has no derived-file pre-patch rewrite (extend_pre_patch=None).
                inventory = inventory_conflicts(self._run)
                if inventory.unresolvable_reason:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=inventory.unresolvable_reason)

                resolve_result = resolve_all(self.llm, self._run, worktree=self.worktree,
                                            key=key, inventory=inventory,
                                            branch_stage=3, master_sha=master_sha,
                                            pre_sha=pre_rebase_sha,
                                            replay_base=pre_base if pre_base != master_sha else "",
                                            decline_retry_model=DECLINE_RETRY_MODEL,
                                            max_decline_retries=MAX_DECLINE_RETRIES - self._decline_retries_used)
                self._decline_retries_used += resolve_result.decline_retries
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                env = {**os.environ, "GIT_EDITOR": "true"}
                cont_proc = self._bounded((*_NO_RERERE, "rebase", "--continue"),
                                          errors="replace", env=env)
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
                adaptations = resolve_result.adaptations
            except HarnessError:
                raise
            except Exception as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"unexpected error during auto-resolve: {exc}")

        # Step 8: TREE-EQUIVALENT proof — gate is conjunctive (stdout contains
        # TREE-EQUIVALENT AND rc == 0), because a diverged tree exits 0 with TREE DIVERGED
        proof_ok, proof_out, adapted = self._prove_with_adaptations(
            lostwork_path, key,
            resolved_files=auto_resolved_files, adaptations=adaptations,
            pre_rebase_sha=pre_rebase_sha, master_sha=master_sha,
        )
        if not proof_ok:
            if not auto_resolved_files:
                return StackedRebaseResult(False, proof_out)
            # Step 8.5: one Opus rung fed the strict proof's full LOST list. proof_out is
            # the 400-char reason string when the adaptation path rejected; re-run strictly.
            from ..conflict_ladder import ladder_failure_reason
            _strict_ok, first_proof = self._run_proof(lostwork_path, key)
            adapt_trail = tuple(
                f"adaptation rejected: {p}" for p in proof_out.split(" | adaptation rejected: ", 1)[1:])
            try:
                outcome = self._run_stacked_lost_retry(
                    key=key, master_sha=master_sha, pre_rebase_sha=pre_rebase_sha,
                    lostwork_path=lostwork_path, conflicted=tuple(auto_resolved_files),
                    proof_out=first_proof)
            except HarnessError as exc:
                self.last_conflict_rung = "exhausted"
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=ladder_failure_reason(
                                      first_proof,
                                      adapt_trail + (f"ladder error: {exc.kind}: {exc.detail}",)))
            if not outcome.rung:
                self.last_conflict_rung = "exhausted"
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=ladder_failure_reason(first_proof, adapt_trail + tuple(outcome.trail)))
            self.last_conflict_rung = outcome.rung
            proof_out = outcome.proof_out
            auto_resolved_files = tuple(sorted(set(auto_resolved_files) | set(outcome.resolved_files)))
            adapted = ()  # the rung re-proved strictly; no adaptation relaxation applies

        # Step 9: manifest + notes + flag + reviewer via _record_resolution
        try:
            manifest_path, notes_path = self._record_resolution(
                key, branch, master_sha, auto_resolved_files,
                collapse_warn=collapse_warn, proof_out=proof_out, base_sha=ibl_base,
                adapted_lines=adapted,
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

        # A marker that survived the reconcile above describes content this run's own collapse
        # and replay just rewrote. Its recorded SHA is gone from history now, so the next
        # run would drop the hold with no CONFLICT-REVIEW=CLEAN verdict. Re-bind it to the
        # new HEAD. (_record_resolution already did so when a model resolved files.) Only a
        # SHA-bound "carried" marker is re-bound; an unknown one (malformed, legacy empty,
        # git error) stays untouched so appending a SHA cannot turn it into a droppable one.
        from ..armable import FLAG_CARRIED, record_conflict_resolution
        if not auto_resolved_files and self.last_conflict_flag_state == FLAG_CARRIED:
            record_conflict_resolution(branch, self._run("rev-parse", "HEAD").strip())

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
            adapted_lines=adapted,
        )

    def capture_lostwork_pre(self, key: str) -> bool:
        """Pre-side capture for lostwork.sh. Must run BEFORE fetch/rebase."""
        pre = self._run_bytes("diff", self._merge_base("origin/master"))
        if not pre.strip():
            return False
        Path(lostwork_pre_path(key)).write_bytes(pre)
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

    def _remote_tip_is_head(self, remote: str, branch: str) -> tuple[bool, None]:
        """netretry landed check for push: True when refs/heads/<branch> on the
        remote already equals local HEAD. A failed read raises, so netretry stops
        and re-raises the push's original error."""
        rc, out = self._run_out("ls-remote", remote, f"refs/heads/{branch}")
        if rc != 0:
            raise HarnessError("push-failed", f"landed check: ls-remote failed: {out[:200]}")
        tip = out.split()[0] if out.strip() else ""
        return (bool(tip) and tip == self.head(), None)

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
            def _lease_read() -> str:
                rc, out = self._run_out("ls-remote", remote, f"refs/heads/{branch}")
                if rc != 0:
                    raise HarnessError("push-failed",
                                       f"lease read failed for {remote}/{branch}: {out[:400]}",
                                       output=out)
                return out
            out = netretry.call(_lease_read, label=f"git ls-remote {remote} (lease)")
            if out.strip():
                sha = out.strip().split()[0]
                raise HarnessError("push-failed",
                                   f"stale-lease: {remote} holds refs/heads/{branch} ({sha}) "
                                   f"but this worktree has no refs/remotes/{remote}/{branch}")
            lease = _ZERO_SHA

        def _push_once() -> None:
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

        netretry.call(_push_once, label=f"git push {remote} {branch}",
                      landed=lambda: self._remote_tip_is_head(remote, branch))

    def push_ff(self) -> str:
        if not self.push_remote:
            raise HarnessError("push-disabled",
                               "no isolated push remote configured; live push requires install approval")
        branch = self.branch()
        if branch == "HEAD":
            raise HarnessError("push-failed", "detached HEAD: refusing to push without a branch name")
        remote = self.push_remote

        def _push_once() -> None:
            rc, out = self._run_out("push", remote, f"HEAD:refs/heads/{branch}")
            if rc != 0:
                if any(m in out for m in _LOCAL_GATE_MARKERS):
                    raise HarnessError("local-gate", f"git push: {out[:600]}",
                                       cmd=f"git push {remote} HEAD:refs/heads/{branch}", output=out)
                raise HarnessError("push-failed", out[:600],
                                   cmd=f"git push {remote} HEAD:refs/heads/{branch}", output=out)

        netretry.call(_push_once, label=f"git push {remote} {branch} (ff)",
                      landed=lambda: self._remote_tip_is_head(remote, branch))
        return self.head()


class ReplayGit:
    """Point-in-time state reconstructed from a historical trace fixture."""

    def __init__(self, fixture: dict):
        self.fx = fixture
        self.commit_messages: list[str] = []
        self.pushes = 0
        self.ff_pushes = 0
        self.meta_checks_calls: list[tuple[str, int]] = []
        self.drops: list[str] = []
        self.extra_dirty: list[str] = []      # paths a test wrote on disk (conformance fixer)
        self.restored: list[list[str]] = []   # restore_paths() calls, in order
        self.unstaged: list[list[str]] = []   # unstage_paths() calls, in order

    def branch(self) -> str:
        return self.fx.get("slug", "unknown-branch")

    def stage_all(self) -> None:
        return

    def is_dirty(self) -> bool:
        # the Phase 2 commit leaves the replay tree clean, as it does live
        return (bool(self.fx.get("worktree_diff")) and not self.commit_messages) \
            or bool(self.extra_dirty)

    def dirty_paths(self) -> list[str]:
        """The fixture's uncommitted paths (until a commit cleans them, as is_dirty() does)
        plus whatever a test appended to `extra_dirty`."""
        out: list[str] = []
        if not self.commit_messages:
            for m in re.finditer(r"^diff --git a/\S+ b/(\S+)$",
                                 self.fx.get("worktree_diff") or "", re.MULTILINE):
                if m.group(1) not in out:
                    out.append(m.group(1))
        for p in self.extra_dirty:
            if p not in out:
                out.append(p)
        return out

    def restore_paths(self, paths) -> None:
        self.restored.append(list(paths))

    def unstage_paths(self, paths) -> None:
        self.unstaged.append(list(paths))

    def diff_vs_base(self, base: str = "origin/master") -> str:
        return self.fx.get("diff") or self.fx.get("worktree_diff") or ""

    def own_change_diff(self, base: str = "origin/master") -> str:
        return self.fx.get("own_change_diff", "")

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
        self.extra_dirty = []   # a commit sweeps the test-written paths, as `git add -A` does live
        return "replay-sha-" + self.fx.get("slug", "x")[:12]

    def drop_commit(self, pre: str) -> str:
        """Record the undo; replay has no tree to move. Returns "" like a successful reset."""
        self.drops.append(pre)
        return ""

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
