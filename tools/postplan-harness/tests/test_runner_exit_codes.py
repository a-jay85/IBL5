import os, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import runner
from harness.state import RunResult, TerminalState

def _res(terminal, error_kind=None):
    r = RunResult(terminal=terminal)
    r.error_kind = error_kind
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
