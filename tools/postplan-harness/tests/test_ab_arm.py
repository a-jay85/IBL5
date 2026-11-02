"""A/B arm assignment: hash stability, 20-PR cap, env override, fail-safe state handling."""
from __future__ import annotations

import json
import multiprocessing
import os
import subprocess
import sys

import pytest

HARNESS_DIR = os.path.join(os.path.dirname(__file__), "..")
sys.path.insert(0, HARNESS_DIR)

from harness import ab_arm
from harness.ab_arm import ENROLL_CAP, ENV_VAR, assign, hash_arm

NOW = lambda: "2026-11-02T00:00:00Z"  # noqa: E731


def _assign(slug, state_dir, env=None, fired_by="automouse", log=None, pr=1):
    return assign(slug, pr=pr, fired_by=fired_by, state_dir=str(state_dir),
                  env=env or {}, log=log or (lambda m: None), now=NOW)


def _enroll_file(state_dir):
    with open(os.path.join(str(state_dir), ab_arm.ENROLL_BASENAME)) as fh:
        return json.load(fh)


def test_hash_arm_stable_across_processes():
    # Guards against hash(slug): Python salts str hashes per process, so a swap to it makes
    # the two PYTHONHASHSEED runs below disagree.
    pinned = {"alpha-slug": "harness", "beta-slug": "closer"}
    code = ("import sys; sys.path.insert(0, %r); from harness.ab_arm import hash_arm; "
            "print(hash_arm('alpha-slug'), hash_arm('beta-slug'))" % HARNESS_DIR)
    seen = set()
    for seed in ("1", "424242"):
        env = dict(os.environ, PYTHONHASHSEED=seed)
        out = subprocess.run([sys.executable, "-c", code], env=env, capture_output=True,
                             text=True, check=True).stdout.split()
        seen.add(tuple(out))
    assert seen == {(pinned["alpha-slug"], pinned["beta-slug"])}
    assert hash_arm("alpha-slug") == pinned["alpha-slug"]
    assert hash_arm("beta-slug") == pinned["beta-slug"]


def test_enrolled_slug_keeps_arm_after_cap(tmp_path):
    first = _assign("slug-1", tmp_path)
    for i in range(2, ENROLL_CAP + 1):
        _assign(f"slug-{i}", tmp_path)
    again = _assign("slug-1", tmp_path)
    assert again.arm == first.arm
    assert again.source == "enrolled"


def test_cap_stops_new_enrollment_at_20(tmp_path):
    for i in range(ENROLL_CAP):
        assert _assign(f"slug-{i}", tmp_path).source == "new-enrollment"
    over = _assign("slug-21", tmp_path)
    assert (over.arm, over.source) == ("harness", "cap-reached")
    assert len(_enroll_file(tmp_path)["enrolled"]) == ENROLL_CAP


@pytest.mark.parametrize("value", ["closer", "harness"])
def test_env_override_forces_arm_without_enrolling(tmp_path, value):
    got = _assign("slug-x", tmp_path, env={ENV_VAR: value})
    assert (got.arm, got.source) == (value, "env")
    assert not os.path.exists(os.path.join(str(tmp_path), ab_arm.ENROLL_BASENAME))


def test_env_empty_treated_as_unset(tmp_path):
    got = _assign("slug-x", tmp_path, env={ENV_VAR: ""})
    assert got.source == "new-enrollment"


@pytest.mark.parametrize("value", ["Closer", "closer ", " closer", "opus", "harness,closer"])
def test_env_near_miss_values_rejected(tmp_path, value):
    logs = []
    got = _assign("slug-x", tmp_path, env={ENV_VAR: value}, log=logs.append)
    assert (got.arm, got.source) == ("harness", "env-invalid")
    assert any("ignoring invalid" in m for m in logs)
    assert not os.path.exists(os.path.join(str(tmp_path), ab_arm.ENROLL_BASENAME))


@pytest.mark.parametrize("fired_by", ["pr-cycle-tick", "unknown", "automouse-x"])
def test_non_automouse_fired_by_not_enrolled(tmp_path, fired_by):
    got = _assign("slug-x", tmp_path, fired_by=fired_by)
    assert (got.arm, got.source) == ("harness", "not-queued")
    assert not os.path.exists(os.path.join(str(tmp_path), ab_arm.ENROLL_BASENAME))


def test_corrupt_enrollment_fails_safe_without_overwrite(tmp_path):
    path = os.path.join(str(tmp_path), ab_arm.ENROLL_BASENAME)
    with open(path, "wb") as fh:
        fh.write(b"{not json")
    got = _assign("slug-x", tmp_path)
    assert (got.arm, got.source) == ("harness", "state-unreadable")
    with open(path, "rb") as fh:
        assert fh.read() == b"{not json"


def _worker(args):
    slug, state_dir = args
    return assign(slug, pr=1, fired_by="automouse", state_dir=state_dir, env={},
                  log=lambda m: None, now=NOW).source


def test_concurrent_assign_never_exceeds_cap(tmp_path):
    for i in range(ENROLL_CAP - 1):
        _assign(f"seed-{i}", tmp_path)
    jobs = [(f"racer-{i}", str(tmp_path)) for i in range(4)]
    with multiprocessing.get_context("fork").Pool(4) as pool:
        sources = pool.map(_worker, jobs)
    assert sources.count("new-enrollment") == 1
    assert sources.count("cap-reached") == 3
    assert len(_enroll_file(tmp_path)["enrolled"]) == ENROLL_CAP


def test_forced_run_session_cap_counts_without_enrollment(tmp_path):
    from harness import ab_arm
    d = str(tmp_path)
    assert ab_arm.closer_sessions(d, "forced-slug") == 0
    for i in range(1, ab_arm.MAX_CLOSER_SESSIONS + 1):
        assert ab_arm.record_closer_session(d, "forced-slug") == i
    assert ab_arm.closer_sessions(d, "forced-slug") == ab_arm.MAX_CLOSER_SESSIONS


@pytest.mark.parametrize("fired_by", ["pr-cycle-tick", "post-plan-now"])
def test_enrolled_slug_keeps_arm_on_non_automouse_rerun(tmp_path, fired_by):
    first = _assign("slug-1", tmp_path)
    again = _assign("slug-1", tmp_path, fired_by=fired_by)
    assert again.arm == first.arm
    assert again.source == "enrolled"
    assert _assign("slug-new", tmp_path, fired_by=fired_by).source == "not-queued"
