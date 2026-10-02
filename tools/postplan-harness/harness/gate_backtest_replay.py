"""I/O shell for the gate backtest: `gh` fetch, scratch worktree, and the replay driver.

All subprocess and network calls live here so `gate_backtest.py` stays pure. Every
subprocess call is an argv list with no `shell=True`; nothing from `gh` JSON (titles, branch
names) is ever shell-parsed.
"""
from __future__ import annotations

import json
import os
import re
import shutil
import subprocess
import tempfile
import time
from datetime import datetime, timezone

from harness.gate_backtest import (GateChange, HistoricalPR, ReplayResult, classify_exit,
                                   expand_argv, expand_env)

REPO_SLUG = "a-jay85/IBL5"
PER_REPLAY_TIMEOUT = 60
TOTAL_CAP = 900
HISTORY_LIMIT = 30

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
                   "number,title,mergeCommit,mergedAt,headRefName,body"])
    out: list[HistoricalPR] = []
    for item in raw:
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
                                item.get("headRefName") or "", files))
    out.sort(key=lambda p: p.merged_at, reverse=True)
    return out


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
        try:
            self._git(self.repo, ["worktree", "add", "--detach", self.dir, self.first_sha])
        except BaseException:
            self._cleanup()
            raise
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
    root = os.path.realpath(plans_dir)
    for candidate in (os.path.join(plans_dir, head_ref + ".md"),
                      os.path.join(plans_dir, "_archive", head_ref + ".md")):
        real = os.path.realpath(candidate)
        if os.path.commonpath([root, real]) != root:
            return None, "bad-head-ref"
        if os.path.isfile(real):
            return real, ""
    return None, "no-plan"


def run_backtest(repo: str, candidate_head: str, gates: list[GateChange],
                 history: list[HistoricalPR], overlay_files: dict[str, tuple[bytes, int]],
                 plans_dir: str, *, per_replay_timeout: int = PER_REPLAY_TIMEOUT,
                 total_cap: int = TOTAL_CAP, clock=time.monotonic,
                 bodies: dict[int, str] | None = None) -> list[ReplayResult]:
    """Replay every replayable gate against every historical PR's tree, newest first."""
    replayable = [g for g in gates if g.state == "replayable" and g.spec is not None]
    results: list[ReplayResult] = []
    if not replayable or not history:
        return results
    bodies = bodies or {}
    start = clock()
    first = next((p for p in history if p.parent_count == 1), None)

    def skip(pr: HistoricalPR, gate: GateChange, reason: str) -> None:
        results.append(ReplayResult(pr.number, gate.path, "skipped", reason))

    if first is None:
        for pr in history:
            for gate in replayable:
                skip(pr, gate, "non-squash" if pr.parent_count > 1 else "sha-missing")
        return results

    git = make_git(repo)
    work = tempfile.mkdtemp(prefix="gate-backtest-files-")
    capped = False
    try:
        with ScratchTree(repo, first.merge_sha) as scratch:
            for pr in history:
                checked_out = False
                for gate in replayable:
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
                           "diff_file": diff_file, "plan_file": plan_file, "body_file": body_file}
                    env = dict(os.environ)
                    env.update(expand_env(spec, ctx))
                    try:
                        proc = subprocess.run(
                            [os.path.join(scratch.dir, gate.path), *expand_argv(spec, ctx)],
                            cwd=scratch.dir, env=env, stdin=subprocess.DEVNULL,
                            capture_output=True, text=True, timeout=per_replay_timeout)
                    except subprocess.TimeoutExpired:
                        results.append(ReplayResult(pr.number, gate.path, "timeout",
                                                    f"{per_replay_timeout}s"))
                        continue
                    except OSError as exc:
                        results.append(ReplayResult(pr.number, gate.path, "error",
                                                    re.sub(r"\s+", " ", str(exc))[:160]))
                        continue
                    outcome, detail = classify_exit(spec, proc.returncode, proc.stdout)
                    results.append(ReplayResult(pr.number, gate.path, outcome, detail))
    finally:
        shutil.rmtree(work, ignore_errors=True)
    return results
