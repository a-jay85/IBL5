"""I/O shell for the gate backtest: `gh` fetch, scratch worktree, and the replay driver.

All subprocess and network calls live here so `gate_backtest.py` stays pure. Every
subprocess call is an argv list with no `shell=True`; nothing from `gh` JSON (titles, branch
names) is ever shell-parsed.
"""
from __future__ import annotations

import fnmatch
import hashlib
import json
import os
import re
import shutil
import signal
import subprocess
import sys
import tempfile
import time
from collections import namedtuple
from dataclasses import dataclass
from datetime import datetime, timezone

from harness.gate_backtest import (GATE_BACKTEST_BEGIN, GATE_BACKTEST_END, HEAD_OID_FIELD,
                                   GateChange, HistoricalPR, ReplayResult, Verdict, classify_exit,
                                   changes_from_unified_diff, classify_truth, compute_verdict,
                                   detect_gate_changes, flag_lines, parse_head_oid,
                                   expand_argv, expand_env, expand_stdin, is_check_script,
                                   render_gate_backtest, resolve_spec)

REPO_SLUG = "a-jay85/IBL5"
PER_REPLAY_TIMEOUT = 60
TOTAL_CAP = 1200
HISTORY_LIMIT = 30
FETCH_CAP = 250  # merged PRs fetched; the newest HISTORY_LIMIT settled ones are replayed

# Hooks must never run inside the scratch worktree: a post-checkout hook would be repo code
# executing against historical trees.
_GIT_QUIET = ["-c", "core.hooksPath=/dev/null"]


def make_git(repo: str):
    """Return git(argv) -> stdout, run inside `repo`. Raises CalledProcessError on failure."""
    def git(argv: list[str]) -> str:
        proc = subprocess.run(["git", "-C", repo, *argv], capture_output=True, text=True,
                              check=True)
        return proc.stdout
    return git


def live_gh_json(argv: list[str]):
    proc = subprocess.run(["gh", *argv], capture_output=True, text=True, check=True)
    return json.loads(proc.stdout)


def _parse_merged_at(value: str) -> datetime:
    parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc)


def fetch_history(gh_json, git, limit: int = HISTORY_LIMIT, exclude: int | None = None,
                  bodies_out: dict[int, str] | None = None) -> list[HistoricalPR]:
    """The most recent merged PRs, newest first. Metadata from `gh`, files and parents from git.

    The candidate's own number (`exclude`) is dropped. A merge SHA missing from the local
    object store gets parent_count 0, and the replay records `skipped: sha-missing`.
    """
    raw = gh_json(["pr", "list", "--repo", REPO_SLUG, "--state", "merged", "--base", "master",
                   "--limit", str(limit), "--json",
                   f"number,title,mergeCommit,mergedAt,headRefName,body,{HEAD_OID_FIELD}"])
    out: list[HistoricalPR] = []
    for item in raw[:limit]:
        number = int(item["number"])
        if exclude is not None and number == exclude:
            continue
        oid = ((item.get("mergeCommit") or {}).get("oid")) or ""
        parent_count = 0
        files: tuple[str, ...] = ()
        if oid:
            try:
                parents = git(["rev-list", "--parents", "-n", "1", oid]).split()
                parent_count = max(len(parents) - 1, 0)
                if parent_count == 1:
                    names = git(["diff-tree", "--no-commit-id", "--name-only", "-r",
                                 oid + "^", oid])
                    files = tuple(n for n in names.splitlines() if n)
            except subprocess.CalledProcessError:
                parent_count = 0
        if bodies_out is not None:
            bodies_out[number] = item.get("body") or ""
        out.append(HistoricalPR(number, item.get("title") or "", oid, parent_count,
                                _parse_merged_at(item["mergedAt"]),
                                item.get("headRefName") or "", files,
                                head_oid=parse_head_oid(item.get(HEAD_OID_FIELD))))
    out.sort(key=lambda p: p.merged_at, reverse=True)
    return out


_UNKNOWN_PATH = "?"   # a chunk whose path we cannot read; never matches "safe"
_CHUNK_START = b"diff --git "


def _header_path(raw: str, prefix: str) -> str | None:
    """One path from a chunk header field: None for /dev/null, _UNKNOWN_PATH when quoted."""
    raw = raw.rstrip("\r").rstrip("\t")
    if raw == "/dev/null":
        return None
    if not raw or raw.startswith('"'):
        return _UNKNOWN_PATH
    if prefix:
        if not raw.startswith(prefix):
            return _UNKNOWN_PATH
        raw = raw[len(prefix):]
    return raw or _UNKNOWN_PATH


