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
