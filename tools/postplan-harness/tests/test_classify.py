import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import manual_testing_clearance
from harness.classify import (classify, files_from_diff, filter_diff,
                               FILES_CHANGED_BEGIN, FILES_CHANGED_END,
                               name_status_from_diff, render_files_changed,
                               upsert_files_changed)

SYN_DIFF = """diff --git a/ibl5/foo.php b/ibl5/foo.php
index 111..222 100644
--- a/ibl5/foo.php
+++ b/ibl5/foo.php
@@ -1,3 +1,5 @@
+// guard: never trust tid as string
+$x = 1;
diff --git a/ibl5/migrations/099_drop.sql b/ibl5/migrations/099_drop.sql
new file mode 100644
--- /dev/null
+++ b/ibl5/migrations/099_drop.sql
@@ -0,0 +1,1 @@
+ALTER TABLE ibl_players DROP COLUMN legacy;
diff --git a/composer.lock b/composer.lock
index 333..444 100644
--- a/composer.lock
+++ b/composer.lock
@@ -1,1 +1,1 @@
+{"hash": "x"}
diff --git a/ibl5/tests/e2e/trades/trade.spec.ts b/ibl5/tests/e2e/trades/trade.spec.ts
index 555..666 100644
--- a/ibl5/tests/e2e/trades/trade.spec.ts
+++ b/ibl5/tests/e2e/trades/trade.spec.ts
@@ -1,1 +1,2 @@
+await expect(page.locator('h1')).toBeVisible();
"""


def test_files_and_flags():
    files = files_from_diff(SYN_DIFF)
    assert files == ["ibl5/foo.php", "ibl5/migrations/099_drop.sql", "composer.lock",
                     "ibl5/tests/e2e/trades/trade.spec.ts"]
    cls = classify(files, SYN_DIFF, modified_files=["ibl5/foo.php"])
    assert cls.count_php == 1 and cls.has_php
    assert cls.count_migration == 1 and cls.has_migration and not cls.migration_only
    assert cls.count_lock == 1
    # modules come from content refs (modules/<name>/ or modules.php?name=),
    # not the spec's directory name — matching the skill's grep
    assert cls.has_e2e_specs and cls.e2e_spec_modules == []
    assert cls.has_modified and cls.has_comments_in_diff
    assert not cls.docs_only and not cls.non_code_only


def test_filter_strips_migrations_and_locks():
    filtered = filter_diff(SYN_DIFF)
    assert "DROP COLUMN" not in filtered
    assert "composer.lock" not in filtered
    assert "ibl5/foo.php" in filtered and "trade.spec.ts" in filtered


def test_docs_only():
    d = ("diff --git a/ibl5/docs/x.md b/ibl5/docs/x.md\n--- a/ibl5/docs/x.md\n"
         "+++ b/ibl5/docs/x.md\n@@ -1 +1 @@\n+hello\n")
    cls = classify(files_from_diff(d), d, modified_files=["ibl5/docs/x.md"])
    assert cls.docs_only and cls.non_code_only and not cls.has_php


def test_golden_and_engine_only():
    d = ("diff --git a/engine/internal/sim/testdata/golden.json b/engine/internal/sim/testdata/golden.json\n"
         "--- a/engine/internal/sim/testdata/golden.json\n+++ b/engine/internal/sim/testdata/golden.json\n"
         "@@ -1 +1 @@\n+{}\n"
         "diff --git a/engine/internal/sim/sim.go b/engine/internal/sim/sim.go\n"
         "--- a/engine/internal/sim/sim.go\n+++ b/engine/internal/sim/sim.go\n@@ -1 +1 @@\n+package sim\n")
    cls = classify(files_from_diff(d), d, modified_files=[])
    assert cls.golden_changed and cls.has_go and cls.engine_only


def test_real_fixture_parity_request_event_logging():
    """Flags must match the historical Phase-3 classify block for PR #1425."""
    path = os.path.join(os.path.dirname(__file__), "..",
                        "fixtures/scenarios/request-event-logging/fixture.json")
    if not os.path.exists(path):
        pytest.skip("replay fixture absent (gitignored; regenerate via ./run replay): "
                    "request-event-logging")
    fx = json.load(open(path))
    cls = classify(fx["files"], fx["diff"], fx.get("modified_files"))
    # historical: total=8 php=7 migration=1 test=4 HAS_PHP=true HAS_MODIFIED=true
    #             HAS_COMMENTS_IN_DIFF=true LINES_PHP_CHANGED=410 (post-filter diff 20259B)
    assert cls.count_total == 8 and cls.count_php == 7
    assert cls.count_migration == 1 and cls.count_test == 4
    assert cls.has_php and cls.has_modified and cls.has_comments_in_diff
    assert not cls.migration_only and not cls.non_code_only
    # historical LINES_PHP_CHANGED=410 was measured mid-run; the rebuilt diff is
    # at final PR head (post-review commits included), so assert gate-equivalence
    # (the only thing the number drives is the >50 agent-launch threshold)
    assert cls.lines_php_changed > 50


