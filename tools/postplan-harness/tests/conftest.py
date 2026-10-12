"""Shared fixtures for the harness test suite.

Opt in per module with `pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")`.
`stub_ambient_git_show` is not autouse: `test_fidelity.py` and `test_fidelity_remediation.py`
exercise the real `_git_show` through a `git` shim on PATH, and a repo-wide patch would
defeat them. `no_real_pr_review_now` is the one autouse fixture.
"""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity


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
