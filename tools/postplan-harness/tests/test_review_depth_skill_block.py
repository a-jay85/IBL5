"""The /post-plan Phase 4 review-depth bash block fetches master's script and fails safe to deep."""
from __future__ import annotations

import json
import os
import re
import stat
import subprocess
from pathlib import Path

import pytest

HERE = Path(__file__).resolve().parent
REPO = HERE.parent.parent.parent
SKILL = REPO / ".claude" / "skills" / "post-plan" / "_phase-4-review-audit.md"
SCRIPT = REPO / "bin" / "lib" / "review-depth.sh"
CASES = json.loads((HERE / "fixtures" / "review_depth_cases.json").read_text(encoding="utf-8"))
BODIES = {row["id"]: row["body"] for row in CASES}

_FENCE = re.compile(r"```bash\n(# phase 4 review-depth\n.*?)\n```", re.DOTALL)


def _block() -> str:
    m = _FENCE.search(SKILL.read_text(encoding="utf-8"))
    assert m, "no '# phase 4 review-depth' bash block in the skill file"
    return m.group(1)


def _stub(path: Path, text: str) -> None:
    path.write_text(text, encoding="utf-8")
    path.chmod(path.stat().st_mode | stat.S_IXUSR)


@pytest.fixture
def stub_dir(tmp_path: Path) -> Path:
    _stub(
        tmp_path / "git",
        "#!/bin/bash\n"
        'if [ "${STUB_GIT_MODE:-}" = ok ] && [ "$1 $2" = "show deadbeef:bin/lib/review-depth.sh" ]; then\n'
        f'  cat "{SCRIPT}"\n'
        "  exit 0\n"
        "fi\n"
        "echo 'fatal: path not in tree' >&2\n"
        "exit 128\n",
    )
    _stub(
        tmp_path / "gh",
        "#!/bin/bash\n"
        'if [ "${STUB_GH_MODE:-}" = ok ]; then\n'
        "  printf '%s' \"$STUB_BODY\"\n"
        "  exit 0\n"
        "fi\n"
        "exit 1\n",
    )
    return tmp_path


def _run_block(stub_dir: Path, git_mode: str, gh_mode: str, body: str = "") -> str:
    code = _block().replace("<MASTER_SHA>", "deadbeef").replace("<N>", f"t{os.getpid()}")
    env = {
        "PATH": f"{stub_dir}:/usr/bin:/bin",
        "STUB_GIT_MODE": git_mode,
        "STUB_GH_MODE": gh_mode,
        "STUB_BODY": body,
    }
    proc = subprocess.run(["/bin/bash", "-c", code], capture_output=True, env=env, cwd=stub_dir)
    return proc.stdout.decode("utf-8").splitlines()[0]


@pytest.mark.parametrize(
    ("git_mode", "gh_mode", "body_id", "expected"),
    [
        ("ok", "ok", "two-way-localized", "standard two-way/localized"),
        ("ok", "ok", "one-way-module", "deep one-way"),
        ("ok", "ok", "missing-line", "deep MISSING"),
        ("ok", "fail", None, "deep UNREADABLE"),
        ("fail", "ok", "two-way-localized", "deep PIN-UNAVAILABLE"),
    ],
    ids=["standard", "one-way", "missing", "gh-fails", "git-fails"],
)
def test_skill_block(stub_dir, git_mode, gh_mode, body_id, expected):
    body = BODIES[body_id] if body_id else ""
    assert _run_block(stub_dir, git_mode, gh_mode, body) == expected


def test_skill_block_is_pinned():
    assert "git show <MASTER_SHA>:bin/lib/review-depth.sh" in _block()
    assert "bash bin/lib/review-depth.sh" not in SKILL.read_text(encoding="utf-8")
