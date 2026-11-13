"""Executable mutation claims (ADR-0199): thin adapter over bin/lib/plan-exec-mutations.

No grammar lives here. The lib parses the plan's `## Executable Mutations` section,
applies each literal edit, requires the row's check to fail, reverts, and verifies
the tree byte-for-byte. This module only runs it under `run_bounded`, reads its
closed result vocabulary, and fails closed on every path the lib cannot finish.
The lib runs under a budget-based outer bound that does not grow with the row count.
"""
from __future__ import annotations

import re
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
    """Read the lib's closed result vocabulary."""
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

