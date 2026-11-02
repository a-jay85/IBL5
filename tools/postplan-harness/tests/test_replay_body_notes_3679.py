"""PR #3679 replay: two body notes fixed, the human-taste row left alone.

The fixtures are inline constants. The live run dir under tools/postplan-harness/out/ is
gitignored scratch and is only the origin of the text below.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import body_notes, fidelity
from harness.body_notes import (FILES_CHANGED_BEGIN, FILES_CHANGED_END, MERGE_DIGEST_BEGIN,
                                MERGE_DIGEST_END)

from test_body_notes import (NAME_STATUS, PURPOSE_FIX, PURPOSE_RECHECK, _edit, _fix,
                             _recheck, _signature, _StubGh, _StubGit, _StubLLM)

NOTE_1 = ("Add the Phase 1 red-half failure output to the PR body. Phase 1 (`plan.md:177`) "
          "says to paste the failing test names and their first assertion message as the red "
          "half of red-green, and `pr-body.md` has no such section. The code matches the "
          "plan's recipe, so this gap is only about evidence. Nothing in the shipped spec or "
          "CSS is affected.")
NOTE_2 = ("Mention the phone column gap change in the PR body. In "
          "`ibl5/design/components/game-boxscore.css` (new lines 91-93), the 640px media "
          "block lowers `.game-boxscore__matchup` `gap` from `.75rem` to `.5rem`. The "
          "Summary never says so. The plan asked for this change, so the fix is one added "
          "clause in the body.")
NOTE_3 = ("Verification Matrix row 11 is still not done. The `## Manual Testing` box for "
          "row 11 is unticked, and the PR does not claim a reviewer has looked at the "
          "stacked phone scoreboard. The human-signoff hold covers this, and the "
          "`**Decision:**` line asks for exactly that taste call.")

VERDICT_3679 = f"""REVIEW-COVERAGE: CURRENT
Passed: 1, 3, 6.

{fidelity.FINDINGS_HEADING}

- [NOTE] {NOTE_1}
- [NOTE] {NOTE_2}
- [NOTE] {NOTE_3}

READY WITH NOTES
"""

SUMMARY_LAST = "The matchup row no longer overlaps logos, names and scores on phones."
MANUAL = ("## Manual Testing\n\n"
          "- [ ] Row 11: look at the stacked phone scoreboard\n"
          "**Decision:** ship once a human has looked at the phone layout\n")
FILES_BLOCK = f"{FILES_CHANGED_BEGIN}\n- ibl5/design/components/game-boxscore.css\n{FILES_CHANGED_END}\n"
DIGEST_BLOCK = f"{MERGE_DIGEST_BEGIN}\nMerge digest row one\n{MERGE_DIGEST_END}\n"
BODY_3679 = f"## Summary\n\n{SUMMARY_LAST}\n\n{FILES_BLOCK}\n{DIGEST_BLOCK}\n{MANUAL}"

ADD_1 = "The Phase 1 red-half failure output was not captured."
ADD_2 = ("In the 640px block of ibl5/design/components/game-boxscore.css, the "
         ".game-boxscore__matchup gap drops from .75rem to .5rem.")


def test_replay_3679_fixes_two_notes_and_leaves_row_11(tmp_path):
    path = tmp_path / "verdict-3679.md"
    path.write_text(VERDICT_3679)
    notes = fidelity.verdict_items(str(path))["notes"]
    assert len(notes) == 3
    assert body_notes.body_citing(notes) is True

    payload = _fix(
        [_edit("N1", SUMMARY_LAST, f"{SUMMARY_LAST} {ADD_1}"),
         _edit("N2", SUMMARY_LAST, f"{SUMMARY_LAST} {ADD_2}")],
        ["shape-b", "fixed", "left-for-human"])
    # Both edits find the same sentence; the second runs on the first's output, so
    # anchor it on the sentence the first edit appended.
    payload["edits"][1]["find"] = ADD_1
    payload["edits"][1]["replace"] = f"{ADD_1} {ADD_2}"

    llm = _StubLLM({PURPOSE_FIX: [payload], PURPOSE_RECHECK: [_recheck]})
    gh = _StubGh(BODY_3679)
    fid = {"verdict_1": "READY WITH NOTES", "verdict_2": None, "rounds": [],
           "rounds_completed": 0, "remediation_sha": None}
    body_notes.fix_body_notes(llm, gh, _StubGit(), str(tmp_path), 3679, str(path), fid,
                              NAME_STATUS, signature=_signature)

    assert ADD_1 in gh.body
    assert ADD_2 in gh.body
    assert "```" not in gh.body
    for protected in (MANUAL, FILES_BLOCK, DIGEST_BLOCK):
        assert protected in gh.body
    assert fid["remediation_sha"] == "body-only"
    assert body_notes.terminal_line(fid) == (
        "READY WITH NOTES — Verification Matrix row 11 is still not done "
        "(left for the human); remediated the rest in the PR body")
    assert llm.count(PURPOSE_FIX) == 1
    assert llm.count(PURPOSE_RECHECK) == 1
