"""Executable mutation claims (ADR-0199): thin adapter over bin/lib/plan-exec-mutations.

No grammar lives here. The lib parses the plan's `## Executable Mutations` section,
applies each literal edit, requires the row's check to fail, reverts, and verifies
the tree byte-for-byte. This module only runs it under `run_bounded`, reads its
closed result vocabulary, and fails closed on every path the lib cannot finish.
The lib runs under a budget-based outer bound that does not grow with the row count.
"""
from __future__ import annotations

import hashlib
import os
import re
import shlex
import shutil
import subprocess
import tempfile
from dataclasses import dataclass
from pathlib import Path
from typing import Optional

from .adapters.llm import run_bounded
from .state import HarnessError

JOURNAL_DIRNAME = "exec-mutations"
DEFAULT_LIB = Path(__file__).resolve().parents[3] / "bin" / "lib" / "plan-exec-mutations"
RESULT_RE = re.compile(
    r"^(TRIPPED|HELD|BASELINE-FAILED|NOT-APPLIED|UNDECIDED|REVERT-FAILED) row ([0-9A-Za-z_-]+) — (.*)$"
)
SIDECAR_MAGIC = "prefire-xm-reuse v1"
ALWAYS_DEPS = ("bin/lib/plan-exec-mutations",)   # the executor itself
_PATHLIKE = re.compile(r"^[\w.-]+\.(?:php|py|sh|ts|js|json|ya?ml|md|sql|go|txt|neon|xml)$")
_UNSAFE = frozenset("*?[$~`")
NO_TIMEOUT_PREFIX = "NO-TIMEOUT"
DEFAULT_PER_CHECK_TIMEOUT = 30   # floor; the pre-2026-11 fixed bound
DEFAULT_MAX_TIMEOUT = 300        # baseline bound and mutated-run ceiling
DEFAULT_SCALE = 2
DEFAULT_BUDGET = 1800            # total wall clock across rows
OUTER_SLACK = 60


@dataclass(frozen=True)
class MutationResult:
    row: int | str  # plain digits parse to int; a suffix id such as "12-neg" stays a str
    kind: str
    detail: str


@dataclass(frozen=True)
class MutationReport:
    ran: bool
    results: tuple[MutationResult, ...]
    holding: tuple[str, ...]
    tree_suspect: bool


def _first_line(text: str) -> str:
    for line in (text or "").splitlines():
        if line.strip():
            return line.strip()[:300]
    return ""


def _recover(lib: Path, cwd: Path) -> bool:
    """Run `recover`; True when the journal is empty afterwards."""
    try:
        proc = run_bounded([str(lib), "recover", "--root", str(cwd)],
                           step="exec-mutations-recover", timeout=60, cwd=str(cwd))
    except (HarnessError, OSError):
        return False
    return proc.returncode == 0


def _collect(lines) -> tuple[list[MutationResult], list[str]]:
    """Read the lib's closed result vocabulary; the fresh and reused paths share this."""
    results: list[MutationResult] = []
    holding: list[str] = []
    for line in lines:
        m = RESULT_RE.match(line)
        if m:
            res = MutationResult(int(m.group(2)) if m.group(2).isdigit() else m.group(2),
                                 m.group(1), m.group(3))
            results.append(res)
            if res.kind != "TRIPPED":
                holding.append(f"row {res.row} {res.kind} — {res.detail}")
        elif line.startswith(NO_TIMEOUT_PREFIX) or line.startswith("PARSE-FAILED"):
            holding.append(f"executor-unavailable: {line.strip()[:300]}")
    return results, holding


