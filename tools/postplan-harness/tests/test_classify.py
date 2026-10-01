import ast
import json
import os
import pathlib
import re
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import (SENTINEL_RE, _manual_section, all_rows_ticked,
                             manual_testing_clearance)
from harness.classify import (_manual_testing_span, classify, files_from_diff, filter_diff,
                               is_gm_visible_path,
                               FILES_CHANGED_BEGIN, FILES_CHANGED_END, MANUAL_TESTING_SENTINEL,
                               MANUAL_TESTING_SENTINEL_STATIC,
                               name_status_from_diff, qualify_backlog_refs,
                               render_files_changed,
                               render_reviewer_verification,
                               retro_registry_row_from_diff,
                               REVIEWER_VERIFICATION_BEGIN, REVIEWER_VERIFICATION_END,
                               slice_agent_e_diff,
                               restore_manual_testing_section, strip_manual_testing_section,
                               upsert_files_changed,
                               upsert_reviewer_verification)
from harness.planfile import parse_hold_justification, split_hold_justification

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.dirname(os.path.abspath(__file__)))))
HOLD_CHECK_LIB = os.path.join(REPO_ROOT, "bin", "lib", "hold-check.sh")

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


SHELL_DIFF = """diff --git a/bin/my-script b/bin/my-script
index 111..222 100755
--- a/bin/my-script
+++ b/bin/my-script
@@ -1,3 +1,6 @@
+echo "added line one"
+echo "added line two"
+echo "added line three"
diff --git a/.github/workflows/main.yml b/.github/workflows/main.yml
new file mode 100644
--- /dev/null
+++ b/.github/workflows/main.yml
@@ -0,0 +1,2 @@
+on: push
+jobs: {}
"""


def test_shell_and_workflow_flags():
    files = files_from_diff(SHELL_DIFF)
    cls = classify(files, SHELL_DIFF, modified_files=["bin/my-script"])
    assert cls.has_shell is True
    assert cls.has_workflow is True
    assert cls.count_shell == 1
    assert cls.count_workflow == 1
    assert cls.lines_shell_changed == 3
    assert cls.has_php is False
    assert cls.non_code_only is False
    sliced = slice_agent_e_diff(cls.filtered_diff)
    assert "bin/my-script" in sliced
    assert ".github/workflows/main.yml" in sliced


SKILL_PROSE_DIFF = """diff --git a/.claude/skills/post-plan/SKILL.md b/.claude/skills/post-plan/SKILL.md
index 111..222 100644
--- a/.claude/skills/post-plan/SKILL.md
+++ b/.claude/skills/post-plan/SKILL.md
@@ -1,1 +1,2 @@
+## Updated section
"""


def test_skill_prose_flag_preserves_non_code_only():
    files = files_from_diff(SKILL_PROSE_DIFF)
    cls = classify(files, SKILL_PROSE_DIFF, modified_files=[".claude/skills/post-plan/SKILL.md"])
    assert cls.has_skill_prose is True
    assert cls.non_code_only is True
    assert cls.docs_only is True
    assert cls.has_shell is False
    assert cls.has_workflow is False


SHELL_NEGATIVE_DIFF = """diff --git a/ibl5/foo.shtml b/ibl5/foo.shtml
index 111..222 100644
--- a/ibl5/foo.shtml
+++ b/ibl5/foo.shtml
@@ -1,1 +1,2 @@
+<p>updated</p>
diff --git a/bin/lib/router.php b/bin/lib/router.php
index 333..444 100644
--- a/bin/lib/router.php
+++ b/bin/lib/router.php
@@ -1,1 +1,2 @@
+<?php return true;
diff --git a/bin/lib/README.md b/bin/lib/README.md
index 555..666 100644
--- a/bin/lib/README.md
+++ b/bin/lib/README.md
@@ -1,1 +1,2 @@
+Updated docs.
"""


def test_shell_negative_excludes_lookalikes():
    files = files_from_diff(SHELL_NEGATIVE_DIFF)
    cls = classify(files, SHELL_NEGATIVE_DIFF, modified_files=files)
    assert cls.has_shell is False
    assert cls.has_workflow is False
    assert cls.has_skill_prose is False
    assert cls.count_shell == 0
    assert slice_agent_e_diff(cls.filtered_diff) == ""


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
        "## Manual Testing\n\nNo manual testing needed — all changes are covered by automated tests.\n"
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
        + "\n\n## Manual Testing\n\nNo manual testing needed — all changes are covered by automated tests.\n"
    ) == "CLEARED"
    assert manual_testing_clearance(
        upsert_files_changed(unknown_body, block)
        + "\n\n## Manual Testing\n\n- [ ] Verify the widget renders.\n"
    ) == "HELD"


# ---------------------------------------------------------------------------
# retro_registry_row_from_diff tests
# ---------------------------------------------------------------------------

_REAL_REGISTRY_ROW = (
    "| 2026-08-14 | #1880 | class: gate escape path conditioned on a git-range query "
    "silently blocks when the range is empty (first-branch-commit), with no null fallback "
    "| routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md "
    "(section: Forced integration-verification trigger): any plan adding or modifying an escape "
    "path in a CI check gate that calls a git-range helper must test the empty-range "
    "(no-prior-commits-on-branch) scenario | prior: -- |"
)

