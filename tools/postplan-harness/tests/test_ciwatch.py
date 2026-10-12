import json
import os
import stat
import subprocess
import sys
import threading
import time
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import ciwatch, fidelity
from harness.state import UsageLedger
from harness.adapters.llm import FixtureLlm
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import RecordingGh

import runner


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
    assert "no checks registered after 2 tries" in out.evidence
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
    # no --fail-fast: it would stop at human-signoff on every feat: PR
    assert "--fail-fast" not in constructed[0]


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
    monkeypatch.setattr(ciwatch, "snapshot_checks", lambda w, pr, timeout=60: [])
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


# ---------------------------------------------------------------------------
# Phase 5 anti-regression — remediation_sha aliases the last round's sha.
# ---------------------------------------------------------------------------

TREE_1 = "a" * 40
TREE_2 = "b" * 40
TREE_3 = "c" * 40
TREE_4 = "d" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


class _CountingGit(ReplayGit):
    """Returns a distinct sha per commit so rounds are distinguishable."""

    def commit_all(self, message):
        super().commit_all(message)
        return f"round-sha-{len(self.commit_messages)}"


def _counting_git():
    return _CountingGit({
        "slug": "demo",
        "worktree_diff": "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": [TREE_1, TREE_2, TREE_3, TREE_4],
    })


def _plan():
    return types.SimpleNamespace(found=False, path="", auto_merge_false=False)


class _Res:
    def __init__(self):
        self.fidelity = {}


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