def run_executable_mutations(plan: Path, cwd: Path, *, lib: Path = DEFAULT_LIB,
                             per_check_timeout: int = DEFAULT_PER_CHECK_TIMEOUT,
                             max_timeout: int = DEFAULT_MAX_TIMEOUT,
                             scale: int = DEFAULT_SCALE, budget: int = DEFAULT_BUDGET,
                             outer_timeout: Optional[int] = None) -> MutationReport:
    """Parse, then run every executable mutation in `plan` against `cwd`."""
    try:
        parsed = run_bounded([str(lib), "parse", "--root", str(cwd), str(plan)],
                             step="exec-mutations-parse", timeout=60, cwd=str(cwd),
                             errors="surrogateescape")
    except OSError as e:
        return MutationReport(True, (), (f"executor-unavailable: {e}",), False)
    except HarnessError as e:
        return MutationReport(True, (), (f"executor-unavailable: {e.detail}",), False)
    if parsed.returncode != 0:
        why = _first_line(parsed.stdout) or _first_line(parsed.stderr) or f"rc {parsed.returncode}"
        return MutationReport(True, (), (f"executor-parse-failed: {why}",), False)
    n_records = sum(1 for line in parsed.stdout.split("\n") if line)
    if n_records == 0:
        return MutationReport(True, (), (), False)

    max_timeout = max(max_timeout, per_check_timeout)   # the lib rejects a ceiling below the floor
    bound = outer_timeout if outer_timeout is not None else budget + 2 * max_timeout + OUTER_SLACK
    try:
        proc = run_bounded([str(lib), "run", "--root", str(cwd),
                            "--timeout", str(per_check_timeout),
                            "--max-timeout", str(max_timeout),
                            "--scale", str(scale),
                            "--budget", str(budget),
                            str(plan)],
                           step="exec-mutations", timeout=bound, cwd=str(cwd),
                           errors="surrogateescape")
    except HarnessError:
        clean = _recover(lib, cwd)
        return MutationReport(True, (), ("executor-timeout",), not clean)
    except OSError as e:
        clean = _recover(lib, cwd)
        return MutationReport(True, (), (f"executor-unavailable: {e}",), not clean)

    results, holding = _collect(proc.stdout.splitlines())
    tree_suspect = proc.returncode == 3
    if proc.returncode != 0 and not holding:
        holding.append(f"executor-unavailable: run exited {proc.returncode}")
    return MutationReport(True, tuple(results), tuple(holding), tree_suspect)


# --- reuse of pre-fire's recorded pass (ADR-0199) ---------------------------------

def _git(root: Path, *args: str, env=None) -> subprocess.CompletedProcess:
    return subprocess.run(["git", "-C", str(root), *args], capture_output=True,
                          timeout=60, env=env)


def _rows_identity(data: bytes) -> str:
    """git's blob id of `parse` stdout: the value prefire-verify gets from `git hash-object`."""
    return hashlib.sha1(b"blob %d\0" % len(data) + data).hexdigest()


def _worktree_tree(root: Path) -> Optional[str]:
    """Hash the working tree the way prefire_tree_hash does: temp index, add -A, write-tree."""
    gp = _git(root, "rev-parse", "--git-path", "index")
    if gp.returncode != 0:
        return None
    index = Path(gp.stdout.decode().strip())
    index = index if index.is_absolute() else root / index
    with tempfile.TemporaryDirectory() as td:
        tmp_index = Path(td) / "index"
        if index.exists():
            shutil.copyfile(index, tmp_index)
        env = {**os.environ, "GIT_INDEX_FILE": str(tmp_index)}
        if _git(root, "add", "-A", env=env).returncode != 0:
            return None
        wt = _git(root, "write-tree", env=env)
    return wt.stdout.decode().strip() if wt.returncode == 0 else None


def _path_state(root: Path, rel: str, pre: str, post: str) -> Optional[str]:
    """tracked | one-tree | ignored, or None when the path resolves nowhere."""
    if rel.startswith("..") or os.path.isabs(rel):
        return None
    in_pre = _git(root, "rev-parse", "--verify", "-q", f"{pre}:{rel}").returncode == 0
    in_post = _git(root, "rev-parse", "--verify", "-q", f"{post}:{rel}").returncode == 0
    if in_pre and in_post:
        return "tracked"
    if in_pre or in_post:
        return "one-tree"
    if (root / rel).exists() and _git(root, "check-ignore", "-q", rel).returncode == 0:
        return "ignored"
    return None


def _check_deps(root: Path, check: str, pre: str, post: str) -> tuple[set[str], Optional[str]]:
    """Repo paths a check command names. A non-empty second value means: run fresh."""
    lex = shlex.shlex(check, posix=True, punctuation_chars=";&|()<>")
    lex.whitespace_split = True
    try:
        toks = list(lex)
    except ValueError:
        return set(), "check is not shell-splittable"
    bases, deps, prev = [""], set(), ""
    for tok in toks:
        if prev == "cd":
            bases.append(tok.rstrip("/") + "/")
            prev = tok
            continue
        prev = tok
        cand = tok.split("=", 1)[1] if tok.startswith("-") and "=" in tok else tok
        if "/" not in cand and not _PATHLIKE.match(cand):
            continue
        if _UNSAFE & set(cand):
            return set(), f"unsafe token {tok}"
        if cand.startswith("/"):
            if not cand.startswith(f"{root}/"):
                continue  # outside the repo, such as /dev/null
            cand = cand[len(str(root)) + 1:]
        state, rel = None, ""
        for base in reversed(bases):
            rel = os.path.normpath(base + cand)
            state = _path_state(root, rel, pre, post)
            if state:
                break
        if state is None:
            return set(), f"unresolved token {tok}"
        if state == "one-tree":
            return set(), f"{rel} added or removed"
        if state == "tracked":
            deps.add(rel)
    return deps, None