_BACKLOG_FILE = "ibl5/docs/retrospective-class-registry.md"


def _diff_adding_row_in_file(path: str, row: str, leading: str = "+") -> str:
    """Minimal unified diff that adds `row` (prefixed by `leading`) inside `path`'s section."""
    return (
        f"diff --git a/{path} b/{path}\n"
        f"index 111..222 100644\n"
        f"--- a/{path}\n"
        f"+++ b/{path}\n"
        f"@@ -1,1 +1,2 @@\n"
        f" | existing row |\n"
        f"{leading}{row}\n"
    )


def test_retro_registry_row_positive():
    diff = _diff_adding_row_in_file(_BACKLOG_FILE, _REAL_REGISTRY_ROW)
    result = retro_registry_row_from_diff(diff)
    assert result == _REAL_REGISTRY_ROW


def test_retro_registry_row_positive_via_classify():
    diff = _diff_adding_row_in_file(_BACKLOG_FILE, _REAL_REGISTRY_ROW)
    files = files_from_diff(diff)
    cls = classify(files, diff, modified_files=[_BACKLOG_FILE])
    assert cls.retro_registry_row == _REAL_REGISTRY_ROW


def test_retro_registry_row_negative_wrong_file():
    diff = _diff_adding_row_in_file("ibl5/docs/backlog/other-backlog.md", _REAL_REGISTRY_ROW)
    assert retro_registry_row_from_diff(diff) == ""


def test_retro_registry_row_negative_context_line():
    # The row is present as an unchanged context line (space prefix, not +)
    diff = _diff_adding_row_in_file(_BACKLOG_FILE, _REAL_REGISTRY_ROW, leading=" ")
    assert retro_registry_row_from_diff(diff) == ""


def test_retro_registry_row_negative_unrelated_addition():
    # An ordinary prose addition in the backlog file — no date, no class:, no routed to:
    diff = _diff_adding_row_in_file(_BACKLOG_FILE, "Some prose paragraph about engineering.")
    assert retro_registry_row_from_diff(diff) == ""


def test_retro_registry_row_negative_non_registry_table_row():
    # A table row that lacks both class: and routed to:
    diff = _diff_adding_row_in_file(_BACKLOG_FILE, "| 2026-08-14 | #1880 | ordinary table row |")
    assert retro_registry_row_from_diff(diff) == ""


def test_retro_registry_row_empty_diff():
    assert retro_registry_row_from_diff("") == ""


# ---------------------------------------------------------------------------
# Phase 4 - D3: strip_manual_testing_section
# ---------------------------------------------------------------------------

def test_strip_manual_testing_section_strips_section():
    body = (
        "## Summary\n\nSome changes.\n\n"
        "## Manual Testing\n\n- [ ] Eyeball the layout.\n\n"
        "## Other Section\n\nStill here.\n"
    )
    new_body, stripped = strip_manual_testing_section(body)
    assert stripped is True
    assert "## Manual Testing" not in new_body
    assert "Eyeball the layout" not in new_body
    assert "## Summary" in new_body
    assert "## Other Section" in new_body


def test_strip_manual_testing_section_absent_returns_byte_identical():
    body = "## Summary\n\nNo manual section here.\n\n## Foo\n\nBar.\n"
    new_body, stripped = strip_manual_testing_section(body)
    assert stripped is False
    assert new_body == body


def test_strip_manual_testing_subsection_strips_only_up_to_next_heading():
    body = (
        "## Summary\n\nIntro.\n\n"
        "### Manual Testing\n\nsome steps\n\n"
        "## Summary\n\nConclusion.\n"
    )
    new_body, stripped = strip_manual_testing_section(body)
    assert stripped is True
    assert "some steps" not in new_body
    assert "Conclusion" in new_body


# ---------------------------------------------------------------------------
# restore_manual_testing_section: a fidelity fixer never keeps an edit to it
# ---------------------------------------------------------------------------

_MT_BEFORE = ("## Summary\n\nOld bullet.\n\n## Manual Testing\n\n"
              f"{MANUAL_TESTING_SENTINEL}\n\n## Files changed\n\nx\n")


def test_restore_reverts_2489_reword():
    # The #2489 fixer swapped the sentinel for prose and fixed a Summary bullet.
    after = ("## Summary\n\nNew bullet.\n\n## Manual Testing\n\n"
             "Covered by `bin/test-pr-cycle`.\n\n## Files changed\n\nx\n")
    body, restored = restore_manual_testing_section(after, _MT_BEFORE)
    assert restored is True
    assert "New bullet." in body
    assert "Covered by" not in body
    assert manual_testing_clearance(body) == "CLEARED"


def test_restore_reappends_deleted_section():
    after = "## Summary\n\nNew bullet.\n\n## Files changed\n\nx\n"
    body, restored = restore_manual_testing_section(after, _MT_BEFORE)
    assert restored is True
    assert manual_testing_clearance(body) == "CLEARED"


