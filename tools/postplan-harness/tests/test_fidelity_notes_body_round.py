"""READY WITH NOTES body-only notes round: eligibility mode, gate, and runner wiring.

A READY WITH NOTES verdict whose kept notes are all `pr-copy` gets one PR-body-only
pass before the notes are filed. A `followup` among the kept notes skips the round.
"""
from __future__ import annotations

import os
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from harness.state import UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_body_recheck import (TREE_1, VERDICT1_PURE_BODY, _body_only_git,
                                        _cleanup, _found_plan, _pass_all, _RecheckLlm,
                                        _Res, git_shim)  # noqa: F401

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


def _drive_notes(tmp_path, pr, notes, extra_scripts=None, bodies=None, git=None, capture=None):
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
    if capture is not None:
        capture.update(gh=gh, res=res, llm=llm)
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


# ---------------------------------------------------------------------------
# Phase 2: eligibility notes mode, gate, fix call
# ---------------------------------------------------------------------------

def test_pr_copy_round_findings_mixed_returns_none():
    assert fidelity.pr_copy_round_findings([_FOLLOWUP, _PR_COPY]) is None


def test_pr_copy_round_findings_empty_returns_none():
    assert fidelity.pr_copy_round_findings([]) is None


def test_pr_copy_round_findings_all_pr_copy_returns_texts():
    got = fidelity.pr_copy_round_findings([_PR_COPY])
    assert got == [f"{_PR_COPY['title']}: {_PR_COPY['detail']}"]


def test_eligibility_notes_mode_accepts_ready_with_notes():
    got = fidelity.body_recheck_eligibility(VERDICT_RWN, TREE_1, TREE_1, PLAN_HASH,
                                            PLAN_HASH, notes=["F"])
    assert got == (["F"], None)


def test_eligibility_notes_mode_declines_not_ready_and_tree_change():
    got = fidelity.body_recheck_eligibility(VERDICT1_PURE_BODY, TREE_1, TREE_1, PLAN_HASH,
                                            PLAN_HASH, notes=["F"])
    assert got == (None, "prior-verdict-unparsed")
    got = fidelity.body_recheck_eligibility(VERDICT_RWN, TREE_1, "b" * 40, PLAN_HASH,
                                            PLAN_HASH, notes=["F"])
    assert got == (None, "tree-changed")


def test_eligibility_default_mode_still_accepts_not_ready_body_findings():
    got, reason = fidelity.body_recheck_eligibility(VERDICT1_PURE_BODY, TREE_1, TREE_1,
                                                    PLAN_HASH, PLAN_HASH)
    assert reason is None and got and got[0].startswith("- [BLOCKING] Check 4")


def test_fix_pr_copy_notes_returns_the_new_body(tmp_path):
    llm = _RecheckLlm(UsageLedger(), {
        "plan-fidelity-notes-body-fix-2": [{"body": "NEW body"}]})
    got = fidelity.fix_pr_copy_notes(llm, "OLD body", ["F"], str(tmp_path), round_num=1)
    assert got == "NEW body"
    assert llm.calls == [("plan-fidelity-notes-body-fix-2", "sonnet")]
    assert "OLD body" in llm.prompts["plan-fidelity-notes-body-fix-2"]


# ---------------------------------------------------------------------------
# Phase 3: runner wiring
# ---------------------------------------------------------------------------

FIX = "plan-fidelity-notes-body-fix-2"
RECHECK = "plan-fidelity-body-recheck-2"
_FIXED_BODY = "FIXED body prose: the file has 26 cases."


def _body_edits(gh):
    return [a["body"] for a in gh.actions() if a["action"] == "pr_edit_body"]


