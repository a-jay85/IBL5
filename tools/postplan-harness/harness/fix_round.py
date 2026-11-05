"""One in-run fix round for holds on arming conditions {2, 3, 11}.

Owns no fixer. It sequences the two that exist, ``thread_ingestion.disposition_thread``
(review threads and unthreaded findings) and ``conformance_fix.remediate_missing`` (MISSING
items), and adds only the budget, the accept-or-discard decision and the deferred reply flush.
Nothing is pushed or posted to GitHub inside the round. The runner pushes an accepted round
and then calls ``flush``; a rejected round leaves head and ``res`` as found.
"""
from __future__ import annotations

import json
import os
import sys
import time
from typing import Mapping

from . import armable, conformance_fix, same_run_threads, thread_ingestion
from .adapters import llm as llm_mod
from .labels import render_body
from .state import HarnessError

ENV_FLAG = "POSTPLAN_FIX_ROUND"
TARGET_CONDITIONS = frozenset({2, 3, 11})
MAX_FIX_ROUNDS = 1          # asserted by test; the round has no loop
MAX_ITEMS = 8               # threads first, then unthreaded findings
DEADLINE_S = 1200           # wall clock for the whole round, re-check included
RESULT_BASENAME = "fix-round.json"

_SNAPSHOT_FIELDS = ("findings", "scored_findings", "degraded_agents",
                    "unresolved_conformance", "review_delta")


class _Reject(Exception):
    def __init__(self, reason: str):
        super().__init__(reason)
        self.reason = reason


def enabled(env: Mapping[str, str] | None = None) -> bool:
    env = os.environ if env is None else env
    return env.get(ENV_FLAG, "") != "0"


def targets(decision) -> list[int]:
    return sorted(c.number for c in decision.holds if c.number in TARGET_CONDITIONS)


def collect_items(gh, pr, res, actions, record: dict | None = None) -> list[dict]:
    """Threads first (trusted authors only), then unthreaded holding findings."""
    record = record if record is not None else {}
    items: list[dict] = []
    rows = thread_ingestion.fetch_trusted_threads(
        gh, pr, gh.pr_thread_ids(pr), include_outdated=True)
    if rows is None:
        record["thread_fetch"] = "failed"
    else:
        rows, _deferred = thread_ingestion.cap_outdated(rows)
        for t in rows:
            if thread_ingestion._is_gate_held(t):
                continue
            items.append(dict(t, threaded=True))
    posted = same_run_threads.posted_keys(actions)
    for f in armable.holding_findings(res.findings):
        body = render_body(f, same_run_threads.POSTED_BODY_LIMIT)
        if same_run_threads._key(f.path, body) in posted:
            continue
        items.append({"commentId": None, "path": f.path, "line": f.line, "body": body,
                      "authorLogin": "review-agent", "authorType": "Bot", "outdated": False,
                      "isResolved": False, "threaded": False})
    if len(items) > MAX_ITEMS:
        record["overflow"] = len(items) - MAX_ITEMS
        items = items[:MAX_ITEMS]
    return items


def confix_items(res) -> list[str]:
    """MISSING items Phase 5.0-pre did not already attempt (the fixer is deterministic)."""
    tried = set((res.conformance_fix or {}).get("items") or ())
    return [i for i in res.unresolved_conformance if i not in tried]


def clean_tree(git) -> None:
    """Drop every uncommitted edit, staged or not, and remove untracked files.

    The round starts from a clean tree, so anything dirty now is the fixer's leftover. The
    undo (`reset --keep`) refuses to run over such edits."""
    if not git.is_dirty():
        return
    paths = git.dirty_paths()
    git.unstage_paths(paths)
    git.restore_paths(paths)