def test_restore_noop_when_untouched_or_absent_before():
    assert restore_manual_testing_section(_MT_BEFORE, _MT_BEFORE) == (_MT_BEFORE, False)
    plain = "## Summary\n\nx\n"
    assert restore_manual_testing_section(plain, plain) == (plain, False)


def test_sentinel_passes_the_ci_checker():
    # bin/check-pr-manual-testing and pr-armable.sh key on this prefix.
    assert MANUAL_TESTING_SENTINEL.startswith("No manual testing needed")
    assert "verified" not in MANUAL_TESTING_SENTINEL


def test_static_sentinel_passes_the_ci_checker():
    # Same prefix contract as MANUAL_TESTING_SENTINEL: SENTINEL_RE and the shell twins
    # read it as CLEARED, and the tail carries no test-type keyword for the diff scan.
    prefix = "No manual testing needed"
    assert MANUAL_TESTING_SENTINEL_STATIC == (
        "No manual testing needed — verification is static; "
        "the plan's Verification Matrix has no executable rows.")
    assert MANUAL_TESTING_SENTINEL_STATIC.startswith(prefix)
    assert "verified" not in MANUAL_TESTING_SENTINEL_STATIC.lower()
    assert SENTINEL_RE.match(MANUAL_TESTING_SENTINEL_STATIC)
    tail = MANUAL_TESTING_SENTINEL_STATIC[len(prefix):]
    assert not re.search(r"e2e|playwright|unit|phpunit|integration", tail, re.I)
    assert MANUAL_TESTING_SENTINEL_STATIC != MANUAL_TESTING_SENTINEL


def test_no_harness_code_compares_sentinel_by_equality():
    # restore/strip key on the `## Manual Testing` heading, so a second wording must
    # never be compared by text equality anywhere in the harness.
    harness_root = pathlib.Path(__file__).resolve().parents[1]
    files = sorted((harness_root / "harness").glob("*.py")) + [harness_root / "runner.py"]
    names = {"MANUAL_TESTING_SENTINEL", "MANUAL_TESTING_SENTINEL_STATIC"}

    def _is_sentinel(node: ast.AST) -> bool:
        return ((isinstance(node, ast.Name) and node.id in names)
                or (isinstance(node, ast.Attribute) and node.attr in names))

    hits = []
    for path in files:
        tree = ast.parse(path.read_text(encoding="utf-8"), filename=str(path))
        for node in ast.walk(tree):
            if isinstance(node, ast.Compare) and (
                    _is_sentinel(node.left) or any(_is_sentinel(c) for c in node.comparators)):
                hits.append(f"{path.name}:{node.lineno}")
    assert hits == [], f"sentinel compared by equality at: {hits}"


# ---------------------------------------------------------------------------
# restore_manual_testing_section when ## Manual Testing is the LAST section
# (backlog#1188). Invariant for every case: the arming gate's window
# (armable._manual_section) and verdict after restore equal the snapshot's.
# ---------------------------------------------------------------------------

_MT_LAST = ("## Summary\n\nOld bullet.\n\n## Manual Testing\n\n"
            f"{MANUAL_TESTING_SENTINEL}\n")
_MT_LAST_HELD = "## Summary\n\nOld bullet.\n\n## Manual Testing\n\n- [ ] **Row 1** — foo\n"


def _assert_gate_untouched(result: str, before: str) -> None:
    assert _manual_section(result) == _manual_section(before)
    assert manual_testing_clearance(result) == manual_testing_clearance(before)
    b = _manual_testing_span(before)
    r = _manual_testing_span(result)
    assert result[r[0]:r[1]].rstrip("\n") == before[b[0]:b[1]].rstrip("\n")


# --- red until Phase 2 -------------------------------------------------------

def test_restore_keeps_evidence_line_appended_after_last_section():
    after = _MT_LAST + "\nEvidence: `bin/test-pr-cycle` ran green.\n"
    body, restored = restore_manual_testing_section(after, _MT_LAST)
    assert restored is True
    assert "Evidence: `bin/test-pr-cycle` ran green." in body
    assert body.index("Evidence:") < body.index("## Manual Testing")
    _assert_gate_untouched(body, _MT_LAST)


def test_restore_keeps_closes_trailer_appended_after_last_section():
    after = _MT_LAST + "\nCloses a-jay85/IBL5-backlog#1188\n"
    body, restored = restore_manual_testing_section(after, _MT_LAST)
    assert restored is True
    assert "Closes a-jay85/IBL5-backlog#1188" in body
    assert body.index("Closes ") < body.index("## Manual Testing")
    _assert_gate_untouched(body, _MT_LAST)


def test_restore_relocation_is_idempotent():
    after = _MT_LAST + "\nEvidence line.\n"
    once, _ = restore_manual_testing_section(after, _MT_LAST)
    assert "Evidence line." in once
    assert restore_manual_testing_section(once, _MT_LAST) == (once, False)


