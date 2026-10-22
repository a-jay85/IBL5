"""Bounded body-only fidelity recheck: predicate, prompt, schema, orchestration, wiring.

A body-only remediation round whose prior verdict holds only PR-body (check 4)
findings gets a no-tools Sonnet recheck in place of the full Opus re-review. Every
non-pass falls through to the unchanged ``fidelity.re_review`` call.
"""
from __future__ import annotations

import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import armable, fidelity
from harness.state import PlanInfo, UsageLedger

from test_fidelity_body_only_round import _BodySeqGh
from test_fidelity_rounds import (TREE_1, TREE_2, _cleanup, _counting_git,  # noqa: F401
                                   _CountingGit, _drive, _plan, _Res, _ScriptedLlm,
                                   git_shim)


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


class _HookGh(_BodySeqGh):
    """Runs `hook()` on the first live body read, which comes after review 1."""

    def __init__(self, out_dir, bodies, hook=None, **kw):
        super().__init__(out_dir, bodies, **kw)
        self._hook = hook

    def pr_body_fresh(self):
        if self._hook is not None and self.fresh_calls == 0:
            self._hook()
        return super().pr_body_fresh()


def _found_plan(tmp_path):
    """A readable plan file, so _plan_hash is non-empty (a plan-blind run never rechecks)."""
    path = tmp_path / "plan.md"
    path.write_text("# Plan\n\n## Phase 1\nDo the thing.\n")
    return PlanInfo(found=True, path=str(path))


def _drive_body_only(tmp_path, pr, verdict1, extra_scripts=None, bodies=None,
                     git=None, plan=None, hook=None):
    gh = _HookGh(str(tmp_path),
                 bodies or ["BEFORE body prose", "AFTER body prose", "AFTER body prose"],
                 hook=hook, fixture={"pr_number": pr})
    git = git or _body_only_git()
    scripts = {
        "plan-fidelity-review": [verdict1],
        "fidelity-remediation": ["answered in the body"],
        "plan-fidelity-re-review-2": [RE_REVIEW_READY],
    }
    scripts.update(extra_scripts or {})
    llm = _RecheckLlm(UsageLedger(), scripts)
    res = _Res()
    runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh,
                         plan or _found_plan(tmp_path),
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


def test_pure_body_round_runs_recheck_not_opus_re_review(tmp_path, git_shim):
    try:
        res, llm = _drive_body_only(tmp_path, 9952, VERDICT1_PURE_BODY,
                                    {"plan-fidelity-body-recheck-2": [_pass_all]})
        assert ("plan-fidelity-body-recheck-2", "sonnet") in llm.calls
        assert not [c for c in llm.calls if c[0].startswith("plan-fidelity-re-review")]
        rec = res.fidelity["rounds"][0]
        assert rec["review_kind"] == "body-recheck"
        assert rec["recheck_fallback_reason"] is None
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["reviewed_tree_2"] == TREE_1
        assert res.fidelity["rounds_completed"] == 1
        assert armable.select_fidelity_verdict(
            "NOT READY", res.fidelity["verdict_2"], res.fidelity["reviewed_tree_2"],
            TREE_1) == ("READY", "verdict-2")
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


import pytest


@pytest.mark.parametrize("verdict", [
    "None.", "None", "Pass.", "OK", "No findings.", "n/a", "Clean!", "None -", "None \u2014",
])
def test_six_d_pass_re_accepts_bare_pass_verdicts(verdict):
    assert fidelity._SIX_D_PASS_RE.search(verdict)


@pytest.mark.parametrize("line", [
    "3. Scope creep: None of these edits were planned.",
    "5. Visual baselines: no issue with layout, but the baseline is missing.",
    "1. Intent coverage: Pass with one gap: Phase 2 is absent.",
    "3. Scope creep: FAIL. Unplanned edit, and there is no issue reference.",
    "6. Tests named by the matrix: None \u2014 the plan's test row is missing",
    "2. Plan text kept: None - the check fails because Phase 3 text is missing.",
    "2. Plan text kept: None \u2014 failed to cover phase 3.",
    "2. Plan text kept: No findings \u2013 but the diff drops plan Phase 3 text.",
])
def test_predicate_6d_loose_pass_wording_declines(line):
    num = line.split(".")[0]
    text = VERDICT1_SIX_D_PURE_BODY
    old = next(ln for ln in text.splitlines() if ln.startswith(num + "."))
    assert fidelity.body_only_findings_text(text.replace(old, line)) is None


