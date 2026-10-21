"""Phase 4.5b: one bounded fix-or-decline pass over the threads this run posted.

Phase 4.5 acts only on trusted threads that existed before this run's review posted.
This pass runs after the review join and covers the threads this process posted itself.
It reuses the Phase 4.5 fixer and trust contract unchanged. A thread qualifies only when
all three hold: its id is new since the pre-posting snapshot, it passes the trust filter,
and its ``(path, body[:160])`` matches a ``pr_review_findings`` record this process wrote.
A thread another actor opened during the review window fails the last check and stays
open, so arming condition (11) still holds on it.

The pass runs once. After a fix commit it calls one delta re-review whose new findings
are posted and left open. They never re-enter candidates (``MAX_SAME_RUN_ROUNDS``).
Every failure path acts on nothing and leaves every hold in place.
"""
from __future__ import annotations

import re

from . import reviewcache
from . import thread_ingestion
from .state import HarnessError

MAX_SAME_RUN_ROUNDS = 1          # asserted by test; the pass has no loop
ORIGIN = thread_ingestion.ORIGIN_SAME_RUN
KEY_LEN = 160                    # matches review.py body_head and the per-thread body_head
_PROPAGATE_KINDS = ("gate-path-edit", "push-failed")

# Local copy of runner._redact's token rules: this module cannot import runner.
_REDACT_RULES = (
    (re.compile(r"(?i)\b([a-z][a-z0-9+.-]*://)[^/\s@]+@"), r"\1***@"),
    (re.compile(r"\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}"), "***"),
    (re.compile(r"\bgithub_pat_[A-Za-z0-9_]{20,}"), "***"),
    (re.compile(r"(?i)\b(bearer\s+)[A-Za-z0-9._~+/=-]+"), r"\1***"),
)


def _redact(text: str) -> str:
    for pattern, repl in _REDACT_RULES:
        text = pattern.sub(repl, text)
    return text


def _key(path, body) -> tuple[str, str]:
    return (path or "", (body or "")[:KEY_LEN])


def candidate_ids(post_ids: set[int], pre_ids: set[int]) -> set[int]:
    """Thread ids new since the pre-posting snapshot. A failed post snapshot is empty."""
    return set(post_ids or ()) - set(pre_ids or ())


def posted_keys(actions: list[dict]) -> set[tuple[str, str]]:
    """``(path, body prefix)`` of every finding this process posted as a review comment."""
    keys: set[tuple[str, str]] = set()
    for a in actions or ():
        if a.get("action") != "pr_review_findings":
            continue
        for f in a.get("findings") or ():
            keys.add(_key(f.get("path"), f.get("body")))
    return keys


def this_run_only(keys):
    """``keep`` predicate for ``run_thread_ingestion``: only threads this run posted."""
    frozen = frozenset(keys)

    def keep(t: dict) -> bool:
        return _key(t.get("path"), t.get("body")) in frozen

    return keep


def dispositioned_keys(result: dict) -> set[tuple[str, str]]:
    """Keys of the threads the fixer FIXED or DECLINED. A skipped thread is never pruned."""
    delta = (result or {}).get("delta_review")
    if delta is not None and delta.get("new_findings") is None:
        return set()  # the fix went unreviewed, so nothing is released from condition (2)
    return {_key(row.get("path"), row.get("body_head"))
            for row in (result or {}).get("threads") or ()
            if row.get("outcome") in ("fixed", "declined")}


def prune_findings(findings, scored, keys) -> tuple[list, list]:
    """Drop the findings (and scored rows) whose thread the fixer dispositioned.

    Arming condition (2) reads ``res.findings`` from memory, so this is what releases it
    for a finding this run fixed or declined. Everything else is kept unchanged.
    """
    keys = set(keys or ())
    kept = [f for f in findings or () if _key(f.path, f.body) not in keys]
    kept_scored = [r for r in scored or ()
                   if _key(r.get("path"), r.get("body_head")) not in keys]
    return kept, kept_scored


def empty_result(reason: str) -> dict:
    return {"found": 0, "fixed": 0, "declined": 0, "skipped": 0, "last_sha": None,
            "origin": ORIGIN, "rounds": 0, "reason": reason}


def run_same_run_pass(gh, llm, git, worktree, pr, pre_posting_ids, out_dir, log, *,
                      commit, push, outcome_log_path, review_delta) -> dict:
    """One round over this run's own threads, then at most one delta re-review."""
    post_ids = gh.pr_thread_ids(pr)
    cands = candidate_ids(post_ids, pre_posting_ids)
    if not cands:
        log("phase4.5b: no same-run threads")
        return empty_result("no-same-run-threads")
    keys = posted_keys(gh.actions())
    if not keys:
        log("phase4.5b: nothing posted by this run")
        return empty_result("nothing-posted")
    before = reviewcache.per_file_patch_ids(git.diff_vs_base())
    try:
        out = thread_ingestion.run_thread_ingestion(
            gh, llm, git, worktree, pr, cands, out_dir, log,
            commit=commit, push=push, outcome_log_path=outcome_log_path,
            origin=ORIGIN, keep=this_run_only(keys))
    except HarnessError as e:
        if e.kind in _PROPAGATE_KINDS:
            raise
        return _failed(e, log)
    except Exception as e:  # noqa: BLE001 - any other fault acts on nothing
        return _failed(e, log)
    out["rounds"] = MAX_SAME_RUN_ROUNDS
    if out.get("last_sha"):
        after = reviewcache.per_file_patch_ids(git.diff_vs_base())
        delta = reviewcache.file_delta(before, after)
        new_findings = 0
        if delta:
            try:
                new_findings = review_delta(delta)
            except Exception as e:  # noqa: BLE001 - indeterminate; posted threads hold via (11)
                log(f"phase4.5b: delta re-review failed ({_redact(repr(e))[:200]})")
                new_findings = None
        out["delta_review"] = {"paths": sorted(delta), "new_findings": new_findings}
    return out


def _failed(e, log) -> dict:
    detail = _redact(repr(e))[:200]
    log(f"phase4.5b: same-run pass failed ({detail}); condition (11) keeps the hold")
    res = empty_result("error")
    res["error"] = detail
    return res