def test_restore_keeps_append_and_a_following_new_section():
    after = _MT_LAST + "\nEvidence line.\n\n## Notes\n\nfoo\n"
    body, restored = restore_manual_testing_section(after, _MT_LAST)
    assert restored is True
    assert body.index("Evidence line.") < body.index("## Manual Testing")
    assert "\n## Notes\n\nfoo\n" in body
    _assert_gate_untouched(body, _MT_LAST)


def test_restore_snapshot_without_trailing_newline_never_glues_next_heading():
    before = _MT_LAST.rstrip("\n")
    after = before + "\n\nEvidence line.\n\n## Notes\n\nfoo"
    body, restored = restore_manual_testing_section(after, before)
    assert restored is True
    assert "Evidence line." in body
    assert "\n## Notes\n" in body
    _assert_gate_untouched(body, before)


def test_restore_counterfeit_level3_heading_with_sentinel_stays_held():
    # `### ` does not break the gate window (armable._manual_section breaks on
    # `^## ` only), so the appended sentinel would be gate input if it survived.
    after = _MT_LAST_HELD + f"\n### Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n"
    assert manual_testing_clearance(_MT_LAST_HELD) == "HELD"
    body, _ = restore_manual_testing_section(after, _MT_LAST_HELD)
    assert manual_testing_clearance(body) == "HELD"
    assert "### Manual Testing" not in body
    _assert_gate_untouched(body, _MT_LAST_HELD)


def test_span_ends_at_next_level2_heading_only():
    body = ("## Manual Testing\n\n- [ ] **R** — x\n\n### Sub\n\nmore\n\n"
            "## Files changed\n\nx\n")
    assert _manual_testing_span(body) == (0, body.index("## Files changed"))
    edited = body.replace("more", "changed")
    assert restore_manual_testing_section(edited, body) == (body, True)


# --- characterization: green before and after Phase 2 ------------------------

def test_restore_drops_edit_plus_append_in_last_section():
    after = ("## Summary\n\nOld bullet.\n\n## Manual Testing\n\n"
             "Covered by x.\n\nCloses a-jay85/IBL5-backlog#1188\n")
    body, restored = restore_manual_testing_section(after, _MT_LAST)
    assert restored is True
    assert body == _MT_LAST


def test_restore_held_section_plus_appended_sentinel_stays_held():
    after = _MT_LAST_HELD + f"\n{MANUAL_TESTING_SENTINEL}\n"
    assert manual_testing_clearance(_MT_LAST_HELD) == "HELD"
    body, _ = restore_manual_testing_section(after, _MT_LAST_HELD)
    assert manual_testing_clearance(body) == "HELD"
    _assert_gate_untouched(body, _MT_LAST_HELD)


def test_restore_held_section_plus_appended_ticked_row_stays_held():
    after = _MT_LAST_HELD + "\n- [x] **Row 2** — bar\n"
    body, _ = restore_manual_testing_section(after, _MT_LAST_HELD)
    assert manual_testing_clearance(body) == "HELD"
    assert all_rows_ticked(body) is False
    _assert_gate_untouched(body, _MT_LAST_HELD)


def test_restore_counterfeit_nospace_heading_in_append_is_dropped():
    # `##Manual Testing` matches classify._MANUAL_HEADING_RE (`\s*`) but is not a
    # `_NEXT_HEADING_RE` line; relocated above the real heading it would become
    # the FIRST span match on the next round. It must be dropped, never moved.
    after = _MT_LAST_HELD + f"\n##Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n"
    body, _ = restore_manual_testing_section(after, _MT_LAST_HELD)
    assert body == _MT_LAST_HELD
    assert _manual_testing_span(body)[0] == _MT_LAST_HELD.index("## Manual Testing")
    _assert_gate_untouched(body, _MT_LAST_HELD)


def test_restore_duplicate_level2_heading_after_section_is_outside_gate():
    after = _MT_LAST_HELD + f"\n## Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n"
    body, _ = restore_manual_testing_section(after, _MT_LAST_HELD)
    assert manual_testing_clearance(body) == "HELD"
    _assert_gate_untouched(body, _MT_LAST_HELD)


def test_restore_whitespace_only_append_collapses_to_snapshot():
    body, restored = restore_manual_testing_section(_MT_LAST + "\n\n\n", _MT_LAST)
    assert (body, restored) == (_MT_LAST, True)


def test_restore_in_section_insert_when_not_last_is_reverted():
    after = _MT_BEFORE.replace("## Files changed", "Extra line.\n\n## Files changed")
    body, restored = restore_manual_testing_section(after, _MT_BEFORE)
    assert restored is True
    assert body == _MT_BEFORE


# ---------------------------------------------------------------------------
# commit_subject: coercion (decoration layer) and schema validation
# ---------------------------------------------------------------------------

from harness.schemas import coerce_commit_subject, validate_pr_copy
from harness.state import Classification, HarnessError


def _flagged(**flags) -> Classification:
    c = Classification()
    for k, v in flags.items():
        setattr(c, k, v)
    return c


