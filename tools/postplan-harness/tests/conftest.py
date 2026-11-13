"""Shared fixtures for the harness test suite.

Opt in per module with `pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")`.
`stub_ambient_git_show` is not autouse: `test_fidelity.py` and `test_fidelity_remediation.py`
exercise the real `_git_show` through a `git` shim on PATH, and a repo-wide patch would
defeat them. `standard_review_depth` and `pid_reap_witness` are opt-in the same way. Autouse fixtures: `no_real_pr_review_now`, `stub_prefire_gate`, `_reset_netretry_latch`,
`isolate_live_reviewcache`, `_private_verdict_files`, `_private_log_dir`, and `no_ambient_usage_gate`.
"""
from __future__ import annotations

import os
import sys
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from parallel_default import (  # noqa: E402,F401  (hooks registered by import)
    pytest_cmdline_main, pytest_configure, pytest_terminal_summary)


@pytest.fixture
def stub_ambient_git_show(monkeypatch):
    """Cut a replay suite's dependency on the ambient checkout's `origin/master` ref.

    `fidelity._git_show` shells out in the cwd. The replay fixtures pass no `worktree`, so
    the lookup hits whatever repo pytest happens to sit in. Locally that resolves and the
    Phase 5.5 path runs; under `actions/checkout` there is no `origin/master` ref, so every
    lookup failed and the run degraded to `fidelity-procedure-missing` with terminal
    `shipped-held`. Procedure lookup keeps its own coverage in `test_fidelity.py` (found in
    either location, and missing from both), so stubbing here loses nothing and makes the
    two environments identical. Returning None for the digest script leaves `digest_lines`
    on its documented degrade instead of running an unrelated body through bash.
    """
    bodies = {path: "STUB PROCEDURE BODY\n"
              for path in fidelity.PROCEDURE_PATHS + fidelity.REMEDIATION_PATHS}

    def _show(worktree, ref_path):
        return bodies.get(ref_path.split(":", 1)[-1])

    monkeypatch.setattr(fidelity, "_git_show", _show)


@pytest.fixture
def standard_review_depth(monkeypatch):
    """Pin runner's Phase 4 review depth to standard for tests that pin cache and interdiff
    behavior at the standard tier. Replay bodies carry no two-way/localized Merge danger line,
    so the real selection would read deep. Selection is covered by test_runner_review_depth.py."""
    import runner
    from harness.review import ReviewPhase
    monkeypatch.setattr(runner, "_review_phase_for",
                        lambda llm, gh, copy, meta, log, worktree=None: ReviewPhase(llm, gh, log=log, worktree=worktree))


@pytest.fixture(autouse=True)
def no_real_pr_review_now(monkeypatch):
    """Keep every test away from the real `bin/pr-review-now`.

    Any `runner.run(..., live=True)` that reaches Phase 6.5 runs review-owed.sh, which
    fires `bin/pr-review-now <pr>` unless REVIEW_OWED_PR_REVIEW_NOW names another binary.
    The real one registers a launchd job against the main checkout and DMs the owner, so
    the replay suites' fixture PR (#999) sent a real DM on every local run. Tests that
    assert on the fire still set their own logging stub, which overrides this default.
    """
    monkeypatch.setenv("REVIEW_OWED_PR_REVIEW_NOW", "/usr/bin/true")


@pytest.fixture(autouse=True)
def no_ambient_usage_gate(monkeypatch):
    """Keep a live run's usage-gate context out of the tests.

    A post-plan session inherits these from its run. A test that builds a real ClaudeCli
    and feeds it limit text would write a real pause marker for the live run, which then
    stops at a usage limit it never hit. Tests that need a context set their own.
    """
    for name in ("IBL5_USAGE_GATE", "IBL5_USAGE_GATE_MODEL", "IBL5_USAGE_GATE_RUNNER",
                 "IBL5_USAGE_GATE_RESUME_BIN", "IBL5_USAGE_GATE_SESSION_ID"):
        monkeypatch.delenv(name, raising=False)


@pytest.fixture(autouse=True)
def offline_branch_base(monkeypatch):
    """Keep the base lookup's gh layer off in tests.

    branch_base_merge_target returns 2 when gh fails with anything but "no pull requests
    found", and gh run against a temp repo with a local-path remote always fails that way.
    Tests that exercise the gh layer (test_merge_target.py) delete this variable themselves.
    """
    monkeypatch.setenv("BRANCH_BASE_OFFLINE", "1")


@pytest.fixture(autouse=True)
def stub_prefire_gate(monkeypatch):
    """Keep every test that runs the real `bin/post-plan-now` away from the real pre-fire gate.

    The gate runs PHPUnit and Infection and exits 9 on a tree without them. These suites
    assert other post-plan-now behavior. `bin/test-prefire-verify` covers the gate itself.
    """
    monkeypatch.setenv("PREFIRE_VERIFY_SCRIPT", "/usr/bin/true")


