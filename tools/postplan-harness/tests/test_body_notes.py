"""Unit tests for harness/body_notes.py: fixing PR-body notes on READY WITH NOTES.

The first three tests characterize behavior this PR relies on. They pass on master.
"""
from __future__ import annotations

import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pytest

from harness import body_notes, fidelity, reviewcache
from harness.state import HarnessError


def test_terminal_line_bare_for_ready_with_notes():
    assert fidelity.terminal_line("READY WITH NOTES", None, None, None, None, 0) \
        == "READY WITH NOTES"


def test_sticky_prior_verdict_accepts_body_notes_rows():
    terminals = [
        "READY WITH NOTES — all notes remediated in the PR body; reviewer verdict covers "
        "tree 0123456789abcdef0123456789abcdef01234567, not the post-remediation head",
        "READY WITH NOTES — Verification Matrix row 11 is still not done "
        "(left for the human); remediated the rest in the PR body",
        "READY WITH NOTES — Add the Phase 1 red-half failure output to the PR body "
        "(body fix reverted: recheck-unresolved)",
    ]
    for terminal in terminals:
        body = "header line\n\n" + terminal + "\n" + fidelity.STICKY_MARKER
        assert fidelity._sticky_prior_verdict(body) == "READY WITH NOTES"
        assert fidelity._RE_REVIEW_TERMINAL_RE.match(terminal) is None


def test_cache_arm_rejects_body_only_remediation():
    fid = {"diff_id": "a" * 40, "plan_hash": "b" * 64, "verdict_1": "READY WITH NOTES",
           "verdict_2": None, "rounds": [], "rounds_completed": 0,
           "remediation_sha": None}
    assert reviewcache.vet_fidelity(dict(fid), "run-1") is not None
    assert reviewcache.vet_fidelity({**fid, "remediation_sha": "body-only"}, "run-1") is None


# ---------------------------------------------------------------------------
# Phase 2: pure helpers
# ---------------------------------------------------------------------------

BODY = """## Summary

The change adds the helper module.

<!-- merge-digest:begin -->
Merge digest row one
<!-- merge-digest:end -->

<!-- files-changed:begin -->
- tools/a.py
<!-- files-changed:end -->

No manual testing needed for the docs half.

## Manual Testing

- [ ] Open the page and look at it
**Decision:** manual only

## Notes

Closing paragraph.
"""


def _edit(note, find, replace):
    return {"note": note, "find": find, "replace": replace}


def _rejects(find, replace, reason, body=BODY):
    new, applied, rejected = body_notes.apply_edits(
        body, [_edit("N1", find, replace)], {"N1"})
    assert rejected == {"N1": reason}
    assert applied == []
    assert new == body


def test_body_citing_matches_pr_body_variants():
    for title in ("Add output to the PR body", "update pr-body.md", "the PR's body is thin",
                  "fix the PR-body"):
        assert body_notes.body_citing([title]) is True, title


def test_body_citing_near_miss_inputs():
    for titles in (["the body of run()"], ["PRbody"], ["PR bodies"], ["body of the PR"], []):
        assert body_notes.body_citing(titles) is False, titles


def test_apply_edits_inserts_clause_outside_protected():
    new, applied, rejected = body_notes.apply_edits(
        BODY, [_edit("N1", "adds the helper module.",
                     "adds the helper module (tools/a.py).")], {"N1"})
    assert "adds the helper module (tools/a.py)." in new
    assert applied == ["N1"]
    assert rejected == {}


def test_apply_edits_rejects_merge_digest_edit():
    _rejects("Merge digest row one", "Merge digest row two", "protected-region")


def test_apply_edits_rejects_files_changed_edit():
    _rejects("- tools/a.py", "- tools/b.py", "protected-region")


def test_apply_edits_rejects_manual_testing_section_edit():
    _rejects("Open the page and look at it", "Skip it", "protected-region")


