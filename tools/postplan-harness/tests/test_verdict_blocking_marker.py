"""Severity marker (`[BLOCKING]` / `[NOTE]`) on plan-fidelity verdict FINDINGS bullets.

Characterization tests: the unchanged parser in `harness/fidelity.py` carries a marked
bullet through whole, and an unmarked legacy bullet is still work (fail-closed).
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

_DISPOSITIONS = (
    "1. **Intent coverage.** Not assessable from the diff alone.\n"
    "2. **Plan-named files.** Passes.\n"
    "3. **Scope creep.** No finding.\n"
    "4. **Body claims.** Passes.\n"
    "    - **4a. Numbers.** Does not fire.\n"
    "    - **4b. Paths.** Does not fire.\n"
    "5. **Test realisation.** Passes.\n"
    "6. **Conflict-resolution audit.** Passes.\n"
)

_MARKED_BODY = (
    "- [BLOCKING] Phase 2 never edited `a.py`; restore the planned change.\n"
    "  - nested detail line for the first finding\n"
    "- [NOTE] The PR body says 3 files; the diff touches 4.\n"
    "### Re-review\n"
    "- [BLOCKING] Check 5 row 4 names `test_x`, absent from the diff.\n"
)

_MARKED_ITEMS = [
    "- [BLOCKING] Phase 2 never edited `a.py`; restore the planned change.",
    "- nested detail line for the first finding",
    "- [NOTE] The PR body says 3 files; the diff touches 4.",
    "- [BLOCKING] Check 5 row 4 names `test_x`, absent from the diff.",
]


def _write(tmp_path, text):
    p = tmp_path / "verdict.md"
    p.write_text(text)
    return str(p)


def _verdict(word, body):
    return (
        _DISPOSITIONS
        + f"\n## FINDINGS\n\n{body}\n{word}\n\n## DIGEST\nstuff\n"
    )


def test_marked_bullets_survive_verdict_findings_in_order(tmp_path):
    """Catches a bullet regex or stripper that eats a leading `[...]` token."""
    path = _write(tmp_path, _verdict("NOT READY", _MARKED_BODY))
    assert fidelity._verdict_findings(path) == _MARKED_ITEMS


def test_marked_bullets_reach_build_work_list_as_hold_12(tmp_path):
    """Catches build_work_list dropping or rewriting marked items."""
    path = _write(tmp_path, _verdict("NOT READY", _MARKED_BODY))
    assert fidelity.build_work_list(path, [], [], []) == [
        {"hold": "12", "text": item} for item in _MARKED_ITEMS
    ]


def test_unmarked_bullet_still_returned_fail_closed(tmp_path):
    """Catches filtering to `[BLOCKING]` bullets only."""
    body = "- Phase 2 never edited a.py.\n- Note: body count is off.\n"
    path = _write(tmp_path, _verdict("NOT READY", body))
    assert fidelity._verdict_findings(path) == [
        "- Phase 2 never edited a.py.",
        "- Note: body count is off.",
    ]


def test_mixed_marked_and_unmarked_bullets_all_returned(tmp_path):
    """Catches dropping unmarked bullets once any marked bullet exists."""
    body = "- [BLOCKING] one\n- unlabelled two\n- [NOTE] three\n"
    path = _write(tmp_path, _verdict("NOT READY", body))
    assert fidelity._verdict_findings(path) == [
        "- [BLOCKING] one",
        "- unlabelled two",
        "- [NOTE] three",
    ]


def test_ready_with_notes_note_bullets_yield_no_work(tmp_path):
    """Catches dropping the `parse_verdict != NOT READY` early return."""
    body = "- [NOTE] first note\n- [NOTE] second note\n"
    path = _write(tmp_path, _verdict("READY WITH NOTES", body))
    assert fidelity._verdict_findings(path) == []
    assert fidelity.build_work_list(path, [], [], []) == []


REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", ".."))


def _flat(rel):
    with open(os.path.join(REPO, rel), encoding="utf-8") as fh:
        return " ".join(fh.read().split())


def test_def_item_2a_states_marker_grammar():
    """Catches deleting the severity paragraph or moving it out of item 2a."""
    text = _flat(".claude/agents/pr-ready-phase6.md")
    for needle in (
        "**Severity marker.**",
        "Open every top-level finding bullet with exactly one marker",
        "inherits its marker and carries none of its own",
        "`NOT READY` has at least one `[BLOCKING]` bullet",
        "`READY WITH NOTES` has only `[NOTE]` bullets",
        "and `READY` has an empty `## FINDINGS` section",
        "Keep it out of the finding's title",
        "treats that finding as blocking",
    ):
        assert needle in text, needle
    i2a = text.index("2a. Emit a `## FINDINGS` section")
    isev = text.index("**Severity marker.**")
    i3 = text.index("3. **Append a `## DIGEST` section")
    assert i2a < isev < i3


def test_procedure_6e_points_to_def_item_2a():
    """Catches deleting the 6e pointer or reverting the writing-style exemption."""
    text = _flat(".claude/review-shared/_plan-fidelity-review.md")
    lo = text.index("**6e. Verdict shape.**")
    hi = text.index("**6e(b). Merge digest")
    for needle in (
        "Open each `## FINDINGS` bullet with `[BLOCKING]` or `[NOTE]`",
        "specified in `.claude/agents/pr-ready-phase6.md` § Output contract item 2a; "
        "that item is authoritative",
    ):
        assert lo < text.index(needle) < hi, needle
    assert "and the `[BLOCKING]` / `[NOTE]` finding marker" in text
    assert "Finding titles, without the marker, are copied verbatim" in text


def test_procedure_does_not_restate_marker_invariant():
    """Catches a second copy of the grammar in the procedure (drift)."""
    text = _flat(".claude/review-shared/_plan-fidelity-review.md")
    for needle in (
        "has at least one `[BLOCKING]` bullet",
        "has only `[NOTE]` bullets",
        "**Severity marker.**",
    ):
        assert needle not in text, needle
