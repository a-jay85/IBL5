"""READY WITH NOTES body-only notes round: eligibility mode, gate, and runner wiring.

A READY WITH NOTES verdict whose kept notes are all `pr-copy` gets one PR-body-only
pass before the notes are filed. A `followup` among the kept notes skips the round.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from harness.state import UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_body_recheck import (TREE_1, VERDICT1_PURE_BODY, _body_only_git,
                                        _cleanup, _found_plan, _RecheckLlm, _Res,
                                        git_shim)  # noqa: F401

PLAN_HASH = "a" * 64

VERDICT_RWN = (
    "REVIEW-COVERAGE: CURRENT\n"
    "4. PR body vs. reality: the body says \"23-case\"; the file has 26 cases.\n"
    "Passed: 1, 2, 3, 5, 6.\n\n"
    "## FINDINGS\n"
    "- [NOTE] Check 4: the PR body claims a \"23-case\" test file; `ibl5/tests/FooTest.php` "
    "carries 26 `test` methods.\n\n"
    "READY WITH NOTES\n\n"
    "## DIGEST\n**What changed:** x\n**Why:** x\n**Risk:** x\n**Rollback:** x\n**Verified:** x\n"
)

_FOLLOWUP = {"title": "Guard the empty roster", "kind": "followup",
             "detail": "RosterParser::parse() crashes on an empty roster."}
_PR_COPY = {"title": "Fix the stale case count in the PR body", "kind": "pr-copy",
            "harm": "none",
            "detail": "The body misstates the count: it says 23 cases and the file has 26."}


# ---------------------------------------------------------------------------
# Phase 1: characterization (passes on master)
# ---------------------------------------------------------------------------

def test_eligibility_default_mode_still_declines_ready_with_notes():
    got = fidelity.body_recheck_eligibility(VERDICT_RWN, TREE_1, TREE_1, PLAN_HASH, PLAN_HASH)
    assert got == (None, "prior-verdict-unparsed")


def _drive_notes(tmp_path, pr, notes, extra_scripts=None, bodies=None, git=None):
    gh = _BodySeqGh(str(tmp_path), bodies or ["BEFORE body prose"],
                    fixture={"pr_number": pr})
    git = git or _body_only_git()
    scripts = {
        "plan-fidelity-review": [VERDICT_RWN],
        "fidelity-notes": [notes],
    }
    scripts.update(extra_scripts or {})
    llm = _RecheckLlm(UsageLedger(), scripts)
    res = _Res()
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh,
                         _found_plan(tmp_path), "diff", "original-body", pr,
                         "dead" * 10, TREE_1, False, lambda _m: None, res)
    return res, llm, gh, git


def _created_titles(gh):
    return [a["title"] for a in gh.actions() if a["action"] == "followup_create"]


def test_notes_round_followup_present_files_without_round(tmp_path, git_shim):
    pr = 9961
    try:
        res, llm, gh, git = _drive_notes(tmp_path, pr, [_PR_COPY, _FOLLOWUP])
        assert res.fidelity["verdict_1"] == "READY WITH NOTES"
        assert res.fidelity.get("verdict_2") is None
        assert [n["title"] for n in res.fidelity["filed_notes"]] == [
            f"{_FOLLOWUP['title']}", f"PR #{pr} copy: {_PR_COPY['title']}"]
        assert _created_titles(gh) == [
            _FOLLOWUP["title"], f"PR #{pr} copy: {_PR_COPY['title']}"]
        assert [a for a in gh.actions() if a["action"] == "pr_edit_body"
                and a["body"].startswith("FIXED")] == []
        purposes = [p for p, _m in llm.calls]
        assert not [p for p in purposes if p.startswith("plan-fidelity-notes-body-fix")]
        assert not [p for p in purposes if p.startswith("plan-fidelity-body-recheck")]
        assert git.head_tree() == TREE_1
    finally:
        _cleanup(pr, f"{pr}-2")
