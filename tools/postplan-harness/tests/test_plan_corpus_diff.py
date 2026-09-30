"""Opt-in corpus diff for planfile.count_executable_matrix_rows (backlog#877).

Runs only with POSTPLAN_CORPUS_DIFF=1 and a local plan corpus; CI sets neither,
so it skips there. Compares the runner's Phase 6 first-branch decision before
this change (covered-by sentinel whenever the plan is found with no manual rows)
against after (static wording when the matrix has zero executable rows).
"""
import datetime
import glob
import os

import pytest

from harness.planfile import locate_plan

CORPUS_SCAN_DATE = datetime.date(2026, 9, 29)
EXPECTED_FLIPS = {
    "autoresearch-oreb-arming-share-lever",
    "check-docs-skill-draft-placeholder",
    "repos-7-6-fetchallinlist-migration",
    "retire-pr-ready",
}
# Cell-scoped matching would flip two more plans (bug-pipeline-tick-utc-epoch-skew,
# lastsimrecap-1-29-extract-subviews); both have real executable rows, so they must stay out of EXPECTED_FLIPS.
UNBALANCED_FENCE_PLAN = "sonnet-recipe-completeness-lint"


def _flips_to_static(info) -> bool:
    old_covered = info.found and not info.truly_manual_rows
    return old_covered and info.has_matrix and info.executable_row_count == 0


def test_local_plan_corpus_flip_set():
    if os.environ.get("POSTPLAN_CORPUS_DIFF") != "1":
        pytest.skip("SKIP: set POSTPLAN_CORPUS_DIFF=1 to diff the local plan corpus")
    plans_dir = os.environ.get("PLANS_DIR") or os.path.expanduser("~/claude-plans")
    if not os.path.isdir(plans_dir):
        pytest.skip(f"SKIP: no plan corpus at {plans_dir}")
    paths = [p for p in sorted(glob.glob(os.path.join(plans_dir, "*.md")))
             if not p.endswith("-shared-context.md")]
    stems = {os.path.basename(p)[:-3]: p for p in paths}
    if not (EXPECTED_FLIPS & stems.keys()):
        pytest.skip(f"SKIP: {plans_dir} holds none of the 2026-09-29 reference plans")

    flips = {s for s, p in stems.items()
             if _flips_to_static(locate_plan("corpus", explicit_path=p))}
    missing = {s for s in EXPECTED_FLIPS if s in stems and s not in flips}
    extras = flips - EXPECTED_FLIPS
    # A plan last written before the scan date that flips unexpectedly is a parser
    # divergence. One written on or after it is new work and is only reported.
    stale = {s for s in extras if datetime.date.fromtimestamp(
        os.path.getmtime(stems[s])) < CORPUS_SCAN_DATE}
    print(f"scanned={len(paths)} flips={sorted(flips)} new-since-scan={sorted(extras - stale)}")

    assert not missing, f"expected static-wording flips not produced: {sorted(missing)}"
    assert not stale, f"unexpected flips on pre-scan plans: {sorted(stale)}"
    if UNBALANCED_FENCE_PLAN in stems:
        info = locate_plan("corpus", explicit_path=stems[UNBALANCED_FENCE_PLAN])
        assert info.has_matrix and info.executable_row_count is None
        assert UNBALANCED_FENCE_PLAN not in flips