@pytest.mark.parametrize("verdict", ["None \u2014", "None \u2014  ", "None -", "None \u2013 "])
def test_six_d_bare_trailing_dash_still_passes(verdict):
    text = VERDICT1_SIX_D_PURE_BODY.replace(
        "6. Tests named by the matrix: pass.", f"6. Tests named by the matrix: {verdict}")
    assert fidelity._six_d_failing(text) == {4}


def test_six_d_dash_followed_by_failure_text_is_not_a_pass():
    text = VERDICT1_SIX_D_PURE_BODY.replace(
        "6. Tests named by the matrix: pass.",
        "6. Tests named by the matrix: None \u2014 the plan's test row is missing")
    assert fidelity._six_d_failing(text) == {4, 6}


@pytest.mark.parametrize("cite", [
    "Checks 3 and 4", "checks 3/4", "Check 4 and 3", "checks 4, 5", "Check 4 & 5",
])
def test_predicate_multi_check_bullet_declines(cite):
    text = VERDICT1_SIX_D_PURE_BODY.replace("Check 4: the PR body", f"{cite}: the PR body")
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


# ---------------------------------------------------------------------------
# Phase 3: body hunks, diff sections, recheck prompt
# ---------------------------------------------------------------------------

from harness import llm_calls  # noqa: E402
from harness.adapters.llm import MAX_PROMPT_BYTES  # noqa: E402
from harness.classify import (FILES_CHANGED_BEGIN, FILES_CHANGED_END,  # noqa: E402
                              MERGE_DIGEST_BEGIN, MERGE_DIGEST_END)

TWO_FILE_DIFF = (
    "diff --git a/ibl5/tests/FooTest.php b/ibl5/tests/FooTest.php\n"
    "--- a/ibl5/tests/FooTest.php\n+++ b/ibl5/tests/FooTest.php\n"
    "@@ -1 +1,2 @@\n+    public function testTwentySix(): void {}\n"
    "diff --git a/ibl5/classes/Bar.php b/ibl5/classes/Bar.php\n"
    "--- a/ibl5/classes/Bar.php\n+++ b/ibl5/classes/Bar.php\n"
    "@@ -1 +1 @@\n-old bar\n+new bar\n"
)


def test_body_hunks_numbers_inserts_and_replacements_in_order():
    hunks = llm_calls.body_hunks("a\nb\nc", "a\nB\nc\nd")
    assert hunks == [
        {"id": "H1", "op": "replace", "old": ["b"], "new": ["B"]},
        {"id": "H2", "op": "insert", "old": [], "new": ["d"]},
    ]


def test_body_hunks_ignores_pure_deletions_and_identical_bodies():
    assert llm_calls.body_hunks("a\nb", "a") == []
    assert llm_calls.body_hunks("a\nb", "a\nb") == []


def test_body_hunks_strips_machine_blocks():
    old = (f"Prose.\n{FILES_CHANGED_BEGIN}\n- a.py\n{FILES_CHANGED_END}\n"
           f"{MERGE_DIGEST_BEGIN}\nold digest\n{MERGE_DIGEST_END}\nTail.")
    new = (f"Prose.\n{FILES_CHANGED_BEGIN}\n- a.py\n- b.py\n{FILES_CHANGED_END}\n"
           f"{MERGE_DIGEST_BEGIN}\nnew digest\n{MERGE_DIGEST_END}\nTail.")
    assert llm_calls.body_hunks(old, new) == []


