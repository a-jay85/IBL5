"""Git adapter. LiveGit runs real git against a worktree (reads + local commits;
push only to an explicitly provided remote, e.g. a local bare repo in isolated
mode). ReplayGit serves recorded point-in-time state."""
from __future__ import annotations

import os
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional

from ..state import HarnessError

# Local gate denials (bin/pre-commit-hook, bin/pre-push-adr-hook) are deterministic:
# re-running the FULL /post-plan skill hits the identical hook and cannot clear it
# without a human writing an ADR, refreshing a doc, or trimming a rule file. A distinct
# kind lets exit_code_for() emit the fail-closed 3 sentinel, so bin/post-plan-now skips
# the ~1M-token skill fallback instead of burning it on a guaranteed re-denial.
# Markers are the hooks' own output: bin/pre-push-adr-hook's prefix and its base-check
# line, the two guidance lines bin/pre-commit-hook echoes, and
# bin/check-rules-byte-budget's summary line.
_ZERO_SHA = "0" * 40

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

    def _run_out(self, *args: str) -> tuple[int, str]:
        proc = subprocess.run(["git", "-C", self.worktree, *args],
                              capture_output=True, text=True, errors="replace")
        return proc.returncode, f"{proc.stdout}\n{proc.stderr}".strip()

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
        proof_proc = subprocess.run(
            ["bash", str(lostwork_path), key],
            capture_output=True, text=True, errors="replace",
            cwd=self.worktree,
        )
        proof_out = proof_proc.stdout
        if not ("TREE-EQUIVALENT" in proof_out and proof_proc.returncode == 0):
            return False, f"tree proof failed: {proof_out.strip()[:400]}"
        return True, proof_out

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
            Path(autoresolved_path).write_text("\n".join(resolved_files) + "\n")

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
        """Repo pre-push policy (pre-push-adr-hook) rejects branches not rebased
        onto origin/master. Conflict → attempt auto-resolution; if that fails or is
        not applicable, abort, restore the tree, and raise HarnessError.
        Fail closed: exit_code_for() maps this to exit 3, which bin/post-plan-now
        refuses to escalate to the skill fallback."""
        from ..conflict import (
            abort_and_restore, assert_text_only, inventory_conflicts,
            purge_verdict_artifacts, resolve_all,
        )

        branch = self.branch()
        key = branch.replace("/", "-")
        self.last_conflict_files = ()
        master_sha = self._run("rev-parse", base).strip()

        pre_rebase_sha = self._run("rev-parse", "HEAD").strip()
        pre_patch = self._run("diff", f"{master_sha}...HEAD")
        if pre_patch.strip():
            Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_text(pre_patch)

        purge_verdict_artifacts(key)

        proc = subprocess.run(["git", "-C", self.worktree, "rebase", base],
                              capture_output=True, text=True, errors="replace")
        if proc.returncode != 0:
            conflict_detail = (proc.stderr or proc.stdout).strip()[:400]

            if self.llm is None or not pre_patch.strip():
                # No LLM or no pre-patch: immediate abort-and-restore. Snapshot the
                # unmerged set FIRST; the abort clears it.
                files = self._snapshot_conflicted_paths()
                subprocess.run(["git", "-C", self.worktree, "rebase", "--abort"],
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
                                            key=key, inventory=inventory)
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                env = {**os.environ, "GIT_EDITOR": "true"}
                cont_proc = subprocess.run(
                    ["git", "-C", self.worktree, "rebase", "--continue"],
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
                    "grep", "-n", "-E", "^(<{7}|={7}|>{7})", "HEAD", "--", ".")
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

                proof_ok, proof_out = self._prove_tree_equivalent(lostwork_path, key)
                if not proof_ok:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=proof_out)

                try:
                    manifest_path, notes_path = self._record_resolution(
                        key, branch, master_sha, resolve_result.resolved_files,
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
                    resolved_files=resolve_result.resolved_files,
                )
            except HarnessError:
                raise
            except Exception as exc:
                abort_and_restore(self._run, worktree=self.worktree,
                                  pre_rebase_sha=pre_rebase_sha,
                                  reason=f"unexpected error during auto-resolve: {exc}")

    def autoresolve_stacked_rebase(self) -> "StackedRebaseResult":
        """Resolve a squash-trap stacked-branch conflict via `git rebase --onto`.
        Returns a StackedRebaseResult; never raises on a decline — the caller owns
        the single decision about exit 3."""
        branch = self.branch()
        key = branch.replace("/", "-")
        self.last_conflict_files = ()

        # Step 2: iblBase (early return before any network/expensive call)
        ibl_base = self.branch_base()
        if ibl_base is None:
            return StackedRebaseResult(False, "no branch.<name>.iblBase recorded; not a known stacked branch")

        # Step 3: refuse dirty tree
        if self.is_dirty():
            return StackedRebaseResult(False, "worktree dirty at resolution entry")

        # Step 4: pre-side capture — must happen before touching history
        pre_patch = self._run("diff", f"{ibl_base}...HEAD")
        if not pre_patch.strip():
            return StackedRebaseResult(False, "pre-rebase diff vs iblBase is empty")
        Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_text(pre_patch)

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
        purge_verdict_artifacts(key)

        rebase_proc = subprocess.run(
            ["git", "-C", self.worktree, "rebase", "--onto", master_sha, ibl_base, branch],
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
                                            key=key, inventory=inventory)
                if not resolve_result.success:
                    abort_and_restore(self._run, worktree=self.worktree,
                                      pre_rebase_sha=pre_rebase_sha,
                                      reason=resolve_result.reason)

                env = {**os.environ, "GIT_EDITOR": "true"}
                cont_proc = subprocess.run(
                    ["git", "-C", self.worktree, "rebase", "--continue"],
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
                    "grep", "-n", "-E", "^(<{7}|={7}|>{7})", "HEAD", "--", ".")
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
        pre = self.diff_vs_base("origin/master")
        if not pre.strip():
            return False
        Path(f"/tmp/pr-ready-diff-pre-{key}.patch").write_text(pre)
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
                raise HarnessError("local-gate", f"git push: {out[:600]}")
            raise HarnessError("push-failed", out[:600])


class ReplayGit:
    """Point-in-time state reconstructed from a historical trace fixture."""

    def __init__(self, fixture: dict):
        self.fx = fixture
        self.commit_messages: list[str] = []
        self.pushes = 0
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
