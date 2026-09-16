import json
import os
import subprocess
import sys
import tempfile
import threading
import time
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import ciwatch


class P:
    """Minimal stand-in for subprocess.CompletedProcess."""
    def __init__(self, returncode=0, stdout="", stderr=""):
        self.returncode = returncode
        self.stdout = stdout
        self.stderr = stderr


def test_probe_short_circuits_on_failed_bucket(monkeypatch):
    """Phase 4 early return fires on a failed-bucket probe; --watch is never called."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # --json probe: one check already in "fail" bucket, one pending
        return P(
            0,
            '[{"name":"build","state":"FAILURE","bucket":"fail"},'
            '{"name":"lint","state":"PENDING","bucket":"pending"}]',
            "",
        )

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    assert out.exit_code == 8
    assert out.failed == ["build"]
    assert "build" in out.evidence
    # The whole point of Phase 4: --watch must never have been invoked
    assert not any("--watch" in c for c in calls)


def test_loop_exhausted_message_carries_rc_and_stderr(monkeypatch):
    """After settle_tries exhausted, the message includes the exit code and stderr."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(1, "", "HTTP 401: Bad credentials")
        # probe: no failures yet
        return P(0, "[]", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1, settle_tries=2)

    assert out.exit_code == -1
    assert "never settled" in out.evidence
    assert "exit 1" in out.evidence
    assert "Bad credentials" in out.evidence


