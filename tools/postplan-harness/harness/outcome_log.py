"""Review-finding outcome log: taxonomy, mapping, path resolution, append.

Pure standard library. Shared by the harness (in-process) and the skill-prose
path (through ``bin/review-outcomes``) so the two pipelines cannot drift on the
verdict-to-outcome mapping.
"""
from __future__ import annotations

import datetime
import json
import os
import subprocess

SCHEMA_VERSION = 1
LOG_BASENAME = "review-outcomes.jsonl"
ENV_OVERRIDE = "IBL_REVIEW_OUTCOMES_LOG"
REASON_CAP = 300  # chars; keeps every line far below PIPE_BUF (4096)
OUTCOMES = ("fixed", "declined-wrong", "intentional", "didnt-matter")
DECLINE_CATEGORIES = {
    "wrong": "declined-wrong",  # not a defect / the finding is mistaken
    "intentional": "intentional",  # the flagged behavior is deliberate
    "already-fixed": "fixed",  # a prior commit already addressed it
    "not-worth-it": "didnt-matter",  # real but out of scope / not worth changing
}


class OutcomeLogError(Exception):
    """The log path could not be resolved."""


def map_outcome(verdict, category):
    """Pure function of verdict + category. ``None`` means log nothing."""
    if verdict == "FIX":
        return "fixed"
    if verdict == "DECLINE":
        if isinstance(category, str):
            return DECLINE_CATEGORIES.get(category, "didnt-matter")
        return "didnt-matter"
    return None


def _clean_reason(reason) -> str:
    text = str(reason if reason is not None else "")
    for ch in ("\r", "\n", "\t"):
        text = text.replace(ch, " ")
    return text[:REASON_CAP]


def build_event(*, pr, finding_id, agent, score, outcome, source, basis, pipeline,
                reason="", sha=None, now=None) -> dict:
    ts_dt = now or datetime.datetime.now(datetime.timezone.utc)
    ts = ts_dt.strftime("%Y-%m-%dT%H:%M:%SZ")
    return {
        "v": SCHEMA_VERSION,
        "ts": ts,
        "pr": int(pr),
        "findingId": int(finding_id),
        "agent": agent or "unknown",
        "score": int(score) if score is not None else None,
        "outcome": outcome,
        "source": source,
        "basis": basis,
        "pipeline": pipeline,
        "reason": _clean_reason(reason),
        "sha": sha,
    }


def resolve_log_path(repo_dir, env=None) -> str:
    env = os.environ if env is None else env
    override = env.get(ENV_OVERRIDE)
    if override:
        return override
    try:
        proc = subprocess.run(
            ["git", "-C", repo_dir, "rev-parse", "--path-format=absolute", "--git-common-dir"],
            capture_output=True, text=True, check=False,
        )
    except OSError as e:
        raise OutcomeLogError(f"git unavailable: {e}") from e
    common = proc.stdout.strip()
    if proc.returncode != 0 or not common:
        raise OutcomeLogError(f"not a git repository: {repo_dir}")
    main = os.path.dirname(common.rstrip("/"))
    return os.path.join(main, "tools", "postplan-harness", "out", "state", LOG_BASENAME)


def append_events(path, events, log) -> int:
    """Append one JSON line per event. Never raises; returns events written."""
    written = 0
    try:
        os.makedirs(os.path.dirname(path), exist_ok=True)
        fd = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_APPEND, 0o644)
        try:
            for event in events:
                line = json.dumps(event, ensure_ascii=True, sort_keys=True) + "\n"
                os.write(fd, line.encode())
                written += 1
        finally:
            os.close(fd)
    except Exception as e:  # noqa: BLE001 - logging must never change a run
        log(f"outcome-log: append failed ({e!r}); continuing")
    return written


def read_events(path) -> list:
    """Parse the log; skip blank, corrupt, or foreign-version lines."""
    if not os.path.exists(path):
        return []
    events = []
    with open(path, encoding="utf-8", errors="replace") as fh:
        for raw in fh:
            raw = raw.strip()
            if not raw:
                continue
            try:
                obj = json.loads(raw)
            except ValueError:
                continue
            if isinstance(obj, dict) and obj.get("v") == SCHEMA_VERSION:
                events.append(obj)
    return events


def seen_finding_ids(path, pr) -> set:
    return {e["findingId"] for e in read_events(path) if e.get("pr") == int(pr)}


def sweep_events(threads, pr, seen, *, pipeline, now=None) -> list:
    """Outcomes for findings Phase 4.5 never answered, from GitHub thread facts only."""
    from .thread_ingestion import _is_trusted  # function-local: thread_ingestion imports this module
    out = []
    for t in threads or []:
        cid = t.get("commentId")
        if cid is None or t.get("score") is None:
            continue  # no <!-- score --> marker: not a posted finding
        if not _is_trusted(t.get("authorLogin") or "", t.get("authorType") or ""):
            continue  # forged marker from an untrusted commenter
        if int(cid) in seen:
            continue  # already logged by 4.5 or an earlier sweep
        if t.get("isOutdated"):
            outcome, basis = "fixed", "outdated"
        elif t.get("isResolved"):
            outcome, basis = "didnt-matter", "resolved-no-reply"
        else:
            continue  # still open: no outcome yet
        out.append(build_event(pr=pr, finding_id=cid, agent=t.get("agent"), score=t.get("score"),
                               outcome=outcome, source="sweep", basis=basis,
                               pipeline=pipeline, now=now))
        seen.add(int(cid))
    return out


def run_sweep(threads, pr, path, log, *, pipeline) -> int:
    """Log sweep events for unanswered findings. Never raises; returns events written."""
    try:
        seen = seen_finding_ids(path, pr)
        return append_events(path, sweep_events(threads, pr, seen, pipeline=pipeline), log)
    except Exception as e:  # noqa: BLE001 - logging must never change a run
        log(f"outcome-log: sweep failed ({e!r}); continuing")
        return 0
