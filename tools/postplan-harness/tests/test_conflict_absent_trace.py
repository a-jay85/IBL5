"""Park-and-restore of a prior conflict review across a rebase (backlog#1778).

purge_verdict_artifacts runs at the start of every rebase. It never removes the
condition-(14) marker. Given keep_sha (the pre-rebase HEAD), it parks the sidecar only
when the sidecar names that exact SHA and its verdict file exists, and keeps only that
one verdict file. restore_prior_verdict brings the parked sidecar back only when HEAD is
still that SHA and no fresh review was written since. Every other path leaves the parked
file unread, so conflict_verdict_for returns None and the sticky shows verdict=ABSENT
(fail-closed).
"""
import glob
import os
import sys
import uuid

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conflict import (
    prior_sidecar_path,
    purge_verdict_artifacts,
    restore_prior_verdict,
    verdict_ok_path,
)
from harness.armable import (
    conflict_flag_path,
    conflict_verdict_for,
    record_conflict_resolution,
)
from runner import _conflict_key

SHA = "c" * 40
OTHER_SHA = "d" * 40


def _sidecar(key):
    return f"/tmp/postplan-conflict-sha-{key}.txt"


def _verdict_files(key):
    return glob.glob(f"/tmp/postplan-conflict-verdict-{key}-*.ok")


def _seed(prefix, *, verdict=True):
    """A unique branch with the marker, sidecar and (optionally) a CLEAN verdict for SHA."""
    branch = f"{prefix}-{uuid.uuid4().hex[:10]}"
    key = _conflict_key(branch)
    record_conflict_resolution(branch, SHA)
    with open(_sidecar(key), "w") as fh:
        fh.write(SHA)
    if verdict:
        with open(verdict_ok_path(key, SHA), "w") as fh:
            fh.write("CONFLICT-REVIEW=CLEAN\nreply text\n")
    return branch, key


def _assert_flag_holds_sha(branch):
    with open(conflict_flag_path(branch)) as fh:
        assert SHA in fh.read()


def _cleanup(branch, key):
    for path in (conflict_flag_path(branch), _sidecar(key), prior_sidecar_path(key),
                 *_verdict_files(key)):
        if os.path.exists(path):
            os.unlink(path)


def test_purge_without_keep_sha_reads_absent():
    branch, key = _seed("absent-trace")
    try:
        assert conflict_verdict_for(branch) == "CONFLICT-REVIEW=CLEAN"

        purge_verdict_artifacts(key)

        assert conflict_verdict_for(branch) is None
        assert not os.path.exists(_sidecar(key))
        assert _verdict_files(key) == []
        _assert_flag_holds_sha(branch)
    finally:
        _cleanup(branch, key)


def test_noop_rebase_keeps_prior_clean_verdict():
    branch, key = _seed("keep-noop")
    try:
        purge_verdict_artifacts(key, keep_sha=SHA)

        assert conflict_verdict_for(branch) is None  # parked state is unreadable
        assert os.path.exists(prior_sidecar_path(key))
        _assert_flag_holds_sha(branch)

        assert restore_prior_verdict(key, SHA) is True

        assert conflict_verdict_for(branch) == "CONFLICT-REVIEW=CLEAN"
        assert not os.path.exists(prior_sidecar_path(key))
        _assert_flag_holds_sha(branch)
    finally:
        _cleanup(branch, key)


def test_head_moved_drops_prior_verdict():
    branch, key = _seed("keep-moved")
    try:
        purge_verdict_artifacts(key, keep_sha=SHA)
        _assert_flag_holds_sha(branch)

        assert restore_prior_verdict(key, OTHER_SHA) is False

        assert conflict_verdict_for(branch) is None
        assert not os.path.exists(prior_sidecar_path(key))
        assert not os.path.exists(verdict_ok_path(key, SHA))
        _assert_flag_holds_sha(branch)
    finally:
        _cleanup(branch, key)


def test_purge_keep_sha_mismatch_deletes_all():
    branch, key = _seed("keep-mismatch")
    try:
        purge_verdict_artifacts(key, keep_sha=OTHER_SHA)

        assert not os.path.exists(_sidecar(key))
        assert not os.path.exists(prior_sidecar_path(key))
        assert _verdict_files(key) == []
        assert conflict_verdict_for(branch) is None
        _assert_flag_holds_sha(branch)
    finally:
        _cleanup(branch, key)


def test_restore_missing_verdict_file_reads_absent():
    branch, key = _seed("keep-noverdict", verdict=False)
    try:
        purge_verdict_artifacts(key, keep_sha=SHA)

        assert not os.path.exists(prior_sidecar_path(key))
        _assert_flag_holds_sha(branch)
        assert restore_prior_verdict(key, SHA) is False
        assert conflict_verdict_for(branch) is None
    finally:
        _cleanup(branch, key)


def test_restore_keeps_live_review_when_sidecar_exists():
    branch, key = _seed("keep-live")
    try:
        purge_verdict_artifacts(key, keep_sha=SHA)
        _assert_flag_holds_sha(branch)
        # A fresh review lands after the purge, for the same HEAD.
        with open(_sidecar(key), "w") as fh:
            fh.write(SHA)
        with open(verdict_ok_path(key, SHA), "w") as fh:
            fh.write("CONFLICT-REVIEW=FOUND-PROBLEM\n")

        assert restore_prior_verdict(key, SHA) is False

        assert not os.path.exists(prior_sidecar_path(key))
        assert os.path.exists(verdict_ok_path(key, SHA))
        assert conflict_verdict_for(branch) == "CONFLICT-REVIEW=FOUND-PROBLEM"
    finally:
        _cleanup(branch, key)


def test_next_purge_clears_stale_pending():
    branch = f"keep-stale-{uuid.uuid4().hex[:10]}"
    key = _conflict_key(branch)
    try:
        record_conflict_resolution(branch, SHA)
        with open(prior_sidecar_path(key), "w") as fh:
            fh.write(SHA)

        purge_verdict_artifacts(key)

        assert not os.path.exists(prior_sidecar_path(key))
        _assert_flag_holds_sha(branch)
    finally:
        _cleanup(branch, key)