def test_apply_edits_rejects_decision_line_edit():
    _rejects("manual only", "not needed", "protected-region")


def test_apply_edits_rejects_sentinel_line_edit():
    _rejects("for the docs half", "for everything", "protected-region")


def test_apply_edits_rejects_html_comment_injection():
    _rejects("Closing paragraph.",
             "Closing paragraph. <!-- no-adr: reason here at length -->", "forbidden-token")


def test_apply_edits_rejects_checkbox_change():
    _rejects("Closing paragraph.", "Closing paragraph.\n- [x] done", "forbidden-token")


def test_apply_edits_rejects_fenced_block():
    _rejects("Closing paragraph.",
             "Closing paragraph.\n```\nFAILED test_x\n```", "forbidden-token")


def test_apply_edits_rejects_non_unique_find():
    body = "## Summary\n\nsame\n\nsame\n"
    _rejects("same", "other", "find-not-unique", body=body)
    _rejects("absent text", "other", "find-not-unique", body=body)
    _rejects("", "other", "find-not-unique", body=body)


def test_apply_edits_rejects_unfixable_note():
    new, applied, rejected = body_notes.apply_edits(
        BODY, [_edit("N2", "Closing paragraph.", "Closing.")], {"N1"})
    assert rejected == {"N2": "not-fixable"}
    assert applied == []
    assert new == BODY


def test_apply_edits_note_all_or_nothing():
    new, applied, rejected = body_notes.apply_edits(
        BODY, [_edit("N1", "Closing paragraph.", "Closing."),
               _edit("N1", "manual only", "not needed")], {"N1"})
    assert rejected == {"N1": "protected-region"}
    assert applied == []
    assert new == BODY


def _fix_payload():
    return {"notes": [{"id": "N1", "disposition": "fixed", "reason": "r"},
                      {"id": "N2", "disposition": "left-for-human", "reason": "r"}],
            "edits": [_edit("N1", "a", "b")]}


def test_fix_validator_accepts_exact_ids():
    body_notes.fix_validator(["N1", "N2"])(_fix_payload())


def test_fix_validator_rejects_missing_or_extra_id():
    validate = body_notes.fix_validator(["N1", "N2"])
    missing = _fix_payload()
    missing["notes"] = missing["notes"][:1]
    extra = _fix_payload()
    extra["notes"].append({"id": "N3", "disposition": "not-body", "reason": "r"})
    for bad in (missing, extra):
        with pytest.raises(HarnessError):
            validate(bad)


def test_fix_validator_rejects_fixed_note_without_edit():
    validate = body_notes.fix_validator(["N1", "N2"])
    payload = _fix_payload()
    payload["edits"] = []
    with pytest.raises(HarnessError):
        validate(payload)
    payload = _fix_payload()
    payload["edits"].append(_edit("N2", "a", "b"))
    with pytest.raises(HarnessError):
        validate(payload)


def test_fix_envelope_unwrapped():
    validate = body_notes.fix_validator(["N1", "N2"])
    validate(body_notes.unwrap_fix_envelope({"result": _fix_payload()}))


def test_fix_validator_rejects_bare_list():
    validate = body_notes.fix_validator(["N1", "N2"])
    bare = body_notes.unwrap_fix_envelope([_fix_payload()])
    with pytest.raises(HarnessError):
        validate(bare)


# ---------------------------------------------------------------------------
# Phase 3: fix_body_notes orchestrator
# ---------------------------------------------------------------------------

T1 = "Add the Phase 1 red-half failure output to the PR body"
T2 = ("Mention the phone column gap change (.game-boxscore__matchup gap .75rem -> .5rem) "
      "in the PR body")
T3 = "Verification Matrix row 11 is still not done"
SHAPE_B = "The red-half failure output was not captured."

