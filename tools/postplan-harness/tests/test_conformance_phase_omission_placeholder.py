"""Phase-omission tests for a phase whose deliverable is a placeholder-numbered file.

Each test is named for the mutation it kills. FRANCHISE_PHASE_2 is the shape of the real
Phase 2 of the franchise-summary-view-slow plan (PR #3228): one `NNN_` placeholder
marked `(example)` plus three tracked references.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile
from harness.conformance import phase_omission_items
from harness.state import PlanInfo

FRANCHISE_PHASE_2 = """\
## Phase 2: Additive migration rewriting `vw_franchise_summary` `wl` onto `ibl_team_season_records` with fail-closed apply-time guard

**Tier:** Sonnet (recipe-backed; the full SQL is given verbatim below, so copy it and do not redesign)

### 2a. Create the migration

Get the number from `bin/next-migration` and use whatever it prints. Create `ibl5/migrations/NNN_franchise_summary_reads_season_records.sql` (example) — NEW. The slug is fixed and only the number floats.

**Reuse:** the view body is migration 174 section C (`ibl5/migrations/174_derive_conference_champions_from_playoffs.sql`). The guard copies the ERROR 1242 idiom from `ibl5/migrations/151_downsize_ibl_draft_team.sql`.
"""

TRACKED = [
    "bin/next-migration",
    "ibl5/migrations/174_derive_conference_champions_from_playoffs.sql",
    "ibl5/migrations/151_downsize_ibl_draft_team.sql",
]


def _plan_with_phases(content: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, phases=planfile.parse_phases(content))


def test_placeholder_phase_without_deliverable_still_missing():
    """Mutation caught: replace `any(_touched(p, changed_files) for p in cands)` with `True`."""
    items = phase_omission_items(
        _plan_with_phases(FRANCHISE_PHASE_2),
        ["ibl5/classes/Franchise/FranchiseSummary.php"],
        tracked_files=TRACKED,
    )
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 2 ")


@pytest.mark.xfail(strict=True, reason="placeholder not in evidence until Phase 2/3")
def test_placeholder_phase_motivating_case_holds_today():
    items = phase_omission_items(
        _plan_with_phases(FRANCHISE_PHASE_2),
        [
            "ibl5/migrations/194_franchise_summary_reads_season_records.sql",
            "ibl5/tests/DatabaseIntegration/FranchiseSummaryGuardMigrationTest.php",
        ],
        tracked_files=TRACKED,
    )
    assert items == []
