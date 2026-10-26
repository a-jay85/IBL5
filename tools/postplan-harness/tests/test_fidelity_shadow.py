"""Shadow-mode plan-fidelity verdict: the typed `## SHADOW-ARMING` block that stands in for
arming conditions (3) and (18) and the body-number checks. Shadow mode only: nothing here may
change the verdict word, the findings parsers, or the arm decision."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from tests.test_runner_replay import CANNED, _pin_verifier_worktree, run_slug  # noqa: F401 (autouse fixture)

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
FIXTURES = os.path.join(ROOT, "tests", "fixtures", "fidelity-shadow")
BASELINE = os.path.join(FIXTURES, "verdict-baseline.md")

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")


def _baseline_text() -> str:
    with open(BASELINE) as fh:
        return fh.read()


# Pinned from one run of unmodified master code (Phase 1). Never re-pin to make a test pass.
BASELINE_WORD = "NOT READY"
BASELINE_FINDINGS = [
    '- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.',
    '- [NOTE] Check 4: the PR body names the helper `fooBar` while the diff calls it `foo_bar`.',
    '',
    'NOT READY',
    '',
]
BASELINE_BODY_ONLY = [
    '- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.',
    '- [NOTE] Check 4: the PR body names the helper `fooBar` while the diff calls it `foo_bar`.',
]


class TestVerdictParsingBaseline:
    def test_baseline_parse_verdict_word(self):
        assert fidelity.parse_verdict(BASELINE) == BASELINE_WORD

    def test_baseline_findings_section(self):
        assert fidelity._findings_section(_baseline_text().splitlines()) == BASELINE_FINDINGS

    def test_baseline_body_only_findings(self):
        assert fidelity.body_only_findings_text(_baseline_text()) == BASELINE_BODY_ONLY

    def test_baseline_digest_is_last(self):
        headings = [ln for ln in _baseline_text().splitlines() if ln.startswith("## ")]
        assert headings[-1] == fidelity.DIGEST_CUT


# Pinned from one run of unmodified master code (Phase 1). `ConditionResult` names the hold
# flag `blocked`. The replay fixtures are gitignored, so these skip where they are absent.
ARM_BASELINE_DOCS_ONLY = (False, [(1, True), (2, False), (3, False), (4, False), (5, False), (6, False), (7, False), (8, False), (9, False), (10, False), (11, False), (12, False), (13, False), (14, False), (15, False), (16, False), (17, False), (18, True)])
ARM_BASELINE_AUTO_MERGE_FALSE = (False, [(1, True), (2, False), (3, True), (4, False), (5, False), (6, False), (7, True), (8, False), (9, True), (10, False), (11, False), (12, False), (13, False), (14, False), (15, False), (16, False), (17, False), (18, True)])


def _arm_snapshot(res):
    return res.arm.armed, sorted((c.number, c.blocked) for c in res.arm.conditions)


class TestArmingBaseline:
    def test_baseline_arm_decision_docs_only(self):
        res, _ = run_slug("backlog-l6-done")
        assert _arm_snapshot(res) == ARM_BASELINE_DOCS_ONLY

    def test_baseline_arm_decision_auto_merge_false(self):
        res, _ = run_slug("request-event-logging")
        assert _arm_snapshot(res) == ARM_BASELINE_AUTO_MERGE_FALSE
