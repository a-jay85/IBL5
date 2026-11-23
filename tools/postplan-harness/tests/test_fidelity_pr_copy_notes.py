"""Unit tests for filing pr-copy notes from a READY WITH NOTES verdict (backlog#1811).

A pr-copy note files only when it says the PR title or body states something false or
stale, or names a harm. Its issue title carries the PR number and its body names the
`gh pr edit` command that fixes the copy after merge.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.llm import FixtureLlm
from harness.state import UsageLedger

import sticky_shapes

_STALE_TITLE = "Regenerate the stale scope-notes block in PR body"
_STALE = {"title": _STALE_TITLE, "kind": "pr-copy", "harm": "none",
          "detail": "The block lists bin/foo, which this diff does not touch."}
_PR_LINK = "https://github.com/a-jay85/IBL5/pull/3679"


def _verdict(tmp_path):
    p = tmp_path / "verdict.md"
    p.write_text("6d checks\n\nREADY WITH NOTES\n\n- finding one\n\n## DIGEST\nstuff\n")
    return str(p)


def _titles(tmp_path, raw):
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": raw})
    return [n["title"] for n in fidelity.extract_notes(llm, _verdict(tmp_path))]


class _Gh:
    def __init__(self, existing=()):
        self.existing = list(existing)
        self.created = []

    def issue_titles(self, label):
        return list(self.existing)

    def followup_create(self, title, body, label):
        self.created.append((title, body, label))
        return 4000 + len(self.created)


def test_stale_body_block_note_is_kept(tmp_path):
    assert _titles(tmp_path, [_STALE]) == [_STALE_TITLE]


def test_omission_pr_copy_note_is_dropped(tmp_path):
    note = {"title": "Mention the jq-failure fallback in the PR body", "kind": "pr-copy",
            "detail": "Readers should learn the script falls back when jq fails."}
    assert _titles(tmp_path, [note]) == []


def test_near_miss_missing_word_does_not_count_as_misstatement(tmp_path):
    notes = [
        {"title": "Add the missing exit-10 behaviour to the PR body", "kind": "pr-copy",
         "detail": "Readers should learn what exit 10 means."},
        {"title": "Mention the misc helper in the PR body", "kind": "pr-copy",
         "detail": "Readers should learn the helper exists."},
    ]
    assert _titles(tmp_path, notes) == []


def test_pr_copy_note_with_listed_harm_is_kept(tmp_path):
    note = {"title": "Add the gate caveat to the PR body", "kind": "pr-copy",
            "harm": "false-gate", "detail": "Readers will think the gate blocks bad input."}
    assert _titles(tmp_path, [note]) == ["Add the gate caveat to the PR body"]


def test_misstated_tooling_pr_copy_note_bypasses_tooling_filter(tmp_path):
    note = {"title": "Remove the stale bin/check-plan claim from the PR body",
            "kind": "pr-copy", "harm": "none",
            "detail": "The body says bin/check-plan gained a gate; the diff touches only "
                      "tools/postplan-harness/."}
    assert fidelity._is_harmless_tooling(note)
    assert _titles(tmp_path, [note]) == [note["title"]]


def test_tooling_path_near_misses_stay_unmatched():
    for text in ("/usr/bin/env python3", "foo/bin/x", "../bin/x", "~/claude-plans/x.md"):
        assert fidelity._TOOLING_PATH_RE.search(text) is None, text
    assert fidelity._TOOLING_PATH_RE.search("bin/check-plan")


def test_website_path_existing_forms_match():
    for text in ("classes/Waivers/WaiversView.php", "`classes/Waivers/WaiversView.php`"):
        assert fidelity._WEBSITE_PATH_RE.search(text), text
    for text in ("/Users/x/IBL5/ibl5/docs/x.md", "ibl5/docs/x.md",
                 "tools/x/tests/fixtures/classes/y.py"):
        assert fidelity._WEBSITE_PATH_RE.search(text) is None, text


_HOME_HOOK_NOTE = {"title": "Hook guard misses a case", "kind": "follow-up", "harm": "none",
                   "detail": "~/.claude/hooks/x.sh skips the empty-input branch."}


def test_tooling_path_matches_home_and_dot_relative():
    for text in ("see ~/.claude/hooks/x.sh", "run ./bin/x"):
        assert fidelity._TOOLING_PATH_RE.search(text), text


def test_home_hook_note_is_harmless_tooling():
    assert fidelity._is_harmless_tooling(dict(_HOME_HOOK_NOTE)) is True


def test_home_hook_note_with_listed_harm_is_kept():
    note = dict(_HOME_HOOK_NOTE, harm="wrong-value")
    assert fidelity._is_harmless_tooling(note) is False


def test_website_path_matches_absolute_ibl5():
    assert fidelity._WEBSITE_PATH_RE.search("/Users/x/IBL5/ibl5/classes/Foo.php")


def test_mixed_tooling_and_website_note_is_kept():
    note = dict(_HOME_HOOK_NOTE,
                detail="~/.claude/hooks/x.sh and ./classes/Waivers/WaiversView.php disagree.")
    assert fidelity._is_harmless_tooling(note) is False


def test_already_done_pr_copy_note_is_dropped(tmp_path):
    note = {"title": "Correct the stale robots.txt scope claim in the PR body",
            "kind": "pr-copy",
            "detail": "The body over-stated it; corrected in Phase 6.5 remediation."}
    assert _titles(tmp_path, [note]) == []


def test_extract_notes_envelope_dict_files_nothing(tmp_path):
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": {"notes": [_STALE]}})
    kept = fidelity.extract_notes(llm, _verdict(tmp_path))
    assert kept == []
    gh = _Gh()
    rec = []
    fidelity.file_note_issues(gh, kept, 3679, filed=rec)
    assert gh.created == []
    assert rec == []


def test_followups_claim_filing_slots_before_pr_copy(tmp_path):
    followup_a = {"title": "Guard the empty roster", "kind": "followup",
                  "detail": "RosterParser::parse() crashes on an empty roster."}
    followup_b = {"title": "Assert sorted id-lists", "kind": "followup",
                  "detail": "ibl5/classes/Roster/RosterParser.php keeps row counts only."}
    llm = FixtureLlm(UsageLedger(), {"fidelity-notes": [_STALE, followup_a, followup_b]})
    kept = fidelity.extract_notes(llm, _verdict(tmp_path))
    rec = []
    fidelity.file_note_issues(_Gh(), kept, 3679, filed=rec)
    assert [r["title"] for r in rec] == [
        "Guard the empty roster", "Assert sorted id-lists", f"PR #3679 copy: {_STALE_TITLE}"]


def test_pr_copy_title_carries_pr_number_so_other_prs_still_file():
    gh = _Gh([f"PR #3600 copy: {_STALE_TITLE}"])
    fidelity.file_note_issues(gh, [_STALE], 3679, filed=[])
    assert [c[0] for c in gh.created] == [f"PR #3679 copy: {_STALE_TITLE}"]


def test_pr_copy_note_does_not_collide_with_followup_title():
    gh = _Gh([_STALE_TITLE])
    rec = []
    fidelity.file_note_issues(gh, [_STALE], 3679, filed=rec)
    assert [r["state"] for r in rec] == ["filed"]


def test_pr_copy_note_already_tracked_on_rerun():
    gh = _Gh([f"PR #3679 copy: {_STALE_TITLE}"])
    rec = []
    fidelity.file_note_issues(gh, [_STALE], 3679, filed=rec)
    assert gh.created == []
    assert [r["state"] for r in rec] == ["already tracked"]


def test_pr_copy_issue_body_names_gh_pr_edit():
    gh = _Gh()
    fidelity.file_note_issues(gh, [_STALE], 3679, filed=[])
    body = gh.created[0][1]
    assert body.startswith(_PR_LINK)
    assert _STALE["detail"] in body
    assert "gh pr edit 3679 -R a-jay85/IBL5" in body


def test_followup_issue_body_has_no_pr_edit_hint():
    followup = {"title": "Guard the empty roster", "kind": "followup",
                "detail": "RosterParser::parse() crashes on an empty roster."}
    gh = _Gh()
    fidelity.file_note_issues(gh, [followup], 3679, filed=[])
    assert gh.created == [("Guard the empty roster", f"{_PR_LINK}\n\n{followup['detail']}",
                           "maintenance")]


def test_sticky_renders_filed_pr_copy_note(tmp_path):
    inputs = sticky_shapes.pr3267_inputs(tmp_path, "READY WITH NOTES", None)
    inputs["fid"]["filed_notes"] = [{
        "title": "PR #3679 copy: Remove the stale Residual Phases block from PR body",
        "issue": 4242, "state": "filed"}]
    body = sticky_shapes.render_sticky(inputs)
    assert "Remove the stale Residual Phases block from PR body" in body
    assert "backlog#4242" in body