@pytest.fixture(autouse=True)
def _reset_netretry_latch():
    from harness import netretry
    netretry.reset_outage_latch()
    yield
    netretry.reset_outage_latch()


@pytest.fixture(autouse=True)
def isolate_live_reviewcache(tmp_path, monkeypatch):
    """Keep live-shaped runs from sharing the harness's real `out/state` review cache.

    A live run keys its state dir to the harness's own `out/state`, so without this a
    record written by one test (or one local suite run) would turn the next live-shaped
    run of the same slug into a cache hit.
    """
    from harness import reviewcache

    real = os.path.realpath(os.path.join(
        os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "out", "state"))
    orig = reviewcache.record_path

    def _path(state_dir, slug):
        if os.path.realpath(state_dir) == real:
            state_dir = str(tmp_path / "reviewcache-live-state")
        return orig(state_dir, slug)

    monkeypatch.setattr(reviewcache, "record_path", _path)


@pytest.fixture(autouse=True)
def _private_verdict_files(tmp_path_factory, monkeypatch):
    """Give each test its own fidelity verdict files.

    `fidelity.verdict_path` is keyed only on the PR and round, under /tmp. Tests reuse the
    same PR numbers, so under xdist one test can read another test's verdict.

    The dir comes from tmp_path_factory because some tests assert their own tmp_path is
    empty. A test that needs a set path can still patch `verdict_path` over this one."""
    d = tmp_path_factory.mktemp("fidelity-verdicts")
    monkeypatch.setattr(
        fidelity, "verdict_path", lambda pr_number: str(d / f"verdict-{pr_number}.md"))


@pytest.fixture(autouse=True)
def _private_log_dir(tmp_path_factory, monkeypatch):
    """Point post-plan-now's $LOG and .session sidecar at a per-test dir.

    bin/post-plan-now writes ${POSTPLAN_LOG_DIR:-/tmp}/post-plan-now-<slug>-<ts>.log.
    Any test that runs the real script past its sourced-return guard would
    otherwise leave a file in /tmp. Autouse here covers every module, so a new
    test that runs the script cannot forget it.

    The dir comes from tmp_path_factory, not tmp_path: tests that assert their own
    tmp_path is empty would otherwise trip over it."""
    d = tmp_path_factory.mktemp("ppn-logs")
    monkeypatch.setenv("POSTPLAN_LOG_DIR", str(d))
    return d


@pytest.fixture(autouse=True)
def _verdict_cache_off(monkeypatch):
    """Run every test on the unchanged verifier spawn path and keep it from writing into a real
    repository's git dir. test_verifier_verdict_cache.py turns the cache back on per test."""
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "0")


# Real-timeout reap witness (backlog#1801). A timeout test passes attempt(timeout); the
# witness escalates the timeout only when the child had not written its pid file yet.
PID_WITNESS_TIMEOUTS = (1, 2, 4)  # seconds, tried in order
REAP_DEADLINE = 10.0              # seconds for a SIGKILLed pid to disappear


def _read_witness_pid(pidfile) -> int | None:
    try:
        text = pidfile.read_text().strip()
    except FileNotFoundError:
        return None
    return int(text) if text else None


def _assert_reaped(pid: int, what: str, deadline_s: float) -> None:
    deadline = time.monotonic() + deadline_s
    while time.monotonic() < deadline:
        try:
            os.kill(pid, 0)
        except ProcessLookupError:
            return
        time.sleep(0.1)
    pytest.fail(f"{what} survived the process-group reap")


def run_pid_witnessed(attempt, pidfile, what, *, reap_deadline=REAP_DEADLINE) -> int:
    """Run attempt(timeout) until the child writes pidfile, then assert the pid is reaped.

    Returns the timeout that produced the pid. Fails when no timeout in
    PID_WITNESS_TIMEOUTS produced one, because then no reap was witnessed.
    """
    for timeout in PID_WITNESS_TIMEOUTS:
        pidfile.unlink(missing_ok=True)
        start = time.monotonic()
        attempt(timeout)
        elapsed = time.monotonic() - start
        assert elapsed < timeout + 14, f"timeout={timeout}s attempt took {elapsed:.1f}s"
        pid = _read_witness_pid(pidfile)
        if pid is not None:
            _assert_reaped(pid, what, reap_deadline)
            return timeout
    pytest.fail(f"{what} never wrote {pidfile.name} within {PID_WITNESS_TIMEOUTS[-1]}s; reap not witnessed")


@pytest.fixture
def pid_reap_witness():
    """The run_pid_witnessed callable, for tests that time a real subprocess hang."""
    return run_pid_witnessed
