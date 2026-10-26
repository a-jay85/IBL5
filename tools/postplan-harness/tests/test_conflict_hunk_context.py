"""build_hunk_context: bounded per-hunk artifact built from the three merge stages."""
from __future__ import annotations

import os
import re
import shutil
import subprocess
import sys
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import harness.conflict
from harness.conflict import HUNK_MAX_COUNT, HunkContext, build_hunk_context, resolve_one


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


# ── resolve_one prompt wiring ──────────────────────────────────────────────────

class _StubLlm:
    """Records call_tooled kwargs; replies are driven by a list."""
    def __init__(self, replies):
        self.replies = list(replies)
        self.calls: list[dict] = []

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools,
                    denied_tools=(), add_dirs=(), max_turns=None, **_):
        self.calls.append({
            "purpose": purpose, "allowed_tools": allowed_tools,
            "denied_tools": denied_tools, "add_dirs": add_dirs, "prompt": prompt,
        })
        return self.replies.pop(0) if self.replies else "FAILED"


class _StagedRun:
    """Git-run stub whose `show` returns per-stage (or per-rev) text."""
    def __init__(self, worktree: str, texts: dict):
        self.worktree = worktree
        self.texts = texts

    def __call__(self, *args, check=True) -> str:
        first = args[0] if args else ""
        if first == "show":
            parts = args[1].split(":")
            key = int(parts[1]) if parts[0] == "" else parts[0]
            return self.texts[key]
        if first == "rev-parse":
            return "abc1234"
        return ""


def _write_worktree_file(worktree, path: str, content: str) -> None:
    full = Path(worktree) / path
    full.parent.mkdir(parents=True, exist_ok=True)
    full.write_text(content)


@pytest.fixture
def stage_key():
    key = f"hunk-{uuid.uuid4().hex[:8]}"
    yield key
    shutil.rmtree(f"/tmp/postplan-conflict-stages-{key}", ignore_errors=True)


def test_resolver_prompt_points_at_hunk_artifact(tmp_path, stage_key):
    base, ours, theirs = _three_hunk_texts()
    worktree = str(tmp_path / "wt")
    _write_worktree_file(worktree, "app/foo.py", "clean merged\n")
    llm = _StubLlm(["RESOLVED"])
    run = _StagedRun(worktree, {1: base, 2: ours, 3: theirs})
    success, _ = resolve_one(llm, run, worktree=worktree, key=stage_key, path="app/foo.py")
    assert success is True
    prompt = llm.calls[0]["prompt"]
    stage_dir = f"/tmp/postplan-conflict-stages-{stage_key}"
    assert "hunk context:" in prompt
    assert f"{stage_dir}/hunks/app__foo.py.md" in prompt
    assert "Never re-read a file you already read" in prompt
    assert "Never Read a whole stage file" in prompt
    assert "Read all three before deciding" not in prompt
    assert f"{stage_dir}/base/app__foo.py" not in prompt
    assert llm.calls[0]["add_dirs"] == (stage_dir,)


def test_resolver_hunk_path_keeps_marker_guard(tmp_path, stage_key):
    base, ours, theirs = _three_hunk_texts()
    worktree = str(tmp_path / "wt")
    _write_worktree_file(
        worktree, "app/foo.py", "<<<<<<< HEAD\nours\n=======\ntheirs\n>>>>>>> b\n")
    llm = _StubLlm(["RESOLVED"])
    run = _StagedRun(worktree, {1: base, 2: ours, 3: theirs})
    success, reason = resolve_one(
        llm, run, worktree=worktree, key=stage_key, path="app/foo.py", max_rounds=1,
    )
    assert success is False
    assert "hunk context:" in llm.calls[0]["prompt"]
    assert "round cap exceeded" in reason


def test_resolver_builds_hunk_context_once(tmp_path, stage_key, monkeypatch):
    base, ours, theirs = _three_hunk_texts()
    worktree = str(tmp_path / "wt")
    _write_worktree_file(worktree, "app/foo.py", "")
    real = harness.conflict.build_hunk_context
    counter = {"n": 0}

    def counting(*a, **k):
        counter["n"] += 1
        return real(*a, **k)

    monkeypatch.setattr(harness.conflict, "build_hunk_context", counting)
    llm = _StubLlm(["RESOLVED"] * 3)
    run = _StagedRun(worktree, {1: base, 2: ours, 3: theirs})
    success, _ = resolve_one(
        llm, run, worktree=worktree, key=stage_key, path="app/foo.py", max_rounds=3,
    )
    assert success is False
    assert counter["n"] == 1
    assert len(llm.calls) == 3


