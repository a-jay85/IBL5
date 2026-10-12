"""Opt-in corpus diff for the phase-2 resolver-refusal gate (backlog#1055).

Runs only with POSTPLAN_CORPUS_DIFF=1 and a local audit-log corpus; CI sets neither,
so it skips there. Scans tools/postplan-harness/out/*/audit.log conflict lines and
applies conflict.classify's path rules (stages fixed at {1, 2, 3}: audit logs record
paths only, so the stages rule is not exercisable here). A run "flips" when any path it
logged as conflicted is one the resolver is certain to refuse.
"""
import datetime
import glob
import os
import re
from pathlib import Path

import pytest

from harness.conflict import classify

CORPUS_SCAN_DATE = datetime.date(2026, 9, 29)
# Step-2 scan (master 987eb907b): ~16 conflict runs, none on a migration or lockfile.
EXPECTED_FLIPS: frozenset[str] = frozenset()
_CONFLICT_LINE = re.compile(r"conflicted paths \((?:plain rebase|probe)\) = (.*)$")


def refusable_paths(audit_text: str) -> set[str]:
    """Paths on conflict lines in one audit log that classify() refuses by path."""
    hits: set[str] = set()
    for line in audit_text.splitlines():
        m = _CONFLICT_LINE.search(line)
        if not m:
            continue
        for path in (p.strip() for p in m.group(1).split(",")):
            if path and path != "-" and classify(path, {1, 2, 3}) is not None:
                hits.add(path)
    return hits


def test_refusable_paths_parser_flags_path_rules():
    """Runs everywhere: pins the parser so the opt-in scan cannot pass vacuously."""
    text = (
        "[16:38:01] phase2: conflicted paths (plain rebase) = a.php, "
        "ibl5/migrations/123_x.sql\n"
        "[16:38:02] phase2: conflicted paths (probe) = composer.lock\n"
        "[16:38:03] phase2: conflicted paths (plain rebase) = ibl5/phpstan-baseline.neon\n"
    )
    assert refusable_paths(text) == {"ibl5/migrations/123_x.sql", "composer.lock"}
    assert refusable_paths("phase2: conflicted paths (probe) = -\n") == set()


def test_json_paths_are_not_refused_by_path():
    """classify has no *.json rule; a json conflict (stages {1, 2, 3}) stays resolvable."""
    text = "phase2: conflicted paths (plain rebase) = ibl5/phpstan-baseline-counts.json\n"
    assert refusable_paths(text) == set()


def test_local_audit_corpus_flip_set():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local audit-log corpus")
    out_dir = os.environ.get("POSTPLAN_OUT_DIR") or str(Path(__file__).resolve().parent.parent / "out")
    logs = sorted(glob.glob(os.path.join(out_dir, "*", "audit.log")))
    if not logs:
        pytest.skip(f"SKIP: no audit logs under {out_dir}")
    conflict_runs = 0
    flips: dict[str, set[str]] = {}
    for log_path in logs:
        text = Path(log_path).read_text(errors="replace")
        if _CONFLICT_LINE.search(text):
            conflict_runs += 1
        hits = refusable_paths(text)
        if hits:
            flips[os.path.basename(os.path.dirname(log_path))] = hits
    if conflict_runs == 0:
        pytest.skip(f"SKIP: {out_dir} holds no conflict lines to diff")
    run_log = {os.path.basename(os.path.dirname(p)): p for p in logs}
    extras = set(flips) - EXPECTED_FLIPS
    missing = {r for r in EXPECTED_FLIPS if r in run_log and r not in flips}
    # A run logged before the scan date that flips unexpectedly contradicts the recorded
    # scan. One logged on or after it is new data and is only reported.
    stale = {r for r in extras if datetime.date.fromtimestamp(
        os.path.getmtime(run_log[r])) < CORPUS_SCAN_DATE}
    print(f"scanned={len(logs)} conflict_runs={conflict_runs} "
          f"flips={ {r: sorted(p) for r, p in sorted(flips.items())} } "
          f"new-since-scan={sorted(extras - stale)}")
    assert not missing, f"expected flips not produced: {sorted(missing)}"
    assert not stale, f"unexpected flips on pre-scan runs: {sorted(stale)}"
