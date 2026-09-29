"""Phase 4 — matrix assertion conformance tests.

Each test exercises _matrix_assertion_items() and/or check() directly,
using the real bin/lib/plan-matrix-assertions script where noted.

MATRIX_ASSERT_ROOT is set to a fresh git-init tmp dir per test (matching
Phase 2's shell-test isolation) to prevent real-repo token interference via
the tree-grep arm.
"""
import os
import stat
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import (
    _MATRIX_ASSERTIONS_SCRIPT,
    _matrix_assertion_items,
    check,
)
from harness import fidelity
from harness.state import PlanInfo

# ---------------------------------------------------------------------------
# Shared plan fixture text (single-word backtick token: FIXTURE_NEW_SYMBOL)
# ---------------------------------------------------------------------------
_MATRIX_PLAN = """\
# Test plan

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 7 | `FIXTURE_NEW_SYMBOL` verifies the gate | CLI-executable | post-impl | `bin/test-it` |
"""

_DIFF_WITH_TOKEN = "+FIXTURE_NEW_SYMBOL=1\n"
_DIFF_WITHOUT_TOKEN = "+something_else=1\n"
_WAIVER_ROW_7 = "WAIVE-MATRIX-ROW: 7\n"
_WAIVER_ROW_9 = "WAIVE-MATRIX-ROW: 9\n"


def _make_plan(tmp_path, has_matrix: bool = True, planned_test_paths=None) -> PlanInfo:
    plan_file = tmp_path / "plan.md"
    if has_matrix:
        plan_file.write_text(_MATRIX_PLAN)
    else:
        plan_file.write_text("# Plan without matrix\n\nNo Verification Matrix here.\n")
    return PlanInfo(
        found=True,
        has_matrix=has_matrix,
        path=str(plan_file),
        planned_test_paths=planned_test_paths or [],
    )


def _isolated_env(git_root: str) -> dict:
    """Env with MATRIX_ASSERT_ROOT pointed at an empty git repo."""
    env = os.environ.copy()
    env["MATRIX_ASSERT_ROOT"] = git_root
    return env


def _make_git_root(tmp_path) -> str:
    root = str(tmp_path / "fake-repo")
    os.makedirs(root, exist_ok=True)
    subprocess.run(["git", "init", root], capture_output=True, check=True)
    return root


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------

def test_realised_assertion_is_clean(tmp_path, monkeypatch):
    """Diff contains the token — no UNREALISED-ASSERTION item."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    items = _matrix_assertion_items(plan, _DIFF_WITH_TOKEN, "")
    assert not any(i.startswith("UNREALISED-ASSERTION:") for i in items)


def test_unrealised_assertion_is_reported(tmp_path, monkeypatch):
    """Diff lacks the token — one UNREALISED-ASSERTION item naming row 7."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    items = _matrix_assertion_items(plan, _DIFF_WITHOUT_TOKEN, "")
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert len(unrealised) == 1
    assert "7" in unrealised[0]


def test_waiver_in_pr_body_clears_item(tmp_path, monkeypatch):
    """Unrealised case but pr_body has WAIVE-MATRIX-ROW: 7 — no item."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    items = _matrix_assertion_items(plan, _DIFF_WITHOUT_TOKEN, _WAIVER_ROW_7)
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert unrealised == []


def test_waiver_for_other_row_does_not_clear(tmp_path, monkeypatch):
    """Waiver names row 9, not row 7 — item still reported."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    items = _matrix_assertion_items(plan, _DIFF_WITHOUT_TOKEN, _WAIVER_ROW_9)
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert len(unrealised) == 1


def test_empty_diff_body_skips_check(tmp_path, monkeypatch):
    """diff_body="" — no UNREALISED-ASSERTION; script is never invoked."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)

    def _fail_if_called(argv, **kwargs):
        raise AssertionError(f"script must not be called when diff_body is empty: {argv}")

    monkeypatch.setattr("harness.conformance.subprocess.run", _fail_if_called)

    # Use check() so we exercise the if diff_body: guard
    items = check(plan, [], diff_body="", pr_body="any")
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert unrealised == []


def test_no_matrix_skips_check(tmp_path, monkeypatch):
    """has_matrix=False — no UNREALISED-ASSERTION item from check()."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path, has_matrix=False)
    items = check(plan, [], diff_body="+something=1\n", pr_body="")
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert unrealised == []


def test_missing_script_fails_closed(tmp_path, monkeypatch):
    """Absent script path — one item containing 'check unavailable'."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    absent = str(tmp_path / "absent")
    items = _matrix_assertion_items(plan, "x", "", script=absent)
    assert len(items) == 1
    assert "check unavailable" in items[0]


def test_script_usage_error_fails_closed(tmp_path, monkeypatch):
    """Script exits 2 — one item containing 'exit 2'."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path)
    stub = tmp_path / "stub.sh"
    stub.write_text("#!/bin/sh\nexit 2\n")
    stub.chmod(stub.stat().st_mode | stat.S_IEXEC | stat.S_IXGRP | stat.S_IXOTH)
    items = _matrix_assertion_items(plan, "x", "", script=str(stub))
    assert len(items) == 1
    assert "exit 2" in items[0]


