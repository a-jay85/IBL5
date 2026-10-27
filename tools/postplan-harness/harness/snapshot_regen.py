"""Golden-snapshot regeneration for the conflict ladder's regen rung.

A golden snapshot is the output of code, so after a merge the right bytes are whatever
the merged code renders. A rendered snapshot is one long line, so no line-level resolver
can satisfy the lost-work proof for it. The regen rung instead deletes each both-modified
conflicted snapshot, reruns its owning PHPUnit test in capture mode, restores any other
file the run touched, then reruns the test with no capture flag. Only a passing second
run lets the path reach `ctx.extend_pre_patch`, which rewrites the proof's PRE patch so
that one file is checked against HEAD. Every other path keeps the unchanged proof.

This module must not import `harness.conflict_ladder`, which imports it.
"""
from __future__ import annotations

import os
import re
import shutil
import subprocess
from dataclasses import dataclass
from typing import Any, Callable, Mapping, Optional

_BOTH_MODIFIED = frozenset({1, 2, 3})

SNAPSHOT_PATTERNS = (
    re.compile(r"^ibl5/tests/(?:[^/]+/)+__snapshots__/[^/]+$"),
    re.compile(r"^ibl5/tests/(?:[^/]+/)+fixtures/[^/]+\.golden\.html$"),
)
PHP_FALLBACKS = ("/opt/homebrew/bin/php", "/usr/local/bin/php")
SNAPSHOT_RUN_TIMEOUT = 180        # seconds, per PHPUnit invocation
MAX_SNAPSHOT_TARGETS = 10         # more conflicted snapshots than this: decline
CAPTURE_ENV = {"UPDATE_SNAPSHOTS": "1", "IBL_GOLDEN_CAPTURE": "1"}
AUDIT_FILE = "postplan-snapshot-regen.log"

_ANSI = re.compile(r"\x1b\[[0-9;]*m")


def is_snapshot_path(path: str) -> bool:
    """True for a golden snapshot path. `.png` is excluded so VR baselines stay out."""
    if path.endswith(".png"):
        return False
    return any(p.match(path) for p in SNAPSHOT_PATTERNS)


def snapshot_stem(path: str) -> str:
    base = os.path.basename(path)
    if base.endswith(".golden.html"):
        return base[: -len(".golden.html")]
    return os.path.splitext(base)[0]


def find_owning_test(worktree: str, path: str) -> tuple[Optional[str], str]:
    """The one `*Test.php` directly under `dirname(dirname(path))` that references the
    snapshot. Returns (owner_repo_rel, "") or (None, reason). Declines on zero or many."""
    search_rel = os.path.dirname(os.path.dirname(path))
    search_dir = os.path.join(worktree, search_rel)
    try:
        names = sorted(n for n in os.listdir(search_dir) if n.endswith("Test.php"))
    except OSError:
        names = []
    texts: dict[str, str] = {}
    for name in names:
        full = os.path.join(search_dir, name)
        if not os.path.isfile(full):
            continue
        try:
            with open(full, encoding="utf-8", errors="replace") as fh:
                texts[name] = fh.read()
        except OSError:
            continue

    stem = snapshot_stem(path)
    direct = re.compile(r"""['"/]""" + re.escape(stem) + r"""(?:\.[A-Za-z0-9.]+)?['"]""")
    hits = [n for n, t in texts.items() if direct.search(t)]
    if not hits:
        parts = stem.split("-")
        for k in range(len(parts) - 1, 0, -1):
            prefix = "-".join(parts[:k])
            fragment = re.compile(r"""['"/]""" + re.escape(prefix) + r"""-['"]""")
            hits = [n for n, t in texts.items() if fragment.search(t)]
            if hits:
                break
    if not hits:
        return None, f"no owning test under {search_rel}"
    if len(hits) > 1:
        return None, "ambiguous owner: " + ", ".join(f"{search_rel}/{n}" for n in hits)
    return f"{search_rel}/{hits[0]}", ""


@dataclass(frozen=True)
class RunResult:
    ok: bool
    tests_run: int
    detail: str


def parse_tests_run(output: str) -> int:
    text = _ANSI.sub("", output)
    m = re.search(r"OK \((\d+) tests?", text)
    if m:
        return int(m.group(1))
    m = re.search(r"^Tests: (\d+),", text, re.MULTILINE)
    if m:
        return int(m.group(1))
    return 0


def resolve_php(which=shutil.which, fallbacks=PHP_FALLBACKS) -> Optional[str]:
    """The launchd PATH is minimal, so fall back to the usual Homebrew locations."""
    found = which("php")
    if found:
        return found
    for p in fallbacks:
        if os.access(p, os.X_OK):
            return p
    return None