def test_notes_round_all_pr_copy_ends_ready(tmp_path, git_shim):
    pr = 9962
    try:
        res, llm, gh, git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"body": _FIXED_BODY}], RECHECK: [_pass_all]})
        assert _body_edits(gh)[-1].startswith("FIXED body prose")
        assert [p for p, _m in llm.calls if p == FIX] == [FIX]
        path2 = fidelity.verdict_path(f"{pr}-2")
        assert fidelity.parse_verdict(path2) == "READY"
        assert res.fidelity["notes_round"]["outcome"] == "ready"
        assert res.fidelity["verdict_1"] == "READY WITH NOTES"
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["reviewed_tree_2"] == TREE_1
        assert res.fidelity["verdict_path"] == path2
        assert res.fidelity["review_items"][-1]["remediation_sha"] == runner.BODY_ONLY_SHA
        terminal = fidelity.sticky_terminal(res.fidelity)
        assert terminal.startswith("READY (re-review)")
        assert fidelity._terminal_verdict_word(terminal) == "READY"
        assert _created_titles(gh) == []
        assert res.fidelity.get("filed_notes") is None
        assert res.fidelity.get("rounds", []) == []
        assert git.head_tree() == TREE_1
    finally:
        _cleanup(pr, f"{pr}-2")


def test_notes_round_recheck_fail_keeps_notes_and_files(tmp_path, git_shim):
    pr = 9963
    unresolved = {"findings": [{"id": "F1", "status": "unresolved",
                                "evidence": "the body still says 23"}], "hunks": []}

    def _fail(prompt):
        hids = re.findall(r"^(H\d+) \((?:insert|replace)\):", prompt, re.M)
        return {**unresolved,
                "hunks": [{"id": h, "status": "supported", "evidence": "ok"} for h in hids]}

    try:
        res, _llm, gh, _git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"body": _FIXED_BODY}], RECHECK: [_fail]})
        edits = _body_edits(gh)
        assert edits[-1] == "BEFORE body prose"
        assert any(e.startswith("FIXED body prose") for e in edits)
        assert res.fidelity["notes_round"]["outcome"] == "recheck-unresolved"
        assert res.fidelity.get("verdict_2") is None
        assert [n["title"] for n in res.fidelity["filed_notes"]] == [
            f"PR #{pr} copy: {_PR_COPY['title']}"]
        assert _created_titles(gh) == [f"PR #{pr} copy: {_PR_COPY['title']}"]
    finally:
        _cleanup(pr, f"{pr}-2")


def test_notes_round_envelope_fix_declines_and_files(tmp_path, git_shim):
    pr = 9964
    try:
        res, _llm, gh, _git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"result": {"body": "x"}}]})
        assert res.fidelity["notes_round"]["outcome"] == "fix-unavailable"
        assert res.fidelity.get("verdict_2") is None
        assert _created_titles(gh) == [f"PR #{pr} copy: {_PR_COPY['title']}"]
        assert not [e for e in _body_edits(gh) if e.startswith("FIXED")]
    finally:
        _cleanup(pr, f"{pr}-2")


def test_notes_round_protected_line_dropped_declines(tmp_path, git_shim):
    pr = 9965
    before = "<!-- keep-me -->\nBEFORE body prose"
    try:
        res, _llm, gh, _git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"body": "FIXED body prose"}]}, bodies=[before])
        assert res.fidelity["notes_round"]["outcome"] == "protected-line-dropped"
        assert not [e for e in _body_edits(gh) if e.startswith("FIXED")]
        assert _created_titles(gh) == [f"PR #{pr} copy: {_PR_COPY['title']}"]
        assert res.fidelity.get("verdict_2") is None
    finally:
        _cleanup(pr, f"{pr}-2")


def test_protected_lines_dropped_helper():
    before = "# Title\n<!-- marker -->\nprose"
    assert runner._protected_lines_dropped(before, "# Title\nprose") is True
    assert runner._protected_lines_dropped(before, "# Title\n<!-- marker -->\nnew prose") is False