def test_coerce_commit_subject_table():
    """The three flag checks are order-sensitive — docs_only MUST be tested first.

    classify.py computes count_non_code = count_md + count_lock + count_snapshot, so a
    docs-only working set ALSO satisfies non_code_only. The `chore:` + docs_only row below
    is the one that fails if the checks are reordered: on the non_code_only branch `chore`
    is already an allowed type and the subject would be waved through as-is.
    """
    cases = [
        ("feat: add roster cache", _flagged(test_only=True), "test: add roster cache"),
        ("test: add roster cache", _flagged(test_only=True), "test: add roster cache"),
        ("feat: add roster cache", _flagged(), "feat: add roster cache"),
        ("chore: update backlog", _flagged(docs_only=True, non_code_only=True), "docs: update backlog"),
        ("feat: bump lockfile", _flagged(non_code_only=True), "chore: bump lockfile"),
    ]
    for subject, cls, expected in cases:
        assert coerce_commit_subject(subject, cls) == expected, (
            f"{subject!r} under docs={cls.docs_only} test={cls.test_only} "
            f"non_code={cls.non_code_only}")


def test_coerce_commit_subject_preserves_scope_and_bang():
    out = coerce_commit_subject("feat(harness)!: x", _flagged(test_only=True))
    assert out == "test(harness)!: x"


def test_coerce_commit_subject_unparseable_returns_unchanged():
    for subject in ("no type prefix here", "FEAT: uppercase type"):
        assert coerce_commit_subject(subject, _flagged(test_only=True)) == subject


# has_gm_visible: non-runtime denylist (Phase 1 of pr-copy-tooling-not-feat)

def test_is_gm_visible_path_denylist_table():
    cases = [
        ("bin/post-plan-now", False),
        ("bin/test-burndown", False),
        ("tools/postplan-harness/runner.py", False),
        (".claude/rules/x.md", False),
        (".github/workflows/ci.yml", False),
        ("README.md", False),
        (".gitignore", False),
        ("ibl5/tests/Foo/BarTest.php", False),
        ("ibl5/docs/decisions/0106-x.md", False),
        ("ibl5/bin/x", False),
        ("ibl5/phpstan-rules/Foo.php", False),
        ("ibl5/phpstan.neon", False),
        ("ibl5/phpunit.xml", False),
        ("ibl5/playwright.config.ts", False),
        ("ibl5/package.json", False),
        ("ibl5/composer.lock", False),
        ("ibl5/bun.lock", False),
        ("ibl5/vendor/x.php", False),
        ("ibl5/classes/SimRecap/README.md", False),
        ("engine/internal/sim/a_test.go", False),
        ("", False),
        ("  ", False),
        ("ibl5/classes/SimRecap/RecapPhasePolicy.php", True),
        ("ibl5/modules/Trades/index.php", True),
        ("ibl5/scripts/import.php", True),
        ("ibl5/shellScripts/sim.sh", True),
        ("ibl5/migrations/001_x.sql", True),
        ("ibl5/design/x.css", True),
        ("engine/internal/sim/a.go", True),
        ("engine/internal/sim/testdata/golden.json", True),
        ("newroot/whatever.txt", True),
    ]
    for path, expected in cases:
        assert is_gm_visible_path(path) is expected, path


def test_classify_sets_has_gm_visible_and_summary_prints_it():
    c = classify(["bin/x", ".claude/rules/y.md"], "")
    assert c.has_gm_visible is False
    assert "HAS_GM_VISIBLE=False" in c.summary()
    c = classify(["bin/x", "ibl5/classes/A.php"], "")
    assert c.has_gm_visible is True
    assert "HAS_GM_VISIBLE=True" in c.summary()


def test_has_gm_visible_is_independent_of_only_flag_ladder():
    c = classify(["ibl5/tests/ATest.php"], "")
    assert c.test_only is True and c.has_gm_visible is False
    c = classify(["ibl5/classes/A.php", "ibl5/tests/ATest.php"], "")
    assert c.test_only is False and c.has_gm_visible is True
    assert classify([], "").has_gm_visible is False


def _valid_pr_copy() -> dict:
    return {"type": "chore", "title": "chore: a title",
            "commit_subject": "chore: a commit subject",
            "summary_md": "## Summary\n- x\n"}


def test_validate_pr_copy_requires_commit_subject():
    validate_pr_copy(_valid_pr_copy())          # control: the four-field object validates

    absent = _valid_pr_copy()
    absent.pop("commit_subject")
    non_string = _valid_pr_copy()
    non_string["commit_subject"] = 123
    wrong_type = _valid_pr_copy()
    wrong_type["commit_subject"] = "feat: disagrees with the type field"

    for bad in (absent, non_string, wrong_type):
        with pytest.raises(HarnessError) as ei:
            validate_pr_copy(bad)
        assert ei.value.kind == "schema"


def test_validate_pr_copy_rejects_envelope_wrapped_payload():
    """No envelope tolerance here — tolerance stays in the decoration layer.

    An envelope-wrapped payload must fail loudly rather than validating an object the
    validator never actually inspected.
    """
    with pytest.raises(HarnessError) as ei:
        validate_pr_copy({"pr_copy": _valid_pr_copy()})
    assert ei.value.kind == "schema"


