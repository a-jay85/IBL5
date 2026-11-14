"""Phase-omission tests for a phase whose deliverable is a `.php` migration placeholder.

Each test is named for the mutation it kills. BOXSCORE_PHASE_3 is the shape of the real
Phase 3 of the boxscore-missing-team-row-restore plan (PR #3946): a PHP migration driver
written as an `NNN_` placeholder marked `(example)`, plus tracked references.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile
from harness.conformance import phase_omission_items
from harness.state import PlanInfo

BOXSCORE_PHASE_3 = """\
## Phase 3: Thin migration driver and local-DB apply

Copy the head of `ibl5/migrations/171_restore_2004_suns_heat_boxscore.php`. Create the driver `ibl5/migrations/NNN_restore_missing_box_score_team_rows.php` (example) with the number from `bin/next-migration`. Rows carry `game_date`.
"""

PLACEHOLDER = "ibl5/migrations/NNN_restore_missing_box_score_team_rows.php"
CLASS_FILE = "ibl5/classes/Boxscore/Legacy/MissingTeamRowRestore.php"
SHIPPED_DRIVER = "ibl5/migrations/208_restore_missing_box_score_team_rows.php"

TRACKED = ["ibl5/migrations/171_restore_2004_suns_heat_boxscore.php", "bin/next-migration"]
SHIPPED = [CLASS_FILE, SHIPPED_DRIVER]


def _plan_with_phases(content: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, phases=planfile.parse_phases(content))


def _run(content, changed, tracked=TRACKED):
    notes: list[str] = []
    items = phase_omission_items(_plan_with_phases(content), changed,
                                 tracked_files=tracked, notes=notes)
    return items, notes


def _evidence(content):
    return planfile.parse_phases(content)[0].evidence_paths


def test_php_migration_placeholder_kept_as_evidence():
    """Mutation caught: revert the `_PLACEHOLDER_TOKEN_RE` migration branch to `\\.sql` only."""
    assert PLACEHOLDER in _evidence(BOXSCORE_PHASE_3)


def test_boxscore_3946_phase_3_cleared_by_renumbered_driver():
    """Mutation caught: `hit = _placeholder_deliverable(...)` replaced with `hit = None`."""
    items, notes = _run(BOXSCORE_PHASE_3, SHIPPED)
    assert items == []
    matched = [n for n in notes if n.startswith("PLACEHOLDER-MATCHED-PHASE: 3 ")]
    assert len(matched) == 1
    assert SHIPPED_DRIVER in matched[0]


def test_php_placeholder_other_directory_still_missing():
    """Mutation caught: `_MIGRATION_RENUMBER` widened to any `ibl5/<dir>/` directory."""
    items, _ = _run(BOXSCORE_PHASE_3, [
        CLASS_FILE, "ibl5/classes/208_restore_missing_box_score_team_rows.php"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 3 ")


def test_php_placeholder_suffix_mismatch_still_missing():
    """Mutation caught: suffixes compared without their extension (`.split(".")[0]`)."""
    items, _ = _run(BOXSCORE_PHASE_3, [
        CLASS_FILE, "ibl5/migrations/208_restore_missing_box_score_team_rows.sql"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 3 ")


def test_lowercase_nnn_php_placeholder_still_skipped():
    """Mutation caught: `_PLACEHOLDER_TOKEN_RE` accepting `[Nn]{3,4}`."""
    content = BOXSCORE_PHASE_3.replace("NNN_restore", "nnn_restore")
    lower = PLACEHOLDER.replace("NNN_", "nnn_")
    assert lower in content
    assert lower not in _evidence(content)


def test_py_suffix_migration_placeholder_still_skipped():
    """Mutation caught: `_PLACEHOLDER_TOKEN_RE` accepting any `\\.\\w+` suffix."""
    content = BOXSCORE_PHASE_3.replace("team_rows.php`", "team_rows.py`")
    py = PLACEHOLDER.replace(".php", ".py")
    assert py in content
    assert py not in _evidence(content)
