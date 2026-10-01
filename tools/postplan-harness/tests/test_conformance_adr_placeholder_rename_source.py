"""Conformance tests for the ADR `NNNN` placeholder token and rename-source paths.

Each test is named for the mutation it kills. Kept out of test_conformance_matching.py
because a peer branch owns that file.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import ReplayGit
from harness.conformance import check
from harness.state import PlanInfo


def _plan_with_critical(path: str, exempt: bool = False) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, critical_files=[(path, "", exempt)])


_TOK = "ibl5/docs/decisions/NNNN-discord-dev-webhook-notify.md"


def _missing(items, needle):
    return any("MISSING-FILE" in i and needle in i for i in items)


def test_adr_placeholder_zero_same_slug_hits_still_missing():
    """Mutation caught: drop the `n.group("suffix") == suffix` clause."""
    items = check(_plan_with_critical(_TOK), ["ibl5/docs/decisions/0146-some-other-slug.md"])
    assert _missing(items, "NNNN-discord-dev-webhook-notify.md")


def test_adr_placeholder_two_same_slug_hits_still_missing():
    """Mutation caught: change `len(hits) == 1` to `hits[0] if hits else None`."""
    items = check(_plan_with_critical(_TOK), [
        "ibl5/docs/decisions/0146-discord-dev-webhook-notify.md",
        "ibl5/docs/decisions/0147-discord-dev-webhook-notify.md",
    ])
    assert _missing(items, "NNNN-discord-dev-webhook-notify.md")


def test_adr_placeholder_outside_decisions_dir_gets_no_tolerance():
    """Mutation caught: loosen `_ADR_TOKEN`'s directory prefix to `.*/`."""
    tok = "ibl5/docs/NNNN-discord-dev-webhook-notify.md"
    items = check(_plan_with_critical(tok),
                  ["ibl5/docs/0146-discord-dev-webhook-notify.md"])
    assert _missing(items, "NNNN-discord-dev-webhook-notify.md")


def test_adr_placeholder_wrong_case_gets_no_tolerance():
    """Mutation caught: add `re.IGNORECASE` to `_ADR_TOKEN`."""
    tok = "ibl5/docs/decisions/nnnn-discord-dev-webhook-notify.md"
    items = check(_plan_with_critical(tok),
                  ["ibl5/docs/decisions/0146-discord-dev-webhook-notify.md"])
    assert _missing(items, "nnnn-discord-dev-webhook-notify.md")


def test_adr_placeholder_five_n_gets_no_tolerance():
    """Mutation caught: `N+` in `_ADR_TOKEN`."""
    tok = "ibl5/docs/decisions/NNNNN-discord-dev-webhook-notify.md"
    items = check(_plan_with_critical(tok),
                  ["ibl5/docs/decisions/0146-discord-dev-webhook-notify.md"])
    assert _missing(items, "NNNNN-discord-dev-webhook-notify.md")


def test_adr_letter_token_other_than_nnnn_gets_no_tolerance():
    """Mutation caught: widen the token class to `\\w{4}` or `[\\dN]{4}`."""
    tok = "ibl5/docs/decisions/ABCD-discord-dev-webhook-notify.md"
    items = check(_plan_with_critical(tok),
                  ["ibl5/docs/decisions/0146-discord-dev-webhook-notify.md"])
    assert _missing(items, "ABCD-discord-dev-webhook-notify.md")


def test_adr_placeholder_changed_side_stays_four_digits():
    """Mutation caught: use `_ADR_TOKEN` in the changed-file loop of `_renumbered_adr`."""
    items = check(_plan_with_critical(_TOK), [
        "ibl5/docs/decisions/NNNN-other.md",
        "ibl5/docs/decisions/MMMM-discord-dev-webhook-notify.md",
    ])
    assert _missing(items, "NNNN-discord-dev-webhook-notify.md")


def test_adr_placeholder_genuinely_missing_adr_still_missing():
    """Mutation caught: return `changed_files[0]` when the token matches."""
    items = check(_plan_with_critical(_TOK), [
        "tools/postplan-harness/harness/conformance.py",
        "ibl5/docs/decisions/README.md",
    ])
    assert _missing(items, "NNNN-discord-dev-webhook-notify.md")


def test_adr_placeholder_does_not_leak_into_migration_tier():
    """Mutation caught: add `|NNN` to `_MIGRATION_RENUMBER`."""
    tok = "ibl5/migrations/NNN_add_thing.sql"
    items = check(_plan_with_critical(tok), ["ibl5/migrations/123_add_thing.sql"])
    assert _missing(items, "NNN_add_thing.sql")


RENAME_DIFF = """\
diff --git a/.claude/agents/sonnet-4-6.md b/.claude/agents/sonnet-5-5.md
similarity index 67%
rename from .claude/agents/sonnet-4-6.md
rename to .claude/agents/sonnet-5-5.md
--- a/.claude/agents/sonnet-4-6.md
+++ b/.claude/agents/sonnet-5-5.md
@@ -1,1 +1,1 @@
-old
+new
diff --git a/ibl5/gone.php b/ibl5/gone.php
deleted file mode 100644
--- a/ibl5/gone.php
+++ /dev/null
@@ -1,1 +0,0 @@
-$old = 1;
"""

NO_RENAME_DIFF = """\
diff --git a/.claude/agents/sonnet-5-5.md b/.claude/agents/sonnet-5-5.md
new file mode 100644
--- /dev/null
+++ b/.claude/agents/sonnet-5-5.md
@@ -0,0 +1,1 @@
+new
"""


def test_replay_conformance_files_has_rename_source_target_and_deleted():
    """Mutation caught: return `files_from_diff` alone."""
    got = ReplayGit({"slug": "x", "diff": RENAME_DIFF}).conformance_files()
    assert ".claude/agents/sonnet-4-6.md" in got
    assert ".claude/agents/sonnet-5-5.md" in got
    assert "ibl5/gone.php" in got


def test_replay_changed_files_still_b_side_only():
    """Mutation caught: widen `files_from_diff`."""
    got = ReplayGit({"slug": "x", "diff": RENAME_DIFF}).changed_files()
    assert got == [".claude/agents/sonnet-5-5.md", "ibl5/gone.php"]


def test_replay_conformance_files_builds_on_overridden_changed_files():
    """Mutation caught: compute `conformance_files` from `files_from_diff` directly
    instead of `self.changed_files`, which breaks the three existing test fakes."""
    class _Fake(ReplayGit):
        def changed_files(self, base="origin/master"):
            return ["ibl5/x.php"]

    got = _Fake({"slug": "x", "diff": RENAME_DIFF}).conformance_files()
    assert got == ["ibl5/x.php", ".claude/agents/sonnet-4-6.md"]


def test_rename_source_named_but_file_absent_still_missing():
    """Mutation caught: make `rename_sources_from_diff` also return a-side paths of
    `new file mode` blocks."""
    files = ReplayGit({"slug": "x", "diff": NO_RENAME_DIFF}).conformance_files()
    items = check(_plan_with_critical(".claude/agents/sonnet-4-6.md"), files)
    assert _missing(items, "sonnet-4-6.md")


def test_rename_source_resolves_critical_file():
    """PR #2514 acceptance at adapter level."""
    files = ReplayGit({"slug": "x", "diff": RENAME_DIFF}).conformance_files()
    assert check(_plan_with_critical(".claude/agents/sonnet-4-6.md"), files) == []
