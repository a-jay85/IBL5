"""runner._run_fidelity end to end for READY WITH NOTES runs and the body-notes step."""
from __future__ import annotations

import hashlib
import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import body_notes, fidelity, fidelity_delta
from harness.adapters.ghad import RecordingGh
from harness.state import UsageLedger

import runner
from test_body_notes import PURPOSE_FIX, PURPOSE_RECHECK, _edit, _fix, _recheck
from test_fidelity_carryforward import (DIFF as CF_DIFF, _cache, _drive_cf,  # noqa: F401
                                         _sticky, git_shim as cf_git_shim)
from test_fidelity_rounds import (NOT_READY, TREE_1, _CountingGit, _counting_git,  # noqa: F401
                                   _cleanup, _plan, _Res, _ScriptedLlm, git_shim)
from test_runner_review_owed import CANNED, _fixture, _sticky_bodies

RWN_NO_BODY_NOTE = (
    "6d checks\n\n## FINDINGS\n\n"
    "- [NOTE] `runner.py` could cache the parsed plan; follow up later.\n\n"
    "READY WITH NOTES\n")
RWN_TWO_BODY = (
    "6d checks\n\n## FINDINGS\n\n"
    "- [NOTE] Add the red-half failure output to the PR body\n"
    "- [NOTE] Mention the gap change in the PR body\n\n"
    "READY WITH NOTES\n")
RWN_ONE_BODY = (
    "6d checks\n\n## FINDINGS\n\n"
    "- [NOTE] Add the red-half failure output to the PR body\n\n"
    "READY WITH NOTES\n")
CLAUSE_1 = "The red-half failure output is in ibl5/x.php."
CLAUSE_2 = "The gap change is in ibl5/x.php."


class _GhSpy(RecordingGh):
    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        self.edits: list = []

    def pr_edit_body(self, pr, body):
        self.edits.append((pr, body))
        return super().pr_edit_body(pr, body)


def test_rwn_without_body_notes_makes_no_fix_call(tmp_path, git_shim):
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [RWN_NO_BODY_NOTE],
        "fidelity-notes": [[]],
    })
    gh = _GhSpy(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), _counting_git(), gh,
                             _plan(), "diff", "body", 9960, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        assert not [p for p, _m in llm.calls if p.startswith("plan-fidelity-body-notes")]
        assert gh.edits == []
        assert res.fidelity["remediation_sha"] is None
    finally:
        _cleanup(9960)


# --- runner.run() end to end: the body-notes step sits between the notes and the record ----

class _BodyGh(RecordingGh):
    """Records every body write; pr_body_fresh answers with the last write, like GitHub."""

    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        self.edits: list[str] = []

    def pr_edit_body(self, pr, body):
        self.edits.append(body)
        return super().pr_edit_body(pr, body)

    def pr_body_fresh(self):
        if self.edits:
            return self.edits[-1]
        return super().pr_body_fresh()


class _BodyNotesLlm(_ScriptedLlm):
    """_ScriptedLlm plus per-purpose queues for the two body-notes calls (a payload or a
    callable(prompt)). Records each body-notes purpose and the write count at the fix call."""

    def __init__(self, ledger, scripts, bn_queues):
        super().__init__(ledger, scripts)
        self.bn_queues = {k: list(v) for k, v in bn_queues.items()}
        self.bn_purposes: list[str] = []
        self.gh = None
        self.writes_at_fix = None

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if not purpose.startswith("plan-fidelity-body-notes"):
            return super().call(purpose, model, prompt, validate, max_retries, normalizer)
        self.bn_purposes.append(purpose)
        if purpose == PURPOSE_FIX:
            self.writes_at_fix = len(self.gh.edits)
        item = self.bn_queues[purpose].pop(0)
        if callable(item):
            item = item(prompt)
        data = normalizer(item) if normalizer else item
        validate(data)
        return data


def _drive_notes(monkeypatch, pr, scripts, bn_queues, commit_returns=("",)):
    out = tempfile.mkdtemp(prefix="postplan-test-body-notes-e2e-")
    fx = _fixture(pr_number=pr, pr_meta={
        "number": pr, "title": "fix: synthetic", "headRefOid": "deadbeef",
        "body": "## Summary\n- 1 file changed\n\n## Manual Testing\n\nNone\n"})
    fx["diff"] = "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n"
    fx["head_trees"] = ["a" * 40, "b" * 40, "c" * 40, "d" * 40]
    git = _CountingGit(fx, commit_returns=list(commit_returns))
    llm = _BodyNotesLlm(UsageLedger(), {**{k: [v] for k, v in CANNED.items()}, **scripts},
                        bn_queues)
    gh = _BodyGh(out, fx)
    llm.gh = gh
    monkeypatch.setattr(runner, "ReplayGit", lambda _fx: git)
    monkeypatch.setattr(runner, "RecordingGh", lambda _out, _fx=None: gh)
    monkeypatch.setenv("REVIEW_OWED_PR_REVIEW_NOW", "/nonexistent")
    res = runner.run(fx, out, llm, mode="replay", headless=True)
    return res, out, gh, llm


def _terminal_of(out):
    """The sticky's terminal line: the line just above the sticky marker."""
    lines = [ln for ln in _sticky_bodies(out)[-1].splitlines() if ln.strip()]
    assert lines[-1] == fidelity.STICKY_MARKER
    return lines[-2]


def _one_fix(prompt):
    return _fix([_edit("N1", "## Summary", f"## Summary\n\n{CLAUSE_1}")], ["fixed"])