class DeferredPosts:
    """Wraps ``gh`` for the re-check's delta review: review comments are held, not posted.

    Everything else passes through. ``replay`` posts the held comments once the round's
    commit is pushed; a rejected round simply drops them."""

    def __init__(self, gh):
        self._gh = gh
        self.held: list[tuple] = []

    def __getattr__(self, name):
        return getattr(self._gh, name)

    def post_review_findings(self, pr, head_sha, title, findings):
        self.held.append(("findings", pr, title, findings))

    def post_review_summary(self, pr, title, body):
        self.held.append(("summary", pr, title, body))

    def replay(self, pushed_sha: str, log) -> int:
        n = 0
        for kind, pr, title, payload in self.held:
            try:
                if kind == "findings":
                    self._gh.post_review_findings(pr, pushed_sha, title, payload)
                else:
                    self._gh.post_review_summary(pr, title, payload)
                n += 1
            except (HarnessError, OSError) as e:
                log(f"fix-round: held review post '{title}' failed ({e!r})")
        self.held = []
        return n


def snapshot(res) -> dict:
    return {k: list(getattr(res, k)) for k in _SNAPSHOT_FIELDS}


def restore(res, snap: dict) -> None:
    for k, v in snap.items():
        setattr(res, k, list(v))


def _write(out_dir: str, record: dict) -> dict:
    try:
        os.makedirs(out_dir, exist_ok=True)
        with open(os.path.join(out_dir, RESULT_BASENAME), "w") as fh:
            json.dump(record, fh, default=str)
    except OSError:
        pass
    return record


def run_fix_round(*, decision, res, gh, llm, git, worktree, plan, pr, out_dir, log,
                  commit, recheck, already_tried, clock=time.monotonic, env=None) -> dict:
    """Run at most one round. Always writes ``fix-round.json`` and returns the record.

    ``record["status"]`` is one of skipped, no-change, accepted, rejected, aborted.
    """
    if res.fix_round:
        return res.fix_round
    tg = targets(decision)
    record: dict = {"status": "skipped", "reason": "", "targets": tg, "items": [],
                    "defer": [], "sha": "", "reevaluate": False, "head_before": ""}
    if not enabled(env):
        record["reason"] = "disabled"
        return _write(out_dir, record)
    if not tg:
        record["reason"] = "no-target-hold"
        return _write(out_dir, record)
    if already_tried:
        record["reason"] = "holdrepeat-same-holds"
        return _write(out_dir, record)

    head_before = git.head()
    record["head_before"] = head_before
    snap = snapshot(res)
    t0 = clock()
    deadline = t0 + DEADLINE_S
    defer: list[dict] = []

    def _check_deadline() -> int:
        remaining = deadline - clock()
        if remaining <= 0:
            raise _Reject("timeout")
        return remaining

    def _discard(cause) -> None:
        restore(res, snap)
        try:
            clean_tree(git)
        except Exception:  # noqa: BLE001 - the undo below fails closed if the tree stayed dirty
            pass
        err = git.drop_commit(head_before)
        if err or git.head() != head_before:
            raise HarnessError("fix-round-undo-failed",
                               f"could not undo to {head_before}: {str(err)[:200]}") from cause
        record["defer"] = []
        record["items"] = [dict(r, outcome="skipped") if r.get("outcome") != "skipped" else r
                           for r in record["items"]]
        record["reevaluate"] = False

    # A UsagePause (BaseException) must reset the tree too. It is handled in the finally
    # below so no handler can swallow it: the pause keeps propagating after the undo.
    settled = False
    try:
        items = collect_items(gh, pr, res, gh.actions(), record)
        for item in items:
            remaining = _check_deadline()
            info: dict = {}
            outcome = thread_ingestion.disposition_thread(
                item, llm, git, worktree, pr, gh, log,
                commit=commit, push=lambda: "", defer=defer, info=info,
                timeout=max(1, int(min(remaining, llm_mod.TOOLED_TIMEOUT))))
            record["items"].append({
                "commentId": item.get("commentId"), "outcome": outcome, "sha": None,
                "path": item.get("path"), "outdated": bool(item.get("outdated")),
                "body_head": (item.get("body") or "")[:160],
                "threaded": bool(item.get("threaded")),
                "author": item.get("authorLogin")})
            _check_deadline()
        mi = confix_items(res) if 3 in tg else []
        if mi:
            _check_deadline()
            record["confix"] = conformance_fix.remediate_missing(
                llm, git, plan, worktree, out_dir, mi,
                commit=commit, push=lambda: "", log=log)
            _check_deadline()
        if git.head() == head_before:
            record["status"] = "no-change"
            record["reason"] = "no-commit"
            record["reevaluate"] = bool(defer)
            record["defer"] = defer
            settled = True
            return _write(out_dir, record)
        rc = recheck(head_before) or {}
        if rc.get("new_holding"):
            raise _Reject("new-finding")
        if rc.get("conformance_added"):
            raise _Reject("conformance-worse")
        if rc.get("degraded"):
            raise _Reject("recheck-degraded")
        _check_deadline()
        record["status"] = "accepted"
        record["reason"] = "recheck-clean"
        record["sha"] = git.head()
        record["defer"] = defer
        record["reevaluate"] = True
        settled = True
        return _write(out_dir, record)
    except Exception as e:
        settled = True
        _discard(e)
        if isinstance(e, _Reject):
            record["status"], record["reason"] = "rejected", e.reason
        elif isinstance(e, HarnessError):
            record["status"], record["reason"] = "rejected", f"error:{e.kind}"
        else:
            record["status"], record["reason"] = "rejected", f"error:{type(e).__name__}"
        log(f"fix-round: rejected ({record['reason']}); tree reset to {head_before[:10]}")
        return _write(out_dir, record)
    finally:
        if not settled:
            inflight = sys.exc_info()[1]
            _discard(inflight)
            record["status"] = "aborted"
            record["reason"] = type(inflight).__name__ if inflight is not None else "interrupted"
            _write(out_dir, record)