def test_recheck_prompt_lists_every_id_once_and_states_the_contract():
    hunks = llm_calls.body_hunks("a", "a\nfollow-up filed")
    prompt = llm_calls.pr_body_recheck_prompt(
        ["- [BLOCKING] Check 4: count", "- [NOTE] Check 4: wording"], hunks,
        "a", "a\nfollow-up filed", "M\tx.py", "diff --git a/x.py b/x.py\n")
    assert prompt.count("F1:") == 1
    assert prompt.count("F2:") == 1
    assert "F0:" not in prompt
    assert prompt.count("H1 (") == 1
    assert '"hunks": [] is required' in prompt
    assert "unsupported" in prompt
    assert "Include every F-id and every H-id exactly once" in prompt


def test_recheck_prompt_caps_each_field_under_prompt_limit():
    prompt = llm_calls.pr_body_recheck_prompt(
        ["- [BLOCKING] Check 4: x"], [], "o" * 100_000, "n" * 200_000, "M\tx",
        "e" * 200_000)
    assert len(prompt.encode()) < MAX_PROMPT_BYTES
    assert "[TRUNCATED]" in prompt


def test_diff_sections_for_selects_only_named_paths():
    got = llm_calls.diff_sections_for(TWO_FILE_DIFF, {"ibl5/tests/FooTest.php"})
    assert "testTwentySix" in got
    assert "Bar.php" not in got
    head = llm_calls.diff_sections_for(TWO_FILE_DIFF, set())
    assert head.startswith(TWO_FILE_DIFF.splitlines()[0])


# ---------------------------------------------------------------------------
# Phase 4: recheck reply schema
# ---------------------------------------------------------------------------

from harness import schemas  # noqa: E402
from harness.state import HarnessError  # noqa: E402


def _item(i, status, evidence="body line 3 matches diff line 12"):
    return {"id": i, "status": status, "evidence": evidence}


def _reply(findings=(("F1", "resolved"), ("F2", "resolved")), hunks=(("H1", "supported"),)):
    return {"findings": [_item(i, s) for i, s in findings],
            "hunks": [_item(i, s) for i, s in hunks]}


def _raises_schema(data, finding_ids=("F1", "F2"), hunk_ids=("H1",)):
    with pytest.raises(HarnessError) as ei:
        schemas.validate_body_recheck(data, finding_ids=list(finding_ids),
                                      hunk_ids=list(hunk_ids))
    assert ei.value.kind == "schema"


def test_validate_body_recheck_accepts_exact_ids():
    schemas.validate_body_recheck(_reply(), finding_ids=["F1", "F2"], hunk_ids=["H1"])
    schemas.body_recheck_validator(["F1", "F2"], ["H1"])(_reply())


@pytest.mark.parametrize("reply", [
    _reply(findings=(("F1", "resolved"),)),
    _reply(findings=(("F1", "resolved"), ("F2", "resolved"), ("F3", "resolved"))),
    _reply(findings=(("F1", "resolved"), ("F1", "resolved"))),
    _reply(hunks=(("H9", "supported"),)),
], ids=["omitted", "unknown", "duplicate", "unknown-hunk"])
def test_validate_body_recheck_rejects_missing_extra_or_duplicate_ids(reply):
    _raises_schema(reply)


@pytest.mark.parametrize("status", ["maybe", "", 1, "supported"])
def test_validate_body_recheck_rejects_unknown_status(status):
    _raises_schema(_reply(findings=(("F1", status), ("F2", "resolved"))))


def test_validate_body_recheck_rejects_non_object_and_extra_keys():
    _raises_schema([_item("F1", "resolved")])
    _raises_schema("resolved")
    _raises_schema({**_reply(), "verdict": "READY"})
    extra = _reply()
    extra["findings"][0]["note"] = "x"
    _raises_schema(extra)
    missing = _reply()
    del missing["findings"][0]["evidence"]
    _raises_schema(missing)