def test_rwn_body_note_fixed_end_to_end(tmp_path, git_shim, monkeypatch):
    def fix(prompt):
        return _fix([_edit("N1", "## Summary", f"## Summary\n\n{CLAUSE_1}"),
                     _edit("N2", CLAUSE_1, f"{CLAUSE_1} {CLAUSE_2}")],
                    ["fixed", "fixed"])

    try:
        res, out, gh, llm = _drive_notes(
            monkeypatch, 9961,
            {"plan-fidelity-review": [RWN_TWO_BODY], "fidelity-notes": [[]]},
            {PURPOSE_FIX: [fix], PURPOSE_RECHECK: [_recheck]})
        assert llm.bn_purposes == [PURPOSE_FIX, PURPOSE_RECHECK]
        written = [b for b in gh.edits[llm.writes_at_fix:] if CLAUSE_1 in b]
        assert len(written) == 1 and CLAUSE_2 in written[0]
        assert res.fidelity["remediation_sha"] == "body-only"
        assert res.fidelity["verdict_1"] == "READY WITH NOTES"
        assert res.fidelity["verdict_2"] is None
        terminal = _terminal_of(out)
        assert terminal == ("READY WITH NOTES — all notes remediated in the PR body; "
                            f"reviewer verdict covers tree {res.fidelity['reviewed_tree']}, "
                            "not the post-remediation head")
        assert terminal == body_notes.terminal_line(res.fidelity)
    finally:
        _cleanup(9961)


def test_rwn_body_fix_runs_before_record_outcome(tmp_path, git_shim, monkeypatch):
    snaps: list = []
    real = fidelity_delta.record_outcome

    def spy(state_dir, slug, fid, **kw):
        snaps.append(dict(fid))
        return real(state_dir, slug, fid, **kw)

    monkeypatch.setattr(fidelity_delta, "record_outcome", spy)
    try:
        _drive_notes(monkeypatch, 9962,
                     {"plan-fidelity-review": [RWN_ONE_BODY], "fidelity-notes": [[]]},
                     {PURPOSE_FIX: [_one_fix], PURPOSE_RECHECK: [_recheck]})
        assert snaps, "record_outcome was never reached"
        assert snaps[0]["remediation_sha"] == "body-only"
    finally:
        _cleanup(9962)


def test_rwn_failed_recheck_sticky_names_note(tmp_path, git_shim, monkeypatch):
    seen: dict = {}

    def fix(prompt):
        seen["original"] = prompt.split("<<<BODY\n", 1)[1].split("\nBODY>>>", 1)[0]
        return _one_fix(prompt)

    try:
        res, out, gh, llm = _drive_notes(
            monkeypatch, 9963,
            {"plan-fidelity-review": [RWN_ONE_BODY], "fidelity-notes": [[]]},
            {PURPOSE_FIX: [fix], PURPOSE_RECHECK: [lambda p: _recheck(p, resolved=False)]})
        assert llm.bn_purposes == [PURPOSE_FIX, PURPOSE_RECHECK]
        step = gh.edits[llm.writes_at_fix:]
        assert CLAUSE_1 in step[0], "the fix body was never written"
        assert step[1].rstrip("\n") == seen["original"].rstrip("\n")
        assert CLAUSE_1 not in gh.edits[-1], "the final stored body kept the fix"
        assert not res.fidelity.get("remediation_sha")
        assert _terminal_of(out).endswith("(body fix reverted: recheck-unresolved)")
    finally:
        _cleanup(9963)


def test_carried_rwn_run_makes_no_fix_call(tmp_path, cf_git_shim, monkeypatch):
    plan_body = b"# plan\n"
    plan_hash = hashlib.sha256(plan_body).hexdigest()
    diff_id = fidelity.diff_patch_id(CF_DIFF)
    sticky = _sticky(verdict="READY WITH NOTES", diff_id=diff_id, plan_hash=plan_hash)
    edits: list = []
    real = RecordingGh.pr_edit_body

    def spy(self, pr, body):
        edits.append(body)
        return real(self, pr, body)

    monkeypatch.setattr(RecordingGh, "pr_edit_body", spy)
    purposes: list = []
    res, spawns, _logs = _drive_cf(
        tmp_path, prior_sticky=sticky, plan_body=plan_body, purposes_out=purposes,
        verdict_text=RWN_TWO_BODY, **_cache(plan_body, verdict="READY WITH NOTES"))
    fid = res.fidelity
    assert spawns == 0 and fid.get("carried_forward") is True
    assert fid["verdict_1"] == "READY WITH NOTES"
    assert not [p for p in purposes if p.startswith("plan-fidelity-body-notes")]
    assert edits == []
    # The sticky terminal line is runner's own expression: body_notes row, else the plain one.
    assert body_notes.terminal_line(fid) is None
    assert fidelity.terminal_line(fid.get("verdict_1"), fid.get("error_kind"),
                                  fid.get("remediation_sha"), fid.get("verdict_2"),
                                  fid.get("reviewed_tree_2"),
                                  fid.get("rounds_completed", 0)) == "READY WITH NOTES"


def test_not_ready_round_then_rwn_makes_no_body_notes_call(tmp_path, git_shim, monkeypatch):
    try:
        res, out, gh, llm = _drive_notes(
            monkeypatch, 9964,
            {"plan-fidelity-review": [NOT_READY],
             "fidelity-remediation": ["committed a fix"],
             "plan-fidelity-re-review-2": [RWN_ONE_BODY],
             "fidelity-notes": [[]]},
            {}, commit_returns=("phase2-sha", "round-sha-1"))
        assert res.fidelity["rounds"], "the remediation round never ran"
        assert res.fidelity["verdict_2"] == "READY WITH NOTES"
        assert llm.bn_purposes == []
        assert not [p for p, _m in llm.calls if p.startswith("plan-fidelity-body-notes")]
    finally:
        _cleanup(9964, "9964-2")
