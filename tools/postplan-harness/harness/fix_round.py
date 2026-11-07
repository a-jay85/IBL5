"""One in-run fix round for holds on arming conditions {2, 3, 11, 12, 14, 19}.

Owns no fixer for review work. It sequences the ones that exist:
``thread_ingestion.disposition_thread`` (review threads, unthreaded findings, and test-only
strengthening for a HELD mutation row), ``conformance_fix.remediate_missing`` (MISSING items)
and the runner's ``fidelity_fix`` and ``conflict_rereview`` callables. It adds the budget, the
accept-or-discard decision, the deferred reply flush, and the one repair it makes itself: a new
``match`` for a stale Executable Mutations row, checked by ``mutation_repair``.
Nothing is pushed or posted to GitHub inside the round, and the plan file is never written
inside it. The runner pushes an accepted round, calls ``flush`` and ``apply_plan_repair``; a
rejected round leaves head and ``res`` as found.
"""
from __future__ import annotations

import hashlib
import json
import os
import shutil
import sys
import tempfile
import time
from typing import Mapping

from . import armable, conformance_fix, mutation_repair, reviewcache, same_run_threads, thread_ingestion
from .adapters import llm as llm_mod
from .labels import render_body
from .state import HarnessError

ENV_FLAG = "POSTPLAN_FIX_ROUND"
TARGET_CONDITIONS = frozenset({2, 3, 11, 12, 14, 19})
REVIEW_CONDITIONS = frozenset({2, 3, 11})
MUTATION_CONDITION = 19
FIDELITY_CONDITION = 12
CONFLICT_CONDITION = 14
REPAIRED_PLAN_BASENAME = "fix-round-plan.md"
ORIGINAL_PLAN_BASENAME = "fix-round-plan.orig.md"
_DECIDED_VERDICTS = ("CONFLICT-REVIEW=CLEAN", "CONFLICT-REVIEW=FOUND-PROBLEM")
_UNREADABLE_DELTA = "<unreadable-delta>"
MAX_FIX_ROUNDS = 1          # asserted by test; the round has no loop
MAX_ITEMS = 8               # threads first, then unthreaded findings
DEADLINE_S = 1200           # wall clock for the whole round, re-check included
RESULT_BASENAME = "fix-round.json"
SECURITY_SOURCE = "security-audit"
SECURITY_AGENT = "security"
SECURITY_PURPOSE = "security-audit"

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


def collect_items(gh, pr, res, actions, record: dict | None = None, *,
                  security_bar=None) -> list[dict]:
    """Threads first (trusted authors only), then unthreaded holding findings.

    A security-audit item reaches the fixer only while `security_bar()` passes. Below
    the bar it stays out of the round and keeps holding arming condition (2), so the
    owner sees it. The bar is evaluated once, and only when a security item appears."""
    record = record if record is not None else {}
    items: list[dict] = []
    sec_flags: list[bool] = []
    excluded = 0
    verdict: list = []

    def _security_allowed() -> bool:
        if not verdict:
            from .reviewer_bar import Verdict
            try:
                v = security_bar() if security_bar is not None else Verdict(False, "no-bar")
            except Exception as e:  # noqa: BLE001 - the bar fails closed
                v = Verdict(False, f"error: {e!r}"[:200])
            record["security_bar"] = ("PASS " if v.passed else "FAIL ") + v.reason
            verdict.append(bool(v.passed))
        return verdict[0]

    rows = thread_ingestion.fetch_trusted_threads(
        gh, pr, gh.pr_thread_ids(pr), include_outdated=True)
    if rows is None:
        record["thread_fetch"] = "failed"
    else:
        rows, _deferred = thread_ingestion.cap_outdated(rows)
        for t in rows:
            if thread_ingestion._is_gate_held(t):
                continue
            if t.get("agent") == SECURITY_AGENT and not _security_allowed():
                excluded += 1
                continue
            items.append(dict(t, threaded=True))
            sec_flags.append(t.get("agent") == SECURITY_AGENT)
    posted = same_run_threads.posted_keys(actions)
    for f in armable.holding_findings(res.findings):
        if f.source == SECURITY_SOURCE and not _security_allowed():
            excluded += 1
            continue
        body = render_body(f, same_run_threads.POSTED_BODY_LIMIT)
        if same_run_threads._key(f.path, body) in posted:
            continue
        items.append({"commentId": None, "path": f.path, "line": f.line, "body": body,
                      "authorLogin": "review-agent", "authorType": "Bot", "outdated": False,
                      "isResolved": False, "threaded": False})
        sec_flags.append(f.source == SECURITY_SOURCE)
    if len(items) > MAX_ITEMS and any(sec_flags[MAX_ITEMS:]):
        # The recheck rejects on any security hold, so a security item the cap would cut
        # dooms the round. Keep every security item out; they hold condition (2).
        record["security_overflow"] = sum(sec_flags)
        items = [i for i, s in zip(items, sec_flags) if not s]
        sec_flags = [False] * len(items)
    if len(items) > MAX_ITEMS:
        record["overflow"] = len(items) - MAX_ITEMS
        items = items[:MAX_ITEMS]
        sec_flags = sec_flags[:MAX_ITEMS]
    if sum(sec_flags):
        record["security_items"] = sum(sec_flags)
    if excluded:
        record["security_excluded"] = excluded
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