def _chunk_paths(chunk: bytes) -> list[str]:
    """Paths one `diff --git` chunk touches, read from its extended header only."""
    lines = chunk.decode("utf-8", errors="replace").split("\n")
    found: list[str | None] = []
    for line in lines[1:]:
        if line.startswith("@@") or line.startswith("GIT binary patch"):
            break
        if line.startswith("--- "):
            found.append(_header_path(line[4:], "a/"))
        elif line.startswith("+++ "):
            found.append(_header_path(line[4:], "b/"))
        else:
            for key in ("rename from ", "rename to ", "copy from ", "copy to "):
                if line.startswith(key):
                    found.append(_header_path(line[len(key):], ""))
                    break
    paths: list[str] = []
    for p in found:
        if p is not None and p not in paths:
            paths.append(p)
    if paths:
        return paths
    if found:
        return [_UNKNOWN_PATH]
    # Binary or mode-only chunk: only the `diff --git a/X b/X` line names the file.
    rest = lines[0][len("diff --git "):].rstrip("\r")
    n = (len(rest) - 5) // 2
    name = rest[2:2 + n] if n > 0 else ""
    if name and not rest.startswith('"') and rest == f"a/{name} b/{name}":
        return [name]
    return [_UNKNOWN_PATH]


def _split_patch(patch: bytes) -> list[tuple[bytes, list[str]]]:
    """Split a `git diff --binary` patch into per-file chunks, each with the paths it touches.

    A chunk starts at a column-0 `diff --git ` line. Paths come from `--- a/`, `+++ b/`,
    `rename from/to`, and `copy from/to` lines (`/dev/null` dropped, a trailing tab stripped).
    A chunk with none of those (binary, mode-only) falls back to `diff --git a/X b/X` when
    both halves are equal. A quoted path, or a chunk with no readable path, yields
    _UNKNOWN_PATH so the fail-closed check can never treat it as unread.
    """
    lines = patch.split(b"\n")
    starts = [i for i, line in enumerate(lines) if line.startswith(_CHUNK_START)]
    if not starts:
        return []
    chunks: list[tuple[bytes, list[str]]] = []
    for k, begin in enumerate(starts):
        end = starts[k + 1] if k + 1 < len(starts) else len(lines)
        body = b"\n".join(lines[begin:end])
        if end < len(lines):
            body += b"\n"
        chunks.append((body, _chunk_paths(body)))
    return chunks


def partial_fix_allowed(reads: tuple[str, ...], skipped: list[str]) -> bool:
    """True only when reads is declared and no skipped path is unknown or matches a reads glob."""
    if not reads:
        return False
    return not any(p == _UNKNOWN_PATH or any(fnmatch.fnmatchcase(p, g) for g in reads)
                   for p in skipped)


def ensure_pr_heads(repo: str, prs, timeout: float = 60.0) -> int:
    """Best-effort fetch of pre-squash PR heads missing from the local object store.

    One batched `git fetch` of refs/pull/<N>/head for every PR whose head_oid is
    unknown locally. Any failure or timeout is swallowed: a missing head leaves
    CHECK_DOCS_EDIT_DATE_REF pointing at an absent commit, which check-docs
    ignores, keeping today's strict base..HEAD read. Returns refspecs requested.
    """
    missing = []
    for pr in prs:
        if not pr.head_oid:
            continue
        probe = subprocess.run(["git", "-C", repo, "cat-file", "-e", f"{pr.head_oid}^{{commit}}"],
                               capture_output=True)
        if probe.returncode != 0:
            missing.append(f"refs/pull/{pr.number}/head")
    if not missing:
        return 0
    try:
        subprocess.run(["git", *_GIT_QUIET, "-C", repo, "fetch", "--no-tags", "--quiet", "origin",
                        *missing], capture_output=True, timeout=timeout, check=False)
    except (subprocess.TimeoutExpired, OSError):
        pass
    return len(missing)


