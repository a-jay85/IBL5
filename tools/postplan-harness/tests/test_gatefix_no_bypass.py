"""The harness never bypasses git hooks, and the gate fixer cannot run git itself."""
from __future__ import annotations

import os
import re
import shutil
import sys
from pathlib import Path

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import gatefix

ROOT = Path(__file__).resolve().parent.parent

_BAD = re.compile(r"--no-verify|no_verify|hookspath|husky", re.IGNORECASE)
_SHORT_N = re.compile(r"""["']commit["']\s*,\s*(?:[^)\]]*,\s*)?["']-n["']""")

# (relative path, stripped line). The replay module re-runs gate history in a scratch
# worktree and never commits or pushes ship content.
ALLOWLIST = {
    ("harness/gate_backtest_replay.py", '_GIT_QUIET = ["-c", "core.hooksPath=/dev/null"]'),
}


def _sources(root: Path):
    yield from sorted((root / "harness").rglob("*.py"))
    yield root / "runner.py"


def scan(root: Path) -> list[tuple[str, int, str]]:
    hits = []
    for path in _sources(root):
        rel = path.relative_to(root).as_posix()
        for n, line in enumerate(path.read_text().splitlines(), 1):
            if not (_BAD.search(line) or _SHORT_N.search(line)):
                continue
            if (rel, line.strip()) in ALLOWLIST:
                continue
            hits.append((rel, n, line.strip()))
    return hits


def _copy_tree(tmp_path: Path) -> Path:
    dst = tmp_path / "copy"
    shutil.copytree(ROOT / "harness", dst / "harness",
                    ignore=shutil.ignore_patterns("__pycache__"))
    shutil.copy(ROOT / "runner.py", dst / "runner.py")
    return dst


def test_no_hook_bypass_in_harness_git_calls():
    assert scan(ROOT) == []


def test_no_bypass_scan_catches_injected_flag(tmp_path):
    dst = _copy_tree(tmp_path)
    with open(dst / "runner.py", "a") as fh:
        fh.write('\nsubprocess.run(["git", "commit", "--no-verify", "-m", "x"])\n')
    hits = scan(dst)
    assert len(hits) == 1 and hits[0][0] == "runner.py"


def test_no_bypass_scan_catches_short_n(tmp_path):
    dst = _copy_tree(tmp_path)
    with open(dst / "runner.py", "a") as fh:
        fh.write('\nsubprocess.run(["git", "commit", "-n"])\n')
    hits = scan(dst)
    assert len(hits) == 1 and hits[0][0] == "runner.py"


def test_no_bypass_allowlist_entries_still_present():
    for rel, text in ALLOWLIST:
        lines = [ln.strip() for ln in (ROOT / rel).read_text().splitlines()]
        assert text in lines, f"allowlisted line gone from {rel}: {text}"


def test_gatefix_denies_bash_tool():
    assert "Bash" in gatefix.GATEFIX_DENIED
    assert "Bash" not in gatefix.GATEFIX_ALLOWED