def test_unrealised_item_joins_missing_items(tmp_path, monkeypatch):
    """Unrealised plan + absent planned_test_paths entry → both MISSING: and UNREALISED-ASSERTION:."""
    git_root = _make_git_root(tmp_path)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _make_plan(tmp_path, planned_test_paths=["tests/test_absent.py"])
    items = check(plan, [], diff_body=_DIFF_WITHOUT_TOKEN, pr_body="")
    missing = [i for i in items if i.startswith("MISSING:")]
    unrealised = [i for i in items if i.startswith("UNREALISED-ASSERTION:")]
    assert len(missing) >= 1
    assert len(unrealised) >= 1


def test_fidelity_work_list_excludes_unrealised_assertion(tmp_path):
    """build_work_list with UNREALISED-ASSERTION in unresolved_conformance → no hold-3 item for it."""
    verdict_path = str(tmp_path / "verdict.txt")
    open(verdict_path, "w").close()  # empty file
    unresolved = ["UNREALISED-ASSERTION: row 3 — some assertion text"]
    work_list = fidelity.build_work_list(
        verdict_path=verdict_path,
        unresolved_conformance=unresolved,
    )
    # No item with hold "3" whose text starts with UNREALISED-ASSERTION
    bad = [item for item in work_list
           if item.get("hold") == "3" and item.get("text", "").startswith("UNREALISED-ASSERTION")]
    assert bad == []


# ---------------------------------------------------------------------------
# Token-notation false-positive fixes (multi-arg call, ${VAR:-}, glob, `=`)
# ---------------------------------------------------------------------------

def _plan_with_row(tmp_path, cell: str) -> PlanInfo:
    """PlanInfo whose matrix has one row (id 1) with the given 'What to verify' cell."""
    plan_file = tmp_path / "notation-plan.md"
    plan_file.write_text(
        "# Test plan\n\n## Verification Matrix\n\n"
        "| # | What to verify | Test type | Timing | Test file / location |\n"
        "|---|---------------|-----------|--------|---------------------|\n"
        f"| 1 | {cell} | CLI-executable | post-impl | `bin/test-it` |\n"
    )
    return PlanInfo(found=True, has_matrix=True, path=str(plan_file),
                    planned_test_paths=[])


def _unrealised(items: list[str]) -> list[str]:
    return [i for i in items if i.startswith("UNREALISED-ASSERTION:")]


def test_multi_arg_call_realised(tmp_path, monkeypatch):
    """`assertCount(3)` is realised by `assertCount(3, extra)` in the diff."""
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", _make_git_root(tmp_path))
    plan = _plan_with_row(tmp_path, "method call: `assertCount(3)` present")
    items = _matrix_assertion_items(plan, "+    self.assertCount(3, extra)\n", "")
    assert _unrealised(items) == []


def test_shell_var_with_default_realised(tmp_path, monkeypatch):
    """`${MY_CONF_VAR}` is realised by `${MY_CONF_VAR:-}` in the diff."""
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", _make_git_root(tmp_path))
    plan = _plan_with_row(tmp_path, "shell var: `${MY_CONF_VAR}` set")
    items = _matrix_assertion_items(plan, '+  local x="${MY_CONF_VAR:-}"\n', "")
    assert _unrealised(items) == []


def test_glob_prefix_realised(tmp_path, monkeypatch):
    """`test_something_*` is realised when a tracked file holds `test_something_impl`."""
    git_root = _make_git_root(tmp_path)
    with open(os.path.join(git_root, "fixture.txt"), "w") as fh:
        fh.write("test_something_impl\n")
    subprocess.run(["git", "-C", git_root, "add", "fixture.txt"],
                   capture_output=True, check=True)
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", git_root)
    plan = _plan_with_row(tmp_path, "glob match: `test_something_*` present")
    items = _matrix_assertion_items(plan, "+unrelated\n", "")
    assert _unrealised(items) == []


def test_equals_class4_name_realised(tmp_path, monkeypatch):
    """`current_tree=T2` is cut to `current_tree`; the name in the diff realises it."""
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", _make_git_root(tmp_path))
    plan = _plan_with_row(tmp_path, "scenario: `current_tree=T2`")
    items = _matrix_assertion_items(plan, '+    run(current_tree="$T2")\n', "")
    assert _unrealised(items) == []


def test_equals_class5_name_realised(tmp_path, monkeypatch):
    """`REVIEW_OWED_TIMEOUT=1` is realised by a monkeypatch of the same name."""
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", _make_git_root(tmp_path))
    plan = _plan_with_row(tmp_path, "env var: `REVIEW_OWED_TIMEOUT=1`")
    diff = '+    monkeypatch.setattr(fidelity, "REVIEW_OWED_TIMEOUT", 1)\n'
    items = _matrix_assertion_items(plan, diff, "")
    assert _unrealised(items) == []


def test_equals_name_absent_still_flags(tmp_path, monkeypatch):
    """An `name=value` token whose name appears nowhere is still flagged."""
    monkeypatch.setenv("MATRIX_ASSERT_ROOT", _make_git_root(tmp_path))
    plan = _plan_with_row(tmp_path, "env var: `FIXTURE_ABSENT_TIMEOUT=1`")
    items = _matrix_assertion_items(plan, "+unrelated\n", "")
    assert len(_unrealised(items)) == 1