class ScratchTree:
    """One detached worktree in system temp, reused across SHAs and removed on every exit."""

    def __init__(self, repo: str, first_sha: str):
        self.repo = repo
        self.first_sha = first_sha
        self.dir = ""

    @staticmethod
    def _git(where: str, argv: list[str]) -> subprocess.CompletedProcess:
        return subprocess.run(["git", *_GIT_QUIET, "-C", where, *argv],
                              capture_output=True, text=True, check=True)

    def __enter__(self) -> "ScratchTree":
        self.dir = tempfile.mkdtemp(prefix="gate-backtest-")
        added = False
        try:
            self._git(self.repo, ["worktree", "add", "--detach", self.dir, self.first_sha])
            added = True
        finally:
            if not added:
                self._cleanup()
        return self

    def checkout(self, sha: str) -> None:
        self._git(self.dir, ["checkout", "--detach", "--force", "-q", sha])
        self._git(self.dir, ["clean", "-fdq"])

    def overlay(self, files: dict[str, tuple[bytes, int]]) -> None:
        """Write each candidate gate file into the tree, executable when its git mode is 100755."""
        for rel, (data, mode) in files.items():
            dest = os.path.join(self.dir, rel)
            os.makedirs(os.path.dirname(dest), exist_ok=True)
            with open(dest, "wb") as fh:
                fh.write(data)
            os.chmod(dest, 0o755 if mode == 0o100755 or mode & 0o111 else 0o644)

    def apply_patch(self, patch: bytes) -> bool:
        """Apply a binary diff to the tree. git apply is all-or-nothing, so False leaves it untouched."""
        proc = subprocess.run(["git", *_GIT_QUIET, "-C", self.dir, "apply", "--binary",
                               "--whitespace=nowarn", "-"], input=patch, capture_output=True)
        return proc.returncode == 0

    def apply_patch_per_file(self, patch: bytes) -> tuple[list[str], list[str]]:
        """Apply the whole patch; if that fails, apply each file's chunk alone.

        Returns (applied paths, skipped paths). A skipped chunk leaves its files untouched,
        because each git apply call is all-or-nothing.
        """
        chunks = _split_patch(patch)
        every = [p for _, paths in chunks for p in paths] or [_UNKNOWN_PATH]
        if self.apply_patch(patch):
            return every, []
        if not chunks:
            return [], [_UNKNOWN_PATH]
        applied: list[str] = []
        skipped: list[str] = []
        for chunk, paths in chunks:
            (applied if self.apply_patch(chunk) else skipped).extend(paths or [_UNKNOWN_PATH])
        return applied, skipped

    def remove(self, files) -> None:
        """Delete each path from the tree. A path that is already absent is fine."""
        for rel in files:
            try:
                os.remove(os.path.join(self.dir, rel))
            except FileNotFoundError:
                pass

    def _cleanup(self) -> None:
        directory, self.dir = self.dir, ""
        if not directory:
            return
        for step in (
            lambda: subprocess.run(["git", *_GIT_QUIET, "-C", self.repo, "worktree", "remove",
                                    "--force", directory], capture_output=True, text=True),
            lambda: shutil.rmtree(directory, ignore_errors=True),
            lambda: subprocess.run(["git", *_GIT_QUIET, "-C", self.repo, "worktree", "prune"],
                                   capture_output=True, text=True),
        ):
            try:
                step()
            except Exception:
                pass

    def __exit__(self, *exc) -> None:
        self._cleanup()


def _safe_head_ref(head_ref: str) -> bool:
    return bool(head_ref) and not head_ref.startswith("/") and ".." not in head_ref.split("/") \
        and "\x00" not in head_ref


def resolve_plan(plans_dir: str, head_ref: str) -> tuple[str | None, str]:
    """(path, "") when found; (None, reason) otherwise. The path stays under plans_dir."""
    if not _safe_head_ref(head_ref):
        return None, "bad-head-ref"
    if not plans_dir:
        return None, "no-plan"
    root = os.path.realpath(plans_dir)
    for candidate in (os.path.join(plans_dir, head_ref + ".md"),
                      os.path.join(plans_dir, "_archive", head_ref + ".md")):
        real = os.path.realpath(candidate)
        if os.path.commonpath([root, real]) != root:
            return None, "bad-head-ref"
        if os.path.isfile(real):
            return real, ""
    return None, "no-plan"


def select_plan_sample(fetched: list[HistoricalPR], truth: dict, plans_dir: str,
                       limit: int) -> tuple[list[HistoricalPR], int]:
    """The newest `limit` settled PRs that have a plan file, newest first, and how many settled
    PRs were walked to find them. Only plan-needing gates replay this sample."""
    sample: list[HistoricalPR] = []
    depth = 0
    for pr in fetched:
        if truth[pr.number].state == "unsettled":
            continue
        if len(sample) >= limit:
            break
        depth += 1
        if resolve_plan(plans_dir, pr.head_ref)[0] is not None:
            sample.append(pr)
    return sample, depth


