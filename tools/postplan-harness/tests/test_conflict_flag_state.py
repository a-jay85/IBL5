"""Condition (14) marker: SHA recorder and the pure classifier, on real temp git repos."""
import os
import shutil
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.armable import (
    FLAG_ABSENT,
    FLAG_CARRIED,
    FLAG_STALE,
    FLAG_UNKNOWN,
    classify_conflict_flag,
    conflict_flag_path,
    record_conflict_resolution,
)
from test_gitad_stacked_rebase import _rev, _sh

BRANCH = "feature-flag-state"


def _make_repo():
    """master with one base commit, plus a feature branch with one commit."""
    d = tempfile.mkdtemp(prefix="postplan-flag-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    _sh(d, "checkout", "-b", BRANCH)
    open(os.path.join(d, "b.txt"), "w").write("one\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: one")
    return d


def _is_ancestor_in(d):
    return lambda sha: _sh(
        d, "merge-base", "--is-ancestor", sha, "HEAD", check=False).returncode == 0


def _squash(d):
    _sh(d, "reset", "--soft", "master")
    _sh(d, "commit", "-m", "squashed")


def _classify(d, tmp_path, legacy=lambda _f: False):
    flag = conflict_flag_path(BRANCH, str(tmp_path))
    notes = str(tmp_path / "notes.md")
    return classify_conflict_flag(flag, notes, _is_ancestor_in(d), legacy)


def test_record_conflict_resolution_appends_full_sha(tmp_path):
    a, b = "a" * 40, "b" * 40
    path = record_conflict_resolution(BRANCH, a, str(tmp_path))
    record_conflict_resolution(BRANCH, b, str(tmp_path))
    assert path == conflict_flag_path(BRANCH, str(tmp_path))
    assert open(path).read().splitlines() == [a, b]


def test_record_conflict_resolution_rejects_short_sha(tmp_path):
    with pytest.raises(ValueError):
        record_conflict_resolution(BRANCH, "abc123", str(tmp_path))
    assert not os.path.lexists(conflict_flag_path(BRANCH, str(tmp_path)))


def test_absent_marker_classifies_absent(tmp_path):
    d = _make_repo()
    try:
        assert _classify(d, tmp_path) == (FLAG_ABSENT, "")
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_recorded_sha_in_history_is_carried(tmp_path):
    d = _make_repo()
    try:
        record_conflict_resolution(BRANCH, _rev(d, "HEAD"), str(tmp_path))
        open(os.path.join(d, "c.txt"), "w").write("two\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "feat: two")
        state, _detail = _classify(d, tmp_path)
        assert state == FLAG_CARRIED
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_recorded_sha_rewritten_away_is_stale(tmp_path):
    d = _make_repo()
    try:
        recorded = _rev(d, "HEAD")
        record_conflict_resolution(BRANCH, recorded, str(tmp_path))
        _squash(d)
        state, detail = _classify(d, tmp_path)
        assert state == FLAG_STALE
        assert recorded[:12] in detail
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_mixed_recorded_shas_carried_if_any_survives(tmp_path):
    d = _make_repo()
    try:
        record_conflict_resolution(BRANCH, _rev(d, "HEAD"), str(tmp_path))
        _squash(d)
        record_conflict_resolution(BRANCH, _rev(d, "HEAD"), str(tmp_path))
        state, _detail = _classify(d, tmp_path)
        assert state == FLAG_CARRIED
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_legacy_empty_marker_is_unknown_when_predicate_false(tmp_path):
    d = _make_repo()
    try:
        open(conflict_flag_path(BRANCH, str(tmp_path)), "w").close()
        state, detail = _classify(d, tmp_path, legacy=lambda _f: False)
        assert state == FLAG_UNKNOWN
        assert "legacy empty marker" in detail
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_legacy_empty_marker_is_stale_when_predicate_true(tmp_path):
    d = _make_repo()
    try:
        open(conflict_flag_path(BRANCH, str(tmp_path)), "w").close()
        state, _detail = _classify(d, tmp_path, legacy=lambda _f: True)
        assert state == FLAG_STALE
    finally:
        shutil.rmtree(d, ignore_errors=True)


def test_legacy_floor_uses_newer_notes_mtime(tmp_path):
    flag = conflict_flag_path(BRANCH, str(tmp_path))
    notes = str(tmp_path / "notes.md")
    open(flag, "w").close()
    open(notes, "w").close()
    os.utime(flag, (100, 100))
    os.utime(notes, (200, 200))
    seen = []

    def legacy(floor):
        seen.append(floor)
        return False

    classify_conflict_flag(flag, notes, lambda _s: False, legacy)
    assert seen == [200]


def test_malformed_marker_is_unknown(tmp_path):
    flag = conflict_flag_path(BRANCH, str(tmp_path))
    open(flag, "w").write("hello\n")
    state, detail = classify_conflict_flag(
        flag, str(tmp_path / "n.md"), lambda _s: True, lambda _f: True)
    assert state == FLAG_UNKNOWN
    assert detail.startswith("malformed marker line")


def test_directory_in_place_of_marker_is_unknown(tmp_path):
    flag = conflict_flag_path(BRANCH, str(tmp_path))
    os.mkdir(flag)
    state, detail = classify_conflict_flag(
        flag, str(tmp_path / "n.md"), lambda _s: True, lambda _f: True)
    assert state == FLAG_UNKNOWN
    assert detail.startswith("unreadable marker")


def test_undecodable_marker_is_unknown(tmp_path):
    flag = conflict_flag_path(BRANCH, str(tmp_path))
    open(flag, "wb").write(b"\xff\xfe")
    state, _detail = classify_conflict_flag(
        flag, str(tmp_path / "n.md"), lambda _s: True, lambda _f: True)
    assert state == FLAG_UNKNOWN