def test_probe_malformed_json_does_not_short_circuit(monkeypatch):
    """A parse error in the probe is treated as [] — falls through to --watch, does not raise."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: exit 0 but garbage JSON
        return P(0, "not json{", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    # Parse error must not raise and must fall through to --watch
    assert any("--watch" in c for c in calls)
    assert out.exit_code == 0
    assert out.failed == []


def test_probe_no_checks_reported_falls_through(monkeypatch):
    """gh exit 1 (no checks reported yet) from the probe returns [] and watch_live reaches --watch."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: gh exits 1 — "no checks reported" right after pr create
        return P(1, "", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    assert ciwatch.probe_failed_checks(".", 1) == []

    calls.clear()
    ciwatch.watch_live(".", 1)
    assert any("--watch" in c for c in calls)


def test_probe_never_reports_green(monkeypatch):
    """The probe is a failure-only short-circuit; all-pass buckets fall through to --watch."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: all checks in the "pass" bucket
        return P(0, '[{"name":"build","state":"SUCCESS","bucket":"pass"}]', "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    assert ciwatch.probe_failed_checks(".", 1) == []

    calls.clear()
    ciwatch.watch_live(".", 1)
    # --watch must have been called — the probe must not short-circuit to "green"
    assert any("--watch" in c for c in calls)


# ---------------------------------------------------------------------------
# Background CI watch (Phase 2 -> Phase 7 reuse). See plan phase 6.1/6.2.
# ---------------------------------------------------------------------------


class FakePopen:
    """Minimal subprocess.Popen stand-in: scripted (rc, stdout) per construction."""
    def __init__(self, rc, out="", err="", hang=False):
        self.returncode, self._out, self._err, self._hang = rc, out, err, hang
        self.terminated = self.killed = False

    def communicate(self, timeout=None):
        if self._hang:
            raise subprocess.TimeoutExpired(cmd="gh", timeout=timeout or 0)
        return self._out, self._err

    def poll(self):
        return self.returncode

    def terminate(self):
        self.terminated = True

    def kill(self):
        self.killed = True

    def wait(self, timeout=None):
        return self.returncode


class _KillablePopen(FakePopen):
    """FakePopen that stops hanging once killed — a real Popen's communicate()
    returns after the kill, so a second raise would be an artifact of the fake."""
    def kill(self):
        super().kill()
        self._hang = False


class _BlockingPopen(FakePopen):
    """communicate() blocks — models a watch still genuinely in flight, so the
    reaper's own finalization (not the thread's) is what writes the outcome."""
    def __init__(self):
        super().__init__(0)
        self.release = threading.Event()

    def communicate(self, timeout=None):
        self.release.wait(5)
        return "", ""


def _scripted_popen(procs, constructed):
    """Hand back `procs` in order (the last one repeats), recording each argv."""
    remaining = list(procs)

    def factory(cmd, **kwargs):
        constructed.append(list(cmd))
        return remaining.pop(0) if len(remaining) > 1 else remaining[0]

    return factory


def _seed(tmp_path, sha, status="success", failed=None, **extra):
    """Write a ci-<sha>.json the way _write_outcome would have."""
    payload = {
        "sha": sha, "pr": 1, "status": status,
        "exit_code": 0 if status == "success" else 8,
        "failed_checks": failed or [], "probe": [],
        "evidence": "seeded", "started": 0.0, "finished": 1.0,
    }
    payload.update(extra)
    path = tmp_path / f"ci-{sha}.json"
    path.write_text(json.dumps(payload, indent=2, sort_keys=True))
    return path


def test_background_watch_success_writes_outcome_file(monkeypatch, tmp_path):
    """rc=0 from the background gh call lands as status "success" on disk."""
    constructed = []
    monkeypatch.setattr(ciwatch.subprocess, "Popen",
                        _scripted_popen([FakePopen(0)], constructed))

    bg = ciwatch.start_background_watch("/wt", 42, "aaa111", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    assert bg is not None
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-aaa111.json").read_text())
    assert data["status"] == "success"
    assert data["sha"] == "aaa111"
    assert data["failed_checks"] == []
    assert data["exit_code"] == 0
    # --fail-fast belongs to the background invocation only
    assert "--fail-fast" in constructed[0]


def test_background_watch_failure_records_failed_checks_and_probe(monkeypatch, tmp_path):
    """rc=8 records both the --watch fail rows and the post-exit probe names."""
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda w, pr: ["lint"])
    monkeypatch.setattr(
        ciwatch.subprocess, "Popen",
        _scripted_popen([FakePopen(8, out="build\tfail\t1m\nunit\tpass\t2m\n")], []))

    bg = ciwatch.start_background_watch("/wt", 42, "bbb222", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-bbb222.json").read_text())
    assert data["status"] == "failure"
    assert data["exit_code"] == 8
    assert data["failed_checks"] == ["build", "lint"]
    assert data["probe"] == ["lint"]


def test_background_watch_retries_when_checks_not_reported(monkeypatch, tmp_path):
    """gh exit 1 right after pr create is a retry, never a verdict."""
    constructed = []
    monkeypatch.setattr(
        ciwatch.subprocess, "Popen",
        _scripted_popen([FakePopen(1, err="no checks reported"), FakePopen(0)],
                        constructed))

    bg = ciwatch.start_background_watch("/wt", 42, "ccc333", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-ccc333.json").read_text())
    assert data["status"] == "success"
    assert len(constructed) == 2, "the settle-retry loop ran only once"


def test_background_watch_timeout_writes_timeout_status(monkeypatch, tmp_path):
    """A gh call that never returns is killed and recorded as "timeout"."""
    proc = _KillablePopen(0, hang=True)
    monkeypatch.setattr(ciwatch.subprocess, "Popen", _scripted_popen([proc], []))

    bg = ciwatch.start_background_watch("/wt", 42, "ddd444", str(tmp_path),
                                        timeout=1, settle_tries=2, settle_wait=0)
    bg.done.wait(15)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-ddd444.json").read_text())
    assert data["status"] == "timeout"
    assert proc.killed


def test_reap_writes_timeout_when_thread_never_finalized(monkeypatch, tmp_path):
    """Teardown guarantees an outcome file even when CI never settled."""
    monkeypatch.setattr(ciwatch.subprocess, "Popen",
                        _scripted_popen([_BlockingPopen()], []))

    bg = ciwatch.start_background_watch("/wt", 7, "eee555", str(tmp_path),
                                        timeout=300, settle_tries=3, settle_wait=0)
    ciwatch.reap_background_watch(bg, join_timeout=0.2)

    data = json.loads((tmp_path / "ci-eee555.json").read_text())
    assert data["status"] == "timeout"


def test_write_outcome_is_first_writer_wins(tmp_path):
    """The first status recorded is the one Phase 7 reads; later writes are ignored."""
    bg = ciwatch.BackgroundWatch(sha="fff666", pr=1, worktree="/wt",
                                 path=str(tmp_path / "ci-fff666.json"),
                                 started=time.time())
    ciwatch._write_outcome(bg, "success", [], "first")
    ciwatch._write_outcome(bg, "failure", ["build"], "second")

    data = json.loads((tmp_path / "ci-fff666.json").read_text())
    assert data["status"] == "success"
    assert data["evidence"] == "first"
    assert data["failed_checks"] == []


def test_start_background_watch_declines_without_pr_or_sha(tmp_path):
    """Nothing to key on => no thread, no file — never a ci-None.json."""
    assert ciwatch.start_background_watch("/wt", None, "aaa111", str(tmp_path)) is None
    assert ciwatch.start_background_watch("/wt", 42, None, str(tmp_path)) is None
    assert ciwatch.start_background_watch("/wt", 42, "aaa111", "") is None
    assert list(tmp_path.iterdir()) == []


def test_read_outcome_rejects_sha_mismatch(tmp_path):
    """A result for another head is a miss, never a verdict for this one."""
    _seed(tmp_path, "aaa111")
    assert ciwatch.read_outcome(str(tmp_path), "bbb222") is None
    assert ciwatch.read_outcome(str(tmp_path), "aaa111") is not None


def test_read_outcome_rejects_non_terminal_status(tmp_path):
    """timeout/indeterminate are not reusable Phase 7 verdicts."""
    _seed(tmp_path, "ggg777", status="timeout")
    _seed(tmp_path, "hhh888", status="indeterminate")
    assert ciwatch.read_outcome(str(tmp_path), "ggg777") is None
    assert ciwatch.read_outcome(str(tmp_path), "hhh888") is None


def test_read_outcome_rejects_corrupt_json(tmp_path):
    """A half-written or garbage file is a miss, not an exception."""
    (tmp_path / "ci-iii999.json").write_text("not json{")
    assert ciwatch.read_outcome(str(tmp_path), "iii999") is None
    assert ciwatch.read_outcome(str(tmp_path), "never-written") is None


def test_watch_or_reuse_uses_file_and_does_not_call_watch_live(monkeypatch, tmp_path):
    """A terminal file for this exact SHA short-circuits the live watch entirely."""
    _seed(tmp_path, "aaa111")

    def boom(*a, **k):
        raise AssertionError("watch_live ran despite a terminal outcome on disk")

    monkeypatch.setattr(ciwatch, "watch_live", boom)

    out = ciwatch.watch_or_reuse("/wt", 42, "aaa111", str(tmp_path))
    assert out.exit_code == 0
    assert "reused background CI watch" in out.evidence


def test_watch_or_reuse_falls_back_when_sha_moved(monkeypatch, tmp_path):
    """A fix commit moves the head => the stale file is a miss, and CI is watched live."""
    _seed(tmp_path, "aaa111")
    calls = []

    def rec(w, pr, timeout=5400, settle_tries=10, settle_wait=30):
        calls.append(pr)
        return ciwatch.CiOutcome(0, [], "live")

    monkeypatch.setattr(ciwatch, "watch_live", rec)

    out = ciwatch.watch_or_reuse("/wt", 42, "bbb222", str(tmp_path))
    assert calls == [42]
    assert out.evidence == "live"


def test_watch_or_reuse_failure_with_empty_list_still_reports_exit_8(tmp_path):
    """A red CI with no parsed check names still reports red."""
    _seed(tmp_path, "jjj000", status="failure", failed=[])
    out = ciwatch.watch_or_reuse("/wt", 42, "jjj000", str(tmp_path))
    assert out.exit_code == 8
    assert out.failed == ["(unnamed failing check)"]


def test_watch_or_reuse_deducts_elapsed_from_fallback_timeout(monkeypatch, tmp_path):
    """Time already spent waiting on the background watch comes out of the
    fallback's budget, so the Phase 7 ceiling stays 5400s end to end."""
    clock = iter([1000.0, 1030.0])
    monkeypatch.setattr(ciwatch, "time",
                        types.SimpleNamespace(time=lambda: next(clock, 1030.0),
                                              sleep=lambda s: None))
    seen = {}

    def rec(w, pr, timeout=5400, settle_tries=10, settle_wait=30):
        seen["timeout"] = timeout
        return ciwatch.CiOutcome(0, [], "live")

    monkeypatch.setattr(ciwatch, "watch_live", rec)

    ciwatch.watch_or_reuse("/wt", 42, "kkk111", str(tmp_path), None, timeout=120)
    assert seen["timeout"] == 90


def test_watch_live_command_is_unchanged(monkeypatch):
    """watch_live's argv is byte-identical to pre-change behaviour: no --fail-fast."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        return P(0, "", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    ciwatch.watch_live("/wt", 42)

    watch = [c for c in calls if "--watch" in c]
    assert watch == [["gh", "pr", "checks", "42", "--watch"]]
