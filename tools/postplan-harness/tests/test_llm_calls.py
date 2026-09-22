import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

from harness.llm_calls import pr_body_check_prompt, pr_copy_prompt, retrospective_prompt
from harness.schemas import validate_body_check
from harness.state import Classification, HarnessError, PlanInfo

_REAL_REGISTRY_ROW = (
    "| 2026-08-14 | #1880 | class: gate escape path conditioned on a git-range query "
    "silently blocks when the range is empty (first-branch-commit), with no null fallback "
    "| routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md "
    "(section: Forced integration-verification trigger): any plan adding or modifying an escape "
    "path in a CI check gate that calls a git-range helper must test the empty-range "
    "(no-prior-commits-on-branch) scenario | prior: -- |"
)


def _cls(retro_row: str = "") -> Classification:
    c = Classification()
    c.retro_registry_row = retro_row
    return c


def test_pr_copy_prompt_with_retro_row_contains_required_elements():
    prompt = pr_copy_prompt("some-slug", _cls(_REAL_REGISTRY_ROW), PlanInfo(), "")
    assert _REAL_REGISTRY_ROW in prompt
    assert "## Why this PR exists" in prompt


def test_pr_copy_prompt_requests_commit_subject():
    """The model is only told to emit commit_subject by this template.

    validate_pr_copy requires the field, so reverting the template line would make every
    live run raise HarnessError("schema", ...) with nothing in the suite noticing.
    """
    prompt = pr_copy_prompt("some-slug", _cls(), PlanInfo(), "")
    assert "commit_subject" in prompt
    assert "must NOT be" in prompt      # the two-artifacts instruction, not just the key


def test_pr_copy_prompt_without_retro_row_omits_retro_block():
    prompt = pr_copy_prompt("some-slug", _cls(""), PlanInfo(), "")
    assert _REAL_REGISTRY_ROW not in prompt
    assert "## Why this PR exists" not in prompt


def test_pr_copy_prompt_contains_manual_testing_prohibition():
    prompt = pr_copy_prompt("some-slug", _cls(""), PlanInfo(), "")
    assert '## Manual Testing' in prompt
    assert "corrupts the arming gate" in prompt


def test_retrospective_prompt_carries_fidelity_outcome():
    fidelity = {"verdict_1": "NOT READY", "verdict_2": "READY", "remediation_sha": "abc"}
    prompt = retrospective_prompt("my-slug", "shipped-armed", None, 0, None, fidelity)
    assert "fidelity=NOT READY->READY remediated=True" in prompt

    prompt_none = retrospective_prompt("my-slug", "shipped-armed", None, 0, None, None)
    assert "fidelity=none" in prompt_none


# ── Phase 2: pr_body_check_prompt and validate_body_check ────────────────────

_SAMPLE_BODY = "## Summary\n- 3 files changed\n\n## Scope\nSome prose here.\n"
_SAMPLE_NS = "A\tibl5/migrations/047_add_x.sql\nM\ttools/postplan-harness/runner.py\n"


def test_body_check_prompt_carves_out_files_changed_block():
    prompt = pr_body_check_prompt(_SAMPLE_BODY, _SAMPLE_NS)
    assert "Exclude the machine-generated files-changed block" in prompt
    assert "already been removed from the text you are shown" in prompt


def test_body_check_prompt_carves_out_manual_testing_section():
    prompt = pr_body_check_prompt(_SAMPLE_BODY, _SAMPLE_NS)
    assert 'runner owns the "## Manual Testing" section' in prompt
    assert "Never write a" in prompt


def test_body_check_prompt_warns_the_file_list_may_be_incomplete():
    prompt = pr_body_check_prompt(_SAMPLE_BODY, _SAMPLE_NS)
    assert "may be incomplete" in prompt
    assert "do not flag claims about it as unsupported" in prompt


def test_body_check_prompt_carries_named_constant_volume_wording():
    prompt = pr_body_check_prompt(_SAMPLE_BODY, _SAMPLE_NS)
    assert "Named-constant volumes" in prompt
    assert "Read the constant, do not trust the body" in prompt


def test_validate_body_check_accepts_valid_shape():
    validate_body_check({"corrected_body": "some body", "findings": ["one finding"]})
    validate_body_check({"corrected_body": "", "findings": []})


def test_validate_body_check_rejects_missing_corrected_body():
    with pytest.raises(HarnessError):
        validate_body_check({"findings": []})
    with pytest.raises(HarnessError):
        validate_body_check({"corrected_body": 123, "findings": []})


def test_validate_body_check_rejects_non_list_findings():
    with pytest.raises(HarnessError):
        validate_body_check({"corrected_body": "ok", "findings": "not a list"})
    with pytest.raises(HarnessError):
        validate_body_check({"corrected_body": "ok", "findings": [1, 2]})