def test_unwrap_body_recheck_envelope_variations():
    v = schemas.body_recheck_validator(["F1", "F2"], ["H1"])
    v(schemas.unwrap_body_recheck_envelope({"result": _reply()}))
    v(schemas.unwrap_body_recheck_envelope({"body_recheck": _reply()}))
    padded = _reply()
    padded["findings"][0]["status"] = " Resolved "
    folded = schemas.unwrap_body_recheck_envelope(padded)
    assert folded["findings"][0]["status"] == "resolved"
    v(folded)
    no_hunks = schemas.unwrap_body_recheck_envelope(
        {"findings": [_item("F1", "resolved")]})
    assert no_hunks["hunks"] == []
    schemas.validate_body_recheck(no_hunks, finding_ids=["F1"], hunk_ids=[])
    bare = [_item("F1", "resolved")]
    assert schemas.unwrap_body_recheck_envelope(bare) is bare
    _raises_schema(schemas.unwrap_body_recheck_envelope(bare))
    _raises_schema(schemas.unwrap_body_recheck_envelope({"result": _reply(), "extra": 1}))


def test_body_recheck_passed_requires_all_resolved_and_supported():
    assert schemas.body_recheck_passed(_reply()) is True
    assert schemas.body_recheck_passed(
        _reply(findings=(("F1", "resolved"), ("F2", "unresolved")))) is False
    assert schemas.body_recheck_passed(_reply(hunks=(("H1", "unsupported"),))) is False
    assert schemas.body_recheck_passed({"findings": [], "hunks": []}) is False


# ---------------------------------------------------------------------------
# Phase 5: body_recheck orchestration
# ---------------------------------------------------------------------------

class _RecheckLlm(_ScriptedLlm):
    """Adds the no-tools `call` path, mirroring ClaudeCli.call after its one re-ask."""

    def __init__(self, ledger, scripts):
        super().__init__(ledger, scripts)
        self.prompts: dict[str, str] = {}

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls.append((purpose, model))
        self.prompts[purpose] = prompt
        queue = self.scripts.get(purpose)
        if queue is None:
            return super().call(purpose, model, prompt, validate, max_retries, normalizer)
        val = queue.pop(0) if len(queue) > 1 else queue[0]
        if isinstance(val, dict) and "raise" in val:
            raise HarnessError(val["raise"]["kind"], val["raise"].get("detail", ""))
        if callable(val):
            val = val(prompt)
        if normalizer is not None:
            val = normalizer(val)
        try:
            validate(val)
        except HarnessError as e:
            raise HarnessError("llm-invalid-output", e.detail) from e
        return val


class _FakeGit:
    def __init__(self, tree=TREE_1, diff=TWO_FILE_DIFF):
        self.tree = tree
        self.diff = diff

    def head_tree(self):
        return self.tree

    def diff_vs_base(self):
        return self.diff


PLAN_HASH = "a" * 64
NAME_STATUS = "M\tibl5/tests/FooTest.php\nM\tibl5/classes/Bar.php\n"
PASS_REPLY = {"findings": [_item("F1", "resolved")], "hunks": []}


def _pass_all(prompt):
    """A passing reply for whatever hunks the drive's body edit produced."""
    hids = re.findall(r"^(H\d+) \((?:insert|replace)\):", prompt, re.M)
    return {"findings": [_item("F1", "resolved")],
            "hunks": [_item(h, "supported") for h in hids]}


def _prior(tmp_path, text=VERDICT1_PURE_BODY, tree=TREE_1):
    p = tmp_path / "v1.md"
    p.write_text(text)
    if tree:
        fidelity.record_reviewed_tree(str(p), tree)
    return str(p)


def _recheck(tmp_path, pr, reply=PASS_REPLY, *, prior=None, git=None,
             prior_body="Body says 26 cases.", pr_body="Body says 26 cases.",
             hashes=(PLAN_HASH, PLAN_HASH)):
    llm = _RecheckLlm(UsageLedger(), {"plan-fidelity-body-recheck-2": [reply]})
    out = fidelity.body_recheck(
        llm, git or _FakeGit(), str(tmp_path), str(tmp_path), _plan(), "dead" * 10,
        pr_body, prior_body, pr, prior or _prior(tmp_path), NAME_STATUS,
        hashes[0], hashes[1], round_num=1)
    return out, llm