def reuse_prefire_pass(plan: Path, cwd: Path, *, lib: Path = DEFAULT_LIB
                       ) -> tuple[Optional[MutationReport], str]:
    """Rebuild pre-fire's recorded pass, or return (None, why) so post-plan runs fresh."""
    try:
        return _reuse(Path(plan), Path(cwd), Path(lib))
    except (OSError, subprocess.SubprocessError, ValueError) as e:
        return None, f"reuse check failed: {e}"


def _reuse(plan: Path, cwd: Path, lib: Path) -> tuple[Optional[MutationReport], str]:
    top = _git(cwd, "rev-parse", "--show-toplevel")
    if top.returncode != 0:
        return None, "not a git worktree"
    root = Path(top.stdout.decode().strip())
    gp = _git(root, "rev-parse", "--git-path", "prefire-verify")
    if gp.returncode != 0:
        return None, "no git dir"
    gpath = Path(gp.stdout.decode().strip())
    state_dir = gpath if gpath.is_absolute() else root / gpath
    parsed = subprocess.run([str(lib), "parse", "--root", str(root), str(plan)],
                            capture_output=True, timeout=60, cwd=str(root))
    if parsed.returncode != 0:
        return None, f"parse exited {parsed.returncode}"
    records = [r.split(b"\0") for r in parsed.stdout.split(b"\n") if r]
    if not records or any(len(r) != 5 for r in records):
        return None, "no well-formed rows"
    rows_key = _rows_identity(parsed.stdout)
    sidecar = state_dir / f"exec-mutations.rows-{rows_key}.reuse"
    if not sidecar.is_file():
        return None, "no pre-fire record for these rows"
    lines = sidecar.read_text(encoding="utf-8").split("\n")
    if lines and lines[-1] == "":
        lines.pop()
    if (len(lines) < 5 or lines[0] != SIDECAR_MAGIC or lines[1] != f"rows={rows_key}"
            or not re.fullmatch(r"tree=[0-9a-f]{40,64}", lines[2])
            or not lines[3].startswith("at=")):
        return None, "record malformed: header"
    pre_tree, body = lines[2][5:], lines[4:]
    if not all(RESULT_RE.match(ln) for ln in body):
        return None, "record malformed: result line"
    if [RESULT_RE.match(ln).group(2) for ln in body] != [r[0].decode() for r in records]:
        return None, "record malformed: row ids differ from the plan"
    if _git(root, "cat-file", "-e", f"{pre_tree}^{{tree}}").returncode != 0:
        return None, "pre-fire tree missing"
    post_tree = _worktree_tree(root)
    if not post_tree:
        return None, "cannot hash the current tree"
    deps: set[str] = set()
    for rel in ALWAYS_DEPS:
        state = _path_state(root, rel, pre_tree, post_tree)
        if state == "one-tree":
            return None, f"dependency changed: {rel}"
        if state == "tracked":
            deps.add(rel)
    for rec in records:
        rid, target, check = (f.decode() for f in rec[:3])
        target = os.path.normpath(os.path.relpath(target, root) if os.path.isabs(target) else target)
        if _path_state(root, target, pre_tree, post_tree) != "tracked":
            return None, f"dependency unresolved: row {rid} target {target}"
        deps.add(target)
        check_deps, why = _check_deps(root, check, pre_tree, post_tree)
        if why:
            return None, f"dependency unresolved: row {rid} {why}"
        if not check_deps:
            return None, f"no tracked dependency in check: row {rid}"
        deps |= check_deps
    diff = _git(root, "diff", "--quiet", pre_tree, post_tree, "--",
                *(f":(literal){p}" for p in sorted(deps)))
    if diff.returncode == 1:
        return None, "dependency changed since pre-fire"
    if diff.returncode != 0:
        return None, f"git diff exited {diff.returncode}"
    # `pending` exits 1 with no output on an empty journal and 0 when an entry exists.
    # Any other answer sends post-plan down the fresh path, which judges the journal.
    pend = subprocess.run([str(lib), "pending", "--root", str(root)],
                          capture_output=True, timeout=60, cwd=str(root))
    if pend.returncode != 1 or pend.stdout.strip():
        return None, "journal not empty"
    results, holding = _collect(body)
    return (MutationReport(True, tuple(results), tuple(holding), False),
            f"rows {rows_key[:7]}, pre-fire tree {pre_tree[:7]}, "
            f"{len(results)} rows, {len(deps)} dependency paths unchanged")
