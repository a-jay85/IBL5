"""Reference-only planned tokens: parser and PlanInfo wiring.

A matrix row can name a file the plan only RUNS or reads (a CI workflow, an
existing spec). The plan itself says so via a Critical Files reference marker or
an Out of Scope no-edit line. Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.planfile import locate_plan, parse_reference_only_test_paths
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
WF = ".github/workflows/e2e-tests.yml"
SPEC = "ibl5/tests/e2e/flows/projected-draft-order.spec.ts"
CF_REF = f"## Critical Files\n- `{SPEC}` — scoped assertions\n- `{WF}` (reference): `e2e-mutators` job at line 844 that picks up the moved spec with no edit.\n\n"
CF_PLAIN = f"## Critical Files\n- `{WF}` — the e2e-mutators job\n\n"
CF_COND = f"## Critical Files\n- `{WF}` (conditional — only if the job list changes)\n\n"
ENV_ROW = f"| 13 | CI runs both specs on the PR | E2E | post-impl | env (2): `{WF}` jobs `e2e-shards` and `e2e-mutators` on the PR |\n"
ENV_ROW_NEW = f"| 13 | CI runs both specs on the PR | E2E | post-impl | env (2): `{WF}` — NEW workflow for the mutators job |\n"
SPEC_ROW = f"| 1 | notes toggle scoped to the Metros row | E2E | post-impl | `{SPEC}` |\n"
OOS_NO_EDIT = f"\n## Out of Scope\n- **The `e2e-mutators` CI job definition.** `{WF}` already runs `--project=mutators`, which picks up the new `testMatch` entry. The workflow file gets no edit.\n"
OOS_DEFERRED = f"\n## Out of Scope\n- `{WF}` gets no edit now; a follow-up adds a second mutators job.\n"
PHASE_REF = f"## Phase 2: Move the spec\n**Reuse:** the existing `mutators` project and the CI `e2e-mutators` job (`{WF}:844`, which runs `--project=mutators` at line 918). No new project, job, or script.\n\n"
PHASE_EDIT = f"## Phase 2: Move the spec\nAdd a `mutators` matrix entry to `{WF}:844` so the job picks up the spec.\n\n"
MATRIX = "## Verification Matrix\n" + HDR


def _fence(text: str) -> str:
    return "```\n" + text + "```\n"


def test_reference_only_critical_files_marker_exempts_env_row():
    exempt, refused = parse_reference_only_test_paths(CF_REF + MATRIX + SPEC_ROW + ENV_ROW)
    assert (exempt, refused) == ({WF: "Critical Files reference marker"}, {})
    assert SPEC not in exempt and SPEC not in refused


def test_reference_only_out_of_scope_no_edit_line_exempts():
    result = parse_reference_only_test_paths(MATRIX + ENV_ROW + OOS_NO_EDIT)
    assert result == ({WF: "Out of Scope no-edit line"}, {})


def test_reference_only_both_signals_name_both():
    result = parse_reference_only_test_paths(CF_REF + MATRIX + ENV_ROW + OOS_NO_EDIT)
    assert result == ({WF: "Critical Files reference marker and Out of Scope no-edit line"}, {})


def test_reference_only_no_signal_returns_nothing():
    assert parse_reference_only_test_paths(MATRIX + ENV_ROW) == ({}, {})


def test_reference_only_unmarked_critical_file_refuses():
    exempt, refused = parse_reference_only_test_paths(CF_PLAIN + MATRIX + ENV_ROW + OOS_NO_EDIT)
    assert exempt == {}
    assert refused[WF].startswith("a Critical Files entry")


def test_reference_only_conditional_marker_is_not_a_signal():
    assert parse_reference_only_test_paths(CF_COND + MATRIX + ENV_ROW) == ({}, {})


def test_reference_only_creation_cue_row_refuses():
    result = parse_reference_only_test_paths(CF_REF + MATRIX + ENV_ROW_NEW)
    assert result == ({}, {WF: "a planning row carries a creation cue"})


def test_reference_only_phase_body_edit_verb_refuses():
    exempt, refused = parse_reference_only_test_paths(PHASE_EDIT + CF_REF + MATRIX + ENV_ROW)
    assert exempt == {}
    assert refused[WF].startswith("a phase body line cites it with an edit verb")


def test_reference_only_phase_body_reference_citation_does_not_veto():
    exempt, refused = parse_reference_only_test_paths(PHASE_REF + CF_REF + MATRIX + ENV_ROW)
    assert WF in exempt
    assert refused == {}


def test_reference_only_out_of_scope_deferral_with_edit_verb_refuses():
    result = parse_reference_only_test_paths(MATRIX + ENV_ROW + OOS_DEFERRED)
    assert result == ({}, {WF: "an Out of Scope line cites it with an edit verb"})


def test_reference_only_fenced_signals_are_ignored():
    body = _fence(CF_REF) + _fence(OOS_NO_EDIT) + MATRIX + ENV_ROW
    assert parse_reference_only_test_paths(body) == ({}, {})


def test_reference_only_run_only_row_is_neutral():
    row = ENV_ROW.replace(f"`{WF}` jobs", f"`{WF}` (run-only) jobs")
    assert row != ENV_ROW
    assert parse_reference_only_test_paths(CF_REF + MATRIX + row) == ({}, {})


def test_reference_only_shared_unmarked_row_is_still_a_candidate():
    exempt, refused = parse_reference_only_test_paths(CF_REF + MATRIX + ENV_ROW + ENV_ROW_NEW)
    assert exempt == {}
    assert WF in refused


# --- PlanInfo / locate_plan wiring (Phase 2) ---


def test_default_plan_info_has_no_reference_only():
    info = PlanInfo()
    assert info.reference_only_test_paths == {}
    assert info.reference_only_refusals == {}


def test_locate_plan_fills_reference_only_fields(tmp_path):
    path = tmp_path / "ref.md"
    path.write_text(CF_REF + MATRIX + ENV_ROW + OOS_NO_EDIT)
    info = locate_plan("ref", explicit_path=str(path))
    assert info.reference_only_test_paths == {
        WF: "Critical Files reference marker and Out of Scope no-edit line"
    }
    assert info.reference_only_refusals == {}


def test_locate_plan_without_matrix_leaves_reference_only_empty(tmp_path):
    path = tmp_path / "ref.md"
    path.write_text(CF_REF + OOS_NO_EDIT)
    info = locate_plan("ref", explicit_path=str(path))
    assert info.has_matrix is False
    assert info.reference_only_test_paths == {}
    assert info.reference_only_refusals == {}
