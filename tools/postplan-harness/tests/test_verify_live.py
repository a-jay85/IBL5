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


def test_live_real_shell_failing_phpunit_reports_fail(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch, phpunit='echo FAILURES! >&2\nexit 1')
    tracks = _by_name(LiveVerify(wt).run(Classification(has_php=True)))
    assert tracks["phpunit"].status == "fail"
    assert tracks["phpstan"].status == "pass"
    assert "FAILURES!" in tracks["phpunit"].evidence


def test_live_real_shell_failing_shellcheck_reports_fail(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch, shellcheck='echo "In a.sh line 1:"\nexit 1')
    tracks = _by_name(LiveVerify(wt).run(Classification(has_shell=True)))
    assert tracks["shellcheck"].status == "fail"
    assert "In a.sh line 1:" in tracks["shellcheck"].evidence


def test_live_real_shell_passing_stubs_report_pass(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch)
    tracks = _by_name(LiveVerify(wt).run(Classification(has_php=True, has_shell=True)))
    assert tracks["phpunit"].status == "pass"
    assert tracks["phpstan"].status == "pass"
    assert tracks["shellcheck"].status == "pass"


def test_live_evidence_keeps_last_3000_chars(tmp_path, monkeypatch):
    wt = _stub_tree(tmp_path, monkeypatch,
                    phpunit='printf "%0.sx" $(seq 1 5000)\necho TAILMARK')
    tracks = _by_name(LiveVerify(wt).run(Classification(has_php=True)))
    evidence = tracks["phpunit"].evidence
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
    barrier = threading.Barrier(3, timeout=5)

    def runner(cmd, cwd):
        if cmd.startswith((PHPUNIT, PHPSTAN, SHELLCHECK)):
            barrier.wait()
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    assert _status(tracks) == {"phpunit": "pass", "phpstan": "pass", "go": "pass",
                               "shellcheck": "pass", "e2e": "unavailable"}


def test_live_concurrent_preserves_submission_order():
    delays = {PHPUNIT: 0.3, PHPSTAN: 0.2, GO_FMT: 0.1, GO_COVER: 0.0, SHELLCHECK: 0.0}

    def runner(cmd, cwd):
        for prefix, delay in delays.items():
            if cmd.startswith(prefix):
                time.sleep(delay)
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    assert [t.name for t in tracks] == NAMES


def test_live_timeout_in_one_track_does_not_swallow_others():
    def runner(cmd, cwd):
        if cmd.startswith(PHPUNIT):
            raise subprocess.TimeoutExpired("x", 1800)
        return 0, ""

    tracks = LiveVerify("/wt", run_cmd=runner).run(_all_flags())
    by = _by_name(tracks)
    assert by["phpunit"].status == "fail"
    assert "timed out" in by["phpunit"].evidence
    assert [by[n].status for n in ("phpstan", "go", "shellcheck")] == ["pass"] * 3
    assert aggregate(tracks) == "fail"


def test_live_exception_in_one_track_does_not_swallow_others():
    def runner(cmd, cwd):
        if cmd.startswith(SHELLCHECK):
            raise OSError("boom")
        return 0, ""

    by = _by_name(LiveVerify("/wt", run_cmd=runner).run(_all_flags()))
    assert by["shellcheck"].status == "fail"
    assert by["shellcheck"].evidence.startswith("OSError")
    assert [by[n].status for n in ("phpunit", "phpstan", "go")] == ["pass"] * 3


def test_live_records_seconds_for_run_tracks_only():
    verify = LiveVerify("/wt", run_cmd=FakeRunner())
    by = _by_name(verify.run(Classification(has_php=True)))
    assert by["phpunit"].seconds is not None
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


# ---- Phase 4: shellcheck fan-out ----

def test_live_shellcheck_command_fans_out():
    fake = FakeRunner()
    LiveVerify("/wt", run_cmd=fake).run(Classification(has_shell=True))
    (cmd,) = [c for c, _ in fake.calls if c.startswith(SHELLCHECK)]
    assert "xargs -P" in cmd
    assert "-n 20" in cmd
    assert "--severity=warning --shell=bash" in cmd
    assert "--exclude=SC2034,SC1090,SC2207" in cmd


def _many_scripts_tree(tmp_path, monkeypatch, shellcheck):
    wt = _stub_tree(tmp_path, monkeypatch, shellcheck=shellcheck)
    names = [f"f{i}.sh" for i in range(59)] + ["bad.sh"]
    _script(tmp_path / "bin" / "lib" / "shell-scripts.sh",
            "printf '%s\\n' " + " ".join(names) + "\n")
    return wt


def test_live_real_shell_one_failing_batch_fails_track(tmp_path, monkeypatch):
    wt = _many_scripts_tree(
        tmp_path, monkeypatch,
        'for a in "$@"; do [ "$a" = bad.sh ] && { echo "In bad.sh line 1:"; exit 1; }; done\nexit 0')
    by = _by_name(LiveVerify(wt).run(Classification(has_shell=True)))
    assert by["shellcheck"].status == "fail"


def test_live_real_shell_all_batches_clean_passes(tmp_path, monkeypatch):
    wt = _many_scripts_tree(tmp_path, monkeypatch, "exit 0")
    by = _by_name(LiveVerify(wt).run(Classification(has_shell=True)))
    assert by["shellcheck"].status == "pass"