def _held_row_item(row) -> dict:
    """One fixer item for a HELD row, in the shape `collect_items` gives an unthreaded finding."""
    body = (f"Executable Mutations row {row.row} HELD: with {row.match} replaced by {row.replace} "
            f"in {row.file}, the check {row.check} still passes. Add or strengthen a test so this "
            f"check fails under that mutation. Edit test files only. Do not edit {row.file}, the "
            "plan, or any gate file.")
    return {"commentId": None, "path": row.file, "line": None, "body": body,
            "authorLogin": "exec-mutations", "authorType": "Bot", "outdated": False,
            "isResolved": False, "threaded": False}


def _row_repair_prompt(row) -> str:
    return (f"Executable Mutations row {row.row} names code that no longer exists in {row.file}.\n"
            f"Old match: {row.match}\n"
            f"Replacement the row applies: {row.replace}\n\n"
            f"Read {row.file} and find the current wording of the same code. Do not move the row "
            "to different code. The replacement stays as written, so the new match must be text "
            "it can stand in for.\n"
            "Reply with exactly one line and nothing else:\n"
            "NEW-MATCH: <one line of text that occurs exactly once in the file>\n")


def _remove_quietly(path: str | None) -> None:
    if not path:
        return
    try:
        os.remove(path)
    except OSError:
        pass


