"""Cross-run review cache keyed on the PR diff's patch-id.

One record per slug, `<state_dir>/<slug>.reviewcache.json`, holds three
independent reusable arms (review, body-check, fidelity) under one key: the
patch-id of the PR diff after Phase 4.5, the plan file hash, a computed
harness/prompt version hash, the PR title and the sorted file list.

Every reader fails closed: a missing, unreadable, schema-mismatched or
other-PR record is a miss, and the arm runs in full.
"""
from __future__ import annotations

import hashlib
import json
import os
import sys

from harness.statefile import safe_slug

SCHEMA_VERSION = 1
# Prompt-defining sources, relative to the harness root (tools/postplan-harness).
# runner.py is deliberately absent: it changes on every harness PR and would void
# the cache for edits that never touch a prompt.
PROMPT_SOURCE_FILES = (
    "harness/review.py",
    "harness/llm_calls.py",
    "harness/fidelity.py",
    "harness/schemas.py",
    "harness/reviewcache.py",
)
# Literal pins the hash also covers. test_model_pins_match_source greps review.py
# and runner.py so these cannot drift silently.
MODEL_PINS = (
    "review-agent-a:sonnet", "review-agent-b:sonnet", "review-agent-d:sonnet",
    "security-audit:sonnet", "score-findings:haiku", "body-check:sonnet",
)
REQUIRED_KEYS = ("schema_version", "slug", "pr_number", "run_id", "key", "per_file_ids")
KEY_FIELDS = ("diff_id", "plan_hash", "version", "pr_title", "file_list")


def record_path(state_dir: str, slug: str) -> str:
    return os.path.join(state_dir, f"{safe_slug(slug)}.reviewcache.json")


def load_record(state_dir, slug):
    try:
        with open(record_path(state_dir, slug)) as fh:
            doc = json.load(fh)
    except (OSError, ValueError):
        return None
    if not isinstance(doc, dict) or doc.get("schema_version") != SCHEMA_VERSION:
        return None
    if any(k not in doc for k in REQUIRED_KEYS):
        return None
    key = doc.get("key")
    if not isinstance(key, dict):
        return None
    if any(not isinstance(key.get(f), (str, list)) for f in KEY_FIELDS):
        return None
    if not isinstance(doc.get("pr_number"), int) or isinstance(doc.get("pr_number"), bool):
        return None
    if not isinstance(doc.get("per_file_ids"), dict):
        return None
    return doc


def save_record(state_dir, slug, doc) -> None:
    path = record_path(state_dir, slug)
    tmp = f"{path}.{os.getpid()}.tmp"
    try:
        os.makedirs(state_dir, exist_ok=True)
        with open(tmp, "w") as fh:
            fh.write(json.dumps(doc, indent=1, sort_keys=True))
        os.replace(tmp, path)
    except OSError as e:
        print(f"reviewcache: record write failed ({e!r}); continuing", file=sys.stderr)
        try:
            os.remove(tmp)
        except OSError:
            pass


def clear_record(state_dir, slug) -> None:
    try:
        os.remove(record_path(state_dir, slug))
    except OSError:
        pass


def _sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def version_hash(harness_root, procedure_texts) -> str:
    """sha256 over prompt sources, model pins, thresholds and procedure text.

    Takes no master SHA: only procedure content enters the hash, so a master
    move with identical text keeps the cache warm. Returns "" when any source
    is missing or unreadable (fail closed).
    """
    from harness import review  # lazy: review.py never imports this module

    h = hashlib.sha256()
    try:
        for rel in PROMPT_SOURCE_FILES:
            with open(os.path.join(harness_root, rel), "rb") as fh:
                h.update(f"{rel}\0{_sha256(fh.read())}\n".encode())
    except OSError:
        return ""
    for p in MODEL_PINS:
        h.update(f"pin:{p}\n".encode())
    h.update(f"threshold:code:{review.CODE_THRESHOLD}\n"
             f"threshold:sec:{review.SEC_THRESHOLD}\n".encode())
    for i, text in enumerate(procedure_texts):
        h.update(f"procedure:{i}:{_sha256(text.encode())}\n".encode())
    return h.hexdigest()


def make_key(*, diff_id, plan_hash, version, pr_title, file_list) -> dict:
    return {
        "diff_id": diff_id,
        "plan_hash": plan_hash,
        "version": version,
        "pr_title": pr_title,
        "file_list": sorted(set(file_list)),
    }


def key_matches(record, key) -> bool:
    if not record or not key:
        return False
    for f in ("diff_id", "version", "plan_hash"):
        if not isinstance(key.get(f), str) or not key[f]:
            return False
    stored = record.get("key")
    if not isinstance(stored, dict):
        return False
    return all(stored.get(f) == key.get(f) for f in KEY_FIELDS)


def pr_matches(record, pr_number) -> bool:
    try:
        return bool(record) and record.get("pr_number") == int(pr_number)
    except (TypeError, ValueError):
        return False


def split_diff_by_file(diff: str) -> dict:
    """{post-image path: section} for each `diff --git` section; preamble dropped."""
    sections: dict = {}
    path = None
    buf: list = []
    for line in (diff or "").splitlines(keepends=True):
        if line.startswith("diff --git "):
            if path is not None:
                sections[path] = "".join(buf)
            header = line.rstrip("\n")
            idx = header.rfind(" b/")
            path = header[idx + 3:] if idx >= 0 else header[len("diff --git "):]
            buf = [line]
        elif path is not None:
            buf.append(line)
    if path is not None:
        sections[path] = "".join(buf)
    return sections


def per_file_patch_ids(diff: str) -> dict:
    """{path: patch-id} per file section; {} when any section's id fails (fail closed)."""
    from harness import fidelity  # lazy: keeps the module import graph flat

    out = {}
    for path, section in split_diff_by_file(diff).items():
        pid = fidelity.diff_patch_id(section)
        if not pid:
            return {}
        out[path] = pid
    return out
