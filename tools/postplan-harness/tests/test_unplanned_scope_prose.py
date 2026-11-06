"""The skill-fallback prose names the classifier, and the rules around it stay pinned."""
from __future__ import annotations

import os

import pytest

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
REMEDIATION = ".claude/review-shared/_phase65-remediation.md"
FIDELITY_STEP = ".claude/skills/post-plan/_phase-5.5-fidelity.md"
FIX_AND_PREVENT = ".claude/skills/fix-and-prevent/_remediation.md"
BODY_CLAIMS = ".claude/skills/post-plan/_pr-body-claims.md"
REVIEW = ".claude/review-shared/_plan-fidelity-review.md"


def _read(rel: str) -> str:
    with open(os.path.join(ROOT, rel), encoding="utf-8") as fh:
        return fh.read()


@pytest.mark.parametrize("rel", [REMEDIATION, FIDELITY_STEP, FIX_AND_PREVENT])
def test_remediation_prose_names_tag_and_cli(rel):
    text = _read(rel)
    assert "[unplanned-scope]" in text
    assert "harness.unplanned_scope" in text


def test_remediation_prose_forbids_untagged_delete():
    assert ("Never delete code for a tagged finding the command did not mark `REMOVE`"
            in _read(REMEDIATION))


def test_digest_rule_still_five_labels():
    text = _read(FIDELITY_STEP)
    assert "do not re-word, re-order, merge, or add a sixth line" in text
    assert ("`**What changed:**`, `**Why:**`, `**Watch:**`, `**Touches:**`, "
            "`**Machine-authored fixes:**`") in text


def test_skill_engine_word_stays_frozen():
    assert ("The `FIDELITY=<word>` that condition (12) reads is the step-3 word and never changes"
            in _read(FIDELITY_STEP))


def test_body_claims_doc_describes_kept_block():
    text = _read(BODY_CLAIMS)
    assert "kept-unplanned:begin" in text
    assert "Never move these bullets under `## Declared scope`" in text


def test_review_contract_excludes_kept_block_from_check4():
    text = _read(REVIEW)
    assert "kept-unplanned:begin" in text
    assert "**Blocking** when an unasked-for change touches a security surface" in text