def host_runner(php: str) -> Callable[[str, str, Mapping[str, str], int], RunResult]:
    def run(ibl5_dir: str, test_rel: str, extra_env: Mapping[str, str], timeout: int) -> RunResult:
        argv = [php, "vendor/bin/phpunit", "--no-progress", "--do-not-cache-result",
                "--colors=never", "--exclude-group", "database", test_rel]
        env = {k: v for k, v in os.environ.items() if k not in CAPTURE_ENV}
        env["PATH"] = os.pathsep.join(
            p for p in (os.path.dirname(php), env.get("PATH", "")) if p)
        env.update(extra_env)
        try:
            proc = subprocess.run(argv, cwd=ibl5_dir, env=env, capture_output=True,
                                  text=True, errors="replace", timeout=timeout)
        except subprocess.TimeoutExpired:
            return RunResult(False, 0, f"timed out after {timeout}s")
        except OSError as exc:
            return RunResult(False, 0, f"could not start php: {exc}")
        combined = (proc.stdout or "") + (proc.stderr or "")
        tests_run = parse_tests_run(combined)
        return RunResult(proc.returncode == 0 and tests_run > 0, tests_run,
                         _ANSI.sub("", combined).strip()[-300:])
    return run


def default_runner(worktree: str, which=shutil.which,
                   fallbacks=PHP_FALLBACKS) -> tuple[Optional[Callable], str]:
    if not os.path.exists(os.path.join(worktree, "ibl5", "vendor", "autoload.php")):
        return None, "ibl5/vendor/autoload.php missing"
    if not os.path.exists(os.path.join(worktree, "ibl5", "vendor", "bin", "phpunit")):
        return None, "ibl5/vendor/bin/phpunit missing"
    php = resolve_php(which, fallbacks)
    if php is None:
        return None, "php not found on PATH or at " + ", ".join(PHP_FALLBACKS)
    return host_runner(php), ""


def require_snapshot_only(paths) -> tuple[str, ...]:
    """Explicit raise, never assert, so `python -O` cannot strip the guard."""
    out = tuple(paths)
    for p in out:
        if not is_snapshot_path(p):
            raise ValueError(f"non-snapshot path offered to the proof seam: {p}")
    return out


def dirty_paths(run) -> dict[str, str]:
    """Path -> two-char porcelain status for every changed or untracked file."""
    raw = run("status", "--porcelain=v1", "-z", "--untracked-files=all")
    entries = raw.split("\0")
    out: dict[str, str] = {}
    i = 0
    while i < len(entries):
        entry = entries[i]
        i += 1
        if len(entry) < 4:
            continue
        status, path = entry[:2], entry[3:]
        out[path] = status
        if "R" in status or "C" in status:
            i += 1   # the rename/copy source path follows as its own entry
    return out


def restore_paths(run, worktree: str, paths) -> None:
    statuses = dirty_paths(run)
    for p in paths:
        if statuses.get(p) == "??":
            try:
                os.remove(os.path.join(worktree, p))
            except OSError:
                pass
        else:
            run("checkout", "HEAD", "--", p)


# -- Audit-line channel: a file in the worktree's git dir, outside the tracked tree -----

def _audit_file(worktree: str) -> str:
    try:
        proc = subprocess.run(
            ["git", "-C", worktree, "rev-parse", "--path-format=absolute",
             "--git-path", AUDIT_FILE],
            capture_output=True, text=True, timeout=60)
        if proc.returncode != 0:
            return ""
        return proc.stdout.strip()
    except (subprocess.CalledProcessError, subprocess.TimeoutExpired, OSError):
        return ""


def _read_lines(path: str) -> list[str]:
    try:
        with open(path, encoding="utf-8") as fh:
            return [l for l in fh.read().splitlines() if l]
    except OSError:
        return []


def append_audit_lines(worktree: str, lines) -> None:
    path = _audit_file(worktree) if worktree else ""
    if not path:
        return
    try:
        with open(path, "a", encoding="utf-8") as fh:
            for line in lines:
                fh.write(line + "\n")
    except OSError:
        pass


def remove_audit_lines(worktree: str, lines) -> None:
    path = _audit_file(worktree) if worktree else ""
    if not path:
        return
    drop = list(lines)
    kept = []
    for line in _read_lines(path):
        if line in drop:
            drop.remove(line)
        else:
            kept.append(line)
    try:
        with open(path, "w", encoding="utf-8") as fh:
            for line in kept:
                fh.write(line + "\n")
    except OSError:
        pass


def clear_audit_lines(worktree: str) -> None:
    path = _audit_file(worktree) if worktree else ""
    if not path:
        return
    try:
        os.remove(path)
    except OSError:
        pass