def _path2(pr):
    return fidelity.verdict_path(f"{pr}-2")


def test_body_recheck_passes_and_writes_ready_verdict_file(tmp_path):
    pr = 990001
    try:
        out, llm = _recheck(tmp_path, pr)
        path2 = _path2(pr)
        assert out == ("READY", TREE_1, path2, None)
        assert fidelity.parse_verdict(path2) == "READY"
        assert fidelity.read_reviewed_tree(path2) == TREE_1
        assert fidelity.verdict_items(path2)["blocking"] == []
        text = open(path2).read()
        assert "Bounded body recheck" in text
        assert "**What changed:** x" in text
        assert llm.calls == [("plan-fidelity-body-recheck-2", "sonnet")]
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_declines_when_tree_changed(tmp_path):
    pr = 990002
    try:
        out, llm = _recheck(tmp_path, pr, git=_FakeGit(tree="beef" * 10))
        assert out == (None, None, None, "tree-changed")
        assert llm.calls == []
        assert not os.path.exists(_path2(pr))
    finally:
        _cleanup(f"{pr}-2")


@pytest.mark.parametrize("hashes,reason", [
    (("a" * 64, "b" * 64), "plan-changed"),
    ((None, "b" * 64), "plan-hash-missing"),
])
def test_body_recheck_declines_when_plan_hash_changed_or_missing(tmp_path, hashes, reason):
    pr = 990003
    try:
        out, llm = _recheck(tmp_path, pr, hashes=hashes)
        assert out == (None, None, None, reason)
        assert llm.calls == []
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_declines_when_prior_tree_unrecorded(tmp_path):
    pr = 990004
    try:
        out, llm = _recheck(tmp_path, pr, prior=_prior(tmp_path, tree=None))
        assert out == (None, None, None, "prior-tree-unrecorded")
        assert llm.calls == []
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_declines_non_pure_body_without_calling_llm(tmp_path):
    pr = 990005
    try:
        out, llm = _recheck(tmp_path, pr, prior=_prior(tmp_path, VERDICT1_CODE))
        assert out == (None, None, None, "not-pure-body")
        assert llm.calls == []
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_declines_on_unresolved_finding_and_writes_nothing(tmp_path):
    pr = 990006
    try:
        out, _llm = _recheck(tmp_path, pr,
                             {"findings": [_item("F1", "unresolved")], "hunks": []})
        assert out == (None, None, None, "recheck-unresolved")
        assert not os.path.exists(_path2(pr))
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_declines_on_unsupported_added_hunk(tmp_path):
    pr = 990007
    reply = {"findings": [_item("F1", "resolved")], "hunks": [_item("H1", "unsupported")]}
    try:
        out, llm = _recheck(tmp_path, pr, reply, prior_body="a\nb",
                            pr_body="a\nb\nFollow-up filed.")
        assert "H1 (insert)" in llm.prompts["plan-fidelity-body-recheck-2"]
        assert out == (None, None, None, "recheck-unresolved")
        assert not os.path.exists(_path2(pr))
    finally:
        _cleanup(f"{pr}-2")


@pytest.mark.parametrize("reply,reason", [
    ("not json", "recheck-llm-invalid-output"),
    ([_item("F1", "resolved")], "recheck-llm-invalid-output"),
    ({"raise": {"kind": "llm-cli"}}, "recheck-llm-cli"),
    ({"raise": {"kind": "usage-limit"}}, "recheck-harness-error"),
], ids=["string", "bare-list", "llm-cli", "unknown-kind"])
def test_body_recheck_declines_on_invalid_reply_or_harness_error(tmp_path, reply, reason):
    pr = 990008
    try:
        out, _llm = _recheck(tmp_path, pr, reply)
        assert out == (None, None, None, reason)
        assert not os.path.exists(_path2(pr))
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_result_envelope_still_passes(tmp_path):
    pr = 990009
    try:
        out, _llm = _recheck(tmp_path, pr, {"result": PASS_REPLY})
        assert out[0] == "READY" and out[3] is None
    finally:
        _cleanup(f"{pr}-2")