def run_backtest(repo: str, candidate_head: str, gates: list[GateChange],
                 history: list[HistoricalPR], overlay_files: dict[str, tuple[bytes, int]],
                 plans_dir: str, *, per_replay_timeout: int = PER_REPLAY_TIMEOUT,
                 total_cap: int = TOTAL_CAP, clock=time.monotonic,
                 bodies: dict[int, str] | None = None,
                 base_overlay: dict[str, tuple[bytes, int] | None] | None = None,
                 fix_patch: bytes | None = None,
                 plan_history: list[HistoricalPR] | None = None
                 ) -> list[ReplayResult]:
    """Replay every replayable gate against every historical PR's tree, newest first.

    With `base_overlay` (the same keys as `overlay_files`, `None` marking a path absent at the
    base ref), a head `flag` is followed by one replay of the base-ref gate on the same checkout.
    The result rides on the head row as `base_outcome`/`base_detail` and never changes `outcome`.
    With `fix_patch`, a head `flag` the base gate did not also flag is followed by one head-gate
    replay on the same checkout with the candidate's own non-gate diff applied; the result rides
    on `fix_outcome`/`fix_detail` and never changes `outcome`.
    With `plan_history`, gates that need a plan replay those PRs after every other gate has
    replayed `history`; gates that need no plan never see `plan_history`.
    """
    replayable = [g for g in gates if g.state == "replayable" and g.spec is not None]
    results: list[ReplayResult] = []
    if plan_history is None:
        schedule = [(pr, replayable) for pr in history]
    else:
        plain = [g for g in replayable if not g.spec.needs_plan]
        planned = [g for g in replayable if g.spec.needs_plan]
        schedule = ([(pr, plain) for pr in history if plain]
                    + [(pr, planned) for pr in plan_history if planned])
    if not schedule or not replayable:
        return results
    bodies = bodies or {}
    start = clock()
    base_plan: dict[str, tuple[str, object]] = {}
    if base_overlay is not None:
        for g in replayable:
            if g.path not in base_overlay:
                base_plan[g.path] = ("run", g.spec)
            elif base_overlay[g.path] is None:
                base_plan[g.path] = ("absent", None)
            else:
                state, base_spec, _ = resolve_spec(
                    g.path, base_overlay[g.path][0].decode("utf-8", "replace"))
                if state == "replayable" and base_spec is not None:
                    base_plan[g.path] = ("run", base_spec)
                else:
                    base_plan[g.path] = ("no-spec", None)
    first = next((p for p, _ in schedule if p.parent_count == 1), None)

    def skip(pr: HistoricalPR, gate: GateChange, reason: str) -> None:
        results.append(ReplayResult(pr.number, gate.path, "skipped", reason))

    if first is None:
        for pr, pr_gates in schedule:
            for gate in pr_gates:
                skip(pr, gate, "non-squash" if pr.parent_count > 1 else "sha-missing")
        return results

    git = make_git(repo)
    ensure_pr_heads(repo, [pr for pr, _ in schedule if pr.parent_count == 1])

    def execute(spec, ctx: dict, gate_path: str) -> tuple[str, str, tuple[str, ...]]:
        """Run one gate replay in ctx["tree"] and return (outcome, detail, flag_lines)."""
        env = dict(os.environ)
        env.update(expand_env(spec, ctx))
        stdin_path = expand_stdin(spec, ctx)
        stdin_fh = None
        if stdin_path:
            try:
                stdin_fh = open(stdin_path, "rb")
            except OSError as exc:
                return "error", re.sub(r"\s+", " ", f"stdin: {exc}")[:160], ()
        try:
            proc = subprocess.run(
                [os.path.join(ctx["tree"], gate_path), *expand_argv(spec, ctx)],
                cwd=ctx["tree"], env=env,
                stdin=stdin_fh if stdin_fh is not None else subprocess.DEVNULL,
                capture_output=True, text=True, timeout=per_replay_timeout)
        except subprocess.TimeoutExpired:
            return "timeout", f"{per_replay_timeout}s", ()
        except OSError as exc:
            return "error", re.sub(r"\s+", " ", str(exc))[:160], ()
        finally:
            if stdin_fh is not None:
                stdin_fh.close()
        outcome, detail = classify_exit(spec, proc.returncode, proc.stdout)
        return outcome, detail, (flag_lines(proc.stdout) if outcome == "flag" else ())

    work = tempfile.mkdtemp(prefix="gate-backtest-files-")
    capped = False
    try:
        with ScratchTree(repo, first.merge_sha) as scratch:
            for pr, pr_gates in schedule:
                checked_out = False
                for gate in pr_gates:
                    if pr.parent_count > 1:
                        skip(pr, gate, "non-squash")
                        continue
                    if pr.parent_count != 1:
                        skip(pr, gate, "sha-missing")
                        continue
                    if capped or clock() - start >= total_cap:
                        capped = True
                        results.append(ReplayResult(pr.number, gate.path, "timeout", "total-cap"))
                        continue
                    spec = gate.spec
                    plan_file = ""
                    if spec.needs_plan:
                        found, why = resolve_plan(plans_dir, pr.head_ref)
                        if found is None:
                            skip(pr, gate, why)
                            continue
                        plan_file = found
                    if not checked_out:
                        scratch.checkout(pr.merge_sha)
                        scratch.overlay(overlay_files)
                        checked_out = True
                    base = git(["rev-parse", pr.merge_sha + "^"]).strip()
                    diff_file = os.path.join(work, f"{pr.number}.diff")
                    with open(diff_file, "w", encoding="utf-8", errors="replace") as fh:
                        fh.write(git(["diff", base, pr.merge_sha]))
                    body_file = os.path.join(work, f"{pr.number}.body")
                    with open(body_file, "w", encoding="utf-8") as fh:
                        fh.write(bodies.get(pr.number, ""))
                    ctx = {"base": base, "head": pr.merge_sha, "tree": scratch.dir,
                           "diff_file": diff_file, "plan_file": plan_file, "body_file": body_file,
                           "pr_head": pr.head_oid}
                    outcome, detail, head_lines = execute(spec, ctx, gate.path)
                    base_outcome = base_detail = ""
                    base_lines: tuple[str, ...] = ()
                    if outcome == "flag" and base_overlay is not None:
                        kind, base_spec = base_plan[gate.path]
                        if kind == "absent":
                            base_outcome = "absent"
                        elif kind == "no-spec":
                            base_outcome, base_detail = "error", "base-no-spec"
                        elif clock() - start >= total_cap:
                            base_outcome, base_detail = "timeout", "total-cap"
                        else:
                            try:
                                scratch.overlay({k: v for k, v in base_overlay.items()
                                                 if v is not None})
                                scratch.remove([k for k, v in base_overlay.items() if v is None])
                                base_outcome, base_detail, base_lines = execute(
                                    base_spec, ctx, gate.path)
                            except OSError as exc:
                                base_outcome = "error"
                                base_detail = re.sub(r"\s+", " ", f"base-overlay: {exc}")[:160]
                            finally:
                                scratch.overlay(overlay_files)
                    fix_outcome = fix_detail = ""
                    fix_applied: list[str] = []
                    fix_skipped: list[str] = []
                    if outcome == "flag" and base_outcome != "flag" and fix_patch is not None:
                        if clock() - start >= total_cap:
                            fix_outcome, fix_detail = "timeout", "total-cap"
                        else:
                            try:
                                fix_applied, fix_skipped = scratch.apply_patch_per_file(fix_patch)
                                if fix_skipped and not fix_applied:
                                    fix_outcome = "no-apply"
                                elif fix_skipped and not partial_fix_allowed(spec.reads, fix_skipped):
                                    fix_outcome = "partial-no-reads" if not spec.reads else "partial-gate-read"
                                else:
                                    scratch.overlay(overlay_files)
                                    fix_outcome, fix_detail, _ = execute(spec, ctx, gate.path)
                            except OSError as exc:
                                fix_outcome = "error"
                                fix_detail = re.sub(r"\s+", " ", f"fix-overlay: {exc}")[:160]
                            finally:
                                scratch.checkout(pr.merge_sha)
                                scratch.overlay(overlay_files)
                    results.append(ReplayResult(pr.number, gate.path, outcome, detail,
                                                base_outcome, base_detail,
                                                fix_outcome, fix_detail,
                                                fix_applied=tuple(fix_applied),
                                                fix_skipped=tuple(fix_skipped),
                                                flag_lines=head_lines,
                                                base_flag_lines=base_lines))
    finally:
        shutil.rmtree(work, ignore_errors=True)
    return results


