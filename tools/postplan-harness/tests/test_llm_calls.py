import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.llm_calls import pr_copy_prompt
from harness.state import Classification, PlanInfo

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


def test_pr_copy_prompt_without_retro_row_omits_retro_block():
    prompt = pr_copy_prompt("some-slug", _cls(""), PlanInfo(), "")
    assert _REAL_REGISTRY_ROW not in prompt
    assert "## Why this PR exists" not in prompt
