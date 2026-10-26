"""Shadow-mode plan-fidelity verdict: the typed `## SHADOW-ARMING` block that stands in for
arming conditions (3) and (18) and the body-number checks. Shadow mode only: nothing here may
change the verdict word, the findings parsers, or the arm decision."""
import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity, schemas
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


# ---- Phase 2: shadow-block validator -------------------------------------------------------

VALID_SHADOW = {
    "schema": "fidelity-shadow/1",
    "planned_landed": {"hold": True, "missing": [
        {"kind": "file", "token": "tools/x.py", "why": "planned in Phase 2, absent from diff"}]},
    "merge_danger": {"door": "one-way", "hold": True, "why": "drops column ibl_x.y with no backfill"},
    "body_numbers": {"consistent": False, "mismatches": [
        {"claim": "adds 4 tests", "actual": "diff adds 3 test functions"}]},
}
CLEAR_SHADOW = {
    "schema": "fidelity-shadow/1",
    "planned_landed": {"hold": False, "missing": []},
    "merge_danger": {"door": "two-way", "hold": False, "why": "additive change only"},
    "body_numbers": {"consistent": True, "mismatches": []},
}
ALL_UNKNOWN = {"3": "unknown", "18": "unknown", "body_numbers": "unknown"}


def shadow_block(obj, raw: str | None = None) -> str:
    payload = raw if raw is not None else json.dumps(obj, indent=2)
    return f"{schemas.FIDELITY_SHADOW_HEADING}\n```json\n{payload}\n```\n"


def verdict_with_block(block: str, base: str | None = None) -> str:
    """The Phase 1 baseline with `block` between the findings and the terminal word."""
    text = base if base is not None else _baseline_text()
    return text.replace("\nNOT READY\n", f"\n{block}\nNOT READY\n", 1)


def _copy(obj):
    return json.loads(json.dumps(obj))


class TestShadowValidator:
    def test_valid_block_accepted(self):
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(VALID_SHADOW)))
        assert rec["status"] == "present"
        assert rec["fields"] == VALID_SHADOW
        assert schemas.shadow_condition_states(rec) == {
            "3": "hold", "18": "hold", "body_numbers": "mismatch"}

    def test_missing_block_is_typed_invalid(self):
        rec = schemas.extract_fidelity_shadow(_baseline_text())
        assert rec == {"status": "invalid:missing-block"}
        assert schemas.shadow_condition_states(rec) == ALL_UNKNOWN

    def test_bad_json_is_typed_invalid(self):
        raw = json.dumps(VALID_SHADOW)[:-1] + ",}"
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(None, raw)))
        assert rec["status"] == "invalid:bad-json"

    def test_duplicate_block_is_typed_invalid(self):
        two = shadow_block(VALID_SHADOW) + "\n" + shadow_block(CLEAR_SHADOW)
        rec = schemas.extract_fidelity_shadow(verdict_with_block(two))
        assert rec["status"] == "invalid:duplicate-block"

    def test_unclosed_fence_is_typed_invalid(self):
        text = (_baseline_text().split(fidelity.DIGEST_CUT)[0]
                + f"{schemas.FIDELITY_SHADOW_HEADING}\n```json\n{json.dumps(VALID_SHADOW)}\n")
        rec = schemas.extract_fidelity_shadow(text)
        assert rec["status"] == "invalid:no-fence"

    def test_hold_flag_contradicts_missing_list_rejected(self):
        bad = _copy(VALID_SHADOW)
        bad["planned_landed"]["hold"] = False
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"
        assert "planned_landed.hold" in rec["detail"]

    def test_unknown_door_rejected(self):
        bad = _copy(VALID_SHADOW)
        bad["merge_danger"]["door"] = "maybe"
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"
        assert "door" in rec["detail"]

    def test_extra_top_level_key_rejected(self):
        bad = dict(VALID_SHADOW, extra_field=1)
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block(bad)))
        assert rec["status"] == "invalid:schema"

    def test_envelope_wrapped_block_unwrapped(self):
        rec = schemas.extract_fidelity_shadow(
            verdict_with_block(shadow_block({"shadow": VALID_SHADOW})))
        assert rec["status"] == "present"
        assert rec["fields"] == VALID_SHADOW

    def test_bare_list_envelope_is_invalid_never_clear(self):
        rec = schemas.extract_fidelity_shadow(verdict_with_block(shadow_block([CLEAR_SHADOW])))
        assert rec["status"] == "invalid:schema"
        states = schemas.shadow_condition_states(rec)
        assert states == ALL_UNKNOWN
        assert "clear" not in states.values()

    def test_block_below_digest_ignored(self):
        text = _baseline_text() + "\n" + shadow_block(CLEAR_SHADOW)
        rec = schemas.extract_fidelity_shadow(text)
        assert rec == {"status": "invalid:missing-block"}
