"""Real-PR replays for the merge-danger notify trigger, plus its stay-one-way cases."""
import os
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import merge_danger as md
from test_workflow_scope import _MAIN_LIKE, _line_of

FIX = Path(__file__).parent / "workflow_replay"
MAIN = ".github/workflows/main.yml"
OLD = ".github/workflows/old.yml"
NEW = ".github/workflows/new.yml"
RENAME_META = f"diff --git a/{OLD} b/{NEW}\nsimilarity index 90%\nrename from {OLD}\nrename to {NEW}\n"


def _replay(*items: tuple[str, str]) -> md.MergeDanger:
    diff = "".join((FIX / f"{stem}.diff").read_text() for stem, _ in items)
    texts = {wf: (FIX / f"{stem}.yml.txt").read_text() for stem, wf in items}
    for wf, text in texts.items():
        assert md._NOTIFY_TEXT_RE.search(text)        # precondition holds: not a vacuous pass
        assert md._section_for(diff, wf) is not None  # the section is found
    return md.derive_merge_danger([("M", wf) for wf in texts], diff, [], texts.get)


def _assert_two_way(d: md.MergeDanger) -> None:
    assert d.door == "two-way"
    assert not any(t.startswith("notify-workflow:") for t in d.triggers)


def _main_with_diff(diff: str) -> md.MergeDanger:
    text = (FIX / "pr3576-main.yml.txt").read_text()
    return md.derive_merge_danger([("M", MAIN)], diff, [], {MAIN: text}.get)


def _assert_main_one_way(d: md.MergeDanger) -> None:
    assert d.door == "one-way"
    assert f"notify-workflow:{MAIN}" in d.triggers


def _renamed(section: str) -> md.MergeDanger:
    return md.derive_merge_danger([("R", f"{OLD} → {NEW}")], section, [], {NEW: _MAIN_LIKE}.get)


def test_replay_pr3718_runs_on_pins_are_two_way():
    _assert_two_way(_replay(("pr3718-main", MAIN), ("pr3718-db-backup", ".github/workflows/db-backup.yml")))


def test_replay_pr3652_mutation_concurrency_is_two_way():
    _assert_two_way(_replay(("pr3652-mutation", ".github/workflows/mutation.yml")))


def test_replay_pr3576_opcache_step_is_two_way():
    _assert_two_way(_replay(("pr3576-main", MAIN)))


def test_empty_diff_keeps_notify_workflow_one_way():
    _assert_main_one_way(_main_with_diff(""))


def test_quoted_path_header_keeps_notify_workflow_one_way():
    lines = (FIX / "pr3576-main.diff").read_text().split("\n")
    lines[0] = f'diff --git "a/{MAIN}" "b/{MAIN}"'
    _assert_main_one_way(_main_with_diff("\n".join(lines)))


def test_bare_hunk_keeps_notify_workflow_one_way():
    diff = (FIX / "pr3576-main.diff").read_text()
    _assert_main_one_way(_main_with_diff(diff[diff.index("@@"):]))


def test_renamed_workflow_with_send_edit_is_one_way():
    n = _line_of(_MAIN_LIKE, "if: ${{ always()")
    new_line = _MAIN_LIKE.split("\n")[n - 1]
    hunk = f"@@ -{n} +{n} @@\n-    if: ${{{{ failure() }}}}\n+{new_line}\n"
    d = _renamed(RENAME_META + f"--- a/{OLD}\n+++ b/{NEW}\n" + hunk)
    assert d.door == "one-way"
    assert f"notify-workflow:{NEW}" in d.triggers


def test_renamed_workflow_without_changes_is_two_way():
    _assert_two_way(_renamed(RENAME_META))


def test_migration_sections_keeps_only_migration_files():
    docs = "diff --git a/docs/a.md b/docs/a.md\n--- a/docs/a.md\n+++ b/docs/a.md\n@@ -1 +1 @@\n-a\n+b\n"
    mig = "ibl5/migrations/200_x.sql"
    migration = f"diff --git a/{mig} b/{mig}\n--- a/{mig}\n+++ b/{mig}\n@@ -1 +1 @@\n-a\n+b\n"
    workflow = f"diff --git a/{MAIN} b/{MAIN}\n--- a/{MAIN}\n+++ b/{MAIN}\n@@ -1 +1 @@\n-a\n+b\n"
    assert md._migration_sections(docs + migration + workflow) == migration


def test_migration_sections_searches_bare_hunk_whole():
    bare = "@@ -0,0 +1 @@\n+DROP TABLE x;\n"
    assert md._migration_sections(bare) == bare
