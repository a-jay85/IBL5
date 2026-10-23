"""Reference-only planned tokens in conformance.check.

PR #3511: `.github/workflows/e2e-tests.yml` named as the CI env of an E2E matrix row
held arming condition (3) with a false `MISSING:` for a file the plan never edits.
Each test is named for the mutation it kills.
"""
import dataclasses
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.conformance as conformance
from harness.conformance import check, early_missing_items
from harness.planfile import (
    parse_critical_files,
    parse_matrix,
    parse_no_change_test_paths,
    parse_reference_only_test_paths,
    parse_required_test_methods,
    parse_run_only_test_paths,
)
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
WF = ".github/workflows/e2e-tests.yml"
SPEC = "ibl5/tests/e2e/flows/projected-draft-order.spec.ts"
CF_REF = f"## Critical Files\n- `{SPEC}` — scoped assertions\n- `{WF}` (reference): `e2e-mutators` job at line 844 that picks up the moved spec with no edit.\n\n"
CF_PLAIN = f"## Critical Files\n- `{WF}` — the e2e-mutators job\n\n"
ENV_ROW = f"| 13 | CI runs both specs on the PR | E2E | post-impl | env (2): `{WF}` jobs `e2e-shards` and `e2e-mutators` on the PR |\n"
ENV_ROW_NEW = f"| 13 | CI runs both specs on the PR | E2E | post-impl | env (2): `{WF}` — NEW workflow for the mutators job |\n"
SPEC_ROW = f"| 1 | notes toggle scoped to the Metros row | E2E | post-impl | `{SPEC}` |\n"
OOS_NO_EDIT = f"\n## Out of Scope\n- **The `e2e-mutators` CI job definition.** `{WF}` already runs `--project=mutators`, which picks up the new `testMatch` entry. The workflow file gets no edit.\n"
PHASE_REF = f"## Phase 2: Move the spec\n**Reuse:** the existing `mutators` project and the CI `e2e-mutators` job (`{WF}:844`, which runs `--project=mutators` at line 918). No new project, job, or script.\n\n"
PHASE_EDIT = f"## Phase 2: Move the spec\nAdd a `mutators` matrix entry to `{WF}:844` so the job picks up the spec.\n\n"
MATRIX = "## Verification Matrix\n" + HDR

PR3511 = CF_REF + PHASE_REF + MATRIX + SPEC_ROW + ENV_ROW + OOS_NO_EDIT
REQ = "\n## Required Test Methods\n- `testNotesToggleScopedToMetros`\n"
MISSING_WF = f"MISSING: {WF} (matrix planned a test the diff never wrote)"

# Copied from test_conformance_run_only.py / test_conformance_no_change.py.
E2E_PATH = "ibl5/tests/e2e/smoke/navigation.spec.ts"
VR_SNAP = "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots"
E2E_MARKED = f"| 1 | nav smoke passes | E2E | post-impl | `{E2E_PATH}` (run-only) |\n"
VR_NO_CHANGE = f"| 1 | baselines unchanged | Visual-regression | post-impl | `{VR_SNAP}` (no-change) |\n"


def _plan(body: str) -> PlanInfo:
    """Build a PlanInfo the way locate_plan does, from the fixture text alone.
    Phases are left unparsed so MISSING-PHASE never enters these assertions."""
    planned, manual, _ = parse_matrix(body)
    exempt, refused = parse_reference_only_test_paths(body)
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=planned,
                    truly_manual_rows=manual,
                    no_change_test_paths=parse_no_change_test_paths(body),
                    run_only_test_paths=parse_run_only_test_paths(body),
                    reference_only_test_paths=exempt, reference_only_refusals=refused,
                    critical_files=parse_critical_files(body),
                    required_test_methods=parse_required_test_methods(body))


def _ref_notes(notes: list[str]) -> list[str]:
    return [n for n in notes if n.startswith("REFERENCE-ONLY")]


def test_pr3511_shape_clears_missing():
    notes: list[str] = []
    items = check(_plan(PR3511), [SPEC], tracked_files=[WF, SPEC], notes=notes)
    assert items == []
    assert len(_ref_notes(notes)) == 1
    assert _ref_notes(notes)[0].startswith("REFERENCE-ONLY: " + WF)


def test_early_missing_items_clears_pr3511_shape(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: (WF, SPEC))
    assert early_missing_items(_plan(PR3511), [SPEC]) == []


def test_unmarked_env_row_keeps_missing():
    notes: list[str] = []
    items = check(_plan(MATRIX + SPEC_ROW + ENV_ROW), [SPEC], tracked_files=[WF, SPEC], notes=notes)
    assert items == [MISSING_WF]
    assert _ref_notes(notes) == []