def test_e2e_module_from_content_refs():
    d = ("diff --git a/ibl5/tests/e2e/trades/trade.spec.ts b/ibl5/tests/e2e/trades/trade.spec.ts\n"
         "--- a/ibl5/tests/e2e/trades/trade.spec.ts\n+++ b/ibl5/tests/e2e/trades/trade.spec.ts\n"
         "@@ -1 +1,2 @@\n+await page.goto('/ibl5/modules.php?name=Trading');\n")
    cls = classify(files_from_diff(d), d, modified_files=[])
    assert cls.e2e_spec_modules == ["Trading"]


# ---------------------------------------------------------------------------
# New tests: name_status_from_diff / render_files_changed / upsert_files_changed
# ---------------------------------------------------------------------------

NAME_STATUS_DIFF = """\
diff --git a/ibl5/added.php b/ibl5/added.php
new file mode 100644
--- /dev/null
+++ b/ibl5/added.php
@@ -0,0 +1,1 @@
+<?php echo 'new';
diff --git a/ibl5/modified.php b/ibl5/modified.php
index aaa..bbb 100644
--- a/ibl5/modified.php
+++ b/ibl5/modified.php
@@ -1,1 +1,2 @@
+$x = 1;
diff --git a/ibl5/deleted.php b/ibl5/deleted.php
deleted file mode 100644
--- a/ibl5/deleted.php
+++ /dev/null
@@ -1,1 +0,0 @@
-$old = 1;
diff --git a/ibl5/old-name.php b/ibl5/new-name.php
similarity index 90%
rename from ibl5/old-name.php
rename to ibl5/new-name.php
--- a/ibl5/old-name.php
+++ b/ibl5/new-name.php
@@ -1,1 +1,1 @@
-old
+new
"""


def test_name_status_from_diff():
    pairs = name_status_from_diff(NAME_STATUS_DIFF)
    assert pairs == [
        ("A", "ibl5/added.php"),
        ("M", "ibl5/modified.php"),
        ("D", "ibl5/deleted.php"),
        ("R", "ibl5/old-name.php → ibl5/new-name.php"),
    ]


def test_render_files_changed():
    block = render_files_changed(NAME_STATUS_DIFF)
    assert FILES_CHANGED_BEGIN in block
    assert FILES_CHANGED_END in block
    assert "- `A` `ibl5/added.php`" in block
    assert "- `M` `ibl5/modified.php`" in block
    assert "- `D` `ibl5/deleted.php`" in block
    assert "- `R` `ibl5/old-name.php → ibl5/new-name.php`" in block
    # markers must be the outer bounds
    assert block.startswith(FILES_CHANGED_BEGIN)
    assert block.endswith(FILES_CHANGED_END)


def test_upsert_files_changed_replace():
    """Surrounding prose is byte-identical after a replace-between-markers upsert."""
    block_v1 = (f"{FILES_CHANGED_BEGIN}\n**Files changed** ...:\n\n- `M` `foo.php`\n"
                f"{FILES_CHANGED_END}")
    block_v2 = (f"{FILES_CHANGED_BEGIN}\n**Files changed** ...:\n\n- `A` `bar.php`\n"
                f"{FILES_CHANGED_END}")
    before = "preamble text\n\n"
    after = "\n\ntrailing text"
    body_with_v1 = before + block_v1 + after

    result = upsert_files_changed(body_with_v1, block_v2)

    assert result.startswith(before)
    assert result.endswith(after)
    assert block_v2 in result
    assert block_v1 not in result
    # only one begin marker
    assert result.count(FILES_CHANGED_BEGIN) == 1


def test_upsert_files_changed_append_when_absent():
    """Block is appended when neither marker is present."""
    body = "some PR prose without any markers"
    block = render_files_changed(NAME_STATUS_DIFF)
    result = upsert_files_changed(body, block)
    assert result.startswith(body.rstrip())
    assert block in result
    assert result.count(FILES_CHANGED_BEGIN) == 1


