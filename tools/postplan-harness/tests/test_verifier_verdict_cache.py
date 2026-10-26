"""Verifier spawn-count and aggregate-record characterization for `verify_findings`.

Two passes over the same findings on the same clean git tree pin how many `call_tooled`
spawns the verifier costs and which records it returns.
"""
from __future__ import annotations

import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase
from tests.test_review_verifiers import _DualLlm, _RecordingGh, _f, _reply, _vote

BASELINE_SPAWNS = 6

DIFF = (
    "diff --git a/ibl5/a.php b/ibl5/a.php\n"
    "--- a/ibl5/a.php\n"
    "+++ b/ibl5/a.php\n"
    "@@ -1,1 +1,2 @@\n"
    " <?php\n"
    "+// changed\n"
    "diff --git a/ibl5/b.php b/ibl5/b.php\n"
    "--- a/ibl5/b.php\n"
    "+++ b/ibl5/b.php\n"
    "@@ -1,1 +1,2 @@\n"
    " <?php\n"
    "+// changed\n"
)

FINDINGS = [
    _f(path="ibl5/a.php", line=3, body="a3"),
    _f(path="ibl5/a.php", line=9, body="a9"),
    _f(source="security-audit", agent="security", path="ibl5/b.php", line=2, body="b2"),
]

SEAT_REPLIES = {
    "verify-findings-1": _reply(_vote(1, "real"), _vote(2, "not-real"), _vote(3, "real")),
    "verify-findings-2": _reply(_vote(1, "real"), _vote(2, "not-real"), _vote(3, "not-real")),
    "verify-findings-3": _reply(_vote(1, "not-real"), _vote(2, "not-real"), _vote(3, "real")),
}


def _git(wt, *args):
    subprocess.run(["git", "-C", wt, "-c", "user.email=t@example.invalid", "-c", "user.name=t", *args],
                   check=True, capture_output=True)


def _git_tree(tmp_path) -> str:
    wt = tmp_path / "wt"
    (wt / "ibl5").mkdir(parents=True)
    subprocess.run(["git", "init", "-q", str(wt)], check=True, capture_output=True)
    (wt / "ibl5" / "a.php").write_text("<?php\n" + "".join(f"// line {n}\n" for n in range(1, 10)))
    (wt / "ibl5" / "b.php").write_text("<?php\n" + "".join(f"// line {n}\n" for n in range(1, 5)))
    _git(str(wt), "add", "-A")
    _git(str(wt), "commit", "-qm", "init")
    return str(wt)


def _two_passes(tmp_path):
    llm = _DualLlm(dict(SEAT_REPLIES))
    phase = ReviewPhase(llm, _RecordingGh(), worktree=_git_tree(tmp_path))
    first = phase.verify_findings(FINDINGS, DIFF)
    second = phase.verify_findings(FINDINGS, DIFF)
    return llm, first, second


def test_baseline_two_passes_same_tree_spawn_count(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, _first, _second = _two_passes(tmp_path)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_second_pass_records_identical_to_first(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    _llm, first, second = _two_passes(tmp_path)
    assert [r["kept"] for r in first] == [True, False, True]
    assert second == first
