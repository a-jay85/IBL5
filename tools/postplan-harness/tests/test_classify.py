import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.classify import classify, files_from_diff, filter_diff

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
