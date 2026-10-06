"""Conformance phase-omission citation shapes: non-repo tokens (shape A)."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.state import PhaseInfo, PlanInfo


def _plan(*phases: PhaseInfo) -> PlanInfo:
    return PlanInfo(found=True, phases=list(phases))


def _run(number, evidence, changed, tracked, heading=None):
    ph = PhaseInfo(number=number, heading=heading or f"Phase {number}: Work",
                   evidence_paths=evidence)
    notes: list[str] = []
    items = conformance.phase_omission_items(_plan(ph), changed, tracked, notes)
    return items, notes


def _drop_notes(notes):
    return [n for n in notes if n.startswith("NON-REPO-CITATION: ")]


def test_slash_command_token_dropped_with_note():
    items, notes = _run(8, ["/post-plan"], ["ibl5/x.php"],
                        [".claude/skills/post-plan/SKILL.md", "bin/post-plan-now"])
    assert items == []
    drops = _drop_notes(notes)
    assert len(drops) == 1
    assert drops[0].startswith("NON-REPO-CITATION: 8 — ")
    assert "/post-plan" in drops[0]
    assert sum(n.startswith("UNCHECKABLE-PHASE: 8 ") for n in notes) == 1


def test_proc_path_token_dropped():
    items, notes = _run(3, ["/proc/self/fd/1"], ["bin/other"],
                        ["bin/fd", "proc/readme.md"])
    assert not any(i.startswith("MISSING-PHASE:") for i in items)
    drops = _drop_notes(notes)
    assert len(drops) == 1 and "/proc/self/fd/1" in drops[0]


def test_home_token_dropped_even_when_basename_tracked():
    items, notes = _run(2, ["~/.claude/settings.json"], ["bin/other"],
                        [".claude/settings.json"])
    assert items == []
    drops = _drop_notes(notes)
    assert len(drops) == 1 and "~/.claude/settings.json" in drops[0]


def test_rooted_token_kept_on_exact_tracked_match_still_holds():
    items, notes = _run(4, ["/ibl5/modules.php"], ["bin/other"], ["ibl5/modules.php"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 4 — ")
    assert "/ibl5/modules.php" in items[0]
    assert _drop_notes(notes) == []


def test_rooted_token_kept_on_suffix_match_and_clears_when_diff_touches_it():
    items, notes = _run(4, ["/modules.php"], ["ibl5/modules.php"], ["ibl5/modules.php"])
    assert items == []
    assert _drop_notes(notes) == []


def test_rooted_token_kept_on_directory_prefix_match():
    items, notes = _run(4, ["/ibl5/classes"], ["bin/other"], ["ibl5/classes/Foo.php"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 4 — ")
    assert _drop_notes(notes) == []


def test_rooted_basename_only_match_drops():
    items, notes = _run(5, ["/skills/post-plan"], ["bin/other"],
                        [".claude/skills/post-plan/SKILL.md"])
    assert items == []
    drops = _drop_notes(notes)
    assert len(drops) == 1 and "/skills/post-plan" in drops[0]


def test_mixed_phase_checks_surviving_candidates_only():
    items, notes = _run(6, ["/post-plan", "bin/wt-up"], ["bin/other"],
                        ["bin/wt-up", ".claude/skills/post-plan/SKILL.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 6 — ")
    assert "phase cites bin/wt-up" in items[0]
    assert "/post-plan" not in items[0]
    assert len(_drop_notes(notes)) == 1


def test_relative_tokens_unaffected():
    items, notes = _run(1, ["bin/wt-up"], ["bin/wt-up"], ["bin/wt-up"])
    assert items == []
    assert notes == []
