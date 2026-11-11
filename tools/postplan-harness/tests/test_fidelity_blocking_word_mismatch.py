"""A top-level [BLOCKING] bullet outranks a READY / READY WITH NOTES verdict word.

Reviewer contract item 2a says the marker and the word must agree. PR #3810's reviewer
wrote a [BLOCKING] bullet and closed with `READY WITH NOTES`, so the harness skipped the
Phase 5.5 round and the wrong body claim stayed live. `fidelity.parse_verdict` now takes
the stricter reading. Only an explicit marker upgrades the word.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

from test_fidelity_rounds import VERDICT_3810


def _parse(tmp_path, text):
    p = tmp_path / "verdict.md"
    p.write_text(text)
    return fidelity.parse_verdict(str(p))


def _note_only():
    return VERDICT_3810.replace("- [BLOCKING] Correct", "- [NOTE] Correct", 1)


def test_3810_ready_with_notes_over_blocking_bullet_parses_not_ready(tmp_path):
    assert _parse(tmp_path, VERDICT_3810) == "NOT READY"


def test_note_only_findings_keep_ready_with_notes(tmp_path):
    assert _parse(tmp_path, _note_only()) == "READY WITH NOTES"


def test_midline_blocking_token_in_note_bullet_keeps_word(tmp_path):
    text = _note_only().replace(
        "\n\n## SHADOW-ARMING",
        "\n- [NOTE] An earlier draft tagged this [BLOCKING] before the check 4 clause "
        "was reread.\n\n## SHADOW-ARMING", 1)
    assert "[BLOCKING] before" in text
    assert _parse(tmp_path, text) == "READY WITH NOTES"


def test_blocking_bullet_in_shadow_arming_only_keeps_word(tmp_path):
    text = _note_only().replace(
        "## SHADOW-ARMING\n", "## SHADOW-ARMING\n- [BLOCKING] shadow-only line\n", 1)
    assert "- [BLOCKING] shadow-only line" in text
    assert _parse(tmp_path, text) == "READY WITH NOTES"


def test_unmarked_bullet_under_ready_with_notes_keeps_word(tmp_path):
    """Only an explicit marker upgrades; an unmarked bullet keeps the word."""
    text = _note_only().replace(
        "\n\n## SHADOW-ARMING", "\n- Add a changelog line.\n\n## SHADOW-ARMING", 1)
    assert "- Add a changelog line." in text
    assert _parse(tmp_path, text) == "READY WITH NOTES"


def test_ready_word_with_blocking_bullet_parses_not_ready(tmp_path):
    text = VERDICT_3810.replace("\nREADY WITH NOTES\n", "\nREADY\n")
    assert text.rstrip().endswith("\nREADY")
    assert _parse(tmp_path, text) == "NOT READY"