def run_fix_round(*, decision, res, gh, llm, git, worktree, plan, pr, out_dir, log,
                  commit, recheck, already_tried, clock=time.monotonic, env=None,
                  inputs=None, mutation_check=None, fidelity_fix=None,
                  conflict_rereview=None, security_bar=None) -> dict:
    """Run at most one round. Always writes ``fix-round.json`` and returns the record.

    ``record["status"]`` is one of skipped, no-change, accepted, rejected, aborted.
    """
    if res.fix_round:
        return res.fix_round
    tg = targets(decision)
    record: dict = {"status": "skipped", "reason": "", "targets": tg, "items": [],
                    "defer": [], "sha": "", "reevaluate": False, "head_before": "",
                    "row_repairs": [], "mutation": {}, "fidelity": {}, "conflict_rereview": {}}
    if not enabled(env):
        record["reason"] = "disabled"
        return _write(out_dir, record)
    if not tg:
        record["reason"] = "no-target-hold"
        return _write(out_dir, record)
    if already_tried:
        record["reason"] = "holdrepeat-same-holds"
        return _write(out_dir, record)
    mutation_holding = getattr(inputs, "exec_mutations_holding", None) or ()
    held, stale = (mutation_repair.eligible_rows(mutation_holding)
                   if MUTATION_CONDITION in tg else ([], []))
    review_tg = set(tg) & REVIEW_CONDITIONS
    if (not review_tg and not held and not stale
            and (FIDELITY_CONDITION not in tg or fidelity_fix is None)
            and (CONFLICT_CONDITION not in tg or conflict_rereview is None)):
        record["reason"] = "no-eligible-hold"
        return _write(out_dir, record)

    head_before = git.head()
    record["head_before"] = head_before
    snap = snapshot(res)
    t0 = clock()
    deadline = t0 + DEADLINE_S
    defer: list[dict] = []
    before_holding = mutation_repair.holding_row_ids(mutation_holding)
    held_worked: list[str] = []
    held_committed: list[str] = []
    held_changed = False

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
        record["row_repairs"] = []
        _remove_quietly(record.pop("repaired_plan", None))

    def _dispose(item: dict) -> None:
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

    def _plan_rows():
        rows = mutation_repair.parse_rows(getattr(plan, "path", "") or "", worktree)
        if rows is None:
            record["mutation"]["skipped"] = "parse-failed"
        return rows

    def _repair_stale_rows() -> None:
        """Propose a new match per stale row into a copy of the plan. `plan.path` stays as is."""
        old_rows = _plan_rows()
        if old_rows is None:
            return
        with open(plan.path, "rb") as fh:
            raw = fh.read()
        text = raw.decode("utf-8", "surrogateescape")
        by_id = {r.row: r for r in old_rows}
        proposals: dict[str, str] = {}
        dropped: list[str] = []
        for row_id in stale:
            row = by_id.get(row_id)
            if row is None:
                dropped.append(f"row {row_id}: not-in-plan")
                continue
            remaining = _check_deadline()
            reply = llm.call_tooled("fix-round-row-repair", "sonnet", _row_repair_prompt(row),
                                    cwd=worktree, allowed_tools=("Read",),
                                    denied_tools=("Bash", "Agent", "Write", "Edit"),
                                    timeout=max(1, int(min(remaining, llm_mod.TOOLED_TIMEOUT))))
            _check_deadline()
            new_match = mutation_repair.parse_new_match(reply)
            if new_match is None:
                dropped.append(f"row {row_id}: no-new-match")
                continue
            rewritten = mutation_repair.rewrite_match(text, row_id, new_match)
            if rewritten is None:
                dropped.append(f"row {row_id}: rewrite-refused")
                continue
            text = rewritten
            proposals[row_id] = new_match
        if dropped:
            record["mutation"]["dropped"] = dropped
        if not proposals:
            return
        os.makedirs(out_dir, exist_ok=True)
        copy_path = os.path.join(out_dir, REPAIRED_PLAN_BASENAME)
        with open(copy_path, "wb") as fh:
            fh.write(text.encode("utf-8", "surrogateescape"))
        new_rows = mutation_repair.parse_rows(copy_path, worktree)
        if new_rows is None:
            reasons = ["reparse-failed"]
        else:
            reasons = mutation_repair.validate_repair(old_rows, new_rows, set(proposals),
                                                      read_file=git.read_worktree_file)
            new_by_id = {r.row: r for r in new_rows}
            reasons += [f"proposal-mismatch row {row_id}" for row_id, match in proposals.items()
                        if row_id not in new_by_id or new_by_id[row_id].match != match]
        if reasons:
            _remove_quietly(copy_path)
            record["mutation"]["refused"] = reasons
            return
        record["row_repairs"] = [{"row": row_id, "file": by_id[row_id].file,
                                  "old_match": by_id[row_id].match, "new_match": match}
                                 for row_id, match in proposals.items()]
        record["repaired_plan"] = copy_path
        record["plan_sha256"] = hashlib.sha256(raw).hexdigest()

    def _prove_mutations() -> None:
        """A strengthened test or a repaired row counts only if the row now trips."""
        if not held_changed and not record["row_repairs"]:
            return
        if mutation_check is None:
            raise _Reject("mutation-unproven")
        wanted = held_committed + [r["row"] for r in record["row_repairs"]]
        report = mutation_check(record.get("repaired_plan") or plan.path,
                                max(1, int(_check_deadline())))
        if not mutation_repair.proof_ok(report, wanted, before_holding):
            raise _Reject("mutation-unproven")

    def _rereview_conflict() -> None:
        """Last, so the verdict binds to the head the re-evaluation sees. Never rejects."""
        if CONFLICT_CONDITION not in tg or conflict_rereview is None:
            return
        got = conflict_rereview()
        if got is not None:
            record["conflict_rereview"] = {"verdict": got[0], "calls": got[1], "reason": got[2]}

    # A UsagePause (BaseException) must reset the tree too. It is handled in the finally
    # below so no handler can swallow it: the pause keeps propagating after the undo.
    settled = False
    try:
        if review_tg:
            items = collect_items(gh, pr, res, gh.actions(), record,
                                  security_bar=security_bar)
        else:
            items = []
        for item in items:
            _dispose(item)
        mi = confix_items(res) if 3 in tg else []
        if mi:
            _check_deadline()
            record["confix"] = conformance_fix.remediate_missing(
                llm, git, plan, worktree, out_dir, mi,
                commit=commit, push=lambda: "", log=log)
            _check_deadline()
        if held:
            held_rows = _plan_rows()
            if held_rows is not None:
                by_row = {r.row: r for r in held_rows}
                held_worked = [row_id for row_id in held if row_id in by_row]
                head_held = git.head()
                pre_ids = reviewcache.per_file_patch_ids(git.diff_vs_base())
                for row_id in held_worked:
                    head_row = git.head()
                    _dispose(_held_row_item(by_row[row_id]))
                    if git.head() != head_row:
                        held_committed.append(row_id)
                touched = reviewcache.file_delta(pre_ids, reviewcache.per_file_patch_ids(git.diff_vs_base()))
                held_changed = git.head() != head_held
                if held_changed and not touched:
                    # A patch-id failure reads as "nothing changed". A new commit says otherwise.
                    touched = {_UNREADABLE_DELTA}
                if not all(mutation_repair.is_test_path(p) for p in touched):
                    raise _Reject("non-test-edit")
        if stale:
            _repair_stale_rows()
        if FIDELITY_CONDITION in tg and fidelity_fix is not None:
            record["fidelity"] = fidelity_fix(max(1, int(_check_deadline()))) or {}
            _check_deadline()
        if git.head() == head_before:
            _prove_mutations()
            _rereview_conflict()
            record["status"] = "no-change"
            record["reason"] = "no-commit"
            record["reevaluate"] = (bool(defer) or bool(record["row_repairs"])
                                    or record["conflict_rereview"].get("verdict") in _DECIDED_VERDICTS)
            record["defer"] = defer
            settled = True
            return _write(out_dir, record)
        if record.get("security_items", 0):
            # A blind Opus re-review of the post-fix tree; any remaining security
            # hold rejects the round, so the original hold goes to the owner.
            rc = recheck(head_before, security_seat="opus") or {}
            if rc.get("security_seat") != "opus":
                raise _Reject("security-recheck-seat")
            if rc.get("degraded"):
                raise _Reject("security-recheck-degraded")
            if any(f.source == SECURITY_SOURCE for f in armable.holding_findings(res.findings)):
                raise _Reject("security-recheck-not-clean")
        else:
            rc = recheck(head_before) or {}
        if rc.get("new_holding"):
            raise _Reject("new-finding")
        if rc.get("conformance_added"):
            raise _Reject("conformance-worse")
        if rc.get("degraded"):
            raise _Reject("recheck-degraded")
        _prove_mutations()
        _check_deadline()
        _rereview_conflict()
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