# --- Branch-level entry point and CLI --------------------------------------------------------

class GateReadError(Exception):
    """A forced `--gate=<path>@<rev>` could not be read."""


@dataclass
class BacktestOutcome:
    verdict: Verdict
    gates: list
    results: list | None
    truth: dict
    window: int
    block: str


def parse_name_status(text: str) -> list[tuple[str, str]]:
    """`git diff --name-status` lines to (status letter, path); a rename carries its new path."""
    out: list[tuple[str, str]] = []
    for line in text.splitlines():
        parts = line.split("\t")
        if len(parts) >= 2 and parts[0]:
            out.append((parts[0][0], parts[-1]))
    return out


def _git_bytes(repo: str, argv: list[str]) -> bytes:
    return subprocess.run(["git", "-C", repo, *argv], capture_output=True, check=True).stdout


def _file_mode(repo: str, rev: str, path: str) -> int:
    try:
        out = _git_bytes(repo, ["ls-tree", rev, "--", path]).decode()
        return int(out.split()[0], 8)
    except (subprocess.CalledProcessError, ValueError, IndexError):
        return 0o100644


def _base_overlay(repo: str, base: str, head: str,
                  paths) -> dict[str, tuple[bytes, int] | None] | None:
    """Each path's bytes at merge-base(base, head); None marks a path absent there.

    An unresolvable base returns None, so no base replay runs and every head flag counts.
    """
    try:
        rev = make_git(repo)(["merge-base", base, head]).strip()
    except subprocess.CalledProcessError:
        return None
    if not rev:
        return None
    out: dict[str, tuple[bytes, int] | None] = {}
    for path in paths:
        try:
            out[path] = (_git_bytes(repo, ["show", f"{rev}:{path}"]),
                         _file_mode(repo, rev, path))
        except subprocess.CalledProcessError:
            out[path] = None
    return out


