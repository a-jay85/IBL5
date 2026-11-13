"""Replay three merged data-migration PRs through merge-danger trigger 1 and the revert proof.

PR #3880, #3806 and #3861 each shipped a data migration with a manual undo script under
`ibl5/scripts/undo/`. The migration runner pairs a down file by stem (`<stem>.down.sql` beside
the up file), so none of those undo scripts counts as a down file and the proof reports
`down-file-missing` for all three. Since the owner redesign (2026-11-12) the proof also refuses
any table site code writes after merge. Every table these migrations rewrote is one, so all
three stay one-way even with a perfect down file. The fixtures are the full `gh pr diff` output of each PR,
captured once. Tests never call `gh`.
"""
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger as md
from harness import migration_revert_proof as mrp
from harness.gate_backtest import changes_from_unified_diff

FIXTURES = Path(__file__).parent / "fixtures" / "merge-danger-migrations"

CASES = {
    "pr3880.diff": "ibl5/migrations/212_asw_2008_allstar_winners_mvps.sql",
    "pr3806.diff": "ibl5/migrations/207_gm_tenure_trigger_display_name.sql",
    "pr3861.diff": "ibl5/migrations/210_asw_stale_rows_allstar_awards_backfill.sql",
}

# The mechanical door each fixture derived on master before this change (Phase 1 replay).
DOORS = {"pr3880.diff": "two-way", "pr3806.diff": "two-way", "pr3861.diff": "two-way"}


def _none(_path):
    return None


def _replay_text(text):
    changed = changes_from_unified_diff(text)
    return md.derive_merge_danger(changed, text, [], _none)


def _text(name):
    return (FIXTURES / name).read_text(encoding="utf-8")


def _inject_truncate(text, up_path):
    """Add one TRUNCATE line as the first added line of the up migration's hunk."""
    i = text.index(f"+++ b/{up_path}\n")
    j = text.index("\n", text.index("\n@@", i) + 1) + 1
    return text[:j] + "+TRUNCATE TABLE ibl_awards;\n" + text[j:]


@pytest.mark.parametrize("name", sorted(CASES))
def test_backtest_fixture_has_no_destructive_trigger(name):
    d = _replay_text(_text(name))
    assert [t for t in d.triggers if t.startswith("destructive-migration:")] == []
    assert d.door == DOORS[name]


@pytest.mark.parametrize("name", sorted(CASES))
def test_backtest_proof_reports_down_file_missing(name):
    assert mrp.prove_revertible(CASES[name], _text(name), _none).reason == "down-file-missing"


@pytest.mark.parametrize("name", sorted(CASES))
def test_backtest_with_injected_truncate_stays_one_way(name):
    up = CASES[name]
    d = _replay_text(_inject_truncate(_text(name), up))
    assert d.door == "one-way"
    assert f"destructive-migration:{up}" in d.triggers


# The live tables each PR's migration rewrote. Site code writes every one of them after merge,
# so even a perfect backup-and-restore down file would leave these PRs one-way.
LIVE_TABLES = {
    "pr3880.diff": ("ibl_awards", "ibl_jsb_allstar_rosters"),
    "pr3806.diff": ("ibl_gm_tenures", "ibl_gm_history"),
    "pr3861.diff": ("ibl_awards", "ibl_jsb_allstar_rosters", "ibl_jsb_allstar_scores"),
}


def _added(path, *rows):
    head = [f"diff --git a/{path} b/{path}", "new file mode 100644", "--- /dev/null",
            f"+++ b/{path}", "@@ -0,0 +1 @@"]
    return "\n".join(head + [f"+{r}" for r in rows]) + "\n"


def _proven_shape_on(table):
    """The reason the proof gives a full backup-and-restore pair on `table`."""
    up_path, down_path = "ibl5/migrations/900_x.sql", "ibl5/migrations/900_x.down.sql"
    diff = (_added(up_path, f"CREATE TABLE {table}_bk AS SELECT * FROM {table};", f"TRUNCATE TABLE {table};")
            + _added(down_path, f"-- restores: {table}", f"DELETE FROM {table};",
                     f"INSERT INTO {table} SELECT * FROM {table}_bk;"))

    def read(path):
        return " ".join(mrp.ROUNDTRIP_MARKERS) if path == mrp.ROUNDTRIP_TEST else None

    return mrp.prove_revertible(up_path, diff, read).reason


@pytest.mark.parametrize("table", sorted({t for ts in LIVE_TABLES.values() for t in ts}))
def test_backtest_live_tables_are_site_written(table):
    assert _proven_shape_on(table) == f"site-writes:{table}"
