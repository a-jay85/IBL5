"""Hold (12) work list: only the bullets under the verdict's `## FINDINGS` heading.

The per-check disposition lines the reviewer writes above the heading (Passes / Not
assessable / No finding / Does not fire) are bullets too, but they are not work.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

FIXTURE = os.path.join(
    os.path.dirname(os.path.abspath(__file__)),
    "fixtures", "verdict_findings", "pr2341_nine_items.txt",
)

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


def _write(tmp_path, text):
    p = tmp_path / "verdict.md"
    p.write_text(text)
    return str(p)


def test_real_pr2341_verdict_yields_single_finding():
    """Catches scanning the whole body instead of the FINDINGS section (9 items)."""
    items = fidelity.build_work_list(FIXTURE, [], [], [])
    assert [i["hold"] for i in items] == ["12"]
    assert items[0]["text"].startswith("- `bin/check-orphan-css` grew by 44 net lines")
    assert len(fidelity._verdict_findings(FIXTURE)) == 1


def test_dispositions_above_findings_heading_are_dropped(tmp_path):
    """Catches `_findings_section` always returning None."""
    path = _write(
        tmp_path,
        _DISPOSITIONS
        + "\nNOT READY\n\n## FINDINGS\n\n- real finding: body claim contradicts diff\n"
        "\n## DIGEST\nstuff\n",
    )
    assert fidelity._verdict_findings(path) == [
        "- real finding: body claim contradicts diff"
    ]


def test_heading_with_prose_only_falls_back_to_whole_body(tmp_path):
    """Catches returning [] for an empty section, or falling back to all bullets."""
    path = _write(
        tmp_path,
        "1. **Intent coverage.** Passes.\n\nNOT READY\n\n## FINDINGS\n\n"
        "The Summary overstates the line delta.\n\n## DIGEST\nstuff\n",
    )
    items = fidelity._verdict_findings(path)
    assert len(items) == 1
    assert "The Summary overstates the line delta." in items[0]
    assert "1. **Intent coverage.** Passes." in items[0]


def test_no_heading_keeps_all_bullets_legacy(tmp_path):
    """Catches treating a missing heading as an empty section."""
    path = _write(
        tmp_path,
        "6d checks\n\nNOT READY\n\n- one\n- two\n- three\n\n## DIGEST\nstuff\n",
    )
    assert fidelity._verdict_findings(path) == ["- one", "- two", "- three"]


def test_section_bounds_next_h2_subsection_and_repeated_heading(tmp_path):
    """Catches stopping at `### `, missing the `## ` stop, or honouring one heading."""
    path = _write(
        tmp_path,
        _DISPOSITIONS
        + "\nNOT READY\n\n## FINDINGS\n\n- f1\n\n### Detail\n\n- f2\n\n"
        "## Blocking summary\n\n- excluded\n\n## FINDINGS\n\n- f3\n\n"
        "## DIGEST\nstuff\n",
    )
    assert fidelity._verdict_findings(path) == ["- f1", "- f2", "- f3"]


def test_mixed_case_findings_heading_is_legacy_path(tmp_path):
    """Catches a case-insensitive heading match."""
    path = _write(
        tmp_path,
        "- disposition a\n\nNOT READY\n\n## Findings\n\n- f1\n\n## DIGEST\nstuff\n",
    )
    assert fidelity._verdict_findings(path) == ["- disposition a", "- f1"]


def test_findings_heading_after_digest_is_ignored(tmp_path):
    """Catches running the section scan before the digest cut."""
    path = _write(
        tmp_path,
        "- a\n- b\n\nNOT READY\n\n## DIGEST\n\n## FINDINGS\n\n- after-digest\n",
    )
    assert fidelity._verdict_findings(path) == ["- a", "- b"]


def test_ready_verdict_with_heading_yields_nothing(tmp_path):
    """Catches dropping the `parse_verdict != NOT READY` guard."""
    for word in ("READY", "READY WITH NOTES"):
        path = _write(
            tmp_path,
            _DISPOSITIONS
            + f"\n{word}\n\n## FINDINGS\n\n- note\n\n## DIGEST\nstuff\n",
        )
        assert fidelity._verdict_findings(path) == []
        assert fidelity.build_work_list(path, [], [], []) == []
    missing = str(tmp_path / "missing.md")
    assert fidelity._verdict_findings(missing) == []
    assert fidelity.build_work_list(missing, [], [], []) == []


def test_nested_bullet_under_finding_is_own_item(tmp_path):
    """Catches anchoring the bullet regex to column 0."""
    path = _write(
        tmp_path,
        _DISPOSITIONS
        + "\nNOT READY\n\n## FINDINGS\n\n- parent finding\n    - nested detail\n"
        "\n## DIGEST\nstuff\n",
    )
    assert fidelity._verdict_findings(path) == ["- parent finding", "- nested detail"]