def _fix_patch(repo: str, base: str, head: str, exclude) -> bytes | None:
    """The candidate's diff from merge-base(base, head) to head minus gate paths and bin/test-*.

    Empty or unreadable returns None, so no fix-overlay rerun runs and every head flag counts.
    """
    try:
        rev = make_git(repo)(["merge-base", base, head]).strip()
        if not rev:
            return None
        argv = ["diff", "--binary", rev, head, "--", ".", ":(exclude,glob)bin/test-*"]
        argv += [f":(exclude){p}" for p in sorted(exclude)]
        data = _git_bytes(repo, argv)
    except (subprocess.CalledProcessError, OSError):
        return None
    return data or None


def _detect(repo: str, head: str, changes: list[tuple[str, str]], *,
            read: bool = True) -> list[GateChange]:
    """The gates `changes` touch. `read=False` skips every `git show` (replay-mode runner)."""
    def read_candidate(path: str) -> str | None:
        if not read:
            return None
        try:
            return _git_bytes(repo, ["show", f"{head}:{path}"]).decode("utf-8", "replace")
        except (subprocess.CalledProcessError, OSError):
            return None

    check_sources: dict[str, str] = {}
    if read and any(p.startswith("bin/lib/") for _, p in changes):
        listing = make_git(repo)(["ls-tree", "-r", "--name-only", head, "--", "bin"]).splitlines()
        for p in listing:
            if is_check_script(p):
                text = read_candidate(p)
                if text is not None:
                    check_sources[p] = text
    return detect_gate_changes(changes, read_candidate, check_sources)


def backtest_changes(repo: str, head: str, changes: list[tuple[str, str]], *,
                     gh_json=live_gh_json, fetch: bool = True, plans_dir: str = "",
                     limit: int = HISTORY_LIMIT, per_replay_timeout: int = PER_REPLAY_TIMEOUT,
                     total_cap: int = TOTAL_CAP, now: datetime | None = None,
                     exclude_pr: int | None = None,
                     base: str = "origin/master",
                     forced_gate: tuple[str, str] | None = None) -> BacktestOutcome:
    """Detect the gates a change list touches and replay them against recent merged PRs.

    A change list with no gate returns NOT-APPLICABLE with no fetch, worktree, or `gh` call.
    Any failure after a replayable gate is found maps to UNKNOWN.
    """
    git = make_git(repo)
    overlay_files: dict[str, tuple[bytes, int]] = {}
    if forced_gate is not None:
        path, rev = forced_gate
        try:
            data = _git_bytes(repo, ["show", f"{rev}:{path}"])
        except subprocess.CalledProcessError:
            raise GateReadError(f"cannot read {path}@{rev}")
        state, spec, reason = resolve_spec(path, data.decode("utf-8", "replace"))
        kind = "check-script" if is_check_script(path) else "lib-gate"
        gates = [GateChange(path, kind, state, spec, reason)]
        overlay_files[path] = (data, 0o100755)
    else:
        changed = changes
        gates = _detect(repo, head, changed)
        if not gates:
            return BacktestOutcome(compute_verdict([], [], {}), [], [], {}, 0, "")
        for status, path in changed:
            if status != "D" and (path.startswith("bin/lib/") or is_check_script(path)):
                try:
                    overlay_files[path] = (_git_bytes(repo, ["show", f"{head}:{path}"]),
                                           _file_mode(repo, head, path))
                except subprocess.CalledProcessError:
                    pass

    results: list[ReplayResult] | None = []
    truth: dict = {}
    window = 0
    plan_history: list[HistoricalPR] | None = None
    plan_sample: tuple[int, int] | None = None
    if any(g.state == "replayable" for g in gates):
        try:
            if fetch:
                git(["fetch", "--quiet", "origin", "master"])
            bodies: dict[int, str] = {}
            # Fetch past `limit` so the replay window can skip the PRs younger than the repair
            # window: at this repo's merge pace the newest 30 PRs are all under 48h old, which
            # would leave no replay to count. Unsettled PRs stay in the fetch as repair evidence.
            fetched = fetch_history(gh_json, git, FETCH_CAP, exclude_pr, bodies)
            truth = classify_truth(fetched, now or datetime.now(timezone.utc))
            history = [p for p in fetched if truth[p.number].state != "unsettled"][:limit]
            window = len(history)
            if any(g.state == "replayable" and g.spec is not None and g.spec.needs_plan
                   for g in gates):
                plan_history, plan_depth = select_plan_sample(fetched, truth, plans_dir, limit)
                plan_sample = (len(plan_history), plan_depth)
            base_overlay = _base_overlay(repo, base, head, overlay_files)
            fix_patch = None if forced_gate is not None else _fix_patch(
                repo, base, head, overlay_files)
            results = run_backtest(repo, head, gates, history, overlay_files, plans_dir,
                                   per_replay_timeout=per_replay_timeout, total_cap=total_cap,
                                   bodies=bodies, base_overlay=base_overlay,
                                   fix_patch=fix_patch, plan_history=plan_history)
        except Exception:
            results = None
            truth = {}
    verdict = compute_verdict(gates, results, truth)
    block = render_gate_backtest(verdict, gates, results or [], truth, window, plan_sample)
    return BacktestOutcome(verdict, gates, results, truth, window, block)


