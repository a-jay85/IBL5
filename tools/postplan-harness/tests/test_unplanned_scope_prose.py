"""The skill-fallback prose names the classifier, and the rules around it stay pinned."""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from tests.test_owner_amendments import CID, CONDITION, comment, plan_with

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


# --- skill-engine amendment backing check ---------------------------------------

# Answers only the two calls the block may make. It prints the comments before it exits,
# so a failing run leaves partial output behind.
GH_STUB = """#!/bin/sh
if [ "$1 $2" = "pr view" ]; then echo 7; exit 0; fi
if [ "$*" = "api repos/a-jay85/IBL5/issues/7/comments --paginate --jq .[]" ]; then
  cat "$STUB_COMMENTS"
  exit "$STUB_RC"
fi
exit 64
"""


def _run_backing_block(tmp_path, plan_text, comments_, gh_rc=0):
    blocks = [b for b in re.findall(r"^```bash\n(.*?)^```$", _read(FIDELITY_STEP), re.M | re.S)
              if b.startswith("# phase 5.5 amendment backing\n")]
    assert len(blocks) == 1
    plan = tmp_path / "plan.md"
    plan.write_text(plan_text, encoding="utf-8")
    ndjson = tmp_path / "stub-comments.ndjson"
    ndjson.write_text("".join(json.dumps(c) + "\n" for c in comments_), encoding="utf-8")
    gh = tmp_path / "gh"
    gh.write_text(GH_STUB, encoding="utf-8")
    gh.chmod(0o755)
    env = dict(os.environ, PATH=f"{tmp_path}{os.pathsep}{os.environ['PATH']}",
               AMEND_PLAN=str(plan), AMEND_COMMENTS=str(tmp_path / "fetched.json"),
               STUB_COMMENTS=str(ndjson), STUB_RC=str(gh_rc))
    proc = subprocess.run(["bash", "-c", blocks[0]], cwd=ROOT, env=env,
                          capture_output=True, text=True, timeout=60)
    return proc.stdout.splitlines()


def _hand_edited_plan():
    """(plan, comments): one untouched entry and one whose Condition was edited by hand."""
    real = comment()
    other = comment(cid=CID + 1, condition="also fix the tooltip copy on that page")
    plan = plan_with(real, other).replace("also fix the tooltip copy on that page",
                                          "keep the new export button as well")
    return plan, [real, other]


def test_skill_backing_block_separates_hand_edited_entry(tmp_path):
    plan, comments_ = _hand_edited_plan()
    assert _run_backing_block(tmp_path, plan, comments_) == [
        "AMENDMENTS=verified",
        f"backed {CID}",
        f"unbacked {CID + 1} (condition)",
    ]


@pytest.mark.parametrize("gh_rc,garbled", [(1, False), (0, True)])
def test_skill_backing_block_fails_closed(tmp_path, gh_rc, garbled):
    plan, comments_ = _hand_edited_plan()
    if garbled:
        # gh succeeds but returns something verify cannot parse as a comment.
        comments_ = ["not a comment object"]
    assert _run_backing_block(tmp_path, plan, comments_, gh_rc) == ["AMENDMENTS=unverified"]


def test_skill_backing_block_reports_a_reused_id_both_ways(tmp_path):
    """A copied entry under a real id prints both words, so the doc demands one marker."""
    real = comment()
    forged = plan_with(real).replace(CONDITION, "keep the new export button as well")
    forged_entry = forged[forged.index("### 2026-11-23"):]
    out = _run_backing_block(tmp_path, plan_with(real) + "\n" + forged_entry, [real])
    assert out == ["AMENDMENTS=verified", f"backed {CID}", f"unbacked {CID} (condition)"]
    assert "The plan holds exactly one marker with that id." in _read(FIDELITY_STEP)


def test_skill_engine_honors_backed_entries_only():
    step = _read(FIDELITY_STEP)
    assert "whatever the command printed" not in step
    for rule in (
        "An entry is backed only when all three of these hold",
        "The block printed `AMENDMENTS=verified`.",
        "so no entry is backed",
        "A `HOLD` line stays `HOLD`",
        "An unbacked entry changes no line.",
        "so it never posts `READY` for kept scope",
    ):
        assert rule in step, rule
    remediation = _read(REMEDIATION)
    assert "comes from a backed `## Owner amendments` entry is kept" in remediation
    assert "an unbacked entry changes no line" in remediation