def test_env_row_planning_a_new_file_keeps_missing():
    notes: list[str] = []
    items = check(_plan(CF_REF + MATRIX + SPEC_ROW + ENV_ROW_NEW + OOS_NO_EDIT), [SPEC],
                  tracked_files=[SPEC], notes=notes)
    assert MISSING_WF in items
    refused = [n for n in notes if n.startswith("REFERENCE-ONLY-REFUSED: " + WF)]
    assert len(refused) == 1


def test_critical_file_without_marker_keeps_missing_and_missing_file():
    items = check(_plan(CF_PLAIN + MATRIX + SPEC_ROW + ENV_ROW + OOS_NO_EDIT), [SPEC],
                  tracked_files=[WF, SPEC])
    assert MISSING_WF in items
    assert any(i.startswith("MISSING-FILE: " + WF) for i in items)


def test_phase_body_edit_verb_keeps_missing():
    notes: list[str] = []
    items = check(_plan(PHASE_EDIT + CF_REF + MATRIX + SPEC_ROW + ENV_ROW), [SPEC],
                  tracked_files=[WF, SPEC], notes=notes)
    assert MISSING_WF in items
    assert any("a phase body line cites it with an edit verb" in n for n in _ref_notes(notes))


def test_untracked_path_keeps_missing():
    notes: list[str] = []
    items = check(_plan(PR3511), [SPEC], tracked_files=[SPEC], notes=notes)
    assert items == [MISSING_WF]
    refused = _ref_notes(notes)
    assert len(refused) == 1
    assert refused[0].startswith("REFERENCE-ONLY-REFUSED: " + WF)
    assert "not a tracked file" in refused[0]


def test_git_unavailable_keeps_missing(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)
    items = check(_plan(PR3511), [SPEC], tracked_files=None)
    assert items == [MISSING_WF]


def test_reference_only_does_not_silence_other_rows():
    items = check(_plan(PR3511), [], tracked_files=[WF, SPEC])
    assert [i for i in items if i.startswith("MISSING: ")] == [
        f"MISSING: {SPEC} (matrix planned a test the diff never wrote)"
    ]


def test_reference_only_token_in_diff_takes_ordinary_path():
    notes: list[str] = []
    assert check(_plan(PR3511), [SPEC, WF], tracked_files=[WF, SPEC], notes=notes) == []
    assert _ref_notes(notes) == []


def test_reference_only_leaves_other_item_kinds_byte_identical():
    cf = CF_REF.rstrip("\n") + "\n- `ibl5/classes/Foo.php` — add the helper\n\n"
    body = cf + PHASE_REF + MATRIX + SPEC_ROW + ENV_ROW + OOS_NO_EDIT + REQ
    a = _plan(body)
    b = dataclasses.replace(a, reference_only_test_paths={})
    kwargs = dict(tracked_files=[WF, SPEC], diff_body="+unrelated change\n")
    with_fix = check(a, [SPEC], **kwargs)
    without_fix = check(b, [SPEC], **kwargs)
    assert set(without_fix) - set(with_fix) == {MISSING_WF}
    assert [i for i in without_fix if i != MISSING_WF] == with_fix
    assert any(i.startswith("MISSING-FILE: ibl5/classes/Foo.php") for i in with_fix)
    assert any(i.startswith("MISSING-METHOD: testNotesToggleScopedToMetros") for i in with_fix)


def test_reference_only_notes_record_every_decision():
    exempt: list[str] = []
    check(_plan(PR3511), [SPEC], tracked_files=[WF, SPEC], notes=exempt)
    untracked: list[str] = []
    check(_plan(PR3511), [SPEC], tracked_files=[SPEC], notes=untracked)
    vetoed: list[str] = []
    check(_plan(PHASE_EDIT + CF_REF + MATRIX + SPEC_ROW + ENV_ROW), [SPEC],
          tracked_files=[WF, SPEC], notes=vetoed)
    assert [n.split(" — ")[0] for n in _ref_notes(exempt)] == ["REFERENCE-ONLY: " + WF]
    assert [n.split(" — ")[0] for n in _ref_notes(untracked)] == ["REFERENCE-ONLY-REFUSED: " + WF]
    assert [n.split(" — ")[0] for n in _ref_notes(vetoed)] == ["REFERENCE-ONLY-REFUSED: " + WF]


def test_run_only_and_no_change_unaffected():
    notes: list[str] = []
    assert check(_plan(HDR + E2E_MARKED), [], tracked_files=[E2E_PATH], notes=notes) == []
    assert len([n for n in notes if n.startswith("RUN-ONLY: ")]) == 1
    assert _ref_notes(notes) == []
    vr_notes: list[str] = []
    assert check(_plan(HDR + VR_NO_CHANGE), [], tracked_files=[VR_SNAP], notes=vr_notes) == []
    assert vr_notes == []