# ---------------------------------------------------------------------------
# Phase 6: reviewer verification render + upsert
# ---------------------------------------------------------------------------

_RV_BODY = (
    "## Summary\n\nStuff happened.\n\n"
    "## Manual Testing\n\n"
    "- [ ] Check the layout looks correct\n"
    "- [ ] Verify the form submits\n\n"
    "## Notes\n\nSome notes.\n"
)


def test_reviewer_verification_lands_after_manual_testing():
    """The block inserts between the last checkbox and ## Notes.

    Negative assertion (same test): the substring between ## Manual Testing and
    the next ## heading still contains every original checkbox — the verification
    block does NOT overwrite the checkboxes.
    """
    block = render_reviewer_verification([
        {"text": "Verify links resolve.", "category": "cli-executable",
         "probe": ["bin/check-docs"], "rationale": "settleable"},
    ])
    result = upsert_reviewer_verification(_RV_BODY, block)

    rv_start = result.index(REVIEWER_VERIFICATION_BEGIN)
    notes_start = result.index("## Notes")
    manual_start = result.index("## Manual Testing")

    # Block must be after Manual Testing and before Notes
    assert manual_start < rv_start < notes_start, (
        "reviewer-verification block must land after Manual Testing and before Notes"
    )

    # Original checkboxes must still be in the Manual Testing window
    between = result[manual_start:notes_start]
    assert "- [ ] Check the layout looks correct" in between
    assert "- [ ] Verify the form submits" in between


def test_reviewer_verification_appends_when_no_following_heading():
    """Manual Testing is the last section: block appends at end."""
    body = (
        "## Summary\n\nStuff happened.\n\n"
        "## Manual Testing\n\n"
        "- [ ] Verify the layout\n"
    )
    block = render_reviewer_verification([
        {"text": "Run docs check.", "category": "cli-executable",
         "probe": ["bin/check-docs"], "rationale": "cli"},
    ])
    result = upsert_reviewer_verification(body, block)

    assert REVIEWER_VERIFICATION_BEGIN in result
    # Block is at end — nothing follows END
    end_pos = result.index(REVIEWER_VERIFICATION_END)
    after = result[end_pos + len(REVIEWER_VERIFICATION_END):].strip()
    assert after == "", f"expected nothing after block end, got: {after!r}"

    # Checkboxes still in Manual Testing window
    manual_start = result.index("## Manual Testing")
    assert "- [ ] Verify the layout" in result[manual_start:]


def test_reviewer_verification_empty_block_removes_pair():
    """Idempotent cleanup: upserting an empty block removes the pair."""
    block = render_reviewer_verification([
        {"text": "Verify docs.", "category": "cli-executable",
         "probe": ["bin/check-docs"], "rationale": "cli"},
    ])
    with_block = upsert_reviewer_verification(_RV_BODY, block)
    assert REVIEWER_VERIFICATION_BEGIN in with_block

    cleaned = upsert_reviewer_verification(with_block, "")
    assert REVIEWER_VERIFICATION_BEGIN not in cleaned
    assert REVIEWER_VERIFICATION_END not in cleaned
    assert "## Notes" in cleaned  # rest of body preserved


def test_reviewer_verification_emits_no_heading_lines_in_bullets():
    """Arming-gate defense: a source sentence beginning '## Manual Testing' is
    neutralized; no emitted bullet starts with '#', and no bullet contains
    '- [ ]' (which the clearance scanner would count)."""
    hostile_entries = [
        {
            "text": "## Manual Testing\n\n- [ ] All clear\n\n## After",
            "category": "truly-manual",
            "probe": None,
            "rationale": "hostile sentence",
        },
        {
            "text": "Check - [x] already done.",
            "category": "truly-manual",
            "probe": None,
            "rationale": "checkbox in text",
        },
    ]
    block = render_reviewer_verification(hostile_entries)

    # Every line must either be part of the block structure (begin/end markers,
    # blank lines, preamble, or the legitimate '## Reviewer verification'
    # heading) or must NOT start with '#'.  Hostile '##' lines embedded in
    # source text must be blockquoted.
    legitimate_headings = {"## Reviewer verification"}
    for line in block.splitlines():
        if line.startswith("#") and line not in legitimate_headings:
            raise AssertionError(f"un-neutralized heading line in block: {line!r}")
    # No content may contain an unchecked checkbox
    assert "- [ ]" not in block, "emitted block must not contain unchecked checkbox"


# ---------------------------------------------------------------------------
# Phase 6: parity test — split_hold_justification vs. hold_check_section
# ---------------------------------------------------------------------------

# Shared fixture: has a Decision block and candidate lines on either side.
_PARITY_PLAN = """\
# Parity Test Plan

## Automouse Hold Justification

First candidate sentence.
**Decision:** The team accepts this risk.
Decision body explaining the reasoning.

Second candidate sentence here.
Third candidate sentence.

## Other Section

unrelated content
"""


