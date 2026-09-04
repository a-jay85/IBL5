"""Tests for harness.adapters.probe — argv validation and fixture probe."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.probe import FixtureProbe, LiveProbe, _validate


# ---------------------------------------------------------------------------
# _validate — argv rejection table
# ---------------------------------------------------------------------------

@pytest.mark.parametrize("argv,desc", [
    (["bash", "-c", "x"], "bash interpreter"),
    (["python3", "-c", "x"], "python3 interpreter"),
    (["env", "X=1", "id"], "env process launcher"),
    (["find", ".", "-exec", "id", ";"], "find process launcher"),
    (["/bin/ls"], "absolute path in argv[0]"),
    (["bin/test-x", "../../etc"], "dotdot traversal"),
    (["pytest", "-p", "evil"], "-p flag (plugin load)"),
    (["bin/test-a"] * 9, "argv longer than 8"),
])
def test_argv_rejected(argv, desc):
    reason = _validate(argv)
    assert reason is not None, f"expected rejection for {desc}: {argv}"


def test_pytest_accepted():
    assert _validate(["pytest", "tools/postplan-harness/tests/test_probe.py", "-q"]) is None


def test_bin_check_accepted():
    assert _validate(["bin/check-docs", "--since=origin/master"]) is None


def test_bin_test_accepted():
    assert _validate(["bin/test-php", "SomeTest"]) is None


def test_grep_accepted():
    assert _validate(["grep", "-r", "pattern", "ibl5/classes/"]) is None


def test_empty_argv_rejected():
    assert _validate([]) is not None


def test_too_long_elem_rejected():
    assert _validate(["pytest", "x" * 201]) is not None


def test_disallowed_chars_rejected():
    assert _validate(["pytest", "path;injection"]) is not None


# ---------------------------------------------------------------------------
# argv rejection never invokes subprocess.run
# ---------------------------------------------------------------------------

def test_rejected_argv_never_calls_subprocess(monkeypatch):
    """Rejected argv must not reach subprocess.run."""
    import subprocess as sp
    calls = []
    monkeypatch.setattr(sp, "run", lambda *a, **kw: calls.append(a) or None)

    probe = LiveProbe()
    rejected_argvs = [
        ["bash", "-c", "x"],
        ["python3", "-c", "x"],
        ["env", "X=1", "id"],
        ["find", ".", "-exec", "id", ";"],
        ["/bin/ls"],
        ["bin/test-x", "../../etc"],
        ["pytest", "-p", "evil"],
        ["bin/test-a"] * 9,
    ]
    for argv in rejected_argvs:
        ok, reason = probe.run(argv)
        assert not ok, f"should be rejected: {argv}"
        assert "calls" not in dir(sp) or len(calls) == 0, f"subprocess.run was called for {argv}"
    assert len(calls) == 0, "subprocess.run must never be called for rejected argv"


# ---------------------------------------------------------------------------
# FixtureProbe
# ---------------------------------------------------------------------------

def test_fixture_probe_hit_true():
    p = FixtureProbe({"probes": {"bin/test-x arg": True}})
    ok, detail = p.run(["bin/test-x", "arg"])
    assert ok is True


def test_fixture_probe_hit_false():
    p = FixtureProbe({"probes": {"bin/test-x arg": False}})
    ok, detail = p.run(["bin/test-x", "arg"])
    assert ok is False


def test_fixture_probe_miss_returns_false():
    p = FixtureProbe({"probes": {}})
    ok, detail = p.run(["bin/test-x"])
    assert ok is False
    assert "no recorded probe" in detail


def test_fixture_probe_no_probes_key():
    p = FixtureProbe({})
    ok, detail = p.run(["pytest"])
    assert ok is False
