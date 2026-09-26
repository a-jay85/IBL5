"""Phase 8 wiring tests: thread ingestion integrated into runner.py.

Source-order tests read runner.py text to assert ordering invariants (the
test_runner_live_wiring.py style).  Behavioural tests call
runner._run_thread_ingestion_phase directly with a fake run_thread_ingestion,
a RecordingGh, a ReplayGit, and a RunResult.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner  # noqa: E402
from harness.adapters.ghad import RecordingGh
from harness.adapters.gitad import ReplayGit
from harness.state import HarnessError, RunResult, TerminalState, UsageLedger


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _src() -> str:
    """Return runner.py source text."""
    path = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "runner.py")
    with open(path) as fh:
        return fh.read()


def _gh(tmp_path):
    return RecordingGh(str(tmp_path), {})


def _git():
    return ReplayGit({})


def _log():
    lines: list[str] = []
    def _log_fn(msg: str) -> None:
        lines.append(msg)
    _log_fn.lines = lines  # type: ignore[attr-defined]
    return _log_fn


def _ledger():
    return UsageLedger()


def _res():
    return RunResult(terminal=TerminalState.FAILED)


# ---------------------------------------------------------------------------
# Source-order tests
# ---------------------------------------------------------------------------

def test_snapshot_is_taken_before_the_review_is_submitted():
    """pre_posting_ids must be captured BEFORE the review worker is submitted.

    Mutation caught: moving the snapshot below the submit — the worker's posts
    could enter the snapshot and be treated as pre-existing threads.
    """
    src = _src()
    snapshot_pos = src.index("pre_posting_ids = gh.pr_thread_ids(pr)")
    submit_pos = src.index("review_future = review_pool.submit(")
    assert snapshot_pos < submit_pos, (
        "pre_posting_ids snapshot must appear before review_pool.submit"
    )


def test_ingestion_runs_after_snapshot_and_before_review_submit():
    """Phase 4.5 must run after the snapshot and before the review worker starts.

    Mutation caught: moving the call below the submit (the head could move under
    the worker's posts) or above the snapshot (the snapshot would miss nothing
    and ingestion would act on an empty id set).
    """
    src = _src()
    s = src.index("pre_posting_ids = gh.pr_thread_ids(pr)")
    i = src.index("res.thread_ingestion = _run_thread_ingestion_phase(")
    r = src.index("review_future = review_pool.submit(")
    assert s < i < r, (
        "order must be snapshot, then _run_thread_ingestion_phase, then review_pool.submit"
    )


def test_fix_refreshes_head_and_meta_before_review_submit():
    """A thread fix must refresh sha and PR meta before the review worker starts.

    Mutation caught: dropping the meta refresh; the review would checkpoint the
    pre-fix head as reviewed_head.
    """
    src = _src()
    i = src.index("res.thread_ingestion = _run_thread_ingestion_phase(")
    j = src.index("if git.head() != head_before_45:")
    r = src.index("review_future = review_pool.submit(")
    assert i < j < r, "the head-refresh block must sit between Phase 4.5 and the submit"
    refresh = src[j:r]
    assert "sha = git.head()" in refresh, "a thread fix must refresh sha"
    assert "gh.pr_meta()" in refresh, "a thread fix must refresh PR meta"
    assert "post-thread-fix" not in src, "the old post-thread-fix re-run block must be gone"


def test_no_join_before_review_submit():
    """No _join_review() call may precede the review submit.

    Mutation caught: re-adding a join ahead of the worker, which serialises
    Phase 4 and breaks the review/fidelity overlap.
    """
    src = _src()
    r = src.index("review_future = review_pool.submit(")
    assert src.find("_join_review()", 0, r) == -1, (
        "_join_review() must not be called before review_pool.submit"
    )


# ---------------------------------------------------------------------------
# Behavioural tests
# ---------------------------------------------------------------------------

def test_phase45_swallows_non_gate_errors(tmp_path, monkeypatch):
    """A non-gate HarnessError must be swallowed and returned as an error dict.

    Mutation caught: re-raising every HarnessError — the run would abort on any
    transient ingestion failure instead of letting Phase 5.5 continue.
    """
    def _fake_ingestion(*args, **kwargs):
        raise HarnessError("llm-fixture-missing", "x")

    monkeypatch.setattr(runner, "run_thread_ingestion", _fake_ingestion)
    gh = _gh(tmp_path)
    git = _git()
    log = _log()
    res = _res()

    result = runner._run_thread_ingestion_phase(
        gh, None, git, str(tmp_path), None, {111}, str(tmp_path), log, res)

    assert result.get("error", "").startswith("llm-fixture-missing"), (
        f"error key should start with 'llm-fixture-missing', got {result.get('error')!r}"
    )
    assert result["fixed"] == 0
    # No exception raised
    assert any(line.startswith("phase4.5: thread ingestion failed") for line in log.lines), (
        f"Expected 'phase4.5: thread ingestion failed' log line, got: {log.lines}"
    )


@pytest.mark.parametrize("kind", ["gate-path-edit", "push-failed"])
def test_phase45_propagates_gate_path_edit_and_push_failed(tmp_path, monkeypatch, kind):
    """gate-path-edit and push-failed must propagate, never be swallowed.

    Mutation caught: catching every HarnessError and returning an error dict —
    a gate edit would silently pass and bypass the gate downstream.
    """
    def _fake_ingestion(*args, **kwargs):
        raise HarnessError(kind, "detail")

    monkeypatch.setattr(runner, "run_thread_ingestion", _fake_ingestion)
    gh = _gh(tmp_path)
    git = _git()
    log = _log()
    res = _res()

    with pytest.raises(HarnessError) as exc_info:
        runner._run_thread_ingestion_phase(
            gh, None, git, str(tmp_path), None, set(), str(tmp_path), log, res)

    assert exc_info.value.kind == kind


def test_phase45_passes_snapshot_and_injected_commit_push(tmp_path, monkeypatch):
    """The wrapper must forward pre_posting_ids, and inject callable commit/push lambdas.

    Mutation caught: forgetting to pass pre_posting_ids (snapshot is always empty),
    or passing None for commit/push (the ingestion loop would TypeError on a fix).
    """
    captured: dict = {}

    def _fake_ingestion(gh, llm, git, worktree, pr, pre_posting_ids, out_dir, log,
                        *, commit, push, **kwargs):
        captured["pre_posting_ids"] = pre_posting_ids
        captured["commit"] = commit
        captured["push"] = push
        return {"found": 2, "fixed": 1, "declined": 1, "skipped": 0, "last_sha": "abc"}

    monkeypatch.setattr(runner, "run_thread_ingestion", _fake_ingestion)
    gh = _gh(tmp_path)
    git = _git()
    log = _log()
    res = _res()

    result = runner._run_thread_ingestion_phase(
        gh, None, git, str(tmp_path), None, {111}, str(tmp_path), log, res)

    assert captured["pre_posting_ids"] == {111}, (
        f"pre_posting_ids forwarded incorrectly: {captured['pre_posting_ids']!r}"
    )
    assert callable(captured["commit"]), "commit argument must be callable"
    assert callable(captured["push"]), "push argument must be callable"

    # Verify the summary log line
    expected_log = "phase4.5: 2 trusted thread(s) found, 1 fixed, 1 declined, 0 skipped (error)"
    assert any(line == expected_log for line in log.lines), (
        f"Expected summary log line {expected_log!r}, got: {log.lines}"
    )


def test_run_result_carries_thread_ingestion():
    """RunResult must expose thread_ingestion so asdict/to_json serialises it.

    Mutation caught: dropping the dataclass field — res.thread_ingestion = ...
    would still work as a dynamic attribute but never reach result.json.
    """
    res = RunResult(terminal=TerminalState.FAILED)
    d = res.to_json()
    import json
    parsed = json.loads(d)
    assert "thread_ingestion" in parsed, (
        "thread_ingestion must appear in RunResult.to_json() output"
    )