def test_fix_pr_copy_notes_envelope_declines(tmp_path):
    for reply in ({"result": {"body": "x"}}, "a bare string", {"body": "   "},
                  {"body": "OLD body"}):
        llm = _RecheckLlm(UsageLedger(), {"plan-fidelity-notes-body-fix-2": [reply]})
        assert fidelity.fix_pr_copy_notes(llm, "OLD body", ["F"], str(tmp_path),
                                          round_num=1) is None


class _RecheckBoom(RuntimeError):
    """Stands in for a git error or UsagePause raised inside body_recheck."""


def test_notes_round_recheck_raises_restores_body(tmp_path, git_shim):
    pr = 9966

    def _raise(_prompt):
        raise _RecheckBoom("recheck boom")

    cap: dict = {}
    try:
        with pytest.raises(_RecheckBoom):
            _drive_notes(tmp_path, pr, [_PR_COPY], {
                FIX: [{"body": _FIXED_BODY}], RECHECK: [_raise]}, capture=cap)
        gh, res = cap["gh"], cap["res"]
        edits = _body_edits(gh)
        assert any(e.startswith("FIXED body prose") for e in edits)
        assert edits[-1] == "BEFORE body prose"
        assert res.fidelity["notes_round"]["outcome"] == "recheck-raised"
        assert res.fidelity.get("verdict_2") is None
    finally:
        _cleanup(pr, f"{pr}-2")


def test_notes_round_ready_records_body_only_remediation(tmp_path, git_shim):
    pr = 9967
    try:
        res, _llm, _gh, _git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"body": _FIXED_BODY}], RECHECK: [_pass_all]})
        fid = res.fidelity
        assert fid["verdict_2"] == "READY"
        # These two hold on master too; the notes round must leave them alone.
        assert fid["findings_round"] == 0
        assert fid["rounds_completed"] == 0
        assert fid["remediation_sha"] == runner.BODY_ONLY_SHA
        assert runner._last_remediation_commit(fid) is None
        digest = ["**What changed:** a", "**Why:** b", "**Watch:** none",
                  "**Touches:** c", "**Machine-authored fixes:** none"]
        rows = fidelity.digest_rows_for_display(digest, fid)
        assert rows[4] == "**Machine-authored fixes:** none (post-plan remediation: body-only)"
    finally:
        _cleanup(pr, f"{pr}-2")


def test_notes_round_declined_leaves_remediation_unset(tmp_path, git_shim):
    pr = 9968
    unresolved = {"findings": [{"id": "F1", "status": "unresolved",
                               "evidence": "the body still says 23"}], "hunks": []}

    def _fail(prompt):
        hids = re.findall(r"^(H\d+) \((?:insert|replace)\):", prompt, re.M)
        return {**unresolved,
                "hunks": [{"id": h, "status": "supported", "evidence": "ok"} for h in hids]}

    try:
        res, _llm, _gh, _git = _drive_notes(tmp_path, pr, [_PR_COPY], {
            FIX: [{"body": _FIXED_BODY}], RECHECK: [_fail]})
        assert res.fidelity["notes_round"]["outcome"] == "recheck-unresolved"
        assert res.fidelity["remediation_sha"] is None
    finally:
        _cleanup(pr, f"{pr}-2")


_COMMIT_SHA = "c0ffee" * 6 + "abcd"


def test_mark_notes_round_remediation_keeps_commit_sha():
    fid = {"remediation_sha": _COMMIT_SHA, "rounds": [{"remediation_sha": _COMMIT_SHA}]}
    runner._mark_notes_round_remediation(fid)
    assert fid["remediation_sha"] == _COMMIT_SHA
    assert runner._last_remediation_commit(fid) == _COMMIT_SHA


@pytest.mark.parametrize("fid", [{}, {"remediation_sha": None}, {"remediation_sha": ""}])
def test_mark_notes_round_remediation_fills_empty_slot(fid):
    runner._mark_notes_round_remediation(fid)
    assert fid["remediation_sha"] == runner.BODY_ONLY_SHA
    assert "findings_round" not in fid
    assert "rounds_completed" not in fid

