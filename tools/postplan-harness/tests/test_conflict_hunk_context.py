"""build_hunk_context: bounded per-hunk artifact built from the three merge stages."""
from __future__ import annotations

import os
import re
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.conflict
from harness.conflict import HUNK_MAX_COUNT, HunkContext, build_hunk_context


def _lines(n: int = 120) -> list[str]:
    return [f"line {i}\n" for i in range(n)]


def _stages(tmp_path, base: str, ours: str, theirs: str):
    out = {}
    for stage, name, text in ((1, "base", base), (2, "ours", ours), (3, "theirs", theirs)):
        d = tmp_path / "stages" / name
        d.mkdir(parents=True, exist_ok=True)
        f = d / "app__foo.py"
        f.write_text(text)
        out[stage] = f
    return out


def _three_hunk_texts():
    base = _lines()
    ours, theirs = list(base), list(base)
    for i in (20, 60, 100):
        ours[i] = f"OURS {i}\n"
        theirs[i] = f"THEIRS {i}\n"
    return "".join(base), "".join(ours), "".join(theirs)


def test_hunk_context_three_small_hunks(tmp_path):
    stages = _stages(tmp_path, *_three_hunk_texts())
    result = build_hunk_context("app/foo.py", stages)
    assert isinstance(result, HunkContext)
    assert result.hunk_count == 3
    assert result.path.parent == tmp_path / "stages" / "hunks"
    text = result.path.read_text()
    assert "## Hunk 3 of 3" in text
    for i in (20, 60, 100):
        assert f"OURS {i}" in text
        assert f"THEIRS {i}" in text
        assert f"line {i}" in text  # base side
    assert result.size_bytes == len(text.encode())


def test_hunk_context_caps_context_lines(tmp_path):
    base = _lines()
    ours, theirs = list(base), list(base)
    ours[60], theirs[60] = "OURS 60\n", "THEIRS 60\n"
    stages = _stages(tmp_path, "".join(base), "".join(ours), "".join(theirs))
    result = build_hunk_context("app/foo.py", stages, context_lines=2)
    assert result is not None
    text = result.path.read_text()
    before = text.split("--- context before\n")[1].split("--- base")[0]
    assert "line 58" in before
    assert "line 59" in before
    assert "line 57" not in before


def test_hunk_context_ranges_match_marker_layout(tmp_path):
    base, ours, theirs = _three_hunk_texts()
    stages = _stages(tmp_path, base, ours, theirs)
    result = build_hunk_context("app/foo.py", stages)
    assert result is not None
    ranges = [
        (int(m.group(1)), int(m.group(2)))
        for m in re.finditer(r"worktree lines ~(\d+)-(\d+)\)", result.path.read_text())
    ]
    assert len(ranges) == 3
    proc = subprocess.run(
        ["git", "merge-file", "-p", "-L", "ours", "-L", "base", "-L", "theirs",
         str(stages[2]), str(stages[1]), str(stages[3])],
        capture_output=True, text=True, check=False,
    )
    marker_lines = proc.stdout.splitlines()
    for start, end in ranges:
        assert marker_lines[start - 1] == "<<<<<<< ours"
        assert marker_lines[end - 1] == ">>>>>>> theirs"


def test_hunk_context_none_when_clean(tmp_path):
    text = "".join(_lines(10))
    stages = _stages(tmp_path, text, text, text)
    assert build_hunk_context("app/foo.py", stages) is None
    assert not (tmp_path / "stages" / "hunks" / "app__foo.py.md").exists()


def test_hunk_context_none_for_modify_delete(tmp_path):
    stages = _stages(tmp_path, *_three_hunk_texts())
    del stages[3]
    assert build_hunk_context("app/foo.py", stages) is None


def test_hunk_context_none_on_nul_byte(tmp_path):
    base, ours, theirs = _three_hunk_texts()
    stages = _stages(tmp_path, base, ours + "\x00", theirs)
    assert build_hunk_context("app/foo.py", stages) is None


def test_hunk_context_none_on_literal_marker_line(tmp_path):
    base = _lines(40)
    base[5] = "<<<<<<< ours\n"
    ours, theirs = list(base), list(base)
    ours[30], theirs[30] = "OURS 30\n", "THEIRS 30\n"
    stages = _stages(tmp_path, "".join(base), "".join(ours), "".join(theirs))
    assert build_hunk_context("app/foo.py", stages) is None


def test_hunk_context_none_when_merge_file_fails(tmp_path, monkeypatch):
    stages = _stages(tmp_path, *_three_hunk_texts())

    def raiser(*a, **k):
        raise FileNotFoundError("git")

    monkeypatch.setattr(harness.conflict.subprocess, "run", raiser)
    assert build_hunk_context("app/foo.py", stages) is None

    def exit_255(*a, **k):
        return subprocess.CompletedProcess(args=[], returncode=255, stdout="", stderr="")

    monkeypatch.setattr(harness.conflict.subprocess, "run", exit_255)
    assert build_hunk_context("app/foo.py", stages) is None


def test_hunk_context_removes_stale_artifact(tmp_path):
    base, ours, theirs = _three_hunk_texts()
    stages = _stages(tmp_path, base, ours, theirs)
    first = build_hunk_context("app/foo.py", stages)
    assert first is not None and first.path.exists()
    stages[2].write_text(theirs)
    assert build_hunk_context("app/foo.py", stages) is None
    assert not first.path.exists()
