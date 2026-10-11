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
        os.makedirs(os.path.dirname(path) or state_dir, exist_ok=True)
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


def restore_review(arm, gates):
    """Rebuild ReviewPhase.run's 4-tuple from a cached `review` arm, or None (= miss)."""
    from harness.state import Finding

    if not isinstance(arm, dict) or arm.get("degraded_agents") != []:
        return None
    if arm.get("gates") != gates:
        return None
    raw, scored = arm.get("findings"), arm.get("scored_findings")
    if not isinstance(raw, list) or not isinstance(scored, list):
        return None
    findings = []
    for d in raw:
        if not isinstance(d, dict) or not isinstance(d.get("score"), int) \
                or isinstance(d.get("score"), bool):
            return None
        try:
            findings.append(Finding(**d))
        except TypeError:
            return None
    return (findings, gates, [dict(s) for s in scored], [])


def file_delta(prior, current) -> set:
    """Paths added, removed, or whose per-file patch-id changed."""
    prior, current = prior or {}, current or {}
    return {p for p in set(prior) | set(current) if prior.get(p) != current.get(p)}


def subset_diff(diff: str, paths) -> str:
    return "".join(s for p, s in split_diff_by_file(diff).items() if p in paths)


def reusable_by_path(findings, scored, delta, current_files):
    """Cached findings (and scored rows) on files that are still present and unchanged.

    Path-less findings are dropped: the agents that produce them re-run on the subset
    with the full PR metadata and regenerate them.
    """
    def keep(path) -> bool:
        return isinstance(path, str) and bool(path) and path in current_files and path not in delta

    return ([f for f in findings if keep(getattr(f, "path", None))],
            [s for s in scored if isinstance(s, dict) and keep(s.get("path"))])


FIDELITY_REUSABLE_VERDICTS = ("READY", "READY WITH NOTES")


def fidelity_reusable(record, version, diff_id, plan_hash):
    """The producing run id when the cached fidelity arm may stand in, else None."""
    if not isinstance(record, dict):
        return None
    arm = record.get("fidelity")
    key = record.get("key")
    if not isinstance(arm, dict) or not isinstance(key, dict):
        return None
    if not version or key.get("version") != version:
        return None
    if not diff_id or not plan_hash:
        return None
    if arm.get("diff_id") != diff_id or arm.get("plan_hash") != plan_hash:
        return None
    if arm.get("verdict") not in FIDELITY_REUSABLE_VERDICTS or arm.get("remediated") is not False:
        return None
    run_id = arm.get("run_id") or record.get("run_id")
    return run_id if isinstance(run_id, str) and run_id else None


def conflict_only_failure(record, key, current_files, pre_patch_id, manifest_paths, verdict_ok) -> str:
    """"" when the delta since the cached diff is proven conflict-resolution only; else
    the first failing clause."""
    if not isinstance(record, dict) or not isinstance(record.get("key"), dict):
        return "no-record"
    if not isinstance(record.get("review"), dict) or not isinstance(record.get("fidelity"), dict):
        return "arm-missing"
    stored = record["key"]
    for f in KEY_FIELDS:
        if f == "diff_id":
            continue
        if not stored.get(f) or stored.get(f) != (key or {}).get(f):
            return f"{f}-changed"
    if not key.get("diff_id") or key["diff_id"] == stored.get("diff_id"):
        return "diff-unchanged"
    if not isinstance(pre_patch_id, str) or len(pre_patch_id) != 40 \
            or any(c not in "0123456789abcdef" for c in pre_patch_id):
        return "no-pre-patch-id"
    if pre_patch_id != stored.get("diff_id"):
        return "pre-patch-id-mismatch"
    manifest = set(manifest_paths or ())
    if not manifest:
        return "empty-manifest"
    delta = file_delta(record.get("per_file_ids"), current_files)
    if not delta:
        return "empty-delta"
    if not delta <= manifest:
        return "delta-outside-manifest"
    if verdict_ok is not True:
        return "conflict-review-not-clean"
    return ""


