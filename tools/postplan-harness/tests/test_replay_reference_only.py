"""Bench script replay_reference_only: loud SKIP paths and flag validation."""
import importlib.util
import os

import pytest

_P = os.path.join(os.path.dirname(__file__), "..", "bench", "replay_reference_only.py")
_spec = importlib.util.spec_from_file_location("replay_reference_only", _P)
replay = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(replay)


def test_replay_reference_only_skips_loudly_without_plan(tmp_path, capsys):
    rc = replay.main(["--plan", str(tmp_path / "nope.md")])
    assert rc == 2
    assert "SKIP: plan file not found" in capsys.readouterr().out


def test_replay_reference_only_skips_on_unknown_head(tmp_path, capsys):
    plan = tmp_path / "p.md"
    plan.write_text("# Plan: x\n")
    rc = replay.main(["--plan", str(plan),
                      "--range", "origin/master...0000000000000000000000000000000000000000"])
    assert rc == 2
    assert "SKIP: head" in capsys.readouterr().out


def test_replay_reference_only_rejects_unknown_flag():
    with pytest.raises(SystemExit) as exc:
        replay.main(["--bogus"])
    assert exc.value.code == 2
