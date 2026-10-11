"""Unit tests for harness.netretry: signatures, budget, latch, landed, call_rc."""
from __future__ import annotations

import logging
import time
from types import SimpleNamespace

import pytest

from harness import netretry
from harness.state import HarnessError


class Flaky:
    def __init__(self, outcomes):
        self.outcomes = list(outcomes)
        self.calls = 0

    def __call__(self):
        self.calls += 1
        o = self.outcomes.pop(0) if len(self.outcomes) > 1 else self.outcomes[0]
        if isinstance(o, BaseException):
            raise o
        return o


def err(text, kind="gh"):
    return HarnessError(kind, f"gh pr create: {text}", output=text)


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(netretry.time, "sleep", recorded.append)
    return recorded


EOF_SAMPLES = [
    'Post "https://api.github.com/graphql": EOF',
    'Get "https://api.github.com/repos/o/r/pulls/1": EOF',
]


@pytest.mark.parametrize("text", list(netretry.TRANSIENT_MARKERS) + EOF_SAMPLES)
def test_each_transient_signature_fails_once_then_succeeds(text, sleeps):
    f = Flaky([err(text), "ok"])
    assert netretry.call(f, label="x") == "ok"
    assert f.calls == 2
    assert sleeps == [5.0]


def test_non_transient_error_raises_after_one_attempt(sleeps):
    first = err("HTTP 422: Unprocessable Entity")
    f = Flaky([first, "ok"])
    with pytest.raises(HarnessError) as ei:
        netretry.call(f, label="x")
    assert ei.value is first
    assert f.calls == 1
    assert sleeps == []


