import os
import subprocess
import sys
import threading
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.verify import (LiveVerify, TrackResult, aggregate, fail_log_lines,
                                     timing_log_line, tracks_log_line)
from harness.state import Classification

# LiveVerify only; the ReplayVerify pin lives in test_verify_shellcheck.py and must stay untouched.
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
    assert [t.status for t in tracks] == ["ci", "pass", "pass", "ci", "unavailable"]
    cwd_by_prefix = {}
    for cmd, cwd in fake.calls:
        for prefix in (PHPUNIT, PHPSTAN, GO_FMT, GO_COVER, SHELLCHECK):
            if cmd.startswith(prefix):
                cwd_by_prefix[prefix] = cwd
    assert PHPUNIT not in cwd_by_prefix
    assert SHELLCHECK not in cwd_by_prefix
    assert cwd_by_prefix[PHPSTAN] == "/wt/ibl5"
    assert cwd_by_prefix[GO_FMT] == "/wt"
    assert cwd_by_prefix[GO_COVER] == "/wt"


def test_live_nonzero_rc_fails_only_that_track():
    fake = FakeRunner({PHPSTAN: (2, "boom")})
    tracks = LiveVerify("/wt", run_cmd=fake).run(_all_flags())
    assert _status(tracks) == {"phpunit": "ci", "phpstan": "fail", "go": "pass",
                               "shellcheck": "ci", "e2e": "unavailable"}


@pytest.mark.parametrize("failing", [GO_FMT, GO_COVER])
def test_live_go_fails_when_either_make_step_fails(failing):
    fake = FakeRunner({failing: (1, "bad")})
    tracks = LiveVerify("/wt", run_cmd=fake).run(_all_flags())
    assert _status(tracks)["go"] == "fail"
    assert _status(tracks)["phpunit"] == "ci"


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


# ---- Phase 2: real-shell stubs (prove the pipeline, which the fake runner cannot) ----

def _script(path, body):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text("#!/usr/bin/env bash\n" + body)
    path.chmod(0o755)


def _stub_tree(tmp_path, monkeypatch, *, phpunit="exit 0", shellcheck="exit 0", composer="exit 0"):
    stubdir = tmp_path / "stubs"
    _script(tmp_path / "ibl5" / "vendor" / "bin" / "phpunit", phpunit + "\n")
    _script(stubdir / "composer", composer + "\n")
    _script(stubdir / "shellcheck", shellcheck + "\n")
    _script(tmp_path / "bin" / "lib" / "shell-scripts.sh", "echo a.sh\n")
    monkeypatch.setenv("PATH", f"{stubdir}:{os.environ['PATH']}")
    return str(tmp_path)


def _by_name(tracks):
    return {t.name: t for t in tracks}


def test_live_php_diff_never_runs_phpunit(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch, phpunit='touch "$0.ran"\nexit 1')
    raw = LiveVerify(wt).run(Classification(has_php=True))
    tracks = _by_name(raw)
    assert tracks["phpunit"].status == "ci"
    assert "covered by CI" in tracks["phpunit"].evidence
    assert not (tmp_path / "ibl5" / "vendor" / "bin" / "phpunit.ran").exists()
    assert tracks["phpstan"].status == "pass"
    assert aggregate(list(raw)) == "pass"


def test_live_php_diff_status_follows_phpstan(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch, composer='echo "PHPStan error" >&2\nexit 1')
    raw = LiveVerify(wt).run(Classification(has_php=True))
    tracks = _by_name(raw)
    assert tracks["phpstan"].status == "fail"
    assert "PHPStan error" in tracks["phpstan"].evidence
    assert aggregate(list(raw)) == "fail"


def test_live_shell_diff_never_runs_shellcheck(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch, shellcheck='touch "$0.ran"\nexit 1')
    raw = LiveVerify(wt).run(Classification(has_shell=True))
    tracks = _by_name(raw)
    assert tracks["shellcheck"].status == "ci"
    assert "covered by CI" in tracks["shellcheck"].evidence
    assert not (tmp_path / "stubs" / "shellcheck.ran").exists()
    assert [tracks[n].status for n in ("phpunit", "phpstan", "go")] == ["skipped"] * 3
    assert aggregate(list(raw)) == "skipped"


def test_aggregate_ignores_ci_status():
    assert aggregate([TrackResult("phpunit", "ci"), TrackResult("phpstan", "pass")]) == "pass"
    assert aggregate([TrackResult("phpunit", "ci"), TrackResult("phpstan", "fail")]) == "fail"
    assert aggregate([TrackResult("shellcheck", "ci")]) == "skipped"


def test_live_ci_status_only_for_classified_tracks():
    fake = FakeRunner()
    by = _by_name(LiveVerify("/wt", run_cmd=fake).run(Classification(has_go=True)))
    assert by["phpunit"].status == "skipped"
    assert by["shellcheck"].status == "skipped"
    assert by["go"].status == "pass"


