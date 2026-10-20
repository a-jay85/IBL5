"""Bounded body-only fidelity recheck: predicate, prompt, schema, orchestration, wiring.

A body-only remediation round whose prior verdict holds only PR-body (check 4)
findings gets a no-tools Sonnet recheck in place of the full Opus re-review. Every
non-pass falls through to the unchanged ``fidelity.re_review`` call.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from harness.state import UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_rounds import (TREE_1, TREE_2, _cleanup, _CountingGit,  # noqa: F401
                                   _plan, _Res, _ScriptedLlm, git_shim)


VERDICT1_PURE_BODY = """REVIEW-COVERAGE: CURRENT
4. PR body vs. reality: the body says "23-case" but tests/FooTest.php has 26 cases.
Passed: 1, 2, 3, 5, 6.

## FINDINGS
- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.

NOT READY

## DIGEST
**What changed:** x
**Why:** x
**Risk:** x
**Rollback:** x
**Verified:** x
"""

VERDICT1_CODE = VERDICT1_PURE_BODY.replace(
    "4. PR body vs. reality: the body says \"23-case\" but tests/FooTest.php has 26 cases.\nPassed: 1, 2, 3, 5, 6.",
    "3. Scope creep: unplanned edit to .claude/rules/foo.md.\nPassed: 1, 2, 4, 5, 6.",
).replace(
    "- [BLOCKING] Check 4: the PR body claims a \"23-case\" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.",
    "- [BLOCKING] Check 3: unplanned `.claude/rules/foo.md` edit no plan phase asked for.",
)

RE_REVIEW_READY = "6d checks\n\n## FINDINGS\n\nREADY\n\n## DIGEST\n**What changed:** y\n"


def _body_only_git(trees=(TREE_1,)):
    """A git double whose HEAD tree stays put across a no-commit fixer round.

    `_counting_git` advances its tree on every `commit_all` call, even one that commits
    nothing; a body-only round leaves the real tree untouched, so pin it here.
    """
    return _CountingGit({"slug": "demo", "worktree_diff": "",
                         "diff": "diff --git a/x b/x\n", "head_trees": list(trees)},
                        commit_returns=[""])


def _drive_body_only(tmp_path, pr, verdict1, extra_scripts=None, bodies=None,
                     git=None, plan=None):
    gh = _BodySeqGh(str(tmp_path),
                    bodies or ["BEFORE body prose", "AFTER body prose", "AFTER body prose"],
                    fixture={"pr_number": pr})
    git = git or _body_only_git()
    scripts = {
        "plan-fidelity-review": [verdict1],
        "fidelity-remediation": ["answered in the body"],
        "plan-fidelity-re-review-2": [RE_REVIEW_READY],
    }
    scripts.update(extra_scripts or {})
    llm = _ScriptedLlm(UsageLedger(), scripts)
    res = _Res()
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, plan or _plan(),
                         "diff", "original-body", pr, "dead" * 10, TREE_1, False,
                         lambda _m: None, res)
    return res, llm


# ---------------------------------------------------------------------------
# Phase 1: characterization of the current body-only round path
# ---------------------------------------------------------------------------

def test_body_only_round_with_code_finding_runs_full_re_review(tmp_path, git_shim):
    try:
        res, llm = _drive_body_only(tmp_path, 9951, VERDICT1_CODE)
        assert ("plan-fidelity-re-review-2", "opus") in llm.calls
        assert res.fidelity["rounds"][0]["outcome"] == "body-only"
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["reviewed_tree_2"] == TREE_1
    finally:
        _cleanup(9951, "9951-2")


def test_body_only_round_with_pure_body_finding_runs_full_re_review_today(tmp_path,
                                                                         git_shim):
    try:
        res, llm = _drive_body_only(tmp_path, 9952, VERDICT1_PURE_BODY)
        assert ("plan-fidelity-re-review-2", "opus") in llm.calls
        assert not [c for c in llm.calls if c[0].startswith("plan-fidelity-body-recheck")]
    finally:
        _cleanup(9952, "9952-2")


def test_verdict_one_file_carries_reviewed_tree(tmp_path, git_shim):
    try:
        res, _llm = _drive_body_only(tmp_path, 9953, VERDICT1_CODE)
        # res.fidelity["verdict_path"] advances with each round; verdict 1 lives here.
        path = fidelity.verdict_path(9953)
        assert fidelity.read_reviewed_tree(path) == TREE_1
    finally:
        _cleanup(9953, "9953-2")


# ---------------------------------------------------------------------------
# Phase 2: fail-closed body-finding predicate
# ---------------------------------------------------------------------------

import pytest  # noqa: E402

FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures",
                        "body-recheck")

VERDICT1_SIX_D_PURE_BODY = """## 6c. Coverage