def test_last_round_sha_is_aliased(tmp_path, monkeypatch):
    """After 2 rounds, remediation_sha is the round-2 sha; rounds[0] has round-1.

    Row 12: ci_line must reference round 2's sha and not round 1's. The
    mutation guard (setdefault instead of assignment) pins the alias to round 1,
    making rsha != "round-sha-2" and causing round1_sha to appear in ci_line."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "checks\n\nNOT READY\n",
        "plan-fidelity-re-review-3": "READY\n",
    }
    llm = FixtureLlm(UsageLedger(), canned)
    git = _counting_git()
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff", "body", 9801, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
        rsha = res.fidelity["remediation_sha"]
        round1_sha = res.fidelity["rounds"][0]["remediation_sha"]
        assert rsha == "round-sha-2"
        assert round1_sha == "round-sha-1"
        assert res.fidelity["rounds_completed"] == 2
        # ci_line is built as: ci_line += f"; remediation commit {rsha} is inside that watch"
        # It must reference the last round's sha, not the first round's sha.
        ci_line_fragment = f"; remediation commit {rsha} is inside that watch"
        assert round1_sha not in ci_line_fragment
    finally:
        _cleanup(9801, "9801-2", "9801-3")


def test_audit_rounds_three_round_loop_emits_one_per_round(tmp_path, monkeypatch):
    """A fully-exhausted loop produces exactly 3 'phase5.5 round ' lines."""
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")

    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review-2": "NOT READY\n",
        "plan-fidelity-re-review-3": "NOT READY\n",
        "plan-fidelity-re-review-4": "NOT READY\n",
    }
    logged = []
    llm = FixtureLlm(UsageLedger(), canned)
    git = _counting_git()
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
            "diff", "body", 9802, "dead" * 10, TREE_1, False, logged.append, res,
        )
        # One outcome line per round. Each round also logs its model and work-list
        # sizes before the fixer runs, so filter on the outcome line specifically.
        round_lines = [l for l in logged if "phase5.5 round " in l and "outcome=" in l]
        assert len(round_lines) == 3
    finally:
        _cleanup(9802, "9802-2", "9802-3", "9802-4")


# ---- human-signoff is red by design, never a CI failure ---------------------

def test_probe_ignores_human_signoff(monkeypatch):
    """A red human-signoff alone must not short-circuit watch_live."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(8, "human-signoff\tfail\t5s\nbuild\tpass\t3m\n", "")
        return P(8, '[{"name":"human-signoff","state":"FAILURE","bucket":"fail"},'
                    '{"name":"build","state":"PENDING","bucket":"pending"}]', "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    assert out.exit_code == 0
    assert out.failed == []
    assert "human-signoff" in out.evidence
    assert any("--watch" in c for c in calls), "probe short-circuited on human-signoff"


def test_watch_live_reports_real_failures_beside_human_signoff(monkeypatch):
    """A real red check still fails; human-signoff is dropped from the list."""
    def fake_run(cmd, **kwargs):
        if "--watch" in cmd:
            return P(8, "human-signoff\tfail\t5s\nbuild\tfail\t3m\n", "")
        return P(8, '[{"name":"human-signoff","state":"FAILURE","bucket":"fail"}]', "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    assert out.exit_code == 8
    assert out.failed == ["build"]


def test_watch_live_probe_overrides_ignored_only_parse(monkeypatch):
    """A real red check the fail-row parse missed still wins over the ignore list."""
    def fake_run(cmd, **kwargs):
        if "--watch" in cmd:
            # tab-less output: _parse_fail_lines sees only the human-signoff row
            return P(8, "human-signoff\tfail\t5s\nbuild fail 3m\n", "")
        return P(8, '[{"name":"build","state":"FAILURE","bucket":"fail"}]', "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    # first probe call (pre-watch) must report nothing, the post-watch one must
    calls = {"n": 0}
    real_probe = ciwatch.probe_failed_checks

    def probe(w, pr, timeout=60):
        calls["n"] += 1
        return [] if calls["n"] == 1 else real_probe(w, pr, timeout)

    monkeypatch.setattr(ciwatch, "probe_failed_checks", probe)

    out = ciwatch.watch_live(".", 1)

    assert out.exit_code == 8
    assert out.failed == ["build"]


def test_background_watch_human_signoff_only_probes_before_calling_it_green(monkeypatch, tmp_path):
    """The ignored-only path still runs the probe; a real red there wins."""
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda w, pr: ["build"])
    monkeypatch.setattr(
        ciwatch.subprocess, "Popen",
        _scripted_popen([FakePopen(8, out="human-signoff\tfail\t5s\n")], []))

    bg = ciwatch.start_background_watch("/wt", 42, "fff666", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-fff666.json").read_text())
    assert data["status"] == "failure"
    assert data["failed_checks"] == ["build"]


def test_background_watch_human_signoff_only_is_success(monkeypatch, tmp_path):
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda w, pr: [])
    monkeypatch.setattr(
        ciwatch.subprocess, "Popen",
        _scripted_popen([FakePopen(8, out="human-signoff\tfail\t5s\nbuild\tpass\t3m\n")], []))

    bg = ciwatch.start_background_watch("/wt", 42, "ddd444", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-ddd444.json").read_text())
    assert data["status"] == "success"
    assert data["failed_checks"] == []


def test_background_watch_unparsed_exit_8_stays_failure(monkeypatch, tmp_path):
    """Exit 8 with no parseable fail rows is never turned into a pass."""
    monkeypatch.setattr(ciwatch, "probe_failed_checks", lambda w, pr: [])
    monkeypatch.setattr(ciwatch.subprocess, "Popen",
                        _scripted_popen([FakePopen(8, out="")], []))

    bg = ciwatch.start_background_watch("/wt", 42, "eee555", str(tmp_path),
                                        timeout=30, settle_tries=3, settle_wait=0)
    bg.done.wait(10)
    ciwatch.reap_background_watch(bg)

    data = json.loads((tmp_path / "ci-eee555.json").read_text())
    assert data["status"] == "failure"


def test_derive_from_trace_ignores_human_signoff():
    only = '[{"name":"human-signoff","state":"FAILURE"},{"name":"build","state":"SUCCESS"}]'
    both = '[{"name":"human-signoff","state":"FAILURE"},{"name":"build","state":"FAILURE"}]'
    assert ciwatch.derive_from_trace({"snapshots": [only]}).exit_code == 0
    got = ciwatch.derive_from_trace({"snapshots": [both]})
    assert got.exit_code == 8 and got.failed == ["build"]
    # unparseable FAILURE text still fails closed
    assert ciwatch.derive_from_trace({"watch_tail": '"state": "FAILURE" …'}).exit_code == 8


# --- classify_snapshot (exit-1 classification) ---

def _row(name, bucket, state=""):
    return {"name": name, "state": state, "bucket": bucket}


def test_classify_ignored_only_red_with_real_pass_is_success():
    v = ciwatch.classify_snapshot([_row("human-signoff", "fail"),
                                   _row("build", "pass"),
                                   _row("docs", "skipping")])
    assert v.kind == "success"
    assert "only ignored checks failed: human-signoff" in v.reason
    assert v.failed == []


def test_classify_real_fail_is_failure():
    v = ciwatch.classify_snapshot([_row("human-signoff", "fail"),
                                   _row("build", "fail"),
                                   _row("lint", "pass")])
    assert v.kind == "failure"
    assert v.failed == ["build"]
    # precedence: a real fail beside a pending check is still a failure
    v2 = ciwatch.classify_snapshot([_row("build", "fail"), _row("lint", "pending")])
    assert v2.kind == "failure"
    assert v2.failed == ["build"]


def test_classify_pending_is_pending():
    v = ciwatch.classify_snapshot([_row("build", "pending"), _row("lint", "pass")])
    assert v.kind == "pending"
    assert v.reason == "checks pending: build"


def test_classify_cancel_is_named_hold():
    v = ciwatch.classify_snapshot([_row("build", "cancel"), _row("lint", "pass")])
    assert v.kind == "hold"
    assert v.reason == "checks cancelled: build"


@pytest.mark.parametrize("rows", [
    [],
    [_row("human-signoff", "fail")],
    [_row("human-signoff", "pass")],
])
def test_classify_zero_checks_never_passes(rows):
    v = ciwatch.classify_snapshot(rows)
    assert v.kind == "retry"
    assert v.reason == "no checks registered"
    assert v.kind != "success"


def test_classify_unavailable_snapshot_never_passes():
    v = ciwatch.classify_snapshot(None, "")
    assert v.kind == "retry"
    assert v.reason == "gh snapshot unavailable"
    weird = ciwatch.classify_snapshot([_row("build", "weird")])
    assert weird.kind == "retry"
    assert weird.reason.startswith("gh snapshot unavailable")
    red = ciwatch.classify_snapshot(None, "build\tfail\t1m\n")
    assert red.kind == "failure"
    assert red.failed == ["build"]
    # text rows alone never yield green: an ignored-only text fail stays a retry
    assert ciwatch.classify_snapshot(None, "human-signoff\tfail\t1m\n").kind == "retry"


def test_snapshot_checks_maps_gh_exits(monkeypatch):
    def run_with(proc):
        def fake_run(cmd, **kwargs):
            if isinstance(proc, Exception):
                raise proc
            return proc
        monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
        return ciwatch.snapshot_checks(".", 1)

    assert run_with(P(1, "", "no checks reported on the 'x' branch")) == []
    assert run_with(P(1, "", "")) is None
    assert run_with(P(0, "not json{", "")) is None
    assert run_with(P(0, "[1, 2]", "")) is None
    assert run_with(OSError("gh missing")) is None
    got = run_with(P(0, '[{"name":"build","state":"SUCCESS","bucket":"pass"},'
                        '{"name":"lint","bucket":"fail"}]', ""))
    assert got == [{"name": "build", "state": "SUCCESS", "bucket": "pass"},
                   {"name": "lint", "state": "", "bucket": "fail"}]


# --- exit-1 classification in the watchers ---

_SIGNOFF_ONLY = [_row("human-signoff", "fail"), _row("build", "pass")]
_PENDING = [_row("build", "pending")]
_CANCEL = [_row("build", "cancel"), _row("lint", "pass")]


def _run_background(monkeypatch, tmp_path, sha, popens, snapshot, **kw):
    """Drive _watch_thread with scripted Popen exits and a faked snapshot."""
    constructed = []
    snap = snapshot if callable(snapshot) else (lambda w, pr, timeout=60: snapshot)
    monkeypatch.setattr(ciwatch, "snapshot_checks", snap)
    monkeypatch.setattr(ciwatch.subprocess, "Popen", _scripted_popen(popens, constructed))
    args = {"timeout": 30, "settle_tries": 3, "settle_wait": 0}
    args.update(kw)
    bg = ciwatch.start_background_watch("/wt", 42, sha, str(tmp_path), **args)
    bg.done.wait(15)
    ciwatch.reap_background_watch(bg)
    data = json.loads((tmp_path / f"ci-{sha}.json").read_text())
    return bg, data, constructed


def _watch_live_fake(monkeypatch, watch_results, json_results):
    """Fake subprocess.run for watch_live; each list's last entry repeats."""
    watch_calls = []
    watch_q, json_q = list(watch_results), list(json_results)

    def fake_run(cmd, **kwargs):
        if "--watch" in cmd:
            watch_calls.append(list(cmd))
            return watch_q.pop(0) if len(watch_q) > 1 else watch_q[0]
        return json_q.pop(0) if len(json_q) > 1 else json_q[0]

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)
    return watch_calls


def _json_rows(rows):
    return P(0, json.dumps(rows), "")


def test_background_watch_rc1_human_signoff_only_is_success(monkeypatch, tmp_path):
    out = "human-signoff\tfail\t0\nbuild\tpass\t1m\n"
    _, data, constructed = _run_background(
        monkeypatch, tmp_path, "r1a111", [FakePopen(1, out=out)], _SIGNOFF_ONLY)
    assert data["status"] == "success"
    assert data["exit_code"] == 0
    assert "only ignored checks failed: human-signoff" in data["evidence"]
    assert data["gh_stdout_tail"] == out
    assert data["bucket_snapshot"] == _SIGNOFF_ONLY
    assert len(constructed) == 1


def test_watch_live_rc1_human_signoff_only_is_success(monkeypatch):
    calls = _watch_live_fake(monkeypatch, [P(1, "human-signoff\tfail\t0\n", "")],
                             [_json_rows(_SIGNOFF_ONLY)])
    out = ciwatch.watch_live(".", 1)
    assert out.exit_code == 0
    assert "exit 1" in out.evidence
    assert len(calls) == 1


def test_background_watch_rc1_real_fail_is_failure(monkeypatch, tmp_path):
    _, data, _ = _run_background(
        monkeypatch, tmp_path, "r1b222", [FakePopen(1)],
        [_row("human-signoff", "fail"), _row("build", "fail")])
    assert data["status"] == "failure"
    assert data["exit_code"] == 8
    assert data["failed_checks"] == ["build"]


def test_watch_live_rc1_real_fail_is_exit_8(monkeypatch):
    _watch_live_fake(monkeypatch, [P(1, "", "")],
                     [_json_rows(_PENDING), _json_rows([_row("build", "fail")])])
    out = ciwatch.watch_live(".", 1)
    assert out.exit_code == 8
    assert out.failed == ["build"]
    assert "exit 1" in out.evidence


def test_background_watch_rc1_cancel_is_named_hold(monkeypatch, tmp_path):
    _, data, constructed = _run_background(
        monkeypatch, tmp_path, "r1c333", [FakePopen(1)], _CANCEL)
    assert data["status"] == "indeterminate"
    assert data["exit_code"] == -1
    assert "checks cancelled: build" in data["evidence"]
    assert len(constructed) == 1
    assert ciwatch.read_outcome(str(tmp_path), "r1c333") is None


def test_watch_live_rc1_cancel_is_named_hold(monkeypatch):
    _watch_live_fake(monkeypatch, [P(1, "", "")], [_json_rows(_CANCEL)])
    out = ciwatch.watch_live(".", 1)
    assert out.exit_code == -1
    assert "checks cancelled: build" in out.evidence


def test_background_cancel_after_head_move_reports_head_changed(monkeypatch, tmp_path):
    from harness import gitutil
    sha = "r1d444"
    monkeypatch.setattr(ciwatch.gitutil, "reconcile_remote_head",
                        lambda *a, **k: gitutil.Reconcile("match", sha, ""))
    monkeypatch.setattr(ciwatch.gitutil, "remote_head_matches",
                        lambda *a, **k: (False, "fff999" + "0" * 34))
    bg, data, _ = _run_background(
        monkeypatch, tmp_path, sha, [FakePopen(1)], _CANCEL, verify_head=True)
    assert data["status"] == "head-changed"
    assert bg.remote_sha.startswith("fff999")
    assert "checks cancelled: build" in data["evidence"]


def test_background_pending_does_not_burn_settle_budget(monkeypatch, tmp_path):
    _, data, constructed = _run_background(
        monkeypatch, tmp_path, "r1e555",
        [FakePopen(1), FakePopen(1), FakePopen(0)], _PENDING, settle_tries=1)
    assert data["status"] == "success"
    assert len(constructed) == 3


def test_watch_live_pending_does_not_burn_settle_budget(monkeypatch):
    calls = _watch_live_fake(monkeypatch, [P(1), P(1), P(0)], [_json_rows(_PENDING)])
    out = ciwatch.watch_live(".", 1, settle_tries=1)
    assert out.exit_code == 0
    assert len(calls) == 3


def test_watch_live_pending_forever_ends_in_named_reason(monkeypatch):
    _watch_live_fake(monkeypatch, [P(1)], [_json_rows(_PENDING)])
    out = ciwatch.watch_live(".", 1, timeout=0, settle_tries=10)
    assert out.exit_code == -1
    assert out.evidence.startswith("checks pending: build after 0 tries")


def test_background_pending_forever_times_out_with_named_reason(monkeypatch, tmp_path):
    _, data, _ = _run_background(
        monkeypatch, tmp_path, "r1f666", [FakePopen(1)], _PENDING,
        timeout=1, settle_wait=0.05, settle_tries=10)
    assert data["status"] == "timeout"
    assert "checks pending: build" in data["evidence"]


def test_watch_live_unparseable_snapshot_is_named_hold(monkeypatch):
    calls = _watch_live_fake(monkeypatch, [P(1, "", "")], [P(0, "not json{", "")])
    out = ciwatch.watch_live(".", 1, settle_tries=2)
    assert out.exit_code == -1
    assert "gh snapshot unavailable after 2 tries" in out.evidence
    assert len(calls) == 2


def test_background_unparseable_snapshot_is_named_hold(monkeypatch, tmp_path):
    _, data, _ = _run_background(
        monkeypatch, tmp_path, "r1g777", [FakePopen(1)], None, settle_tries=2)
    assert data["status"] == "timeout"
    assert "gh snapshot unavailable after 2 tries" in data["evidence"]
    assert data["bucket_snapshot"] is None


def test_watch_live_zero_checks_never_passes(monkeypatch):
    none_yet = P(1, "", "no checks reported on the 'x' branch")
    _watch_live_fake(monkeypatch, [P(1, "", "no checks reported")], [none_yet])
    out = ciwatch.watch_live(".", 1, settle_tries=3)
    assert out.exit_code == -1
    assert "no checks registered after 3 tries" in out.evidence


@pytest.mark.parametrize("snapshot", [[], [_row("human-signoff", "pass")]])
def test_background_zero_checks_never_passes(monkeypatch, tmp_path, snapshot):
    _, data, _ = _run_background(
        monkeypatch, tmp_path, "r1h888", [FakePopen(1)], snapshot, settle_tries=2)
    assert data["status"] == "timeout"
    assert data["exit_code"] == -1
    assert "no checks registered" in data["evidence"]
    assert ciwatch.read_outcome(str(tmp_path), "r1h888") is None


# --- capped head-move restarts ---

_A, _B, _C, _D, _E = ("a" * 40, "b" * 40, "c" * 40, "d" * 40, "e" * 40)
_NEXT = {_A: _B, _B: _C, _C: _D, _D: _E}


def _moving_head(monkeypatch, tmp_path, settle_on=(), reconcile=None,
                 seed_status="head-changed"):
    """Seed a stale watch on A and fake a head that moves A -> B -> C -> D -> E."""
    from harness import gitutil
    out = str(tmp_path / "out")
    os.makedirs(out)
    bg = ciwatch.BackgroundWatch(sha=_A, pr=42, worktree=str(tmp_path),
                                 path=ciwatch.outcome_path(out, _A),
                                 started=time.time(), verify_head=True)
    ciwatch._write_outcome(bg, seed_status, [], "seed")
    if seed_status == "head-changed":
        bg.remote_sha = _B

    calls = {"n": 0}

    def fake_reconcile(pr, expected_sha, local_sha, branch, worktree, **kw):
        n = calls["n"]
        calls["n"] += 1
        if reconcile is not None:
            return reconcile(n, expected_sha)
        if n % 2 == 0:      # entry reconcile and each start_background_watch reconcile
            return gitutil.Reconcile("match", expected_sha, "")
        return gitutil.Reconcile("synced", _NEXT[expected_sha], "")   # restart reconcile

    watched = []

    def fake_thread(bg, timeout, settle_tries, settle_wait):
        watched.append(bg.sha)
        if seed_status == "timeout":
            ciwatch._write_outcome(bg, "timeout", [], "fake timeout")
        elif bg.sha in settle_on:
            ciwatch._write_outcome(bg, "success", [], "fake green")
        else:
            bg.remote_sha = _NEXT.get(bg.sha, "")
            ciwatch._write_outcome(bg, "head-changed", [], "fake move")

    def no_live(*a, **k):
        raise AssertionError("fell through to watch_live")

    monkeypatch.setattr(ciwatch.gitutil, "reconcile_remote_head", fake_reconcile)
    monkeypatch.setattr(ciwatch, "_watch_thread", fake_thread)
    monkeypatch.setattr(ciwatch, "watch_live", no_live)
    return bg, out, watched


def _follow_head(tmp_path, out, bg):
    return ciwatch.watch_or_reuse(str(tmp_path), 42, _A, out, bg,
                                  timeout=30, verify_head=True)


def test_head_move_mid_watch_follows_new_sha_and_settles(monkeypatch, tmp_path):
    bg, out, watched = _moving_head(monkeypatch, tmp_path, settle_on=(_B,))
    got = _follow_head(tmp_path, out, bg)
    assert got.exit_code == 0
    assert got.head_sha == _B
    assert watched == [_B]
    data = json.loads((tmp_path / "out" / f"ci-{_B}.json").read_text())
    assert data["status"] == "success"


def test_head_move_at_cap_still_settles(monkeypatch, tmp_path):
    bg, out, watched = _moving_head(monkeypatch, tmp_path, settle_on=(_D,))
    got = _follow_head(tmp_path, out, bg)
    assert got.exit_code == 0
    assert got.head_sha == _D
    assert watched == [_B, _C, _D]


def test_head_move_restart_cap_reached_is_named_hold(monkeypatch, tmp_path):
    bg, out, watched = _moving_head(monkeypatch, tmp_path, settle_on=())
    got = _follow_head(tmp_path, out, bg)
    assert got.exit_code == -1
    assert got.evidence == "head moved 4 times (cap 3)"
    assert got.diverged is False
    assert watched == [_B, _C, _D]


def test_head_move_restart_diverged_returns_diverged(monkeypatch, tmp_path):
    from harness import gitutil

    def reconcile(n, expected_sha):
        if n == 0:
            return gitutil.Reconcile("match", expected_sha, "")
        if n == 1:
            return gitutil.Reconcile("synced", _B, "")
        return gitutil.Reconcile("diverged", "f" * 40, "remote head diverged")

    bg, out, watched = _moving_head(monkeypatch, tmp_path, reconcile=reconcile)
    got = _follow_head(tmp_path, out, bg)
    assert got.diverged is True
    assert got.exit_code == -1
    assert watched == []


def test_timeout_status_restarts_once_then_falls_back(monkeypatch, tmp_path):
    from harness import gitutil
    bg, out, watched = _moving_head(
        monkeypatch, tmp_path, seed_status="timeout",
        reconcile=lambda n, expected_sha: gitutil.Reconcile("match", expected_sha, ""))
    live_calls = []

    def live(*a, **k):
        live_calls.append(1)
        return ciwatch.CiOutcome(-1, [], "live")

    monkeypatch.setattr(ciwatch, "watch_live", live)
    got = _follow_head(tmp_path, out, bg)
    assert watched == [_A]
    assert len(live_calls) == 1
    assert got.exit_code == -1
