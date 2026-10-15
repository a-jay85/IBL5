"""Phase-omission tests for a phase whose deliverable is a placeholder-numbered file.

Each test is named for the mutation it kills. FRANCHISE_PHASE_2 is the shape of the real
Phase 2 of the franchise-summary-view-slow plan (PR #3228): one `NNN_` placeholder
marked `(example)` plus three tracked references.
"""
import os
import sys

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


F194 = "ibl5/migrations/194_franchise_summary_reads_season_records.sql"
ADR_PHASE_4 = (
    "## Phase 4: Record the decision\n"
    "Write `ibl5/docs/decisions/NNNN-postplan-placeholder.md` (example) — NEW, modelled on "
    "`ibl5/docs/decisions/0092-postplan-harness-in-repo-with-python-ci.md`."
)
ADR_TRACKED = ["ibl5/docs/decisions/0092-postplan-harness-in-repo-with-python-ci.md"]


def _run(content, changed, tracked=TRACKED):
    notes: list[str] = []
    items = phase_omission_items(_plan_with_phases(content), changed,
                                 tracked_files=tracked, notes=notes)
    return items, notes


def _matched(notes):
    return [n for n in notes if n.startswith("PLACEHOLDER-MATCHED-PHASE: ")]


def test_placeholder_matched_phase_not_held():
    """Mutation caught: delete the placeholder-match insertion in `phase_omission_items`."""
    items, notes = _run(FRANCHISE_PHASE_2, [
        F194, "ibl5/tests/DatabaseIntegration/FranchiseSummaryGuardMigrationTest.php"])
    assert items == []
    matched = _matched(notes)
    assert len(matched) == 1
    assert matched[0].startswith("PLACEHOLDER-MATCHED-PHASE: 2 ")
    assert "shipped as ibl5/migrations/194_franchise_summary_reads_season_records.sql" in matched[0]


def test_placeholder_suffix_mismatch_still_missing():
    """Mutation caught: replace `r.group("suffix") == suffix` with `True`."""
    items, notes = _run(FRANCHISE_PHASE_2, ["ibl5/migrations/194_franchise_summary_other.sql"])
    assert len(items) == 1 and items[0].startswith("MISSING-PHASE: 2 ")
    assert _matched(notes) == []


def test_placeholder_wrong_directory_still_missing():
    """Mutation caught: match on `PurePosixPath(f).name` instead of the anchored pattern."""
    items, notes = _run(FRANCHISE_PHASE_2, [
        "ibl5/migrations/archive/194_franchise_summary_reads_season_records.sql",
        "docs/194_franchise_summary_reads_season_records.sql"])
    assert len(items) == 1 and items[0].startswith("MISSING-PHASE: 2 ")
    assert _matched(notes) == []


def test_placeholder_case_mismatch_still_missing():
    """Mutation caught: `.lower()` both suffixes."""
    items, notes = _run(FRANCHISE_PHASE_2, [
        "ibl5/migrations/194_Franchise_Summary_Reads_Season_Records.sql"])
    assert len(items) == 1 and items[0].startswith("MISSING-PHASE: 2 ")
    assert _matched(notes) == []


def test_placeholder_two_numbered_hits_clear_phase():
    """Mutation caught: change `if hits:` to `if len(hits) == 1:`."""
    items, notes = _run(FRANCHISE_PHASE_2, [F194, F194.replace("194_", "195_")])
    assert items == []
    matched = _matched(notes)
    assert len(matched) == 1
    assert "shipped as ibl5/migrations/194_" in matched[0]


def test_adr_placeholder_matches_four_digit_file():
    """Mutation caught: delete the `_ADR_PLACEHOLDER_TOKEN` fallback in the helper."""
    items, notes = _run(ADR_PHASE_4, ["ibl5/docs/decisions/0140-postplan-placeholder.md"],
                        tracked=ADR_TRACKED)
    assert items == []
    matched = _matched(notes)
    assert len(matched) == 1 and matched[0].startswith("PLACEHOLDER-MATCHED-PHASE: 4 ")


def test_adr_placeholder_three_digit_file_still_missing():
    """Mutation caught: use a `\\d+` file regex on the ADR side instead of `_ADR_RENUMBER`."""
    items, notes = _run(ADR_PHASE_4, ["ibl5/docs/decisions/140-postplan-placeholder.md"],
                        tracked=ADR_TRACKED)
    assert len(items) == 1 and items[0].startswith("MISSING-PHASE: 4 ")
    assert _matched(notes) == []


def test_lone_unmatched_placeholder_phase_is_not_held():
    """Mutation caught: append unmatched placeholders to `cands` before `if not cands`."""
    content = ("## Phase 2: New migration\n"
               "Create `ibl5/migrations/NNN_seed_rows.sql` (example) — NEW.")
    items, notes = _run(content, ["ibl5/classes/Foo.php"], tracked=[])
    assert items == []
    assert any(n.startswith("UNCHECKABLE-PHASE: 2 ") for n in notes)