def test_live_real_shell_passing_stubs_report_pass(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch)
    tracks = _by_name(LiveVerify(wt).run(Classification(has_php=True, has_shell=True)))
    assert tracks["phpunit"].status == "ci"
    assert tracks["phpstan"].status == "pass"
    assert tracks["shellcheck"].status == "ci"


def test_live_evidence_keeps_last_3000_chars(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch,
                    composer='printf "%0.sx" $(seq 1 5000)\necho TAILMARK')
    tracks = _by_name(LiveVerify(wt).run(Classification(has_php=True)))
    evidence = tracks["phpstan"].evidence
    # Guards the `[-3000:]` slice in `_sh`: a head slice (`[:3000]`) loses TAILMARK.
    assert len(evidence) <= 3000
    assert evidence.rstrip().endswith("TAILMARK")


def test_fail_log_lines_names_each_failed_track():
    tracks = [
        TrackResult("phpunit", "fail", "x\nFAILURES!\n\n"),
        TrackResult("phpstan", "pass", "ok"),
        TrackResult("go", "fail", ""),
    ]
    assert fail_log_lines(tracks) == [
        "phase5 FAIL phpunit: FAILURES!",
        "phase5 FAIL go: (no output)",
    ]


def test_fail_log_lines_empty_when_nothing_failed():
    tracks = [
        TrackResult("phpunit", "pass", "boom FAILURES!"),
        TrackResult("go", "skipped"),
        TrackResult("e2e", "unavailable", "isolated mode"),
    ]
    assert fail_log_lines(tracks) == []


# ---- Phase 3: concurrency ----

def test_live_tracks_overlap_in_time():
    # phpstan and go are the tracks that still run; each blocks until the other arrives.
    barrier = threading.Barrier(2, timeout=5)

    def runner(cmd, cwd):
        if cmd.startswith((PHPSTAN, GO_FMT)):
            barrier.wait()
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    assert _status(tracks) == {"phpunit": "ci", "phpstan": "pass", "go": "pass",
                               "shellcheck": "ci", "e2e": "unavailable"}


def test_live_concurrent_preserves_submission_order():
    delays = {PHPUNIT: 0.3, PHPSTAN: 0.2, GO_FMT: 0.1, GO_COVER: 0.0, SHELLCHECK: 0.0}

    def runner(cmd, cwd):
        for prefix, delay in delays.items():
            if cmd.startswith(prefix):
                time.sleep(delay)
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    # The slowest track is submitted first, so reading futures with `as_completed`
    # would return shellcheck first and break this order.
    assert [t.name for t in tracks] == NAMES


def test_live_timeout_in_one_track_does_not_swallow_others():
    def runner(cmd, cwd):
        if cmd.startswith(PHPSTAN):
            raise subprocess.TimeoutExpired("x", 1800)
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    by = _by_name(tracks)
    assert by["phpstan"].status == "fail"
    assert "timed out" in by["phpstan"].evidence
    assert by["go"].status == "pass"
    assert [by[n].status for n in ("phpunit", "shellcheck")] == ["ci"] * 2
    assert aggregate(tracks) == "fail"


def test_live_exception_in_one_track_does_not_swallow_others():
    def runner(cmd, cwd):
        if cmd.startswith(GO_FMT):
            raise OSError("boom")
        return 0, ""

    by = _by_name(LiveVerify("/wt", run_cmd=runner).run(_all_flags()))
    assert by["go"].status == "fail"
    assert by["go"].evidence.startswith("OSError")
    assert by["phpstan"].status == "pass"
    assert [by[n].status for n in ("phpunit", "shellcheck")] == ["ci"] * 2


def test_live_records_seconds_for_run_tracks_only():
    verify = LiveVerify("/wt", run_cmd=FakeRunner())
    by = _by_name(verify.run(Classification(has_php=True)))
    assert by["phpunit"].seconds is None
    assert by["phpstan"].seconds is not None
    assert by["go"].seconds is None
    assert by["shellcheck"].seconds is None
    assert by["e2e"].seconds is None
    assert isinstance(verify.last_wall_seconds, float)

    idle = LiveVerify("/wt", run_cmd=FakeRunner())
    idle.run(Classification())
    assert idle.last_wall_seconds is None


def test_timing_log_line_only_when_a_track_ran():
    assert timing_log_line([TrackResult("phpunit", "skipped")], 1.0) is None
    assert timing_log_line([TrackResult("phpunit", "pass", seconds=1.0)], None) is None
    ran = [TrackResult("phpunit", "pass", seconds=12.34), TrackResult("go", "skipped")]
    assert timing_log_line(ran, 12.5) == "phase5 timing: phpunit=12.3s wall=12.5s"


# ---- Shell-only diffs: shellcheck is CI's job; Phase 5 runs nothing ----

def test_live_shell_only_diff_runs_no_command():
    fake = FakeRunner()
    verify = LiveVerify("/wt", run_cmd=fake)
    by = _by_name(verify.run(Classification(has_shell=True)))
    assert fake.calls == []
    assert by["shellcheck"].status == "ci"
    # An empty job set skips the executor (max_workers=0 would raise).
    assert verify.last_wall_seconds is None