def test_split_hold_justification_matches_shell(tmp_path):
    """Python split_hold_justification and bash hold_check_section must produce
    the same set of candidate lines for the shared fixture.  Kills a **Decision:**
    exemption that ends at a different place in the two languages."""
    if not os.path.isfile(HOLD_CHECK_LIB):
        pytest.skip(f"hold-check.sh not found: {HOLD_CHECK_LIB}")

    plan_file = tmp_path / "parity.md"
    plan_file.write_text(_PARITY_PLAN)

    # Python side
    section = parse_hold_justification(_PARITY_PLAN)
    _, py_candidates = split_hold_justification(section)

    # Shell side
    proc = subprocess.run(
        ["bash", "-c", 'source "$1" && hold_check_section "$2"', "_",
         HOLD_CHECK_LIB, str(plan_file)],
        capture_output=True, text=True, check=True,
    )
    # Shell outputs `lineno:text`; strip lineno and keep non-blank text lines
    shell_candidates = [
        ln.split(":", 1)[1]
        for ln in proc.stdout.splitlines()
        if ":" in ln and ln.split(":", 1)[1].strip()
    ]

    assert py_candidates == shell_candidates, (
        f"parser divergence\n python: {py_candidates}\n shell:  {shell_candidates}"
    )


@pytest.mark.parametrize("src,want,n", [
    ("Closes harness half of backlog issue #160 (skill half landed in PR #2158).",
     "Closes harness half of backlog issue a-jay85/IBL5-backlog#160 (skill half landed in PR #2158).", 1),
    ("See backlog items #12 and #13.", "See backlog items a-jay85/IBL5-backlog#12 and a-jay85/IBL5-backlog#13.", 2),
    ("Backlog #7, #8", "Backlog a-jay85/IBL5-backlog#7, a-jay85/IBL5-backlog#8", 2),
    ("Filed a-jay85/IBL5-backlog#9 from backlog housekeeping.", "Filed a-jay85/IBL5-backlog#9 from backlog housekeeping.", 0),
    ("Fixes #2311 and backlog", "Fixes #2311 and backlog", 0),
])
def test_qualify_backlog_refs(src, want, n):
    assert qualify_backlog_refs(src) == (want, n)


# ---------------------------------------------------------------------------
# Phase 4a: render_residual_phases / upsert_residual_phases (post-impl)
# ---------------------------------------------------------------------------

from harness.classify import (render_residual_phases, upsert_residual_phases,
                               RESIDUAL_PHASES_BEGIN, RESIDUAL_PHASES_END)


def test_render_residual_phases_empty_and_nonempty():
    """render([]) == ""; nonempty output has both markers, heading, and stripped bullets.

    Mutation caught: dropping removeprefix leaves the MISSING-PHASE: prefix in bullets.
    """
    assert render_residual_phases([]) == ""
    items = [
        "MISSING-PHASE: 2 — Phase 2: B (phase cites harness/b.py; none appeared in the diff)",
        "MISSING-PHASE: 3 — Phase 3: C (phase cites bin/c; none appeared in the diff)",
    ]
    out = render_residual_phases(items)
    assert out.startswith(RESIDUAL_PHASES_BEGIN)
    assert out.endswith(RESIDUAL_PHASES_END)
    assert "## Residual Phases" in out
    assert "- 2 — Phase 2: B" in out
    assert "- 3 — Phase 3: C" in out
    assert "MISSING-PHASE:" not in out


def test_upsert_residual_phases_append_replace_remove():
    """Append, replace, and remove the marker block.

    Mutation caught: dropping the `if not block:` removal branch leaves v1 in place.
    """
    body = "## Summary\n- x"
    block_v1 = render_residual_phases(["MISSING-PHASE: 2 — B (...)"])
    block_v2 = render_residual_phases(["MISSING-PHASE: 3 — C (...)"])

    # append: body unchanged before the block
    appended = upsert_residual_phases(body, block_v1)
    assert body in appended
    assert RESIDUAL_PHASES_BEGIN in appended

    # replace: surrounding text is byte-identical
    replaced = upsert_residual_phases(appended, block_v2)
    assert body in replaced
    assert "3 — C" in replaced
    assert "2 — B" not in replaced

    # remove: body equals pre-append body
    removed = upsert_residual_phases(replaced, "")
    assert removed.strip() == body.strip()


def test_upsert_residual_phases_noop_without_items_or_markers():
    """Empty block + no markers → body unchanged; orphan END is left, nonempty block appended.

    Mutation caught: treating an orphan as well-formed slices the body.
    """
    body = "## Summary\n- x"
    assert upsert_residual_phases(body, "") == body

    orphan_body = f"## Summary\n{RESIDUAL_PHASES_END}\n- x"
    block = render_residual_phases(["MISSING-PHASE: 2 — B (...)"])
    result = upsert_residual_phases(orphan_body, block)
    assert RESIDUAL_PHASES_END in result
    assert RESIDUAL_PHASES_BEGIN in result
    assert "2 — B" in result


