"""Verifier verdict reuse in `verify_findings`: spawn counts, record identity, and the cases
that must never reuse (dirty tree, new commit, changed prompt, errored or partial seats).

`BASELINE_SPAWNS` is the cost of two passes over the same findings on the same clean git tree
before reuse existed: one chunk times three seats times two passes.
"""
from __future__ import annotations

import glob
import json
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import verdictcache
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


def _phase(tmp_path, canned=None):
    llm = _DualLlm(dict(canned or SEAT_REPLIES))
    wt = _git_tree(tmp_path)
    return llm, ReviewPhase(llm, _RecordingGh(), worktree=wt), wt


def _two_passes(tmp_path):
    llm, phase, _wt = _phase(tmp_path)
    first = phase.verify_findings(FINDINGS, DIFF)
    second = phase.verify_findings(FINDINGS, DIFF)
    return llm, first, second


def _cache_files(wt):
    return glob.glob(os.path.join(verdictcache.cache_dir(wt), "*.json"))


def test_baseline_two_passes_same_tree_spawn_count(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, _first, _second = _two_passes(tmp_path)
    assert len(llm.tooled_calls) < BASELINE_SPAWNS
    assert len(llm.tooled_calls) == BASELINE_SPAWNS // 2


def test_second_pass_records_identical_to_first(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    _llm, first, second = _two_passes(tmp_path)
    assert [r["kept"] for r in first] == [True, False, True]
    assert second == first


def test_dirty_tree_never_reuses(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, wt = _phase(tmp_path)
    phase.verify_findings(FINDINGS, DIFF)
    with open(os.path.join(wt, "ibl5", "a.php"), "a") as fh:
        fh.write("// uncommitted\n")
    phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_untracked_file_is_dirty(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, wt = _phase(tmp_path)
    phase.verify_findings(FINDINGS, DIFF)
    with open(os.path.join(wt, "ibl5", "new.php"), "w") as fh:
        fh.write("<?php\n")
    phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_new_commit_misses(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, wt = _phase(tmp_path)
    phase.verify_findings(FINDINGS, DIFF)
    with open(os.path.join(wt, "ibl5", "b.php"), "a") as fh:
        fh.write("// committed\n")
    _git(wt, "add", "-A")
    _git(wt, "commit", "-qm", "edit b")
    phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_changed_finding_text_misses(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, _wt = _phase(tmp_path)
    phase.verify_findings(FINDINGS, DIFF)
    edited = [FINDINGS[0], _f(path="ibl5/a.php", line=9, body="a9 edited"), FINDINGS[2]]
    phase.verify_findings(edited, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_errored_seat_is_never_cached(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    canned = dict(SEAT_REPLIES)
    canned["verify-findings-2"] = {"raise": {"kind": "llm-invalid-output"}}
    llm, phase, wt = _phase(tmp_path, canned)
    first = phase.verify_findings(FINDINGS, DIFF)
    assert [r["abstain"] for r in first] == [1, 1, 1]
    assert _cache_files(wt) == []
    llm.canned["verify-findings-2"] = SEAT_REPLIES["verify-findings-2"]
    phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_partial_seat_reply_is_never_cached(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    canned = dict(SEAT_REPLIES)
    canned["verify-findings-3"] = _reply(_vote(1, "not-real"), _vote(3, "real"))
    llm, phase, wt = _phase(tmp_path, canned)
    phase.verify_findings(FINDINGS, DIFF)
    assert _cache_files(wt) == []
    phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_corrupt_cache_file_is_a_miss(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, wt = _phase(tmp_path)
    first = phase.verify_findings(FINDINGS, DIFF)
    files = _cache_files(wt)
    assert files
    for f in files:
        with open(f, "w") as fh:
            json.dump({"schema_version": 1, "seats": [{}, {}, {}]}, fh)
    second = phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS
    assert second == first


def test_reused_drop_still_needs_all_three_seats(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, wt = _phase(tmp_path)
    phase.verify_findings(FINDINGS, DIFF)
    (path,) = _cache_files(wt)
    with open(path) as fh:
        data = json.load(fh)
    data["seats"][0]["1"] = ["not-real", "r", False]
    with open(path, "w") as fh:
        json.dump(data, fh)
    second = phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS // 2
    assert [r["kept"] for r in second] == [True, False, True]


def test_cache_disabled_by_env(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "0")
    llm, _first, _second = _two_passes(tmp_path)
    assert len(llm.tooled_calls) == BASELINE_SPAWNS


def test_all_chunks_reused_skips_executor(tmp_path, monkeypatch):
    monkeypatch.setenv("POSTPLAN_VERDICT_CACHE", "1")
    llm, phase, _wt = _phase(tmp_path)
    first = phase.verify_findings(FINDINGS, DIFF)
    spawned = len(llm.tooled_calls)
    second = phase.verify_findings(FINDINGS, DIFF)
    assert len(llm.tooled_calls) == spawned
    assert second == first
