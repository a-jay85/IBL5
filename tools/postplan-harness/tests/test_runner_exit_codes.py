import dataclasses
import inspect
import os, sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import runner
from harness.state import RunResult, TerminalState, HarnessError

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

_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.dirname(os.path.abspath(__file__)))))   # tests/ -> postplan-harness/ -> tools/ -> repo root


class _FakeGit:
    """Scripted commit double. Each entry in `denials` is (kind, detail) raised by the
    matching commit_all call; calls past the list succeed."""
    def __init__(self, *denials):
        self.denials, self.messages, self.staged = list(denials), [], 0

    def commit_all(self, message):
        self.messages.append(message)
        if self.denials:
            kind, detail = self.denials.pop(0)
            raise HarnessError(kind, detail)
        return "sha-ok"

    def stage_all(self):
        self.staged += 1


_DOC_STALE = "Bump last_verified on the doc(s) above (and confirm the content is"
_BYTE_BUDGET = "Trim the rule(s) above (or move detail into a path-scoped *-detail.md"
_ADR = "pre-push-adr-hook: a decision-trigger surface is being pushed without an ADR."


def test_doc_staleness_is_remediated_and_retried(monkeypatch):
    monkeypatch.setattr(runner, "_remediate_doc_staleness", lambda w, g, l: 3)
    git = _FakeGit(("local-gate", _DOC_STALE))
    sha = runner._commit_with_gate_remediation(git, "/wt", "feat: x\n\nbody", lambda m: None)
    assert sha == "sha-ok"
    assert len(git.messages) == 2                       # original + one retry
    assert git.messages[1].endswith(runner._REMEDIATION_NOTE)
    assert runner._REMEDIATION_NOTE not in git.messages[0]


def test_retry_is_bounded_at_one_and_preserves_the_original_cause(monkeypatch):
    """Second denial must NOT trigger a second remediation, and the error that
    propagates is the FIRST one."""
    monkeypatch.setattr(runner, "_remediate_doc_staleness", lambda w, g, l: 1)
    git = _FakeGit(("local-gate", _DOC_STALE), ("local-gate", "second denial text"))
    with pytest.raises(HarnessError) as e:
        runner._commit_with_gate_remediation(git, "/wt", "msg", lambda m: None)
    assert e.value.detail == _DOC_STALE                 # original, not "second denial text"
    assert len(git.messages) == 2                       # exactly two attempts, no loop


@pytest.mark.parametrize("detail", [_BYTE_BUDGET, _ADR, "Author identity unknown",
                                    "Fix the above doc issues before committing."])
def test_non_remediable_classes_never_remediate(monkeypatch, detail):
    """Load-bearing: the ADR arm and every unrecognised denial re-raise untouched."""
    called = []
    monkeypatch.setattr(runner, "_remediate_doc_staleness",
                        lambda w, g, l: called.append(1) or 1)
    git = _FakeGit(("local-gate", detail))
    with pytest.raises(HarnessError) as e:
        runner._commit_with_gate_remediation(git, "/wt", "msg", lambda m: None)
    assert e.value.detail == detail and called == [] and len(git.messages) == 1


def test_no_worktree_never_remediates(monkeypatch):
    """Negative path: replay mode has worktree=None - re-raise, run no subprocess."""
    monkeypatch.setattr(runner, "_remediate_doc_staleness",
                        lambda *a: pytest.fail("remediation ran without a worktree"))
    git = _FakeGit(("local-gate", _DOC_STALE))
    with pytest.raises(HarnessError):
        runner._commit_with_gate_remediation(git, None, "msg", lambda m: None)


def test_non_local_gate_error_passes_straight_through():
    git = _FakeGit(("git", "fatal: could not read from remote repository"))
    with pytest.raises(HarnessError) as e:
        runner._commit_with_gate_remediation(git, "/wt", "msg", lambda m: None)
    assert e.value.kind == "git"


def test_doc_gate_base_matches_the_pre_commit_hook(monkeypatch):
    """Cross-file contract pin. The hook derives its base with `git merge-base HEAD
    origin/master`; hardcoding origin/master here would fix a different changed set
    than the gate checks, and every mocked test would still be green."""
    seen = {}

    def fake_run(argv, **kw):
        seen["argv"] = argv
        class P: returncode, stdout, stderr = 0, "abc123\n", ""
        return P()

    monkeypatch.setattr(runner.subprocess, "run", fake_run)
    assert runner._doc_gate_base("/wt") == "abc123"
    assert seen["argv"] == ["git", "-C", "/wt", "merge-base", "HEAD", "origin/master"]
    with open(os.path.join(_ROOT, "bin", "pre-commit-hook")) as fh:
        assert "merge-base HEAD origin/master" in fh.read()


def test_remediation_flags_exist_in_the_real_check_docs():
    """Non-mocked pin on the real script: a flag rename breaks the helper silently
    otherwise, because every fake-subprocess test would keep passing."""
    with open(os.path.join(_ROOT, "bin", "check-docs")) as fh:
        src = fh.read()
    assert runner._DOC_FIX_FLAG in src and "--since=" in src
    assert os.path.exists(os.path.join(_ROOT, runner._DOC_FIX_SCRIPT))


def test_run_commits_through_the_remediation_wrapper():
    """Pin the wiring: reverting runner.py:275 to a bare git.commit_all() leaves every
    Phase 2 unit test green, so assert the call site by source inspection."""
    src = open(os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))),
                            "runner.py")).read()
    assert "_commit_with_gate_remediation(" in src
    assert "sha = git.commit_all(" not in src        # the old call site is gone
    assert 'upsert_files_changed(copy["summary_md"]' in src   # PR body still unmutated
