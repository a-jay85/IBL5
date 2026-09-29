"""Tests for build_packet phase4b_ran and review_findings params (fidelity.py Phase 2)."""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity

TREE = "a" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


def _plan():
    return types.SimpleNamespace(found=False, path="")


def _packet_context(tmp_path, **kwargs):
    out = tmp_path / "out"
    out.mkdir(exist_ok=True)
    packet = fidelity.build_packet(
        str(out), "deadbeef", TREE, _plan(),
        "diff", "body", 42, **kwargs,
        worktree=str(tmp_path),
    )
    return open(os.path.join(packet, "context.md")).read()


def test_build_packet_phase4b_ran_true_writes_yes(tmp_path, git_shim):
    ctx = _packet_context(tmp_path, phase4b_ran=True)
    assert "PHASE_4B_RAN: yes" in ctx


def test_build_packet_phase4b_ran_false_writes_no(tmp_path, git_shim):
    ctx = _packet_context(tmp_path, phase4b_ran=False)
    assert "PHASE_4B_RAN: no" in ctx


def test_build_packet_review_findings_written_to_context(tmp_path, git_shim):
    ctx = _packet_context(tmp_path, phase4b_ran=True,
                          review_findings="- foo.py:10 (score 80) -- bad call")
    assert "PHASE_4B_FINDINGS:" in ctx
    assert "- foo.py:10 (score 80) -- bad call" in ctx


def test_build_packet_empty_findings_omits_block(tmp_path, git_shim):
    ctx = _packet_context(tmp_path, phase4b_ran=False, review_findings="")
    assert "PHASE_4B_FINDINGS:" not in ctx