def flush(gh, pr, record: dict, pushed_sha: str, log) -> int:
    """Post the deferred resolve replies once the round's commit is pushed. Returns count."""
    posted = 0
    for d in record.get("defer") or ():
        cid = d.get("comment_id")
        if cid is None:
            continue
        if d.get("kind") == "fixed":
            body = thread_ingestion.fixed_body(pushed_sha, d.get("reason", ""))
        else:
            body = thread_ingestion.declined_body(d.get("reason", ""))
        try:
            gh.resolve_review_thread(pr, cid, body)
            posted += 1
        except (HarnessError, OSError) as e:
            d["flush_failed"] = True
            log(f"fix-round: reply on thread {cid} failed ({e!r}); thread stays open")
            continue
        if d.get("followup"):
            thread_ingestion.file_declined_followup(gh, pr, cid, d.get("path"), d.get("line"),
                                                    d["followup"], d.get("reason", ""), log)
    return posted


def prune_keys(record: dict) -> set:
    """Keys of rows whose finding the round released from conditions (2) and (11)."""
    keys = set()
    for row in record.get("items") or ():
        outcome = row.get("outcome")
        ok = outcome in ("fixed", "declined") if row.get("threaded") else outcome == "fixed"
        if ok:
            keys.add(same_run_threads._key(row.get("path"), row.get("body_head")))
    return keys


def summary_line(record: dict) -> str:
    """One sticky line per status; empty when the round did not run or was disabled."""
    status = record.get("status")
    items = record.get("items") or []
    fixed = sum(1 for r in items if r.get("outcome") == "fixed")
    declined = sum(1 for r in items if r.get("outcome") == "declined")
    if status == "accepted":
        return (f"Fix round: fixed {fixed} of {len(items)} items in {record.get('sha', '')[:12]}; "
                "re-reviewed and re-evaluated on the new head")
    if status == "no-change" and record.get("reevaluate"):
        return f"Fix round: no code change; {declined} threads declined"
    if status == "rejected":
        return (f"Fix round: discarded ({record.get('reason', '')}); "
                "this verdict is the pre-round hold")
    if status == "skipped" and record.get("reason") == "holdrepeat-same-holds":
        return "Fix round: skipped (already tried for these holds)"
    return ""
