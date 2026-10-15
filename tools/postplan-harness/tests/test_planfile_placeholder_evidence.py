"""Phase-evidence tests for the `NNN_` / `NNNN-` number-placeholder tokens.

Each test is named for the mutation it kills. The `(example)` marker normally drops a
token from evidence; the two contract placeholder shapes are the one exception.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile


def test_non_placeholder_example_token_stays_skipped():
    """Mutation caught: delete the `_EXAMPLE_SUFFIX_RE` skip in `_phase_evidence_paths`."""
    body = "Create `ibl5/modules/Foo/index.php` (example) — NEW, then edit `ibl5/modules.php`."
    assert planfile._phase_evidence_paths(body) == ["ibl5/modules.php"]


_FRANCHISE_BODY = (
    "Get the number from `bin/next-migration`. Create "
    "`ibl5/migrations/NNN_franchise_summary_reads_season_records.sql` (example) — NEW.\n"
    "Copy `ibl5/migrations/174_derive_conference_champions_from_playoffs.sql`.\n"
    "Guard idiom from `ibl5/migrations/151_downsize_ibl_draft_team.sql`.\n"
)


def test_migration_placeholder_example_token_enters_evidence():
    """Mutation caught: drop the `and not _PLACEHOLDER_TOKEN_RE.match(tok)` clause."""
    paths = planfile._phase_evidence_paths(_FRANCHISE_BODY)
    assert "ibl5/migrations/NNN_franchise_summary_reads_season_records.sql" in paths
    assert "bin/next-migration" in paths
    assert "ibl5/migrations/174_derive_conference_champions_from_playoffs.sql" in paths
    assert "ibl5/migrations/151_downsize_ibl_draft_team.sql" in paths


def test_adr_placeholder_example_token_enters_evidence():
    """Mutation caught: delete the ADR alternative from `_PLACEHOLDER_TOKEN_RE`."""
    body = "Write `ibl5/docs/decisions/NNNN-postplan-placeholder.md` (example) — NEW."
    assert planfile._phase_evidence_paths(body) == [
        "ibl5/docs/decisions/NNNN-postplan-placeholder.md"]


def test_placeholder_shape_outside_its_directory_stays_skipped():
    """Mutation caught: drop the directory prefixes from `_PLACEHOLDER_TOKEN_RE`."""
    body = ("Add `ibl5/tests/fixtures/NNN_seed.sql` (example) and "
            "`docs/NNNN-note.md` (example).")
    assert planfile._phase_evidence_paths(body) == []


def test_lowercase_or_five_n_placeholder_stays_skipped():
    """Mutation caught: change `N{3,4}` to `N+`, or compile the regex with `re.I`."""
    body = ("Add `ibl5/migrations/nnn_seed.sql` (example), "
            "`ibl5/migrations/NNNNN_seed.sql` (example) and "
            "`ibl5/docs/decisions/NNN-short.md` (example).")
    assert planfile._phase_evidence_paths(body) == []


def test_placeholder_without_example_marker_is_unchanged():
    """Mutation caught: invert the `and not` so every placeholder is skipped."""
    body = "Create `ibl5/migrations/NNN_seed.sql` — NEW."
    assert planfile._phase_evidence_paths(body) == ["ibl5/migrations/NNN_seed.sql"]