def test_resolver_hunk_artifact_from_stage_revs(tmp_path, stage_key):
    base, ours, theirs = _three_hunk_texts()
    worktree = str(tmp_path / "wt")
    _write_worktree_file(worktree, "app/foo.py", "earlier failed resolution, no markers\n")
    llm = _StubLlm(["RESOLVED"])
    run = _StagedRun(worktree, {"mb": base, "pre": ours, "mstr": theirs})
    resolve_one(
        llm, run, worktree=worktree, key=stage_key, path="app/foo.py",
        stage_revs={1: "mb", 2: "pre", 3: "mstr"},
    )
    assert "hunk context:" in llm.calls[0]["prompt"]
    artifact = Path(f"/tmp/postplan-conflict-stages-{stage_key}/hunks/app__foo.py.md")
    text = artifact.read_text()
    assert "OURS 20" in text
    assert "THEIRS 100" in text


# ── large-file byte bound and degrade paths ────────────────────────────────────

def _big_lines(n: int = 1100) -> list[str]:
    return [f"value_{i:05d} = compute({i})  # padding to make this line long\n"
            for i in range(n)]


def _run_big(tmp_path, key, base, ours, theirs):
    worktree = str(tmp_path / "wt")
    _write_worktree_file(worktree, "app/foo.py", "clean merged\n")
    llm = _StubLlm(["RESOLVED"])
    run = _StagedRun(worktree, {1: "".join(base), 2: "".join(ours), 3: "".join(theirs)})
    resolve_one(llm, run, worktree=worktree, key=key, path="app/foo.py")
    return llm.calls[0]["prompt"]


def test_large_file_prompt_and_artifact_under_byte_bound(tmp_path, stage_key):
    base = _big_lines()
    ours, theirs = list(base), list(base)
    for i in (200, 600, 1000):
        ours[i], theirs[i] = f"OURS {i}\n", f"THEIRS {i}\n"
    base_bytes = len("".join(base).encode())
    assert base_bytes >= 30_000
    prompt = _run_big(tmp_path, stage_key, base, ours, theirs)
    stage_dir = f"/tmp/postplan-conflict-stages-{stage_key}"
    line = next(l for l in prompt.splitlines() if l.startswith("  hunk context: "))
    artifact = Path(line.split("hunk context: ", 1)[1].split(" (", 1)[0])
    total = len(prompt.encode()) + artifact.stat().st_size
    assert total <= 8_000
    assert total <= base_bytes * 0.15
    assert "Read all three before deciding" not in prompt
    assert f"{stage_dir}/base/app__foo.py" not in prompt


def test_many_hunks_degrade_to_whole_file(tmp_path, stage_key):
    base = _big_lines(20 * (HUNK_MAX_COUNT + 2))
    ours, theirs = list(base), list(base)
    for n in range(HUNK_MAX_COUNT + 1):
        i = 20 * n + 10
        ours[i], theirs[i] = f"OURS {i}\n", f"THEIRS {i}\n"
    prompt = _run_big(tmp_path, stage_key, base, ours, theirs)
    assert "Read all three before deciding" in prompt
    assert not list(Path(f"/tmp/postplan-conflict-stages-{stage_key}").glob("hunks/*"))


def test_oversized_hunk_degrades_to_whole_file(tmp_path, stage_key):
    base = _big_lines()
    ours, theirs = list(base), list(base)
    ours[500], theirs[500] = "x" * 20_000 + "\n", "y\n"
    prompt = _run_big(tmp_path, stage_key, base, ours, theirs)
    assert "Read all three before deciding" in prompt
    assert not list(Path(f"/tmp/postplan-conflict-stages-{stage_key}").glob("hunks/*"))


def test_binary_like_stage_degrades_to_whole_file(tmp_path, stage_key):
    base = _big_lines()
    ours, theirs = list(base), list(base)
    ours[300], theirs[300] = "OURS\x00\n", "THEIRS\n"
    prompt = _run_big(tmp_path, stage_key, base, ours, theirs)
    assert "Read all three before deciding" in prompt