def apply_plan_repair(plan, record: dict, out_dir: str, log) -> bool:
    """Swap the proven repaired copy onto the plan. Called only after the round's push.

    Refuses when the plan changed during the round. The original stays in the run dir.
    """
    if not record.get("row_repairs"):
        return False
    tmp_path = None
    try:
        target = os.path.realpath(plan.path)
        with open(target, "rb") as fh:
            original = fh.read()
        if hashlib.sha256(original).hexdigest() != record.get("plan_sha256"):
            log("fix-round: plan changed during the round; row repair not applied")
            record["repair_applied"] = False
            _write(out_dir, record)
            return False
        with open(record["repaired_plan"], "rb") as fh:
            repaired = fh.read()
        with open(os.path.join(out_dir, ORIGINAL_PLAN_BASENAME), "wb") as fh:
            fh.write(original)
        fd, tmp_path = tempfile.mkstemp(dir=os.path.dirname(target), prefix=".fix-round-plan.")
        with os.fdopen(fd, "wb") as fh:
            fh.write(repaired)
        shutil.copymode(target, tmp_path)
        os.replace(tmp_path, target)
    except OSError as e:
        _remove_quietly(tmp_path)
        log(f"fix-round: row repair not applied ({e!r})")
        record["repair_applied"] = False
        _write(out_dir, record)
        return False
    record["repair_applied"] = True
    _write(out_dir, record)
    return True


def prune_keys(record: dict) -> set:
    """Keys of rows whose finding the round released from conditions (2) and (11)."""
    keys = set()
    for row in record.get("items") or ():
        outcome = row.get("outcome")
        ok = outcome in ("fixed", "declined") if row.get("threaded") else outcome == "fixed"
        if ok:
            keys.add(same_run_threads._key(row.get("path"), row.get("body_head")))
    return keys


def _extra_clauses(record: dict) -> str:
    """Sticky clauses for the repairs that stood and for a re-run conflict review."""
    clauses = ""
    if record.get("repair_applied") is not False:
        for r in record.get("row_repairs") or ():
            clauses += f"; repaired Executable Mutations row {r.get('row')} match in {r.get('file')}"
    verdict = (record.get("conflict_rereview") or {}).get("verdict")
    if verdict:
        clauses += f"; conflict review re-run: {verdict}"
    return clauses


def summary_line(record: dict) -> str:
    """One sticky line per status; empty when the round did not run or was disabled."""
    status = record.get("status")
    items = record.get("items") or []
    fixed = sum(1 for r in items if r.get("outcome") == "fixed")
    declined = sum(1 for r in items if r.get("outcome") == "declined")
    if status == "accepted":
        return (f"Fix round: fixed {fixed} of {len(items)} items in {record.get('sha', '')[:12]}; "
                "re-reviewed and re-evaluated on the new head" + _extra_clauses(record))
    if status == "no-change" and record.get("reevaluate"):
        return f"Fix round: no code change; {declined} threads declined" + _extra_clauses(record)
    if status == "rejected":
        return (f"Fix round: discarded ({record.get('reason', '')}); "
                "this verdict is the pre-round hold")
    if status == "skipped" and record.get("reason") == "holdrepeat-same-holds":
        return "Fix round: skipped (already tried for these holds)"
    return ""


def digest_rows(record: dict) -> list:
    """One merge-digest row per repair that reached the plan file."""
    if not record.get("repair_applied"):
        return []
    return [f"**Fix round repaired Executable Mutations row {r.get('row')} ({r.get('file')}):** "
            f"old match `{r.get('old_match')}`, new match `{r.get('new_match')}`"
            for r in record.get("row_repairs") or ()]
