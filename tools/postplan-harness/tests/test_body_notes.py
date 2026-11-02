"""Unit tests for harness/body_notes.py: fixing PR-body notes on READY WITH NOTES.

The first three tests characterize behavior this PR relies on. They pass on master.
"""
from __future__ import annotations

import os
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