def test_body_recheck_evidence_names_only_cited_files(tmp_path):
    pr = 990010
    try:
        _out, llm = _recheck(tmp_path, pr)
        prompt = llm.prompts["plan-fidelity-body-recheck-2"]
        evidence = prompt.split("## DIFF EVIDENCE", 1)[1]
        assert "testTwentySix" in evidence
        assert "Bar.php" not in evidence
    finally:
        _cleanup(f"{pr}-2")


# ---------------------------------------------------------------------------
# Phase 6: round-loop wiring in runner._run_fidelity
# ---------------------------------------------------------------------------

class _MovedTreeGit(_CountingGit):
    """head_tree() answers a tree other than TREE_1, the one review 1 recorded.

    _run_fidelity records verdict 1 against its `current_tree` argument, so the
    recheck's own head_tree() read is the first one this double serves.
    """

    def head_tree(self):
        return "beef" * 10


def _recheck_calls(llm):
    return [c for c in llm.calls if c[0].startswith("plan-fidelity-body-recheck")]


def test_body_only_round_tree_changed_falls_back_to_full_re_review(tmp_path, git_shim):
    git = _MovedTreeGit({"slug": "demo", "worktree_diff": "",
                         "diff": "diff --git a/x b/x\n"}, commit_returns=[""])
    try:
        res, llm = _drive_body_only(tmp_path, 9961, VERDICT1_PURE_BODY,
                                    {"plan-fidelity-body-recheck-2": [_pass_all]}, git=git)
        rec = res.fidelity["rounds"][0]
        assert rec["outcome"] == "body-only"
        assert rec["recheck_fallback_reason"] == "tree-changed"
        assert rec["review_kind"] == "full"
        assert ("plan-fidelity-re-review-2", "opus") in llm.calls
        assert _recheck_calls(llm) == []
    finally:
        _cleanup(9961, "9961-2")


def test_body_only_round_with_code_note_falls_back(tmp_path, git_shim):
    verdict1 = VERDICT1_PURE_BODY.replace(
        "methods.\n", "methods.\n- [NOTE] Check 5: VR baseline missing.\n", 1)
    try:
        res, llm = _drive_body_only(tmp_path, 9962, verdict1,
                                    {"plan-fidelity-body-recheck-2": [_pass_all]})
        assert res.fidelity["rounds"][0]["recheck_fallback_reason"] == "not-pure-body"
        assert ("plan-fidelity-re-review-2", "opus") in llm.calls
    finally:
        _cleanup(9962, "9962-2")


NOT_READY_RE_REVIEW = "6d checks\n\n## FINDINGS\n- [BLOCKING] Check 4: still wrong.\n\nNOT READY\n"


def test_recheck_unresolved_then_not_ready_re_review_holds_condition_12(tmp_path, git_shim):
    try:
        res, _llm = _drive_body_only(tmp_path, 9963, VERDICT1_PURE_BODY, {
            "plan-fidelity-body-recheck-2": [lambda p: {**_pass_all(p),
                                                        "findings": [_item("F1", "unresolved")]}],
            "plan-fidelity-re-review-2": [NOT_READY_RE_REVIEW],
        })
        assert res.fidelity["rounds"][0]["recheck_fallback_reason"] == "recheck-unresolved"
        assert res.fidelity["verdict_2"] == "NOT READY"
        assert armable.select_fidelity_verdict(
            "NOT READY", res.fidelity["verdict_2"], res.fidelity["reviewed_tree_2"],
            TREE_1) == ("NOT READY", "verdict-2")
    finally:
        _cleanup(9963, "9963-2", "9963-3", "9963-4")


