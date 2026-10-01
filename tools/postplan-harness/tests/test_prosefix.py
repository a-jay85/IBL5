"""Prose-fix pass: characterization of today's hold, then the pre-push auto-fix.

Every test runs on a real temp git repo with a verbatim copy of `bin/check-prose`
behind a stub `bin/run-meta-checks-local`, so the gate that clears the hold is the
production gate, not a mock.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit
from harness.armable import meta_checks_clearance

REAL_CHECK_PROSE = Path(__file__).resolve().parents[3] / "bin" / "check-prose"

STUB = r"""#!/usr/bin/env bash
BASE=master
while [ $# -gt 0 ]; do
  case "$1" in --base) BASE="$2"; shift 2 ;; *) shift ;; esac
done
DIR="$(cd "$(dirname "$0")" && pwd)"
failed=()
out="$("$DIR/check-prose" "--since=$BASE" 2>&1)"; rc=$?
if [ "$rc" -ne 0 ]; then
  printf 'FAIL  %s\n%s\n' check-prose-since "$out"; failed+=(check-prose-since)
else
  printf 'PASS  %s\n' check-prose-since
fi
if [ -n "${STUB_EXTRA_FAIL:-}" ]; then
  printf 'FAIL  %s\n' "$STUB_EXTRA_FAIL"; failed+=("$STUB_EXTRA_FAIL")
fi
for n in "${failed[@]}"; do printf 'META-CHECK-FAILED: %s\n' "$n"; done
[ "${#failed[@]}" -eq 0 ]
"""

TELL_LINE = "The cache warms fast — the pool stays small."
CLEAN_LINE = "The cache warms fast. The pool stays small."


class Env:
    def __init__(self, repo, git, flag, log):
        self.repo = repo
        self.git = git
        self.flag = flag
        self.log = log


def _git(repo, *args):
    return subprocess.run(["git", "-C", repo, *args], capture_output=True, text=True,
                          check=True).stdout


@pytest.fixture
def prose_repo(tmp_path, monkeypatch):
    repo = tmp_path / "repo"
    (repo / "bin").mkdir(parents=True)
    (repo / "docs").mkdir()
    r = str(repo)
    _git(r, "init", "-b", "master")
    _git(r, "config", "user.email", "t@t")
    _git(r, "config", "user.name", "t")
    _git(r, "config", "commit.gpgsign", "false")
    (repo / "docs" / "note.md").write_text("# Note\n\nThe pool starts small.\n")
    shutil.copy2(REAL_CHECK_PROSE, repo / "bin" / "check-prose")
    stub = repo / "bin" / "run-meta-checks-local"
    stub.write_text(STUB)
    stub.chmod(0o755)
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "base")
    branch = f"pfx-{tmp_path.name}"
    _git(r, "checkout", "-b", branch)
    with open(repo / "docs" / "note.md", "a") as fh:
        fh.write(TELL_LINE + "\n")
    _git(r, "add", "-A")
    _git(r, "commit", "-m", "feat: add note")
    flag = f"/tmp/ibl5-meta-checks-prepush-{branch.replace('/', '-')}.failed"
    if os.path.exists(flag):
        os.unlink(flag)
    monkeypatch.setattr(runner, "_remediate_doc_staleness", lambda *a, **k: 0)
    proc = subprocess.run([str(stub), "--stage", "pre-push", "--base", "master"],
                          cwd=r, capture_output=True, text=True)
    assert proc.returncode == 1
    assert "docs/note.md:4:" in proc.stdout
    yield Env(r, LiveGit(r), flag, [])
    if os.path.exists(flag):
        os.unlink(flag)


def test_char_sole_prose_failure_holds_today(prose_repo):
    e = prose_repo
    head = e.git.head()
    fo: list = []
    ok = runner.run_meta_checks_local(e.git, e.repo, "master", e.log.append,
                                      failures_out=fo)
    assert ok is False
    assert open(e.flag).read() == "check-prose-since\n"
    assert e.log[-1] == ("phase2: META-CHECKS FAILED (check-prose-since) — "
                         "pushing anyway, auto-merge will not arm")
    assert e.git.head() == head
    assert e.git.is_dirty() is False
    assert [f["name"] for f in fo] == ["check-prose-since"]
    assert "docs/note.md:4:" in fo[0]["output"]
    assert meta_checks_clearance(e.flag, 0) == "HELD"


def test_char_clean_branch_unlinks_stale_flag(prose_repo):
    e = prose_repo
    note = Path(e.repo) / "docs" / "note.md"
    note.write_text(note.read_text().replace(TELL_LINE, CLEAN_LINE))
    _git(e.repo, "add", "-A")
    _git(e.repo, "commit", "-m", "fix: clean prose")
    Path(e.flag).write_text("check-prose-since\n")
    assert runner.run_meta_checks_local(e.git, e.repo, "master", e.log.append) is True
    assert os.path.exists(e.flag) is False
    assert meta_checks_clearance(e.flag, 0) == "CLEARED"