def test_bare_could_not_read_remote_is_not_retried(sleeps):
    f = Flaky([err("fatal: Could not read from remote repository."), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1
    assert sleeps == []


def test_could_not_resolve_host_is_not_retried(sleeps):
    f = Flaky([err("ssh: Could not resolve hostname github.com"), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1
    assert sleeps == []


@pytest.mark.parametrize("text", [
    "unexpected EOF", "EOF", 'Post "https://api.github.com/graphql": unexpected EOF',
])
def test_bare_eof_is_not_retried(text, sleeps):
    f = Flaky([err(text), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1
    assert sleeps == []


def test_persistent_transient_makes_four_attempts_and_reraises_original(sleeps):
    first = err("kex_exchange_identification: read: Connection reset by peer")
    f = Flaky([first])
    with pytest.raises(HarnessError) as ei:
        netretry.call(f, label="x")
    assert ei.value is first
    assert ei.value.kind == "gh"
    assert f.calls == 4
    assert sleeps == [5.0, 20.0, 60.0]


def test_harness_timeout_is_not_retried(sleeps):
    f = Flaky([HarnessError("gh", "gh pr view: exceeded 60s (Operation timed out)"), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1


def test_local_gate_kind_is_not_retried(sleeps):
    f = Flaky([HarnessError("local-gate", "hook said Connection reset by peer"), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1


def test_outage_latch_makes_next_call_single_attempt(sleeps):
    with pytest.raises(HarnessError):
        netretry.call(Flaky([err("Connection timed out")]), label="x")
    f = Flaky([err("Connection timed out"), "ok"])
    with pytest.raises(HarnessError):
        netretry.call(f, label="x")
    assert f.calls == 1
    assert sleeps == [5.0, 20.0, 60.0]


def test_outage_latch_expires_after_window(sleeps, monkeypatch):
    clock = [0.0]
    monkeypatch.setattr(netretry.time, "monotonic", lambda: clock[0])
    with pytest.raises(HarnessError):
        netretry.call(Flaky([err("Connection timed out")]), label="x")
    clock[0] = 301.0
    f = Flaky([err("Connection timed out"), "ok"])
    assert netretry.call(f, label="x") == "ok"
    assert sleeps == [5.0, 20.0, 60.0, 5.0]


def test_landed_true_returns_value_without_reattempt(sleeps):
    f = Flaky([err("Connection timed out"), "ok"])
    assert netretry.call(f, label="x", landed=lambda: (True, 77)) == 77
    assert f.calls == 1
    assert sleeps == []


def test_landed_false_then_retry_succeeds(sleeps):
    seen = []

    def landed():
        seen.append(1)
        return False, None

    f = Flaky([err("Connection timed out"), "ok"])
    assert netretry.call(f, label="x", landed=landed) == "ok"
    assert len(seen) == 1
    assert f.calls == 2


def test_landed_raising_reraises_original_error(sleeps):
    first = err("Connection timed out")

    def landed():
        raise RuntimeError("boom")

    f = Flaky([first, "ok"])
    with pytest.raises(HarnessError) as ei:
        netretry.call(f, label="x", landed=landed)
    assert ei.value is first
    assert f.calls == 1
    assert sleeps == []


def test_landed_checked_after_final_attempt(sleeps):
    answers = iter([(False, None)] * 3 + [(True, "ok")])
    f = Flaky([err("Connection timed out")])
    assert netretry.call(f, label="x", landed=lambda: next(answers)) == "ok"
    assert f.calls == 4
    assert not netretry.outage_latched()


def test_retry_log_names_label_marker_attempt_delay(sleeps, caplog):
    caplog.set_level(logging.WARNING, logger="harness.netretry")
    body = "SECRET-REQUEST-BODY"
    f = Flaky([err("TLS handshake timeout"), "ok"])
    netretry.call(lambda: (body, f())[1], label="gh pr comment")
    msgs = [r.getMessage() for r in caplog.records if r.name == "harness.netretry"]
    assert len(msgs) == 1
    for needle in ("gh pr comment", "TLS handshake timeout", "retry 1/3", "5s"):
        assert needle in msgs[0]
    assert body not in msgs[0]


def test_sleep_resolved_at_call_time(monkeypatch):
    got = []
    monkeypatch.setattr(time, "sleep", got.append)
    netretry.call(Flaky([err("Connection timed out"), "ok"]), label="x")
    assert got == [5.0]


def _rc(code, stderr=""):
    return SimpleNamespace(returncode=code, stdout="", stderr=stderr)


def test_call_rc_retries_transient_returncode_then_returns_success(sleeps):
    ok = _rc(0)
    f = Flaky([_rc(128, "kex_exchange_identification: read: Connection reset by peer"), ok])
    assert netretry.call_rc(f, label="x") is ok
    assert sleeps == [5.0]


def test_call_rc_non_transient_returns_first_result(sleeps):
    bad = _rc(128, "fatal: repository not found")
    f = Flaky([bad, _rc(0)])
    assert netretry.call_rc(f, label="x") is bad
    assert f.calls == 1
    assert sleeps == []


def test_call_rc_exhaustion_returns_last_failure_and_latches(sleeps):
    f = Flaky([_rc(128, "Connection timed out")])
    r = netretry.call_rc(f, label="x")
    assert r.returncode == 128
    assert f.calls == 4
    assert sleeps == [5.0, 20.0, 60.0]
    assert netretry.outage_latched()


@pytest.mark.parametrize("args", [
    ["repo", "view"], ["pr", "view", "1", "--json", "x"], ["pr", "list", "--head", "b"],
    ["pr", "checks", "1"], ["issue", "list"], ["run", "view", "9"], ["run", "list"],
    ["pr", "edit", "1", "--add-label", "x"], ["pr", "merge", "1", "--auto", "--squash"],
    ["api", "repos/o/r/pulls/1/comments"], ["api", "repos/o/r", "--paginate"],
])
def test_gh_retry_safe_allows(args):
    assert netretry.gh_retry_safe(args)


@pytest.mark.parametrize("args", [
    ["pr", "create"], ["pr", "comment", "1", "--body", "x"], ["issue", "create"],
    ["label", "create", "x"],
    ["api", "repos/o/r/pulls/1/reviews", "--method", "POST", "--input", "-"],
    ["api", "-XPOST", "repos/o/r"], ["api", "graphql", "-f", "query=..."],
    ["api", "repos/o/r", "-F", "a=1"], ["api", "repos/o/r", "--method=PATCH"],
])
def test_gh_retry_safe_rejects(args):
    assert not netretry.gh_retry_safe(args)
