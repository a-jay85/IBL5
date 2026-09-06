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

def test_other_typed_failure_exits_1():          # negative path: not everything is 3
    assert runner.exit_code_for(_res(TerminalState.FAILED, "push-disabled")) == 1
    assert runner.exit_code_for(_res(TerminalState.FAILED, None)) == 1

def test_success_and_nothing_to_ship_exit_0():
    assert runner.exit_code_for(_res(TerminalState.SHIPPED_ARMED)) == 0
    assert runner.exit_code_for(_res(TerminalState.SHIPPED_HELD)) == 0
    assert runner.exit_code_for(_res(TerminalState.NOTHING_TO_SHIP)) == 0

def test_fidelity_pending_returns_4():
    assert runner.exit_code_for(
        _res(TerminalState.SHIPPED_HELD, fidelity_pending=True)) == 4


def test_rebase_conflict_beats_fidelity_pending():
    """Exit 3's sentinel semantics are untouched: a conflict is 3, never 4."""
    assert runner.exit_code_for(
        _res(TerminalState.FAILED, "rebase-conflict", fidelity_pending=True)) == 3


def test_failed_run_never_returns_4():
    assert runner.exit_code_for(
        _res(TerminalState.FAILED, "push-disabled", fidelity_pending=True)) == 1


def test_existing_codes_unchanged_without_fidelity_pending():
    for t in (TerminalState.SHIPPED_ARMED, TerminalState.SHIPPED_HELD,
              TerminalState.NOTHING_TO_SHIP):
        assert runner.exit_code_for(_res(t)) == 0