def backtest_from_refs(repo: str, base: str, head: str, **kw) -> BacktestOutcome:
    """CLI entry: the change list comes from `git diff --name-status <base>...<head>`."""
    out = make_git(repo)(["diff", "--name-status", f"{base}...{head}"])
    return backtest_changes(repo, head, parse_name_status(out), base=base, **kw)


def backtest_branch(repo: str, head: str, changes: list[tuple[str, str]], *,
                    exclude_pr: int | None = None, fixture_history=None,
                    now: datetime | None = None, plans_dir: str | None = None,
                    per_replay_timeout: int = PER_REPLAY_TIMEOUT,
                    total_cap: int = TOTAL_CAP,
                    base: str = "origin/master") -> tuple[Verdict, str]:
    """Runner entry: replay the gates in `changes`. A fixture history replaces `gh` and the fetch."""
    if plans_dir is None:
        home = os.environ.get("HOME", "")
        plans_dir = os.path.join(home, "claude-plans") if home else ""
    kw: dict = {}
    if fixture_history is not None:
        kw = {"gh_json": (lambda _argv: fixture_history), "fetch": False}
    out = backtest_changes(repo, head, changes, plans_dir=plans_dir, exclude_pr=exclude_pr,
                           now=now, per_replay_timeout=per_replay_timeout, total_cap=total_cap,
                           base=base, **kw)
    return out.verdict, out.block


GateBacktestResult = namedtuple("GateBacktestResult", "status reason block")
_MEMO: dict[tuple[str, str], GateBacktestResult] = {}


def _unknown(gates: list[GateChange], reason: str) -> GateBacktestResult:
    shown = gates or [GateChange("(detector)", "check-script", "unspecified", None,
                                 "detector failed")]
    block = render_gate_backtest(Verdict("UNKNOWN", reason, {}), shown, [], {}, 0)
    return GateBacktestResult("UNKNOWN", reason, block)


def gate_backtest_result(repo: str, diff_text: str, *, pr, live: bool, fixture, log
                         ) -> GateBacktestResult:
    """The runner's one call: the backtest verdict for the diff it already holds.

    No gate in the diff returns NOT-APPLICABLE with no fetch, worktree, or `gh` call. Replay mode
    reads a `gate_backtest` fixture key and never touches git. A live run memoizes on the diff hash,
    so the body-assembly and arming call sites share one backtest, and any raise maps to UNKNOWN.
    """
    changes = changes_from_unified_diff(diff_text)
    gates: list[GateChange] = []
    try:
        gates = _detect(repo, "HEAD", changes, read=live)
        if not gates:
            return GateBacktestResult("NOT-APPLICABLE", "", "")
        if not live:
            seam = (fixture or {}).get("gate_backtest")
            if isinstance(seam, dict):
                return GateBacktestResult(seam.get("status", "NOT-APPLICABLE"),
                                          seam.get("reason", ""), seam.get("block", ""))
            return GateBacktestResult("NOT-APPLICABLE", "replay fixture has no gate_backtest", "")
        key = (repo, hashlib.sha256(diff_text.encode("utf-8", "replace")).hexdigest())
        if key not in _MEMO:
            verdict, block = backtest_branch(repo, "HEAD", changes, exclude_pr=pr)
            _MEMO[key] = GateBacktestResult(verdict.state, verdict.reason, block)
        return _MEMO[key]
    except Exception as exc:
        log(f"gate-backtest: runner error — {type(exc).__name__}: {exc}")
        return _unknown(gates, f"runner error: {type(exc).__name__}")


_VALUE_FLAGS = ("repo", "base", "head", "limit", "plans-dir", "per-replay-timeout", "total-cap",
                "fixture-history", "now", "gate")
EXIT_FOR_STATE = {"NOT-APPLICABLE": 0, "CLEARED": 0, "HELD": 1, "UNKNOWN": 3}