def pop_audit_lines(worktree: str) -> tuple[str, ...]:
    path = _audit_file(worktree) if worktree else ""
    if not path:
        return ()
    lines = _read_lines(path)
    try:
        os.remove(path)
    except OSError:
        pass
    return tuple(lines)


# -- Orchestration -----------------------------------------------------------------------

@dataclass(frozen=True)
class SnapshotRegen:
    paths: tuple[str, ...] = ()        # snapshot paths whose verify pass passed
    notes: tuple[str, ...] = ()        # "snapshot regenerated: <path> via <test>", one per path
    decline: str = ""                  # "" = nothing declined
    after_amend: Optional[Callable[[], Optional[Callable[[], None]]]] = None


def _declined(reason: str) -> SnapshotRegen:
    return SnapshotRegen(decline=f"snapshot regen declined: {reason}")


def regenerate_conflicted(ctx: Any, lost_paths, *, runner=None,
                          timeout: int = SNAPSHOT_RUN_TIMEOUT) -> SnapshotRegen:
    conflicted = sorted(p for p in ctx.conflicted_files if is_snapshot_path(p))
    if not conflicted:
        return SnapshotRegen()
    for p in conflicted:
        stages = frozenset(ctx.stage_sets.get(p, ()))
        if stages != _BOTH_MODIFIED:
            return _declined(f"{p}: not both-modified (stages {sorted(stages)})")
    targets = [p for p in conflicted if p in lost_paths]
    if not targets:
        return SnapshotRegen()
    if len(targets) > MAX_SNAPSHOT_TARGETS:
        return _declined(
            f"{len(targets)} conflicted snapshots exceeds cap {MAX_SNAPSHOT_TARGETS}")
    if ctx.extend_pre_patch is None:
        return _declined("proof seam unavailable")
    if dirty_paths(ctx.run):
        return _declined("worktree not clean before regen")
    if runner is None:
        runner, reason = default_runner(ctx.worktree)
        if runner is None:
            return _declined(reason)
    pairs: list[tuple[str, str]] = []
    for target in targets:
        owner, reason = find_owning_test(ctx.worktree, target)
        if owner is None:
            return _declined(f"{target}: {reason}")
        pairs.append((target, owner))

    ibl5_dir = os.path.join(ctx.worktree, "ibl5")

    def fail(reason: str) -> SnapshotRegen:
        restore_paths(ctx.run, ctx.worktree, list(dirty_paths(ctx.run)))
        return _declined(reason)

    all_targets = frozenset(targets)

    def restore_non_targets(before) -> None:
        # Sibling targets stay as the run wrote them: one owner test may render several
        # of them, and the verify pass must see fresh bytes. Each is rebuilt on its turn.
        stray = [q for q in dirty_paths(ctx.run)
                 if q not in before and q not in all_targets]
        restore_paths(ctx.run, ctx.worktree, stray)

    for target, owner in pairs:
        test_rel = owner.removeprefix("ibl5/")
        before = dirty_paths(ctx.run)
        abs_target = os.path.join(ctx.worktree, target)
        os.remove(abs_target)
        r1 = runner(ibl5_dir, test_rel, CAPTURE_ENV, timeout)
        restore_non_targets(before)
        if r1.ok and r1.tests_run == 0:
            return fail(f"{target}: owner test {owner} ran zero tests")
        if not r1.ok:
            return fail(f"{target}: owner test {owner} failed during capture "
                        f"({r1.tests_run} tests): {r1.detail}")
        if not os.path.exists(abs_target):
            return fail(f"{target}: owner test {owner} did not write the snapshot")
        r2 = runner(ibl5_dir, test_rel, {}, timeout)
        restore_non_targets(before)
        if r2.ok and r2.tests_run == 0:
            return fail(f"{target}: owner test {owner} ran zero tests")
        if not r2.ok:
            return fail(f"{target}: verify pass failed via {owner} "
                        f"({r2.tests_run} tests): {r2.detail}")

    paths = require_snapshot_only(tuple(targets))
    notes = tuple(f"snapshot regenerated: {t} via {o}" for t, o in pairs)
    seam, worktree = ctx.extend_pre_patch, ctx.worktree

    def after_amend():
        # The seam reads each snapshot from HEAD, so it runs after the ladder amends
        # (same ordering as conflict_fix.py). Only verified snapshot paths reach it.
        undo = seam(require_snapshot_only(paths))
        append_audit_lines(worktree, notes)

        def undo_all():
            remove_audit_lines(worktree, notes)
            if undo is not None:
                undo()
        return undo_all

    return SnapshotRegen(paths, notes, "", after_amend)
