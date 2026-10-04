"""Conformance relaxations: line suffixes, glob tokens, exact-match precedence, and the
MISSING-METHOD changed-file fallback. Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

from harness import conformance
from harness.conformance import check, _resolve
from harness.state import PlanInfo


@pytest.fixture(autouse=True)
def _tracked_files(monkeypatch):
    """Invented paths; fail closed so no citation is judged against the real checkout."""
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)


def _plan_with_test(path: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=[path])


def _plan_with_critical(path: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, critical_files=[(path, "", False)])


_E2E_CHANGED = ["ibl5/tests/e2e/vr-manifest.ts", "ibl5/tests/e2e/smoke/public-pages.spec.ts"]


def test_method_declared_in_changed_file_but_absent_from_diff_is_present():
    """PRs #2708, #2772, #2707: the declaration sits in a changed file the hunks omit."""
    plan = PlanInfo(found=True, has_matrix=True,
                    required_test_methods=["testRenderResultsExpanderStartsCollapsed"])
    path = "ibl5/tests/Voting/VotingBallotViewTest.php"
    diff = f"+++ b/{path}\n+        self::assertTrue(true);\n"
    tree = {path: "<?php\n    public function testRenderResultsExpanderStartsCollapsed(): void {}\n"}
    items = check(plan, [path], diff, read_file=tree.get)
    assert not [i for i in items if i.startswith("MISSING-METHOD:")]


def test_glob_token_resolves_when_changed_files_match():
    """PR #2744: `ibl5/tests/Cli/*.php` names two changed files."""
    changed = ["ibl5/tests/Cli/MergeMasterToProdCliTest.php",
               "ibl5/tests/Cli/RollbackPhantomRepairCliTest.php"]
    items = check(_plan_with_test("ibl5/tests/Cli/*.php"), changed)
    assert not [i for i in items if i.startswith("MISSING:")]


def test_exact_match_wins_over_suffix_hit():
    """PR #2786: `bin/README.md` beside `ibl5/bin/README.md` is not ambiguous."""
    changed = ["bin/README.md", "ibl5/bin/README.md", "bin/test-test", "ibl5/bin/e2e-local"]
    assert _resolve("bin/README.md", changed) == "bin/README.md"
    items = check(_plan_with_critical("bin/README.md"), changed)
    assert not [i for i in items if i.startswith("MISSING-FILE:")]


def test_line_range_suffix_is_stripped_before_resolve():
    """PR #2775: `vr-manifest.ts:100-101`."""
    tok = "ibl5/tests/e2e/vr-manifest.ts:100-101"
    assert _resolve(tok, _E2E_CHANGED) == "ibl5/tests/e2e/vr-manifest.ts"
    assert check(_plan_with_test(tok), _E2E_CHANGED) == []


def test_line_list_suffix_is_stripped_before_resolve():
    """PR #2775: `public-pages.spec.ts:41,47`."""
    tok = "ibl5/tests/e2e/smoke/public-pages.spec.ts:41,47"
    assert _resolve(tok, _E2E_CHANGED) == "ibl5/tests/e2e/smoke/public-pages.spec.ts"


def test_line_suffixed_token_absent_from_diff_stays_missing():
    """PR #2775: `ci-seed.sql:902-903` is not in the diff, so it stays held."""
    tok = "ibl5/tests/e2e/fixtures/ci-seed.sql:902-903"
    items = check(_plan_with_test(tok), _E2E_CHANGED)
    assert len([i for i in items if i.startswith(f"MISSING: {tok}")]) == 1


def test_glob_token_matching_nothing_stays_missing():
    items = check(_plan_with_test("ibl5/tests/Cli/*.php"), ["ibl5/tests/Unit/FooTest.php"])
    assert len([i for i in items if i.startswith("MISSING: ibl5/tests/Cli/*.php")]) == 1


def test_colon_word_suffix_is_not_stripped():
    assert _resolve("bin/foo:bar", ["bin/foo"]) is None


def test_exact_match_requires_identical_path():
    assert _resolve("README.md", ["bin/README.md", "ibl5/bin/README.md"]) is None
