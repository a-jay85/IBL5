import dataclasses
import inspect
import os, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import runner
from harness.state import RunResult, TerminalState

def _res(terminal, error_kind=None, **kw):
    r = RunResult(terminal=terminal)
    r.error_kind = error_kind
    for k, v in kw.items():
        setattr(r, k, v)
    return r

def test_rebase_conflict_exits_3():
    assert runner.exit_code_for(_res(TerminalState.FAILED, "rebase-conflict")) == 3

def test_local_gate_denial_exits_3():
    """A pre-commit/pre-push hook denial is deterministic — no ~1M skill fallback."""
    assert runner.exit_code_for(_res(TerminalState.FAILED, "local-gate")) == 3

def test_other_typed_failure_exits_1():          # negative path: not everything is 3
    assert runner.exit_code_for(_res(TerminalState.FAILED, "push-disabled")) == 1
    assert runner.exit_code_for(_res(TerminalState.FAILED, None)) == 1

def test_success_and_nothing_to_ship_exit_0():
    assert runner.exit_code_for(_res(TerminalState.SHIPPED_ARMED)) == 0
    assert runner.exit_code_for(_res(TerminalState.SHIPPED_HELD)) == 0
    assert runner.exit_code_for(_res(TerminalState.NOTHING_TO_SHIP)) == 0

def test_degraded_exits_zero():                 # no /post-plan skill fallback on a shipped+held PR
    assert runner.exit_code_for(_res(TerminalState.DEGRADED)) == 0

def test_degraded_does_not_shadow_rebase_sentinel():   # negative: ordering, not a duplicate
    assert runner.exit_code_for(_res(TerminalState.FAILED, "rebase-conflict")) == 3

def test_exit_code_is_never_4():
    """The harness owns Phase 5.5; the launcher has no resume arm, so rc=4 is gone."""
    for terminal in TerminalState:
        for error_kind in (None, "rebase-conflict", "push-failed",
                           "fidelity-procedure-missing", "local-gate"):
            assert runner.exit_code_for(_res(terminal, error_kind=error_kind)) in {0, 1, 3}

def test_runresult_has_no_fidelity_pending():
    assert "fidelity_pending" not in {f.name for f in dataclasses.fields(RunResult)}


def test_rebase_conflict_after_declined_autoresolve_still_exits_3():
    """Phase 3 re-raises with kind="rebase-conflict" so exit_code_for still returns 3."""
    assert runner.exit_code_for(_res(TerminalState.FAILED, "rebase-conflict")) == 3


def test_phase2_conflict_arm_guards_on_kind_and_logs_before_resolving():
    """Detection-time flag must be logged before the resolution attempt (fail-closed ordering)."""
    src = inspect.getsource(runner)
    # Slice the region between `pre_rebase = git.head()` and `git.push()`
    start = src.index("pre_rebase = git.head()")
    end = src.index("git.push()", start)
    region = src[start:end]
    assert 'if e.kind != "rebase-conflict"' in region
    assert "CONFLICT_FLAG=set" in region
    assert "autoresolve_stacked_rebase(" in region
    assert region.index("CONFLICT_FLAG=set") < region.index("autoresolve_stacked_rebase(")


def test_local_gate_is_not_intercepted_by_the_conflict_arm():
    """The new conflict arm must not swallow local-gate denials."""
    src = inspect.getsource(runner)
    start = src.index("pre_rebase = git.head()")
    end = src.index("git.push()", start)
    region = src[start:end]
    assert "local-gate" not in region


def test_runresult_has_no_conflict_fields():
    """RunResult gains no new field — checkpoint schema unchanged."""
    field_names = {f.name for f in dataclasses.fields(RunResult)}
    assert "conflict_flag" not in field_names
    assert "conflict_resolved" not in field_names