from harness.classify import (FILES_CHANGED_BEGIN, FILES_CHANGED_END,
                              TESTS_CHANGED_BEGIN, TESTS_CHANGED_END,
                              render_files_changed, render_tests_changed,
                              upsert_files_changed, upsert_tests_changed)


def _make_diff_entry(path: str, status: str) -> str:
    """Build a minimal diff --git block for the given path and A/M/D status."""
    if status == "A":
        return (f"diff --git a/{path} b/{path}\n"
                f"new file mode 100644\n"
                f"--- /dev/null\n"
                f"+++ b/{path}\n"
                f"@@ -0,0 +1,1 @@\n"
                f"+x\n")
    elif status == "D":
        return (f"diff --git a/{path} b/{path}\n"
                f"deleted file mode 100644\n"
                f"--- a/{path}\n"
                f"+++ /dev/null\n"
                f"@@ -1,1 +0,0 @@\n"
                f"-x\n")
    else:
        return (f"diff --git a/{path} b/{path}\n"
                f"index aaa..bbb 100644\n"
                f"--- a/{path}\n"
                f"+++ b/{path}\n"
                f"@@ -1,1 +1,2 @@\n"
                f"+x\n")


def test_render_tests_changed_filters_to_test_paths():
    diff = (
        _make_diff_entry("ibl5/classes/Foo.php", "M")
        + _make_diff_entry("ibl5/tests/Unit/FooTest.php", "A")
        + _make_diff_entry("ibl5/tests/e2e/roster.spec.ts", "M")
        + _make_diff_entry("tools/postplan-harness/tests/test_classify.py", "M")
        + _make_diff_entry("engine/internal/sim/rng_test.go", "A")
        + _make_diff_entry("bin/test-plan-now", "M")
    )
    block = render_tests_changed(diff)
    assert "- `A` `ibl5/tests/Unit/FooTest.php`" in block
    assert "- `M` `ibl5/tests/e2e/roster.spec.ts`" in block
    assert "- `M` `tools/postplan-harness/tests/test_classify.py`" in block
    assert "- `A` `engine/internal/sim/rng_test.go`" in block
    assert "- `M` `bin/test-plan-now`" in block
    assert "ibl5/classes/Foo.php" not in block


def test_render_tests_changed_reports_none_when_no_tests():
    diff = (
        _make_diff_entry("ibl5/classes/Foo.php", "M")
        + _make_diff_entry("README.md", "M")
    )
    block = render_tests_changed(diff)
    header = ("**Tests changed** (generated from "
              "`git diff --name-status origin/master...HEAD` — do not edit by hand):")
    expected = (TESTS_CHANGED_BEGIN + "\n" + header + "\n\n"
                "- _(no test files changed)_\n" + TESTS_CHANGED_END)
    assert block == expected


def test_render_tests_changed_empty_diff():
    block = render_tests_changed("")
    assert block.startswith(TESTS_CHANGED_BEGIN)
    assert block.endswith(TESTS_CHANGED_END)
    assert "_(no test files changed)_" in block


def test_upsert_tests_changed_replace():
    old_block = (TESTS_CHANGED_BEGIN + "\nold header\n\n- `M` `old.py`\n" + TESTS_CHANGED_END)
    body = "## Before\n\n" + old_block + "\n\n## After\n"
    new_block = (TESTS_CHANGED_BEGIN + "\nnew header\n\n- `A` `new.py`\n" + TESTS_CHANGED_END)
    result = upsert_tests_changed(body, new_block)
    assert result == "## Before\n\n" + new_block + "\n\n## After\n"


def test_upsert_tests_changed_append_when_absent_and_empty_body():
    block = render_tests_changed("")
    body = "## Summary\n- x"
    result = upsert_tests_changed(body, block)
    assert result == body.rstrip() + "\n\n" + block + "\n"

    assert upsert_tests_changed("", block) == block
    assert upsert_tests_changed(None, block) == block


def test_upsert_tests_changed_orphan_marker_appends():
    orphan_body = f"## Orphan\n{TESTS_CHANGED_END}\n- lone begin: {TESTS_CHANGED_BEGIN}\n"
    block = render_tests_changed("")
    result = upsert_tests_changed(orphan_body, block)
    assert orphan_body.rstrip() in result
    assert result.count(TESTS_CHANGED_BEGIN) >= 2
    assert result.endswith(block + "\n")


def test_upsert_tests_changed_preserves_files_changed_block():
    diff = _make_diff_entry("ibl5/tests/Unit/FooTest.php", "A")
    files_block = render_files_changed(diff)
    tests_block = render_tests_changed(diff)
    body_with_files = upsert_files_changed("## Summary\n", files_block)
    files_snapshot = body_with_files[
        body_with_files.find(FILES_CHANGED_BEGIN):
        body_with_files.find(FILES_CHANGED_END) + len(FILES_CHANGED_END)
    ]

    result_1 = upsert_tests_changed(body_with_files, tests_block)
    result_2 = upsert_tests_changed(result_1, tests_block)

    assert files_snapshot in result_1
    assert files_snapshot in result_2
    assert result_1.count(TESTS_CHANGED_BEGIN) == 1
    assert result_1 == result_2
