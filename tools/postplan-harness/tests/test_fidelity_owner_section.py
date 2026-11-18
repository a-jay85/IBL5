"""The fidelity reviewer prompt carries the captured owner request when there is one."""
from __future__ import annotations

import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.adapters.gitad import ReplayGit

SECTION = "## Owner request\n\nMessage at t:\n\n```text\nplease also add a json flag\n```"

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


class _Stop(Exception):
    pass


class _RecordingLlm:
    def __init__(self):
        self.prompts: list[str] = []

    def call_tooled(self, purpose, model, prompt, **kw):
        self.prompts.append(prompt)
        raise _Stop(purpose)


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")


def _review(tmp_path, **kw):
    llm = _RecordingLlm()
    packet = str(tmp_path / "packet")
    with pytest.raises(_Stop):
        fidelity.review(llm, str(tmp_path), str(tmp_path), packet, 41, **kw)
    return llm.prompts, packet


def test_review_prompt_appends_owner_section(tmp_path):
    prompts, packet = _review(tmp_path, owner_section=SECTION)
    assert len(prompts) == 1
    assert prompts[0].endswith("\n\n" + SECTION)
    assert prompts[0].startswith(fidelity._pointer_prompt(packet, 41))


def test_review_prompt_without_owner_section_unchanged(tmp_path):
    prompts, packet = _review(tmp_path)
    assert prompts == [fidelity._pointer_prompt(packet, 41)]
    prompts, packet = _review(tmp_path, owner_section="")
    assert prompts == [fidelity._pointer_prompt(packet, 41)]


def test_re_review_prompt_appends_owner_section(tmp_path, git_shim):
    llm = _RecordingLlm()
    git = ReplayGit({"slug": "demo", "worktree_diff": "", "diff": "diff --git a/x b/x\n",
                     "head_trees": ["a" * 40, "b" * 40]})
    verdict1 = tmp_path / "verdict.md"
    verdict1.write_text("NOT READY\n\n- [BLOCKING] finding one\n", encoding="utf-8")
    plan = types.SimpleNamespace(found=False, path="", auto_merge_false=False)
    with pytest.raises(_Stop):
        fidelity.re_review(llm, git, str(tmp_path), str(tmp_path), plan, "deadbeef", "body",
                           42, "sha123", str(verdict1), owner_section=SECTION)
    assert len(llm.prompts) == 1
    assert llm.prompts[0].endswith("\n\n" + SECTION)
    assert "PR #42" in llm.prompts[0]
