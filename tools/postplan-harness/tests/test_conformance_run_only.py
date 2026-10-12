"""`(run-only)` matrix marker: parser and conformance behaviour.

Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.conformance as conformance
from harness.conformance import check
from harness.planfile import (
    parse_matrix,
    parse_no_change_test_paths,
    parse_run_only_test_paths,
)
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
E2E_PATH = "ibl5/tests/e2e/smoke/navigation.spec.ts"
VR_SNAP = "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots"
PHP_PATH = "tests/Trade/TradeValidatorTest.php"
E2E_MARKED = f"| 1 | nav smoke passes | E2E | post-impl | `{E2E_PATH}` (run-only) |\n"
E2E_UNMARKED = f"| 1 | nav smoke passes | E2E | post-impl | `{E2E_PATH}` |\n"
VR_NO_CHANGE = f"| 1 | baselines unchanged | Visual-regression | post-impl | `{VR_SNAP}` (no-change) |\n"
E2E_RUN_ONLY_ON_SNAP = f"| 2 | snapshots still pass | E2E | post-impl | `{VR_SNAP}` (run-only) |\n"
PHP_ROW = f"| 2 | cap math | PHPUnit | post-impl | `{PHP_PATH}` |\n"


def _plan(body: str) -> PlanInfo:
    """Build a PlanInfo the way locate_plan does, from matrix text alone."""
    planned, manual = parse_matrix(body)
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=planned,
                    truly_manual_rows=manual,
                    no_change_test_paths=parse_no_change_test_paths(body),
                    run_only_test_paths=parse_run_only_test_paths(body))


# --- Parser tests (Phase 1) ---

def test_run_only_e2e_row_is_exempt():
    body = HDR + E2E_MARKED
    assert parse_run_only_test_paths(body) == [E2E_PATH]
    assert parse_matrix(body)[0] == [E2E_PATH]


def test_run_only_honored_on_every_planning_type():
    for kind, tok in (("PHPUnit", PHP_PATH),
                      ("API-test", "tests/Api/FooTest.php"),
                      ("E2E", E2E_PATH),
                      ("Visual-regression", VR_SNAP)):
        body = HDR + f"| 1 | x | {kind} | post-impl | `{tok}` (run-only) |\n"
        assert parse_run_only_test_paths(body) == [tok]


def test_unmarked_row_is_not_exempt():
    assert parse_run_only_test_paths(HDR + E2E_UNMARKED) == []


def test_run_only_shared_with_unmarked_row_drops_token():
    body = HDR + E2E_MARKED + PHP_ROW.replace(PHP_PATH, E2E_PATH)
    assert parse_run_only_test_paths(body) == []


def test_run_only_marker_must_follow_the_rows_own_token():
    body = HDR + ("| 1 | x | E2E | post-impl | "
                  "`a/a.spec.ts` and `b/b.spec.ts` (run-only) |\n")
    assert parse_run_only_test_paths(body) == []


def test_run_only_marker_inside_fence_is_not_honored():
    fenced = "\n```markdown\n" + HDR + E2E_MARKED + "```\n"
    assert parse_run_only_test_paths(HDR + E2E_UNMARKED + fenced) == []
    assert parse_run_only_test_paths("intro\n" + fenced) == []


def test_run_only_is_case_insensitive_and_whitespace_tolerant():
    body = HDR + f"| 1 | x | E2E | post-impl | `{E2E_PATH}`   (Run-Only) |\n"
    assert parse_run_only_test_paths(body) == [E2E_PATH]


def test_no_change_vr_row_is_neutral_for_run_only():
    body = HDR + VR_NO_CHANGE + E2E_RUN_ONLY_ON_SNAP
    assert parse_run_only_test_paths(body) == [VR_SNAP]
    assert parse_no_change_test_paths(body) == []


def test_no_change_marker_alone_is_not_run_only():
    assert parse_run_only_test_paths(HDR + VR_NO_CHANGE) == []


def test_cli_row_mentioning_run_only_plans_nothing():
    body = HDR + "| 1 | x | CLI-executable | post-impl | `bin/foo-test` (run-only) |\n"
    assert parse_run_only_test_paths(body) == []


def test_default_plan_info_has_no_run_only():
    assert PlanInfo().run_only_test_paths == []


# --- Conformance tests (Phase 2) ---

def test_run_only_e2e_row_clears_missing():
    items = check(_plan(HDR + E2E_MARKED), ["ibl5/design/tables.css"],
                  tracked_files=[E2E_PATH])
    assert items == []


def test_run_only_on_untracked_path_keeps_missing():
    items = check(_plan(HDR + E2E_MARKED), ["ibl5/design/tables.css"],
                  tracked_files=[])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + E2E_PATH)


def test_run_only_with_git_unavailable_keeps_missing(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: None)
    items = check(_plan(HDR + E2E_MARKED), ["ibl5/design/tables.css"],
                  tracked_files=None)
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + E2E_PATH)


def test_run_only_shared_with_unmarked_row_keeps_missing():
    body = HDR + E2E_MARKED + E2E_UNMARKED.replace("| 1 |", "| 3 |")
    items = check(_plan(body), [], tracked_files=[E2E_PATH])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + E2E_PATH)


def test_unmarked_absent_test_still_missing():
    items = check(_plan(HDR + E2E_UNMARKED), [], tracked_files=[E2E_PATH])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + E2E_PATH)


def test_run_only_does_not_silence_other_rows():
    items = check(_plan(HDR + E2E_MARKED + PHP_ROW), [],
                  tracked_files=[E2E_PATH, PHP_PATH])
    assert items == ["MISSING: tests/Trade/TradeValidatorTest.php (matrix planned a test the diff never wrote)"]


def test_run_only_does_not_exempt_critical_file():
    plan = PlanInfo(found=True, has_matrix=True, planned_test_paths=[E2E_PATH],
                    run_only_test_paths=[E2E_PATH],
                    critical_files=[(E2E_PATH, "", False)])
    items = check(plan, [], tracked_files=[E2E_PATH])
    assert [i for i in items if i.startswith("MISSING-FILE: " + E2E_PATH)]
    assert len([i for i in items if i.startswith("MISSING-FILE: ")]) == 1
    assert not [i for i in items if i.startswith("MISSING: ")]


def test_run_only_does_not_exempt_required_method():
    plan = PlanInfo(found=True, has_matrix=True, planned_test_paths=[E2E_PATH],
                    run_only_test_paths=[E2E_PATH],
                    required_test_methods=["testNavSmoke"])
    items = check(plan, [], diff_body="+nothing", tracked_files=[E2E_PATH])
    assert [i for i in items if i.startswith("MISSING-METHOD: testNavSmoke")]
    assert not [i for i in items if i.startswith("MISSING: ")]


def test_run_only_token_changed_in_diff_takes_ordinary_path():
    notes: list[str] = []
    items = check(_plan(HDR + E2E_MARKED), [E2E_PATH],
                  tracked_files=[E2E_PATH], notes=notes)
    assert items == []
    assert not [n for n in notes if n.startswith("RUN-ONLY")]


def test_mixed_no_change_and_run_only_rows_exempt_token():
    body = HDR + VR_NO_CHANGE + E2E_RUN_ONLY_ON_SNAP
    items = check(_plan(body), [],
                  tracked_files=[VR_SNAP + "/nav-chromium-darwin.png"])
    assert items == []


def test_run_only_notes_record_decision():
    notes: list[str] = []
    assert check(_plan(HDR + E2E_MARKED), [], tracked_files=[E2E_PATH], notes=notes) == []
    assert len(notes) == 1
    assert notes[0].startswith("RUN-ONLY: ")

    notes = []
    items = check(_plan(HDR + E2E_MARKED), [], tracked_files=[], notes=notes)
    assert len(items) == 1
    assert [n for n in notes if n.startswith("RUN-ONLY-REFUSED: ")]


def test_default_plan_info_keeps_missing():
    plan = PlanInfo(found=True, has_matrix=True, planned_test_paths=[E2E_PATH])
    items = check(plan, [], tracked_files=[E2E_PATH])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + E2E_PATH)
