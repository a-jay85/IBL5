"""Characterization of conformance.phase_omission_items.

The tests here pin behaviour that must survive the non-diff-phase exemption work
unchanged. They are green on master.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.state import PhaseInfo, PlanInfo


def _plan(*phases: PhaseInfo, deferred: list[int] | None = None) -> PlanInfo:
    return PlanInfo(found=True, phases=list(phases),
                    deferred_phase_numbers=deferred or [])


def test_real_repo_path_untouched_holds():
    ph = PhaseInfo(number=1, heading="Phase 1: Mask the log", evidence_paths=["bin/wt-up"])
    items = conformance.phase_omission_items(_plan(ph), ["ibl5/docs/API_GUIDE.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1 — Phase 1: Mask the log")
    assert "phase cites bin/wt-up" in items[0]
    assert "none appeared in the diff" in items[0]


def test_real_repo_path_touched_by_basename_clears():
    ph = PhaseInfo(number=1, heading="Phase 1: Mask the log", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(_plan(ph), ["bin/wt-up"]) == []
    assert conformance.phase_omission_items(_plan(ph), ["ibl5/x/wt-up"]) == []


def test_no_evidence_phase_skipped():
    ph = PhaseInfo(number=1, heading="Phase 1: Nothing cited", evidence_paths=[])
    assert conformance.phase_omission_items(_plan(ph), []) == []


def test_bookkeeping_and_deferred_phases_skipped():
    book = PhaseInfo(number=1, heading="Phase 1: Tidy [phases: S]",
                     evidence_paths=["bin/wt-up"], bookkeeping=True)
    deferred = PhaseInfo(number=4, heading="Phase 4: Later", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(_plan(book, deferred, deferred=[4]), []) == []


def test_hold_line_shape_lists_three_and_more_count():
    paths = ["bin/wt-up", "tools/postplan-harness/harness/conformance.py",
             "ibl5/docs/API_GUIDE.md", "bin/check-plan", "bin/wt-new"]
    five = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths)
    item = conformance.phase_omission_items(_plan(five), [])[0]
    assert ("phase cites bin/wt-up, tools/postplan-harness/harness/conformance.py, "
            "ibl5/docs/API_GUIDE.md (+2 more);") in item
    four = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths[:4])
    assert "(+1 more)" in conformance.phase_omission_items(_plan(four), [])[0]
    three = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths[:3])
    assert "more" not in conformance.phase_omission_items(_plan(three), [])[0]


def test_plan_not_found_yields_nothing():
    ph = PhaseInfo(number=1, heading="Phase 1: X", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(PlanInfo(found=False, phases=[ph]), []) == []
    assert conformance.phase_omission_items(PlanInfo(found=True, phases=[]), []) == []


TRACKED = ["bin/wt-up", "ibl5/docs/API_GUIDE.md", ".claude/skills/post-plan/SKILL.md"]
NON_REPO = ["/proc/self/fd/1", "api/v1", "a-jay85/ibl5-bugs", "~/claude-plans/x.md", "origin/master"]


def test_non_repo_citations_only_is_uncheckable_not_held():
    ph = PhaseInfo(number=1, heading="Phase 1: Elsewhere", evidence_paths=list(NON_REPO))
    notes: list[str] = []
    items = conformance.phase_omission_items(
        _plan(ph), ["ibl5/docs/API_GUIDE.md"], tracked_files=TRACKED, notes=notes)
    assert items == []
    assert len(notes) == 1
    assert notes[0].startswith("UNCHECKABLE-PHASE: 1")
    assert "/proc/self/fd/1, api/v1, a-jay85/ibl5-bugs (+2 more)" in notes[0]


def test_mixed_citations_check_only_repo_ones():
    ph = PhaseInfo(number=1, heading="Phase 1: Mixed",
                   evidence_paths=["/proc/self/fd/1", "bin/wt-up"])
    items = conformance.phase_omission_items(
        _plan(ph), ["ibl5/docs/API_GUIDE.md"], tracked_files=TRACKED)
    assert len(items) == 1
    assert "phase cites bin/wt-up;" in items[0]
    assert "/proc" not in items[0]
    notes: list[str] = []
    assert conformance.phase_omission_items(
        _plan(ph), ["bin/wt-up"], tracked_files=TRACKED, notes=notes) == []
    assert notes == []


def test_basename_collision_with_tracked_dir_stays_candidate():
    ph = PhaseInfo(number=1, heading="Phase 1: Skill", evidence_paths=["/post-plan"])
    items = conformance.phase_omission_items(
        _plan(ph), ["ibl5/docs/API_GUIDE.md"], tracked_files=TRACKED)
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")


def test_no_diff_phase_exempt_with_note():
    ph = PhaseInfo(number=1, heading="Phase 1: Close the issue", evidence_paths=["bin/wt-up"],
                   no_diff_reason="closes the issue through the PR body")
    notes: list[str] = []
    items = conformance.phase_omission_items(_plan(ph), [], tracked_files=TRACKED, notes=notes)
    assert items == []
    assert notes == ["NO-DIFF-PHASE: 1 — Phase 1: Close the issue "
                     "(exempt: closes the issue through the PR body)"]


def test_no_diff_rejected_marker_still_holds():
    ph = PhaseInfo(number=1, heading="Phase 1: Close", evidence_paths=["bin/wt-up"],
                   no_diff_rejected=True)
    notes: list[str] = []
    items = conformance.phase_omission_items(_plan(ph), [], tracked_files=TRACKED, notes=notes)
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")
    assert len(notes) == 1
    assert notes[0].startswith("NO-DIFF-IGNORED: phase 1")


def test_tracked_unavailable_fails_closed(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)
    ph = PhaseInfo(number=1, heading="Phase 1: Elsewhere", evidence_paths=["/proc/self/fd/1"])
    items = conformance.phase_omission_items(_plan(ph), ["ibl5/docs/API_GUIDE.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")


def test_empty_tracked_list_is_not_fail_closed():
    ph = PhaseInfo(number=1, heading="Phase 1: Elsewhere", evidence_paths=["/proc/self/fd/1"])
    assert conformance.phase_omission_items(
        _plan(ph), ["ibl5/docs/API_GUIDE.md"], tracked_files=[]) == []


def test_check_passes_tracked_and_notes_through():
    ph = PhaseInfo(number=1, heading="Phase 1: Elsewhere", evidence_paths=["/proc/self/fd/1"])
    plan = PlanInfo(found=True, has_matrix=False, phases=[ph])
    notes: list[str] = []
    items = conformance.check(plan, ["ibl5/docs/API_GUIDE.md"],
                              tracked_files=TRACKED, notes=notes)
    assert not any(i.startswith("MISSING-PHASE") for i in items)
    assert len(notes) == 1
    assert notes[0].startswith("UNCHECKABLE-PHASE: 1")


def test_tracked_files_reads_git_ls_files(tmp_path):
    import subprocess
    repo = tmp_path / "repo"
    (repo / "a").mkdir(parents=True)
    (repo / "a" / "b.txt").write_text("x")
    subprocess.run(["git", "init", "-q"], cwd=repo, check=True)
    subprocess.run(["git", "add", "a/b.txt"], cwd=repo, check=True)
    subprocess.run(["git", "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "x"],
                   cwd=repo, check=True)
    got = conformance._tracked_files.__wrapped__(str(repo))
    assert isinstance(got, tuple)
    assert "a/b.txt" in got
    nogit = tmp_path / "nogit"
    nogit.mkdir()
    assert conformance._tracked_files.__wrapped__(str(nogit)) is None
