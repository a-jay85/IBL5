"""Conformance path-matching tests — Phase 1.

Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import check, phase_omission_items
from harness.state import PhaseInfo, PlanInfo


def _plan_with_test(path: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=[path])


def _plan_with_critical(path: str, exempt: bool = False) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, critical_files=[(path, "", exempt)])


# ---------------------------------------------------------------------------
# Post-impl tests (permanent)
# ---------------------------------------------------------------------------

def test_suffix_path_resolves():
    """#2311 shape: token is a suffix of the diff path → resolves, no MISSING.

    Mutation caught: revert tier 1 to `t not in joined` and the diff path
    `tools/postplan-harness/tests/test_foo.py` contains `tests/test_foo.py` as
    a substring, so the old code would return [] — but it would NOT populate
    resolutions, meaning callers could not log which path was matched.
    """
    plan = _plan_with_test("tests/test_foo.py")
    resolutions: dict[str, str] = {}
    items = check(plan, ["tools/postplan-harness/tests/test_foo.py"],
                  resolutions=resolutions)
    assert items == []
    assert resolutions.get("tests/test_foo.py") == "tools/postplan-harness/tests/test_foo.py"


def test_moved_directory_resolves_by_basename():
    """#2316 shape: directory token under moved path → resolves, no MISSING.

    Mutation caught: count matching FILES instead of distinct DIRECTORIES in
    tier 2 and this produces 2 candidates (two files), so the assertion fails.
    """
    plan = _plan_with_critical("ibl5/tests/BulkImport/")
    items = check(plan, [
        "ibl5/tests/Unit/BulkImport/ATest.php",
        "ibl5/tests/Unit/BulkImport/BTest.php",
    ])
    assert items == []


def test_absent_file_stays_missing():
    """Mutation caught: _resolve returning any path on zero candidates."""
    plan = _plan_with_test("tests/test_never_written.py")
    items = check(plan, ["runner.py"])
    assert any("MISSING" in i and "test_never_written.py" in i for i in items)


def test_substring_false_positive_is_not_a_match():
    """token bin/x, diff only bin/xylophone → MISSING (x ≠ xylophone basename).

    Mutation caught: revert to bare `in` test and `bin/x` becomes a substring
    of `bin/xylophone`, so the old code returns [] instead of MISSING.
    """
    plan = _plan_with_test("bin/x")
    items = check(plan, ["bin/xylophone"])
    assert any("MISSING" in i and "bin/x" in i for i in items)


def test_ambiguous_basename_stays_missing():
    """Two same-named directories → ambiguous → MISSING-FILE survives.

    Mutation caught: drop the len(cands) == 1 uniqueness guard.
    """
    plan = _plan_with_critical("tests/BulkImport/")
    items = check(plan, [
        "ibl5/tests/Unit/BulkImport/A.php",
        "ibl5/other/BulkImport/B.php",
    ])
    assert any("MISSING-FILE" in i and "BulkImport" in i for i in items)


def test_ambiguous_file_basename_stays_missing():
    """Two same-named files → ambiguous → MISSING survives.

    Mutation caught: drop the len(cands) == 1 guard.
    """
    plan = _plan_with_test("tests/test_foo.py")
    items = check(plan, ["a/test_foo.py", "b/test_foo.py"])
    assert any("MISSING" in i and "test_foo.py" in i for i in items)


def test_two_suffix_hits_stay_missing():
    """Two tier-1 suffix hits → ambiguous → MISSING survives.

    Mutation caught: change `if hits: return None` to `return hits[0] if hits
    else None` and the first suffix hit is returned, clearing the MISSING.
    """
    plan = _plan_with_test("tests/test_foo.py")
    items = check(plan, ["x/tests/test_foo.py", "y/tests/test_foo.py"])
    assert any("MISSING" in i and "test_foo.py" in i for i in items)


def test_exempt_critical_file_still_skipped():
    """exempt=True → [] regardless of changed_files.

    Mutation caught: drop the `if exempt: continue` guard.
    """
    plan = _plan_with_critical("tools/some/file.py", exempt=True)
    items = check(plan, [])
    assert items == []


def test_evidence_token_still_exact():
    """`_contract_items` uses exact-or-suffix match, not _resolve.

    Mutation caught: route _contract_items through _resolve and `bin/x`
    resolves to `bin/xylophone` by basename, clearing the UNMET-CONTRACT item.
    """
    plan = PlanInfo(
        found=True,
        has_matrix=False,
        evidence=["bin/x"],
    )
    items = check(plan, ["bin/xylophone"])
    assert any("UNMET-CONTRACT" in i and "bin/x" in i for i in items)


def test_empty_token_is_missing():
    """Empty token after strip → MISSING rather than crash.

    Mutation caught: delete the `if not tok: return None` guard and
    `PurePosixPath('').name` yields `''`, matching nothing but also never
    raising; the guard is what ensures the MISSING item is appended.
    """
    plan = _plan_with_test("/")
    items = check(plan, ["any/file.py"])
    assert any("MISSING" in i for i in items)


# ---------------------------------------------------------------------------
# Phase 3: phase_omission_items / check integration (post-impl)
# ---------------------------------------------------------------------------

def _plan_with_phases(phases, deferred=()):
    return PlanInfo(found=True, has_matrix=True,
                    phases=list(phases),
                    deferred_phase_numbers=list(deferred))


def test_phase_with_untouched_evidence_is_missing():
    """Untouched evidence path → one MISSING-PHASE item naming the path.

    Mutation caught: inverting `if any(...)` to `if not any(...)` yields zero items.
    """
    ph = PhaseInfo(number=2, heading="Phase 2: Do stuff", evidence_paths=["harness/x.py"])
    plan = _plan_with_phases([ph])
    items = phase_omission_items(plan, ["harness/y.py"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 2 —")
    assert "harness/x.py" in items[0]


def test_phase_with_any_touched_evidence_ships():
    """Suffix match on ANY evidence path → no item.

    Mutation caught: requiring all() instead of any() emits an item.
    """
    ph = PhaseInfo(number=2, heading="Phase 2: Do stuff",
                   evidence_paths=["harness/x.py", "harness/z.py"])
    plan = _plan_with_phases([ph])
    items = phase_omission_items(plan, ["tools/postplan-harness/harness/z.py"])
    assert items == []


def test_phase_basename_ambiguous_hit_counts_as_shipped():
    """2+ files with same basename → shipped (contrast _resolve's unique-hit rule).

    Mutation caught: replacing _touched with _resolve(...) is not None emits an item.
    """
    ph = PhaseInfo(number=3, heading="Phase 3: A", evidence_paths=["tests/test_a.py"])
    plan = _plan_with_phases([ph])
    items = phase_omission_items(plan, ["a/tests/test_a.py", "b/tests/test_a.py"])
    assert items == []


def test_bookkeeping_phase_is_exempt():
    """bookkeeping=True → no item even with untouched evidence.

    Mutation caught: dropping `ph.bookkeeping or` emits an item.
    """
    ph = PhaseInfo(number=4, heading="Close backlog [phases: S]",
                   evidence_paths=["docs/x.md"], bookkeeping=True)
    plan = _plan_with_phases([ph])
    items = phase_omission_items(plan, [])
    assert items == []


def test_deferred_phase_is_exempt():
    """Phase number in deferred_phase_numbers → no item; different number is not exempt.

    Mutation caught: dropping `ph.number in deferred` fails the first assertion.
    """
    ph = PhaseInfo(number=5, heading="Phase 5: Defer me", evidence_paths=["bin/x"])
    plan_deferred = _plan_with_phases([ph], deferred=[5])
    assert phase_omission_items(plan_deferred, []) == []

    plan_other = _plan_with_phases([ph], deferred=[6])
    items = phase_omission_items(plan_other, [])
    assert len(items) == 1


def test_phase_without_evidence_is_exempt():
    """evidence_paths=[] → no item (cannot verify = skip).

    Mutation caught: dropping `not ph.evidence_paths` emits an item.
    """
    ph = PhaseInfo(number=7, heading="ADR", evidence_paths=[])
    plan = _plan_with_phases([ph])
    assert phase_omission_items(plan, []) == []


def test_check_includes_phase_items_for_matrixless_plan():
    """has_matrix=False plan still gets MISSING-PHASE items from check().

    Mutation caught: moving items.extend(...) below the has_matrix early return drops it.
    """
    ph = PhaseInfo(number=2, heading="Phase 2: B", evidence_paths=["bin/b"])
    plan = PlanInfo(found=True, has_matrix=False, phases=[ph])
    items = check(plan, [])
    assert any(i.startswith("MISSING-PHASE: 2") for i in items)


def test_check_not_found_plan_yields_nothing():
    """found=False → check returns [] even with untouched phases.

    Mutation caught: removing the plan.found guard in phase_omission_items emits an item.
    """
    ph = PhaseInfo(number=3, heading="Phase 3: C", evidence_paths=["harness/c.py"])
    plan = PlanInfo(found=False, phases=[ph])
    assert check(plan, []) == []
