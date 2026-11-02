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


MAX_CLOSER_SESSIONS = 3


def _locked_update(state_dir: str, fn) -> int:
    """Run `fn(data)` on the enrollment data under the shared lock; returns its int result.
    Any OS or shape problem returns 0 (a counter that cannot be read counts as zero)."""
    path = enrollment_path(state_dir)
    try:
        os.makedirs(state_dir, exist_ok=True)
        with open(os.path.join(state_dir, LOCK_BASENAME), "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX)
            try:
                if not os.path.exists(path):
                    return 0
                with open(path, encoding="utf-8") as fh:
                    data = json.load(fh)
                if not isinstance(data, dict) or data.get("v") != SCHEMA_VERSION \
                        or not isinstance(data.get("enrolled"), dict):
                    return 0
                return fn(data, path)
            finally:
                fcntl.flock(lock, fcntl.LOCK_UN)
    except (OSError, ValueError):
        return 0


def closer_sessions(state_dir: str, slug: str) -> int:
    """Closer sessions already spent on this PR (0 for an unenrolled slug)."""
    entry = read_enrollment(state_dir).get(safe_slug(slug))
    n = entry.get("closer_sessions", 0) if isinstance(entry, dict) else 0
    return n if isinstance(n, int) and n > 0 else 0


def record_closer_session(state_dir: str, slug: str) -> int:
    """Count one more closer session for an enrolled slug. Returns the new count, or 0
    for a slug with no enrollment entry (an env-forced run enrolls nothing)."""
    key = safe_slug(slug)

    def bump(data, path):
        entry = data["enrolled"].get(key)
        if not isinstance(entry, dict):
            return 0
        n = entry.get("closer_sessions", 0)
        entry["closer_sessions"] = (n if isinstance(n, int) and n > 0 else 0) + 1
        _write_atomic(path, data)
        return entry["closer_sessions"]
    return _locked_update(state_dir, bump)


OUTCOME_BASENAME = "ab-outcomes.jsonl"
OUTCOME_SCHEMA = "ab-outcome/1"


def outcome_row(res, *, run_started: str, run_finished: str) -> dict:
    """One line per enrolled run. The readout folds the lines per PR."""
    calls = list(getattr(getattr(res, "ledger", None), "calls", []) or [])
    closer = getattr(res, "closer", None) or {}
    return {
        "schema": OUTCOME_SCHEMA, "slug": safe_slug(res.slug), "pr": res.pr_number,
        "ab_arm": res.ab_arm, "ab_source": res.ab_source,
        "fired_by": getattr(res, "fired_by", None),
        "run_started": run_started, "run_finished": run_finished,
        "terminal": getattr(res.terminal, "value", res.terminal),
        "error_kind": res.error_kind,
        "llm_cost_usd": round(sum(float(c.cost_usd or 0) for c in calls), 4),
        "closer_ran": bool(closer.get("ran")), "closer_status": closer.get("status"),
        "closer_cost_usd": closer.get("cost_usd"), "closer_recheck": closer.get("recheck"),
    }
