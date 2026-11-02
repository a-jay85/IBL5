"""A/B arm assignment for the Opus closer experiment.

A pure decision module plus one locked state file. It imports nothing from `armable.py` and
holds no `gh` or `git` handle, so it cannot reach an arming path.

Arm order of evaluation: env override, empty env, invalid env, not automouse-queued, then the
locked enrollment file. Every failure falls back to the harness arm; the A/B must never change
a post-plan run's exit code.
"""
from __future__ import annotations

import fcntl
import hashlib
import json
import os
from dataclasses import dataclass
from typing import Callable, Mapping

from harness.statefile import safe_slug

ARMS = ("harness", "closer")
ENV_VAR = "POSTPLAN_AB_ARM"
ENROLL_CAP = 20
ENROLL_BASENAME = "ab-enrollment.json"
LOCK_BASENAME = "ab-enrollment.lock"
ENROLL_FIRED_BY = "automouse"
SCHEMA_VERSION = 1


@dataclass(frozen=True)
class ArmAssignment:
    arm: str       # "harness" | "closer"
    source: str    # "env" | "enrolled" | "new-enrollment" | "cap-reached" | "not-queued"
    #                | "state-unreadable" | "env-invalid"


def hash_arm(slug: str) -> str:
    """Stable arm for a slug. sha256, because `hash()` is salted per process."""
    return ARMS[hashlib.sha256(slug.encode("utf-8")).digest()[0] & 1]


def enrollment_path(state_dir: str) -> str:
    return os.path.join(state_dir, ENROLL_BASENAME)


def read_enrollment(state_dir: str) -> dict:
    """The `enrolled` map, `{}` when the file is absent or unreadable."""
    try:
        with open(enrollment_path(state_dir), encoding="utf-8") as fh:
            data = json.load(fh)
        if isinstance(data, dict) and data.get("v") == SCHEMA_VERSION \
                and isinstance(data.get("enrolled"), dict):
            return data["enrolled"]
    except (OSError, ValueError):
        pass
    return {}


def _write_atomic(path: str, data: dict) -> None:
    tmp = f"{path}.{os.getpid()}.tmp"
    with open(tmp, "w", encoding="utf-8") as fh:
        json.dump(data, fh, indent=2, sort_keys=True)
        fh.write("\n")
    os.replace(tmp, path)


def assign(slug: str, *, pr: int | None, fired_by: str, state_dir: str,
           env: Mapping[str, str], log: Callable[[str], None],
           now: Callable[[], str]) -> ArmAssignment:
    v = env.get(ENV_VAR, "")
    if v in ARMS:
        return ArmAssignment(v, "env")
    if v != "":
        log(f"ab-arm: ignoring invalid {ENV_VAR}={v!r}; using harness")
        return ArmAssignment("harness", "env-invalid")
    key = safe_slug(slug)
    if fired_by != ENROLL_FIRED_BY or key == "unknown":
        return ArmAssignment("harness", "not-queued")
    path = enrollment_path(state_dir)
    try:
        os.makedirs(state_dir, exist_ok=True)
        with open(os.path.join(state_dir, LOCK_BASENAME), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            try:
                return _assign_locked(key, pr, path, log, now)
            finally:
                fcntl.flock(lock, fcntl.LOCK_UN)
    except OSError as e:
        log(f"ab-arm: enrollment state error ({e!r}); using harness")
        return ArmAssignment("harness", "state-unreadable")


def _assign_locked(key: str, pr: int | None, path: str, log, now) -> ArmAssignment:
    if os.path.exists(path):
        try:
            with open(path, encoding="utf-8") as fh:
                data = json.load(fh)
            if not isinstance(data, dict) or data.get("v") != SCHEMA_VERSION \
                    or not isinstance(data.get("enrolled"), dict):
                raise ValueError("bad shape")
        except (ValueError, UnicodeDecodeError):
            log("ab-arm: enrollment state unreadable; using harness")
            return ArmAssignment("harness", "state-unreadable")
    else:
        data = {"v": SCHEMA_VERSION, "cap": ENROLL_CAP, "enrolled": {}}
    enrolled = data["enrolled"]
    entry = enrolled.get(key)
    if isinstance(entry, dict) and entry.get("arm") in ARMS:
        return ArmAssignment(entry["arm"], "enrolled")
    if len(enrolled) >= ENROLL_CAP:
        return ArmAssignment("harness", "cap-reached")
    arm = hash_arm(key)
    enrolled[key] = {"arm": arm, "pr": pr, "enrolled_at": now()}
    _write_atomic(path, data)
    return ArmAssignment(arm, "new-enrollment")