def test_upsert_files_changed_orphan_begin():
    """Orphan BEGIN: the orphan survives and a fresh complete block is appended."""
    orphan = FILES_CHANGED_BEGIN + "\nsome stale content without an end marker"
    block = render_files_changed(NAME_STATUS_DIFF)
    result = upsert_files_changed(orphan, block)
    # orphan begin marker still present (untouched)
    assert orphan.rstrip() in result
    # fresh complete block also present
    assert block in result
    # end marker appears (from the appended block)
    assert FILES_CHANGED_END in result


def test_upsert_files_changed_orphan_end():
    """Orphan END: the orphan survives and a fresh complete block is appended."""
    orphan = "some body\n" + FILES_CHANGED_END + "\nmore text"
    block = render_files_changed(NAME_STATUS_DIFF)
    result = upsert_files_changed(orphan, block)
    # orphan end marker still present (untouched)
    assert FILES_CHANGED_END in result
    # the orphan text itself survives
    assert "some body" in result
    # the complete fresh block is also appended
    assert block in result
    # begin marker appears (from the appended block)
    assert FILES_CHANGED_BEGIN in result


def test_upsert_files_changed_idempotent():
    """upsert(upsert(body, b), b) == upsert(body, b) on a normal body."""
    body = "## Summary\n\nAdds widget support.\n\n## Manual Testing\n\nNo manual testing needed.\n"
    block = render_files_changed(NAME_STATUS_DIFF)
    once = upsert_files_changed(body, block)
    twice = upsert_files_changed(once, block)
    assert once == twice


# ---------------------------------------------------------------------------
# Predicate-safety test
# ---------------------------------------------------------------------------

_ADVERSARIAL_DIFF = """\
diff --git a/a b/src/Depends-on: badge-data.ts
new file mode 100644
--- /dev/null
+++ b/src/Depends-on: badge-data.ts
@@ -0,0 +1,1 @@
+export const x = 1;
diff --git a/b b/docs/manual testing guide.md
index 111..222 100644
--- a/docs/manual testing guide.md
+++ b/docs/manual testing guide.md
@@ -1,1 +1,2 @@
+updated
diff --git a/c b/src/## chapter-header.ts
new file mode 100644
--- /dev/null
+++ b/src/## chapter-header.ts
@@ -0,0 +1,1 @@
+export const y = 2;
"""


def test_predicate_safety():
    """The files-changed block must not corrupt the two key shell/harness predicates.

    Proves:
    1. manual_testing_clearance() returns the same value whether or not the block
       is present, for CLEARED, HELD, and UNKNOWN bodies.
    2. No rendered line starts with '## ' or 'Depends-on:' at column 0 — the
       safety is structural (every rendered line begins with '<!--', '**', or
       '- '), so no adversarial path substring can ever reach column 0.
    """
    block = render_files_changed(_ADVERSARIAL_DIFF)

    # Verify the block contains the adversarial path substrings (so the test is live)
    assert "Depends-on:" in block
    assert "manual testing" in block
    assert "##" in block

    # No line starts with '## ' or 'Depends-on:' at column 0
    for line in block.splitlines():
        assert not line.startswith("## "), f"line starts with '## ': {line!r}"
        assert not line.startswith("Depends-on:"), f"line starts with 'Depends-on:': {line!r}"

    # CLEARED body: manual_testing_clearance unchanged by block
    cleared_body = (
        "## Summary\n\nSome changes.\n\n"
        "## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
    )
    assert manual_testing_clearance(cleared_body) == "CLEARED"
    assert manual_testing_clearance(cleared_body + "\n\n" + block + "\n") == "CLEARED"

    # HELD body: manual_testing_clearance unchanged by block
    held_body = (
        "## Summary\n\nSome changes.\n\n"
        "## Manual Testing\n\n- [ ] Verify the widget renders.\n"
    )
    assert manual_testing_clearance(held_body) == "HELD"
    assert manual_testing_clearance(held_body + "\n\n" + block + "\n") == "HELD"

    # UNKNOWN body: manual_testing_clearance unchanged by block
    unknown_body = "## Summary\n\nSome changes.\n"
    assert manual_testing_clearance(unknown_body) == "UNKNOWN"
    assert manual_testing_clearance(unknown_body + "\n\n" + block + "\n") == "UNKNOWN"

    # The runner's real ordering puts the block BEFORE `## Manual Testing` (it is
    # written at PR creation; the sentinel is appended in Phase 6). Cover that too.
    assert manual_testing_clearance(
        upsert_files_changed(unknown_body, block)
        + "\n\n## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
    ) == "CLEARED"
    assert manual_testing_clearance(
        upsert_files_changed(unknown_body, block)
        + "\n\n## Manual Testing\n\n- [ ] Verify the widget renders.\n"
    ) == "HELD"