def test_recheck_invalid_reply_falls_back_and_never_reads_ready(tmp_path, git_shim):
    try:
        res, _llm = _drive_body_only(tmp_path, 9964, VERDICT1_PURE_BODY, {
            "plan-fidelity-body-recheck-2": ["garbage"],
            "plan-fidelity-re-review-2": [NOT_READY_RE_REVIEW],
        })
        rec = res.fidelity["rounds"][0]
        assert rec["recheck_fallback_reason"] == "recheck-llm-invalid-output"
        assert res.fidelity["verdict_2"] == "NOT READY"
    finally:
        _cleanup(9964, "9964-2", "9964-3", "9964-4")


def test_recheck_plan_hash_changed_falls_back(tmp_path, git_shim, monkeypatch):
    def hook():
        monkeypatch.setattr(runner, "_plan_hash", lambda plan: "f" * 64)
    try:
        res, llm = _drive_body_only(tmp_path, 9965, VERDICT1_PURE_BODY,
                                    {"plan-fidelity-body-recheck-2": [_pass_all]},
                                    hook=hook)
        assert res.fidelity["rounds"][0]["recheck_fallback_reason"] == "plan-changed"
        assert _recheck_calls(llm) == []
    finally:
        _cleanup(9965, "9965-2")


def test_recheck_prior_body_missing_falls_back(tmp_path, git_shim):
    def hook():
        os.remove(os.path.join(str(tmp_path), "fidelity-packet", "pr-body.md"))
    try:
        res, llm = _drive_body_only(tmp_path, 9966, VERDICT1_PURE_BODY,
                                    {"plan-fidelity-body-recheck-2": [_pass_all]},
                                    hook=hook)
        assert res.fidelity["rounds"][0]["recheck_fallback_reason"] == "prior-body-missing"
        assert _recheck_calls(llm) == []
        assert ("plan-fidelity-re-review-2", "opus") in llm.calls
    finally:
        _cleanup(9966, "9966-2")


def test_commit_round_records_full_review_kind(tmp_path, git_shim):
    llm = _RecheckLlm(UsageLedger(), {
        "plan-fidelity-review": [VERDICT1_PURE_BODY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-body-recheck-2": [_pass_all],
        "plan-fidelity-re-review-2": [RE_REVIEW_READY],
    })
    try:
        res = _drive(tmp_path, llm, _counting_git(), 9967)
        rec = res.fidelity["rounds"][0]
        assert rec["outcome"] == "committed"
        assert rec["review_kind"] == "full"
        assert rec["recheck_fallback_reason"] is None
        assert _recheck_calls(llm) == []
    finally:
        _cleanup(9967, "9967-2")


class _SeqBodyGh(_BodySeqGh):
    """Every live read returns a new body, so each round sees an edited body."""

    def pr_body_fresh(self):
        self._body_override = None
        self.fresh_calls += 1
        return f"body prose v{self.fresh_calls}"


def test_round_two_body_only_uses_round_one_re_review_as_prior(tmp_path, git_shim):
    git = _CountingGit({"slug": "demo", "worktree_diff": "",
                        "diff": "diff --git a/x b/x\n", "head_trees": [TREE_1, TREE_2]},
                       commit_returns=["round-sha-1", ""])
    gh = _SeqBodyGh(str(tmp_path), ["unused"], fixture={"pr_number": 9968})
    llm = _RecheckLlm(UsageLedger(), {
        "plan-fidelity-review": [VERDICT1_PURE_BODY],
        "fidelity-remediation": ["edited"],
        "plan-fidelity-re-review-2": [VERDICT1_PURE_BODY],
        "plan-fidelity-body-recheck-3": [_pass_all],
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh,
                             _found_plan(tmp_path), "diff",
                             "original-body", 9968, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        rounds = res.fidelity["rounds"]
        assert rounds[0]["outcome"] == "committed"
        assert rounds[1]["outcome"] == "body-only"
        assert ("plan-fidelity-body-recheck-3", "sonnet") in llm.calls
        assert rounds[1]["review_kind"] == "body-recheck"
        assert rounds[1]["verdict_path"].endswith("-3.md")
    finally:
        _cleanup(9968, "9968-2", "9968-3")