USAGE = """Usage: bin/gate-backtest [--base=<ref>] [--head=<ref>] [--limit=<N>] [--gate=<path>@<rev>]
                         [--plans-dir=<dir>] [--per-replay-timeout=<s>] [--total-cap=<s>]
                         [--repo=<dir>] [--fixture-history=<file>] [--now=<ISO-8601>]

Replays the gates this branch adds or changes against the last merged PRs and prints the
catch-list block. Exit 0 NOT-APPLICABLE or CLEARED, 1 HELD, 2 usage, 3 UNKNOWN."""


def _parse_args(argv: list[str]) -> dict[str, str] | str:
    """Hand-rolled on purpose: argparse would accept the space form. Returns an error string."""
    opts: dict[str, str] = {}
    for arg in argv:
        if arg in ("-h", "--help"):
            opts["help"] = "1"
            continue
        if not arg.startswith("--"):
            return f"gate-backtest: unknown flag: {arg}"
        name, eq, value = arg[2:].partition("=")
        if name not in _VALUE_FLAGS:
            return f"gate-backtest: unknown flag: {arg}"
        if not eq:
            return f"gate-backtest: use --{name}=<value>"
        opts[name] = value
    return opts


def _int_opt(opts: dict[str, str], name: str, default: int, lo: int, hi: int) -> int | str:
    if name not in opts:
        return default
    try:
        value = int(opts[name])
    except ValueError:
        return f"gate-backtest: --{name} needs an integer"
    if not lo <= value <= hi:
        return f"gate-backtest: --{name} must be between {lo} and {hi}"
    return value


def main(argv: list[str]) -> int:
    signal.signal(signal.SIGTERM, lambda *_: sys.exit(143))
    opts = _parse_args(argv)
    if isinstance(opts, str):
        print(opts, file=sys.stderr)
        return 2
    if "help" in opts:
        print(USAGE)
        return 0
    numbers = {}
    for name, default, lo, hi in (("limit", HISTORY_LIMIT, 1, 100),
                                  ("per-replay-timeout", PER_REPLAY_TIMEOUT, 1, 3600),
                                  ("total-cap", TOTAL_CAP, 1, 7200)):
        value = _int_opt(opts, name, default, lo, hi)
        if isinstance(value, str):
            print(value, file=sys.stderr)
            return 2
        numbers[name] = value
    now = None
    if "now" in opts:
        try:
            now = _parse_merged_at(opts["now"])
        except ValueError:
            print("gate-backtest: --now needs an ISO-8601 timestamp", file=sys.stderr)
            return 2
    forced = None
    if "gate" in opts:
        path, at, rev = opts["gate"].rpartition("@")
        if not at or not path or not rev:
            print(f"gate-backtest: cannot read {opts['gate']}", file=sys.stderr)
            return 2
        forced = (path, rev)
    home = os.environ.get("HOME", "")
    plans_dir = opts.get("plans-dir", os.path.join(home, "claude-plans") if home else "")
    fixture = opts.get("fixture-history")
    gh_json = live_gh_json
    if fixture:
        try:
            with open(fixture, encoding="utf-8") as fh:
                fixture_data = json.load(fh)
        except (OSError, ValueError) as exc:
            print(f"gate-backtest: cannot read fixture history: {exc}", file=sys.stderr)
            return 2
        gh_json = lambda _argv: fixture_data  # noqa: E731
    repo = opts.get("repo") or os.getcwd()
    try:
        outcome = backtest_from_refs(
            repo, opts.get("base", "origin/master"), opts.get("head", "HEAD"), gh_json=gh_json,
            fetch=not fixture, plans_dir=plans_dir, limit=numbers["limit"],
            per_replay_timeout=numbers["per-replay-timeout"], total_cap=numbers["total-cap"],
            now=now, forced_gate=forced)
    except GateReadError as exc:
        print(f"gate-backtest: {exc}", file=sys.stderr)
        return 2
    except Exception as exc:
        reason = f"backtest crashed: {type(exc).__name__}"
        print(f"{GATE_BACKTEST_BEGIN}\n<!-- gate-backtest-state: UNKNOWN -->\n### Gate backtest\n\n"
              f"**State:** UNKNOWN. {reason}\n{GATE_BACKTEST_END}")
        print(f"gate-backtest: UNKNOWN: {reason}", file=sys.stderr)
        return 3
    if not outcome.gates:
        print("gate-backtest: NOT-APPLICABLE (no gate files changed)", file=sys.stderr)
        return 0
    print(outcome.block)
    print(f"gate-backtest: {outcome.verdict.state}: {outcome.verdict.reason}", file=sys.stderr)
    return EXIT_FOR_STATE[outcome.verdict.state]


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