def conflict_only_delta(record, key, current_files, pre_patch_id, manifest_paths, verdict_ok) -> bool:
    return conflict_only_failure(record, key, current_files, pre_patch_id,
                                 manifest_paths, verdict_ok) == ""


REVIEW_PURPOSES = ("review-agent-a", "review-agent-b", "review-agent-d",
                   "security-audit", "score-findings")


def restore_body_check(record, version, input_hash, validator):
    """(stored result, producing run id) when the body-check arm may stand in, else None."""
    import copy

    if not isinstance(record, dict) or not isinstance(record.get("key"), dict):
        return None
    if not version or record["key"].get("version") != version:
        return None
    arm = record.get("body_check")
    if not isinstance(arm, dict) or not input_hash or arm.get("input_hash") != input_hash:
        return None
    result = arm.get("result")
    if not isinstance(result, dict):
        return None
    try:
        validated = validator(copy.deepcopy(result))
    except Exception:
        return None
    if not isinstance(validated, dict):
        validated = result
    run_id = arm.get("run_id") or record.get("run_id")
    if not isinstance(run_id, str) or not run_id:
        return None
    return validated, run_id


def build_record(*, slug, pr_number, run_id, key, per_file_ids,
                 review=None, body_check=None, fidelity=None, now=None) -> dict:
    import datetime

    now = now or datetime.datetime.now(datetime.timezone.utc)
    return {"schema_version": SCHEMA_VERSION, "slug": slug, "pr_number": int(pr_number),
            "run_id": run_id, "written_at": now.strftime("%Y-%m-%dT%H:%M:%SZ"),
            "key": dict(key), "per_file_ids": dict(per_file_ids),
            "review": review, "body_check": body_check, "fidelity": fidelity}


def vet_review(findings, scored, degraded_agents, gates, run_id):
    import dataclasses

    if any(p in (degraded_agents or ()) for p in REVIEW_PURPOSES):
        return None
    if not isinstance(findings, list) or not isinstance(scored, list):
        return None
    out = []
    for f in findings:
        d = dataclasses.asdict(f) if dataclasses.is_dataclass(f) else f
        if not isinstance(d, dict) or not isinstance(d.get("score"), int) \
                or isinstance(d.get("score"), bool):
            return None
        out.append(d)
    return {"run_id": run_id, "gates": dict(gates), "findings": out,
            "scored_findings": [dict(s) for s in scored], "degraded_agents": []}


def vet_body_check(input_hash, result, degraded_agents, run_id):
    if "body-check" in (degraded_agents or ()) or not input_hash:
        return None
    if not isinstance(result, dict) or not isinstance(result.get("findings"), list):
        return None
    return {"run_id": run_id, "input_hash": input_hash, "result": result}


def vet_fidelity(fid, run_id):
    """A fresh, un-remediated READY / READY WITH NOTES verdict, else None."""
    fid = fid or {}
    verdict = fid.get("verdict_1")
    if verdict not in FIDELITY_REUSABLE_VERDICTS or fid.get("error_kind"):
        return None
    # The sticky's **Re-reviewed tree:** line derives from verdict_2; any remediation
    # round means the verdict judged a tree this run changed, so it is never cached.
    if fid.get("verdict_2") is not None or fid.get("remediation_sha") \
            or fid.get("rounds_completed") or fid.get("rounds"):
        return None
    if not fid.get("diff_id") or not fid.get("plan_hash"):
        return None
    return {"run_id": run_id, "verdict": verdict, "diff_id": fid["diff_id"],
            "plan_hash": fid["plan_hash"], "remediated": False}


def avoided_cost(sample_calls, absent_purposes) -> float:
    """Sum of `cost_usd` over `sample_calls` whose `purpose` is in `absent_purposes`.

    Prices what a cache hit skipped: `sample_calls` is a real run's ledger (dicts with
    `purpose`, `model`, `cost_usd`) and `absent_purposes` the purposes the hit never
    called. Carries no pricing table of its own."""
    absent = set(absent_purposes)
    return sum(float(c.get("cost_usd") or 0.0) for c in sample_calls
               if c.get("purpose") in absent)