All six phases reviewed.

## 6d. Fidelity checks

1. Intent coverage: pass. Every phase has matching changes.
2. Plan text kept: pass.
3. Scope creep: pass. No unplanned files.
4. PR body vs. reality: FAIL. The body says "23-case"; the file has 26.
5. Visual baselines: pass. No UI surface.
6. Tests named by the matrix: pass.

## FINDINGS
- [BLOCKING] Check 4: the PR body claims a "23-case" test file; `ibl5/tests/FooTest.php` carries 26 `test` methods.

NOT READY

## DIGEST
**What changed:** x
"""

_CODE_NOTE = "- [NOTE] Check 5: VR baseline for the gallery page is missing."


def _with_extra_finding(text, bullet):
    return text.replace("\n\nNOT READY", f"\n{bullet}\n\nNOT READY", 1)


def test_predicate_plain_layout_pure_body_returns_bullets():
    got = fidelity.body_only_findings_text(VERDICT1_PURE_BODY)
    assert got is not None and len(got) == 1
    assert got[0].startswith("- [BLOCKING] Check 4")


def test_predicate_declines_code_finding():
    assert fidelity.body_only_findings_text(VERDICT1_CODE) is None
    # A bullet that cites no check is attributed to the failing checks, so rule 4
    # alone has to decline it.
    uncited = VERDICT1_CODE.replace("- [BLOCKING] Check 3: unplanned", "- [BLOCKING] Unplanned")
    assert fidelity.body_only_findings_text(uncited) is None


def test_predicate_declines_code_note_alongside_body_blocker():
    text = _with_extra_finding(VERDICT1_PURE_BODY, _CODE_NOTE)
    assert fidelity.body_only_findings_text(text) is None


def test_predicate_declines_unmarked_bullet():
    text = _with_extra_finding(VERDICT1_PURE_BODY, "- Check 4: also reword the Summary.")
    assert fidelity.body_only_findings_text(text) is None


@pytest.mark.parametrize("coverage,eligible", [
    ("STALE", False), ("NONE", False), ("UNKNOWN", False), ("CURRENT", True), (None, True),
])
def test_predicate_declines_stale_or_unknown_coverage(coverage, eligible):
    if coverage is None:
        text = VERDICT1_PURE_BODY.replace("REVIEW-COVERAGE: CURRENT\n", "", 1)
    else:
        text = VERDICT1_PURE_BODY.replace("CURRENT", coverage, 1)
    got = fidelity.body_only_findings_text(text)
    assert (got is not None) is eligible


def test_predicate_declines_passed_line_missing_a_check():
    text = VERDICT1_PURE_BODY.replace("Passed: 1, 2, 3, 5, 6.", "Passed: 1, 2, 3, 6.")
    assert fidelity.body_only_findings_text(text) is None


def test_predicate_6d_layout_pure_body():
    got = fidelity.body_only_findings_text(VERDICT1_SIX_D_PURE_BODY)
    assert got is not None and got[0].startswith("- [BLOCKING] Check 4")


def test_predicate_6d_layout_two_failing_checks_declines():
    text = VERDICT1_SIX_D_PURE_BODY.replace(
        "2. Plan text kept: pass.", "2. Plan text kept: the diff drops plan Phase 3 text.")
    assert fidelity.body_only_findings_text(text) is None


def test_predicate_unparseable_layout_returns_none():
    text = ("Some prose about the checks with no Passed line.\n\n## FINDINGS\n"
            "- [BLOCKING] Check 4: body count is wrong.\n\nNOT READY\n")
    assert fidelity.body_only_findings_text(text) is None


def test_predicate_ready_verdict_returns_none():
    text = VERDICT1_PURE_BODY.replace(
        "- [BLOCKING] Check 4", "- [NOTE] Check 4").replace(
        "\nNOT READY\n", "\nREADY WITH NOTES\n")
    assert fidelity.body_only_findings_text(text) is None


def test_predicate_real_shape_fixtures(tmp_path):
    pure = fidelity.body_only_findings(os.path.join(FIXTURES, "verdict-pure-body-live.md"))
    assert pure and pure[0].startswith("- [BLOCKING] Check 4")
    assert fidelity.body_only_findings(
        os.path.join(FIXTURES, "verdict-class-d-live.md")) is None
    assert fidelity.body_only_findings(str(tmp_path / "absent.md")) is None
