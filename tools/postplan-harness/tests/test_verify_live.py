import os
import sys
import threading

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.verify import LiveVerify, TrackResult, tracks_log_line
from harness.state import Classification

PHPUNIT = "vendor/bin/phpunit --no-progress"
PHPSTAN = "composer run analyse -- --no-progress"
GO_FMT = "make -C engine fmt-check"
GO_COVER = "make -C engine cover"
SHELLCHECK = "bin/lib/shell-scripts.sh --full"

NAMES = ["phpunit", "phpstan", "go", "shellcheck", "e2e"]


class FakeRunner:
    """Records (cmd, cwd) under a lock and answers by command prefix."""

    def __init__(self, results=None):
        self.results = results or {}
        self.calls = []
        self._lock = threading.Lock()

    def __call__(self, cmd, cwd):
        with self._lock:
            self.calls.append((cmd, cwd))
        for prefix, answer in self.results.items():
            if cmd.startswith(prefix):
                return answer
        return 0, ""


def _all_flags():
    return Classification(has_php=True, has_go=True, has_shell=True)


def _status(tracks):
    return {t.name: t.status for t in tracks}


def test_live_no_flags_skips_all_and_runs_nothing():
    fake = FakeRunner()
    tracks = LiveVerify("/wt", run_cmd=fake).run(Classification())
    assert [t.name for t in tracks] == NAMES
    assert [t.status for t in tracks] == ["skipped", "skipped", "skipped", "skipped", "unavailable"]
    assert "isolated mode" in tracks[-1].evidence
    assert fake.calls == []


def test_live_all_tracks_pass_in_fixed_order():
    fake = FakeRunner()
    tracks = LiveVerify("/wt", run_cmd=fake).run(_all_flags())
    assert [t.name for t in tracks] == NAMES
    assert [t.status for t in tracks] == ["pass", "pass", "pass", "pass", "unavailable"]
    cwd_by_prefix = {}
    for cmd, cwd in fake.calls:
        for prefix in (PHPUNIT, PHPSTAN, GO_FMT, GO_COVER, SHELLCHECK):
            if cmd.startswith(prefix):
                cwd_by_prefix[prefix] = cwd
    assert cwd_by_prefix[PHPUNIT] == "/wt/ibl5"
    assert cwd_by_prefix[PHPSTAN] == "/wt/ibl5"
    assert cwd_by_prefix[GO_FMT] == "/wt"
    assert cwd_by_prefix[GO_COVER] == "/wt"
    assert cwd_by_prefix[SHELLCHECK] == "/wt"


def test_live_nonzero_rc_fails_only_that_track():
    fake = FakeRunner({PHPSTAN: (2, "boom")})
    tracks = LiveVerify("/wt", run_cmd=fake).run(_all_flags())
    assert _status(tracks) == {"phpunit": "pass", "phpstan": "fail", "go": "pass",
                               "shellcheck": "pass", "e2e": "unavailable"}


@pytest.mark.parametrize("failing", [GO_FMT, GO_COVER])
def test_live_go_fails_when_either_make_step_fails(failing):
    fake = FakeRunner({failing: (1, "bad")})
    tracks = LiveVerify("/wt", run_cmd=fake).run(_all_flags())
    assert _status(tracks)["go"] == "fail"
    assert _status(tracks)["phpunit"] == "pass"


def test_tracks_log_line_byte_identical():
    tracks = [
        TrackResult("phpunit", "pass"),
        TrackResult("phpstan", "fail"),
        TrackResult("go", "skipped"),
        TrackResult("shellcheck", "skipped"),
        TrackResult("e2e", "unavailable"),
    ]
    assert tracks_log_line(tracks, "fail") == (
        "phase5 tracks: phpunit=pass, phpstan=fail, go=skipped, shellcheck=skipped, "
        "e2e=unavailable -> PHASE5_VERIFY_STATUS=fail (fidelity degraded: ['e2e'] unavailable)"
    )
    clean = [TrackResult("phpunit", "pass"), TrackResult("go", "skipped")]
    assert tracks_log_line(clean, "pass") == (
        "phase5 tracks: phpunit=pass, go=skipped -> PHASE5_VERIFY_STATUS=pass"
    )
