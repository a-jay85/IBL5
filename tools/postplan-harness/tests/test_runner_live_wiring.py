import contextlib
import os
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner  # noqa: E402


class _Stop(BaseException):
    """Raised from the fake constructor so run() halts right after the call under test.
    BaseException so no `except Exception` inside run() can swallow it."""


def _capture_live_git(captured: dict):
    class _FakeLiveGit:
        def __init__(self, worktree, push_remote=None, llm=None):
            captured.update(worktree=worktree, push_remote=push_remote, llm=llm)
            raise _Stop()
    return _FakeLiveGit


def _fake_llm():
    # run() touches only llm.ledger before it reaches the LiveGit construction site.
    return types.SimpleNamespace(ledger=[], call=lambda *a, **k: None)


def test_live_git_receives_run_llm(tmp_path, monkeypatch):
    """The production construction site must hand run()'s llm to LiveGit by keyword.
    Mutation caught: drop `llm=llm` at the construction site -> captured['llm'] is None."""
    captured: dict = {}
    monkeypatch.setattr(runner, "LiveGit", _capture_live_git(captured))
    llm = _fake_llm()
    with pytest.raises(_Stop):
        runner.run(None, str(tmp_path / "out"), llm, mode="live", live=False,
                   worktree=str(tmp_path))
    assert captured, "run() never reached the LiveGit construction site"
    assert captured["llm"] is llm


def test_live_git_llm_is_passed_by_keyword_not_positionally(tmp_path, monkeypatch):
    """Boundary: llm must not shift into the push_remote slot.
    Mutation caught: `LiveGit(worktree, llm)` positional -> push_remote is the llm object."""
    captured: dict = {}
    monkeypatch.setattr(runner, "LiveGit", _capture_live_git(captured))
    llm = _fake_llm()
    with pytest.raises(_Stop):
        runner.run(None, str(tmp_path / "out"), llm, mode="live", live=False,
                   worktree=str(tmp_path))
    assert captured["push_remote"] is None
    assert captured["llm"] is llm


def test_replay_path_never_constructs_live_git(tmp_path, monkeypatch):
    """Negative path: mode='replay' must not touch LiveGit at all, so the wiring change
    cannot leak into fixture-driven runs. Mutation caught: moving the LiveGit call above
    the `if mode == 'replay'` branch -> _Stop escapes here."""
    captured: dict = {}
    monkeypatch.setattr(runner, "LiveGit", _capture_live_git(captured))
    fixture = {"slug": "wiring-test", "plan_content": "# plan\n"}
    with contextlib.suppress(Exception):
        runner.run(fixture, str(tmp_path / "out"), _fake_llm(), mode="replay")
    assert captured == {}
