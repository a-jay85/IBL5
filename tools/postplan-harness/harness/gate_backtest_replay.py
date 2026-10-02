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
import signal
import subprocess
import sys
import tempfile
import time
from dataclasses import dataclass
from datetime import datetime, timezone

from harness.gate_backtest import (GATE_BACKTEST_BEGIN, GATE_BACKTEST_END, GateChange,
                                   HistoricalPR, ReplayResult, Verdict, classify_exit,
                                   classify_truth, compute_verdict, detect_gate_changes,
                                   expand_argv, expand_env, is_check_script,
                                   render_gate_backtest, resolve_spec)

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


def backtest_branch(repo: str, base: str, head: str = "HEAD", *, gh_json=live_gh_json,
                    fetch: bool = True, plans_dir: str = "", limit: int = HISTORY_LIMIT,
                    per_replay_timeout: int = PER_REPLAY_TIMEOUT, total_cap: int = TOTAL_CAP,
                    now: datetime | None = None, exclude_pr: int | None = None,
                    forced_gate: tuple[str, str] | None = None) -> BacktestOutcome:
    """Detect the gates a branch changes and replay them against recent merged PRs.

    A branch that touches no gate returns NOT-APPLICABLE after one `git diff`, with no fetch,
    worktree, or `gh` call. Any failure after a replayable gate is found maps to UNKNOWN.
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
        changed = parse_name_status(git(["diff", "--name-status", f"{base}...{head}"]))

        def read_candidate(path: str) -> str | None:
            try:
                return _git_bytes(repo, ["show", f"{head}:{path}"]).decode("utf-8", "replace")
            except subprocess.CalledProcessError:
                return None

        check_sources: dict[str, str] = {}
        if any(p.startswith("bin/lib/") for _, p in changed):
            listing = git(["ls-tree", "-r", "--name-only", head, "--", "bin"]).splitlines()
            for p in listing:
                if is_check_script(p):
                    text = read_candidate(p)
                    if text is not None:
                        check_sources[p] = text
        gates = detect_gate_changes(changed, read_candidate, check_sources)
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
    if any(g.state == "replayable" for g in gates):
        try:
            if fetch:
                git(["fetch", "--quiet", "origin", "master"])
            bodies: dict[int, str] = {}
            history = fetch_history(gh_json, git, limit, exclude_pr, bodies)
            window = len(history)
            results = run_backtest(repo, head, gates, history, overlay_files, plans_dir,
                                   per_replay_timeout=per_replay_timeout, total_cap=total_cap,
                                   bodies=bodies)
            truth = classify_truth(history, now or datetime.now(timezone.utc))
        except Exception:
            results = None
            truth = {}
    verdict = compute_verdict(gates, results, truth)
    block = render_gate_backtest(verdict, gates, results or [], truth, window)
    return BacktestOutcome(verdict, gates, results, truth, window, block)


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
        outcome = backtest_branch(
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