# Shaped like a PR #3679 verdict: three [NOTE] bullets, READY WITH NOTES last.
VERDICT_3679 = f"""REVIEW-COVERAGE: CURRENT
Passed: 1, 2, 3, 4, 5, 6.

## FINDINGS

- [NOTE] {T1}
- [NOTE] {T2}
- [NOTE] {T3}

READY WITH NOTES
"""
VERDICT_TWO_BODY = f"""## FINDINGS

- [NOTE] {T1}
- [NOTE] {T2}

READY WITH NOTES
"""
VERDICT_NO_BODY = f"""## FINDINGS

- [NOTE] {T3}

READY WITH NOTES
"""

PR_BODY = """## Summary

The change restyles the boxscore matchup row.

## Testing

Unit tests were run.

<!-- merge-digest:begin -->
Merge digest row one
<!-- merge-digest:end -->

## Manual Testing

- [ ] Look at the phone layout
**Decision:** manual only
"""

NAME_STATUS = "M\tibl5/design/components/game-boxscore.css\nM\tibl5/classes/Foo.php"
DIFF = """diff --git a/ibl5/design/components/game-boxscore.css b/ibl5/design/components/game-boxscore.css
--- a/ibl5/design/components/game-boxscore.css
+++ b/ibl5/design/components/game-boxscore.css
@@ -1,2 +1,2 @@
-.game-boxscore__matchup { gap: .75rem; }
+.game-boxscore__matchup { gap: .5rem; }
diff --git a/ibl5/classes/Foo.php b/ibl5/classes/Foo.php
--- a/ibl5/classes/Foo.php
+++ b/ibl5/classes/Foo.php
@@ -1 +1 @@
-old
+new
"""

PURPOSE_FIX = "plan-fidelity-body-notes-fix"
PURPOSE_RECHECK = "plan-fidelity-body-notes-recheck"


class _StubLLM:
    """Per-purpose queue of payloads, HarnessError instances, or callables(prompt)."""

    def __init__(self, queues):
        self.queues = {k: list(v) for k, v in queues.items()}
        self.calls = []

    def call(self, purpose, model, prompt, validator, normalizer=None):
        self.calls.append((purpose, model))
        item = self.queues[purpose].pop(0)
        if isinstance(item, HarnessError):
            raise item
        if callable(item):
            item = item(prompt)
        data = normalizer(item) if normalizer else item
        validator(data)
        return data

    def count(self, purpose):
        return sum(1 for p, _ in self.calls if p == purpose)


class _StubGh:
    def __init__(self, body, empty_reads=()):
        self.body = body
        self.empty_reads = set(empty_reads)
        self.reads = 0
        self.edits = []

    def pr_edit_body(self, pr, body):
        self.edits.append(body)
        self.body = body

    def pr_body_fresh(self):
        self.reads += 1
        return "" if self.reads in self.empty_reads else self.body


class _StubGit:
    def diff_vs_base(self):
        return DIFF


def _signature(body):
    return body


def _fix(edits, dispositions):
    notes = [{"id": f"N{i}", "disposition": d, "reason": "r"}
             for i, d in enumerate(dispositions, 1)]
    return {"notes": notes, "edits": edits}


def _shape_b_edit(note="N1"):
    return _edit(note, "Unit tests were run.", f"Unit tests were run. {SHAPE_B}")


def _fixed_edit(note="N2"):
    return _edit(note, "restyles the boxscore matchup row.",
                 "restyles the boxscore matchup row (ibl5/design/components/game-boxscore.css); "
                 "the phone column gap changes from .75rem to .5rem.")


def _recheck(prompt, resolved=True):
    fids = re.findall(r"^(F\d+):", prompt, re.M)
    hids = re.findall(r"^(H\d+) \(", prompt, re.M)
    status = "resolved" if resolved else "unresolved"
    return {"findings": [{"id": f, "status": status, "evidence": "e"} for f in fids],
            "hunks": [{"id": h, "status": "supported", "evidence": "e"} for h in hids]}


