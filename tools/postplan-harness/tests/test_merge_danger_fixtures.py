"""Replay the migration sections of three live PRs through merge-danger trigger 1.

PR #3513 and #3495 were held one-way by comment text alone; PR #3472 is the additive control.
The fixtures are the `_migration_sections` slices of each PR diff, captured once with
`gh pr diff`. Tests never call `gh`.
"""
import os
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger as md
from harness.gate_backtest import changes_from_unified_diff

FIXTURES = Path(__file__).parent / "fixtures" / "merge-danger-migrations"
NAMES = ("pr3513.diff", "pr3495.diff", "pr3472.diff")


def _none(_path):
    return None


def _replay_text(text):
    changed = changes_from_unified_diff(text)
    return md.derive_merge_danger(changed, text, [], _none)


def _replay(name):
    return _replay_text((FIXTURES / name).read_text(encoding="utf-8"))


@pytest.mark.parametrize("name", NAMES)
def test_fixture_has_one_migration_header(name):
    text = (FIXTURES / name).read_text(encoding="utf-8")
    headers = list(md._DIFF_HEADER_RE.finditer(text))
    assert len(headers) == 1
    assert md._MIGRATION_RE.match(headers[0].group(1))


def test_pr3513_comment_only_drop_is_two_way():
    d = _replay("pr3513.diff")
    assert d.door == "two-way"
    assert d.triggers == ()


def test_pr3495_rollback_comment_is_two_way():
    d = _replay("pr3495.diff")
    assert d.door == "two-way"
    assert d.triggers == ()


def test_pr3472_additive_migration_is_two_way():
    d = _replay("pr3472.diff")
    assert d.door == "two-way"
    assert d.blast == "site-wide"
    # Additive DDL stays below the floor. PR #3472 widens an ENUM and creates a table; an
    # added column is the same class. Appending `ADD\s+COLUMN` to DESTRUCTIVE_SQL turns this red.
    text = (FIXTURES / "pr3472.diff").read_text(encoding="utf-8")
    if not text.endswith("\n"):
        text += "\n"
    d = _replay_text(text + "+ALTER TABLE `ibl_bug_reports` ADD COLUMN `note` TEXT NULL;\n")
    assert d.door == "two-way"
    assert d.triggers == ()


@pytest.mark.parametrize("name", NAMES)
def test_fixture_with_a_real_drop_is_still_one_way(name):
    text = (FIXTURES / name).read_text(encoding="utf-8")
    if not text.endswith("\n"):
        text += "\n"
    d = _replay_text(text + "+ALTER TABLE ibl_plr DROP COLUMN foo;\n")
    assert d.door == "one-way"
    assert any(t.startswith("destructive-migration:ibl5/migrations/") for t in d.triggers)