def _run(tmp_path, verdict, fix_payload, *, recheck=None, gh=None, rounds=None,
         verdict_1="READY WITH NOTES"):
    vpath = tmp_path / "verdict.txt"
    vpath.write_text(verdict)
    queues = {PURPOSE_FIX: [fix_payload] if fix_payload is not None else [],
              PURPOSE_RECHECK: [recheck if recheck is not None else _recheck]}
    llm = _StubLLM(queues)
    gh = gh or _StubGh(PR_BODY)
    fid = {"verdict_1": verdict_1, "verdict_2": None, "rounds": rounds or [],
           "rounds_completed": 0, "remediation_sha": None}
    before = dict(fid)
    out_dir = tmp_path / "out"
    out_dir.mkdir(exist_ok=True)
    body_notes.fix_body_notes(llm, gh, _StubGit(), str(out_dir), 3679, str(vpath), fid,
                              NAME_STATUS, signature=_signature)
    return llm, gh, fid, before, vpath


def test_fix_body_notes_happy_path_all_fixed(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")], ["shape-b", "fixed"])
    llm, gh, fid, _, _ = _run(tmp_path, VERDICT_TWO_BODY, payload)
    assert fid["remediation_sha"] == "body-only"
    assert gh.body != PR_BODY
    assert fid["body_notes"] == {"outcome": "fixed", "fixed": [T1, T2], "remaining": []}
    assert fid["body_fixed_notes"] == [T1, T2]
    assert llm.count(PURPOSE_RECHECK) == 1


def test_fix_body_notes_skips_decision_note(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")],
                   ["shape-b", "fixed", "left-for-human"])
    llm, gh, fid, before, vpath = _run(tmp_path, VERDICT_3679, payload)
    assert fid["body_notes"]["remaining"] == [T3 + " (left for the human)"]
    assert fid["body_fixed_notes"] == [T1, T2]
    for key in ("verdict_1", "verdict_2", "rounds", "rounds_completed"):
        assert fid[key] == before[key]
    assert "verdict_path" not in fid
    assert vpath.read_text() == VERDICT_3679


def test_fix_body_notes_shape_b_sentence_no_fabricated_output(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")],
                   ["shape-b", "fixed", "left-for-human"])
    _, gh, fid, _, _ = _run(tmp_path, VERDICT_3679, payload)
    assert f"Unit tests were run. {SHAPE_B}" in gh.body
    assert "```" not in gh.body
    assert fid["body_notes"]["outcome"] == "fixed"


def test_fix_body_notes_rejects_fabricated_fenced_output(tmp_path):
    fabricated = _edit("N1", "Unit tests were run.",
                       "Unit tests were run.\n```\nFAILED test_x - AssertionError\n```")
    payload = _fix([fabricated, _fixed_edit("N2")], ["fixed", "fixed", "left-for-human"])
    _, gh, fid, _, _ = _run(tmp_path, VERDICT_3679, payload)
    assert T1 + " (body fix rejected: forbidden-token)" in fid["body_notes"]["remaining"]
    assert "FAILED" not in gh.body
    assert "```" not in gh.body
    assert "Unit tests were run.\n" in gh.body
    assert fid["body_fixed_notes"] == [T2]


def test_fix_body_notes_no_body_citing_notes_zero_calls(tmp_path):
    llm, gh, fid, before, _ = _run(tmp_path, VERDICT_NO_BODY, None)
    assert llm.calls == []
    assert gh.edits == []
    assert "body_notes" not in fid
    assert fid == before


def test_fix_body_notes_failed_recheck_restores_body(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")], ["shape-b", "fixed"])
    llm, gh, fid, _, _ = _run(tmp_path, VERDICT_TWO_BODY, payload,
                              recheck=lambda p: _recheck(p, resolved=False))
    assert gh.body == PR_BODY
    assert fid["remediation_sha"] is None
    assert T1 + " (body fix reverted: recheck-unresolved)" in fid["body_notes"]["remaining"]
    assert fid["body_notes"]["outcome"] == "reverted"
    assert "body_fixed_notes" not in fid


def test_fix_body_notes_fix_call_error_leaves_body(tmp_path):
    llm, gh, fid, _, _ = _run(tmp_path, VERDICT_3679,
                              HarnessError("timeout", "slow"))
    assert gh.edits == []
    assert fid["body_notes"]["outcome"] == "fix-call-failed"
    assert fid["remediation_sha"] is None
    assert llm.count(PURPOSE_RECHECK) == 0


def test_fix_body_notes_envelope_bare_list_no_false_remediated(tmp_path):
    payload = _fix([_shape_b_edit("N1")], ["shape-b", "left-for-human", "left-for-human"])
    llm, gh, fid, _, _ = _run(tmp_path, VERDICT_3679, [payload])
    assert fid["body_notes"]["outcome"] == "fix-call-failed"
    assert fid["remediation_sha"] is None
    assert gh.edits == []


def test_fix_body_notes_makes_at_most_one_fix_and_one_recheck(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")], ["shape-b", "fixed"])
    llm, _, fid, _, _ = _run(tmp_path, VERDICT_TWO_BODY, payload)
    assert fid["body_notes"]["outcome"] == "fixed"
    assert llm.count(PURPOSE_FIX) == 1
    assert llm.count(PURPOSE_RECHECK) <= 1
    sub = tmp_path / "fail"
    sub.mkdir()
    llm, _, fid, _, _ = _run(sub, VERDICT_TWO_BODY, payload,
                             recheck=lambda p: _recheck(p, resolved=False))
    assert fid["body_notes"]["outcome"] == "reverted"
    assert llm.count(PURPOSE_FIX) == 1
    assert llm.count(PURPOSE_RECHECK) <= 1


def test_fix_body_notes_skips_when_rounds_ran(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")], ["shape-b", "fixed"])
    llm, gh, fid, before, _ = _run(tmp_path, VERDICT_TWO_BODY, payload,
                                   rounds=[{"round": 1}])
    assert llm.calls == []
    assert gh.edits == []
    assert fid == before


def test_fix_body_notes_readback_empty_discloses(tmp_path):
    payload = _fix([_shape_b_edit("N1"), _fixed_edit("N2")], ["shape-b", "fixed"])
    llm, gh, fid, _, _ = _run(tmp_path, VERDICT_TWO_BODY, payload, gh=_StubGh(PR_BODY, {2}))
    assert fid["remediation_sha"] == "body-only"
    assert fid["body_notes"]["outcome"] == "body-fetch-failed"
    assert "body_fixed_notes" not in fid
    assert llm.count(PURPOSE_RECHECK) == 0


def test_fix_body_notes_shortens_titles_before_suffix(tmp_path):
    long_title = ("Verification Matrix row 11 is still not done. " + "x" * 100 + ". "
                  + "The human-signoff hold covers this one. " + "y" * 70)
    assert len(long_title) >= 250
    single = "Mention the PR body gap " + "z" * 126
    assert len(single) == 150
    verdict = (f"## FINDINGS\n\n- [NOTE] {long_title}\n- [NOTE] {single}\n\n"
               "READY WITH NOTES\n")
    payload = _fix([], ["left-for-human", "left-for-human"])
    _, _, fid, _, _ = _run(tmp_path, verdict, payload)
    remaining = fid["body_notes"]["remaining"]
    assert remaining[0] == "Verification Matrix row 11 is still not done (left for the human)"
    assert remaining[1] == f"{single[:100]} (left for the human)"


# ---------------------------------------------------------------------------
# Phase 4: terminal-line wrapper, sticky sections, digest disclosure
# ---------------------------------------------------------------------------

def _tfid(**over):
    return {"verdict_1": "READY WITH NOTES", "remediation_sha": "body-only",
            "reviewed_tree": "a" * 40, **over}


ROW_ALL = ("READY WITH NOTES — all notes remediated in the PR body; reviewer verdict "
           f"covers tree {'a' * 40}, not the post-remediation head")
ROW_REST = ("READY WITH NOTES — Verification Matrix row 11 is still not done "
            "(left for the human); remediated the rest in the PR body")
ROW_NONE = ("READY WITH NOTES — Add red-half output "
            "(body fix reverted: recheck-unresolved)")


def test_terminal_all_notes_remediated_row():
    fid = _tfid(body_notes={"fixed": ["x"], "remaining": []})
    assert body_notes.terminal_line(fid) == ROW_ALL


def test_terminal_remaining_named_with_remediated_rest():
    fid = _tfid(body_notes={"fixed": ["x"], "remaining": [
        "Verification Matrix row 11 is still not done (left for the human)"]})
    assert body_notes.terminal_line(fid) == ROW_REST


def test_terminal_nothing_remediated_names_notes():
    fid = _tfid(body_notes={"fixed": [], "remaining": [
        "Add red-half output (body fix reverted: recheck-unresolved)"]})
    assert body_notes.terminal_line(fid) == ROW_NONE


def test_terminal_truncates_beyond_three_names():
    fid = _tfid(body_notes={"fixed": [], "remaining": [f"note {i}" for i in range(5)]})
    assert body_notes.terminal_line(fid) == \
        "READY WITH NOTES — note 0; note 1; note 2; +2 more"


def test_terminal_none_without_body_notes():
    assert body_notes.terminal_line(_tfid()) is None
    assert body_notes.terminal_line(_tfid(body_notes={
        "outcome": "fix-call-failed", "fixed": [], "remaining": []})) is None
    assert body_notes.terminal_line(
        _tfid(verdict_1="READY", body_notes={"fixed": ["x"], "remaining": []})) is None
    assert body_notes.terminal_line(
        _tfid(verdict_1="NOT READY", body_notes={"fixed": ["x"], "remaining": []})) is None


def test_terminal_rows_parse_as_ready_with_notes():
    for row in (ROW_ALL, ROW_REST, ROW_NONE):
        sticky = "header line\n\n" + row + "\n" + fidelity.STICKY_MARKER
        assert fidelity._sticky_prior_verdict(sticky) == "READY WITH NOTES"
        assert fidelity._terminal_verdict_word(row) == "READY WITH NOTES"
        assert fidelity._RE_REVIEW_TERMINAL_RE.match(row) is None


def _items():
    return [{"verdict": "READY WITH NOTES", "blocking": [],
             "notes": ["Note A", "Note B"], "remediation_sha": None}]


def test_status_sections_moves_body_fixed_notes_to_fixed():
    fid = {"review_items": _items(), "body_fixed_notes": ["Note A"]}
    text = "\n".join(fidelity._status_sections(fid, "READY WITH NOTES"))
    assert "### Fixed in this PR" in text
    assert "- [x] Note A (fixed in the PR body)" in text
    assert "- [ ] Note A" not in text
    assert "- [ ] Note B (not filed)" in text


def test_status_sections_reverted_fix_keeps_notes_open():
    fid = {"review_items": _items(),
           "body_notes": {"outcome": "reverted", "fixed": [], "remaining": [
               "Note A (body fix reverted: recheck-unresolved)"]}}
    text = "\n".join(fidelity._status_sections(fid, "READY WITH NOTES"))
    assert "### Fixed in this PR" not in text
    assert "- [ ] Note A (not filed)" in text
    assert "- [ ] Note B (not filed)" in text


def test_digest_row_discloses_body_only():
    rows = [f"row {i}" for i in range(5)]
    out = fidelity.digest_rows_for_display(list(rows), {"remediation_sha": "body-only"})
    assert out[4].endswith(" (post-plan remediation: body-only)")
    out = fidelity.digest_rows_for_display(list(rows), {"remediation_sha": None})
    assert out[4] == "row 4"
