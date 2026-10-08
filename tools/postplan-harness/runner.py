#!/usr/bin/env python3
"""Compiled post-plan runner — the phase sequencer.

Code owns: sequencing, classification, conformance, verification aggregation,
all fifteen arming conditions, numbered 1–15 as in the skill ((11) unresolved review-thread
findings via bin/lib/pr-armable.sh, (12) the Phase 5.5 plan-fidelity verdict, (13) the
plan-slug-drift hold, (14) the conflict-resolved flag, (15) already-red CI checks), the Phase 5.5 sticky verdict comment, the review-owed decision that follows it,
CI-watch interpretation, terminal states,
side-effect gating, and the audit log. Bounded LLM calls own: PR copy, review/security
judgment, finding scoring, plan-blind manual-step classification, the add-only
safety verdict, and the retrospective.

Modes:
  replay           — point-in-time fixture from a historical trace; gh mutations
                     are recorded intents (out/actions.jsonl), never executed.
  isolated         — live git worktree + live verify, gh mutations record-only.
  isolated --live  — the INSTALLED mode (approved 2026-07-16): push to origin,
                     execute the eight allowlisted gh mutations (still audited to
                     actions.jsonl), watch CI. Phase 9 records a memory-save
                     intent only (no memory or registry write); Phase 10 is a
                     logged skip; Phase 11 removes the LLM adapter's temp cwd.
"""
from __future__ import annotations

import argparse
import concurrent.futures
import datetime
import hashlib
import json
import os
import re
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import (adr_draft, body_numbers, cifix, cifix_ship, ciwatch, conformance, fidelity, gitutil, holdrepeat, llm_calls,
                     manual_rows, manual_testing, outofscope, prosefix, rebase_cause, rules_budget_carveout,
                     schemas, scope_conformance, statefile, usage_pause)
from harness.armable import (AGGREGATOR_CONTEXT, ArmInputs, conflict_flag_path, conflict_verdict_for, evaluate,
                             manual_testing_clearance, meta_checks_clearance,
                             select_fidelity_verdict)
from harness.classify import (BACKLOG_REPO, FILES_CHANGED_BEGIN, FILES_CHANGED_END,
                              MERGE_DIGEST_BEGIN, MERGE_DIGEST_END, render_merge_digest,
                              upsert_merge_digest,
                              MANUAL_TESTING_SENTINEL, MANUAL_TESTING_SENTINEL_STATIC,
                              backlog_closes_mismatch, classify, files_from_diff,
                              modified_files_from_diff,
                              name_status_text, normalize_backlog_closes, numstat_text,
                              qualify_backlog_refs,
                              render_files_changed,
                              render_tests_changed,
                              render_residual_phases,
                              render_scope_notes,
                              restore_manual_testing_section, strip_manual_testing_section,
                              upsert_files_changed,
                              upsert_tests_changed,
                              upsert_hold_notice, upsert_residual_phases,
                              upsert_scope_notes)
from harness.gate_backtest import upsert_gate_backtest
from harness.gate_backtest_replay import gate_backtest_result
from harness.planfile import locate_plan
from harness.review import ReviewPhase
from harness import baseline_guard
from harness.state import (SUBPROCESS_TIMEOUT, HarnessError, RunResult, TerminalState,
                           UsageLedger)
from harness.thread_ingestion import run_thread_ingestion
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.adapters.gitad import (LiveGit, ReplayGit, classify_local_gate_denial,
                                    is_stale_base, is_stale_lease)
from harness.adapters.llm import ClaudeCli, FixtureLlm, TOOLED_TIMEOUT
from harness.adapters.probe import FixtureProbe, LiveProbe
from harness.adapters.verify import LiveVerify, ReplayVerify, aggregate, fail_log_lines, timing_log_line, tracks_log_line

# ── SIGTERM handler — abort any in-progress rebase before the process dies ───
_active_git: "LiveGit | None" = None  # set once in run() for the isolated/live path
_active_audit: "list[str] | None" = None  # the live run's audit list; read by the SIGTERM handler


def _last_audit_phase(audit) -> str:
    """The most recent `phaseN[.x]` token in the audit trail, or "start"."""
    for line in reversed(list(audit or [])[-200:]):
        m = re.search(r"\bphase\d+(?:\.\d+)?[a-z]?\b", line)
        if m:
            return m.group(0)
    return "start"


def _write_killed_line(signum: int) -> None:
    """Append one `RESULT: post-plan KILLED` line to $POSTPLAN_LOG_PATH. stdout is only
    flushed at exit, so without this a killed run leaves an empty log. One os.write on an
    O_APPEND fd (no buffered file object); never raises."""
    path = os.environ.get("POSTPLAN_LOG_PATH")
    if not path:
        return
    try:
        line = (f"RESULT: post-plan KILLED (signal {signum}) at "
                f"{_last_audit_phase(_active_audit)}\n")
        fd = os.open(path, os.O_WRONLY | os.O_APPEND | os.O_CREAT, 0o644)
        try:
            os.write(fd, line.encode())
        finally:
            os.close(fd)
    except Exception:
        pass


def _install_sigterm_handler() -> None:
    """Install a SIGTERM handler that cleans up any in-progress rebase."""
    import signal

    def _sigterm_handler(signum: int, frame: object) -> None:  # type: ignore[type-arg]
        git = _active_git
        if git is not None:
            try:
                git.emergency_abort()
            except Exception:
                pass
        _write_killed_line(signum)
        os._exit(128 + signum)

    signal.signal(signal.SIGTERM, _sigterm_handler)


def _post_status_label(gh, pr):
    if not pr:
        return
    # Set only by bin/post-plan-now; runs outside it never mark the PR, so nothing
    # is left orphaned when no wrapper exists to remove the label.
    label = os.environ.get("POSTPLAN_STATUS_LABEL")
    if not label:
        return
    try:
        gh.pr_status_label(pr, label)
    except Exception:
        pass


def _recheck_manual_rows(llm, probe, plan, cls, log, res) -> list:
    """Phase 6 attempt-then-demote: may drop a truly-manual row only when a
    concrete probe ran and returned (True, _). Any exception keeps all rows.
    Logs one line per row and appends each demotion to res.manual_demotions."""
    try:
        items = llm.call(
            "manual-recheck", "sonnet",
            llm_calls.manual_recheck_prompt(plan.truly_manual_rows, cls),
            schemas.validate_manual_recheck,
        )
    except Exception as exc:  # noqa: BLE001
        log(f"phase6 recheck: LLM call failed ({exc}) — keeping all rows held")
        return list(plan.truly_manual_rows)

    by_n = {item["n"]: item for item in items}
    surviving = []
    for row in plan.truly_manual_rows:
        n = int(row.number)
        item = by_n.get(n)
        if item is None or item.get("hold"):
            log(f"phase6 recheck: row {n} held (hold=true or absent)")
            surviving.append(row)
            continue
        argv = item.get("probe")
        if not isinstance(argv, list) or not argv:
            log(f"phase6 recheck: row {n} held (invalid probe)")
            surviving.append(row)
            continue
        ok, detail = probe.run(argv)
        if ok:
            log(f"phase6 recheck: row {n} demoted via {argv}")
            res.manual_demotions.append({"row": n, "argv": argv, "exit_ok": True})
        else:
            log(f"phase6 recheck: row {n} held via {argv} ({detail[:80]})")
            surviving.append(row)
    return surviving


_rebase_cause_runners = rebase_cause.live_runners   # tests monkeypatch this to inject a fake gh


def _classify_rebase_block(git, head_sha, plain, onto, reason) -> str:
    """Render the cause of a declined rebase. Never raises; never changes the exit code."""
    try:
        worktree = getattr(git, "worktree", "") or os.getcwd()
        run_git, run_gh = _rebase_cause_runners(worktree)
        cause = rebase_cause.classify_rebase_block(
            tuple(plain) or tuple(onto), reason, head_sha=head_sha,
            branch=git.branch(), run_git=run_git, run_gh=run_gh)
    except Exception as exc:  # the classifier is advisory; the block must still raise
        cause = rebase_cause.RebaseBlockCause(
            rebase_cause.CAUSE_UNKNOWN, tuple(plain) or tuple(onto),
            note=f"classifier error: {type(exc).__name__}")
    return cause.render()


def _record_failure_context(res: RunResult, e: HarnessError) -> None:
    """Copy the failing command and its output tail from a HarnessError onto the result.
    Both fields are redacted before storage; res.error stays raw."""
    res.error_cmd = _redact(e.cmd) or None
    res.error_output_tail = _redact(e.output) or None


def run(fixture: dict | None, out_dir: str, llm, *, mode: str = "replay",
        worktree: str | None = None, headless: bool = True,
        plans_dir: str | None = None, live: bool = False,
        explicit_path: str | None = None,
        probe=None, state_dir: str | None = None) -> RunResult:
    os.makedirs(out_dir, exist_ok=True)
    run_started = time.time()
    ledger = llm.ledger
    audit: list[str] = []

    # audit.log is the ONLY mid-run progress signal bin/fleet-status can read for a
    # harness run. The other two sources it tries both come up empty here: the harness
    # prints nothing until exit (so the /tmp log tails to nothing), and its bounded
    # `claude -p` calls carry no --session-id, so the transcript glob keyed on the
    # producer's sidecar uuid never resolves. Without this mirror every harness row
    # shows the "no output yet" placeholder for the whole run.
    #
    # _finish still rewrites the file whole at exit. This is a live mirror of the same
    # lines in the same format, not a second one -- the final bytes are unchanged, which
    # is what keeps the existing audit.log assertions valid. Truncated here so a reused
    # out_dir can never serve the previous run's tail as this run's progress.
    audit_path: str | None = os.path.join(out_dir, "audit.log")
    try:
        open(audit_path, "w").close()
    except OSError:
        audit_path = None

    def log(msg: str) -> None:
        line = f"[{time.strftime('%H:%M:%S')}] {msg}"
        audit.append(line)
        if audit_path is None:
            return
        # A progress mirror must never be able to fail a run.
        try:
            with open(audit_path, "a") as fh:
                fh.write(line + "\n")
        except OSError:
            pass

    if mode == "replay":
        assert fixture is not None
        git, gh = ReplayGit(fixture), RecordingGh(out_dir, fixture)
        verifier = ReplayVerify(fixture)
        if probe is None:
            probe = FixtureProbe(fixture)
        slug = fixture.get("slug", "unknown")
        plan = locate_plan(slug, content_override=fixture.get("plan_content") or None)
    else:
        assert worktree
        git = LiveGit(worktree, push_remote="origin" if live else None, llm=llm)
        global _active_git
        _active_git = git
        slug = git.branch()
        # Once per run, before the first rebase: clear a stale auto-resolved list from a
        # previous run of this branch. Per-rebase purges must not touch it (BEHIND retries).
        from harness.conflict import purge_autoresolved_list
        purge_autoresolved_list(slug.replace("/", "-"))
        gh = LiveGh(out_dir, worktree, slug) if live else RecordingGh(out_dir)
        gh = usage_pause.dedupe_on_resume(gh, worktree, out_dir)   # ADR-0143 addendum: no double post on resume
        verifier = LiveVerify(worktree)
        if probe is None:
            probe = LiveProbe(repo_root=worktree)
        plan = locate_plan(slug, plans_dir=plans_dir, explicit_path=explicit_path)

    res = RunResult(terminal=TerminalState.FAILED, slug=slug, plan=plan,
                    ledger=ledger, audit=audit)
    global _active_audit
    _active_audit = audit
    state = statefile.StateFile(os.path.join(_state_dir(out_dir, live, state_dir), statefile.safe_slug(slug) + ".json"), slug, git, out_dir, log)
    log(f"phase1 plan: found={plan.found} auto_merge_false={plan.auto_merge_false} "
        f"matrix={plan.has_matrix} critical_files={len(plan.critical_files)} "
        f"slug_drift={plan.slug_drift or '-'} plan_source={plan.plan_source or '-'}")

    bg_ci = None       # background CI watch handle; reaped in the finally below
    join_review = None # set once Phase 4 is launched; the finally below drains it

    try:
        # ---- Phase 2/3: ship + classify -------------------------------
        if mode != "replay":
            git.stage_all()
            if live:
                git.fetch_base()
        diff = git.diff_vs_base()
        if not diff.strip():
            res.terminal = TerminalState.NOTHING_TO_SHIP
            log("phase2: empty diff vs base — nothing to ship")
            return _finish(res, out_dir)
        files = git.changed_files()
        # Rename sources included; read ONLY by the conformance check. classify(),
        # scope conformance and denied_gate_edits keep `files`, or an old path
        # would surface as UNPLANNED-FILE and create a new hold.
        conf_files = git.conformance_files()
        cls = classify(files, diff, git.modified_files())
        res.classification = cls
        log("phase3 classify:\n" + cls.summary())

        # Conflict probe: predict a rebase conflict before the LLM body check spends a call.
        # A git error here means "no prediction", so the run falls through and the real
        # rebase_onto() below stays authoritative. A predicted conflict propagates to the
        # outer `except HarnessError`, which sets error_kind="rebase-conflict" (exit 3 via
        # _FAIL_CLOSED_KINDS). This is the same terminal the post-commit rebase arm reaches.
        # With an LLM wired, the probe also exits 3 when resolver_refusal_reason() names a
        # conflict the resolver is certain to refuse; any other predicted conflict is advisory.
        if live:
            try:
                conflict_files = git.predict_rebase_conflict()
            except HarnessError as e:
                conflict_files = ()
                log(f"phase2: conflict probe git error -- {e.detail}; falling through")
            if conflict_files:
                log(f"phase2: merge-tree probe predicted a rebase conflict on {git.branch()}")
                log("phase2: conflicted paths (probe) = "
                    f"{', '.join(conflict_files) or '-'}")
                if getattr(git, 'llm', None) is None:
                    log("phase2: no LLM resolver -- stopping before body check (exit 3)")
                    raise HarnessError(
                        "rebase-conflict",
                        f"predicted by merge-tree probe vs origin/master: "
                        f"{', '.join(conflict_files)}")
                refusal_fn = getattr(git, 'resolver_refusal_reason', None)
                refusal = refusal_fn() if callable(refusal_fn) else None
                if refusal is not None:
                    log(f"phase2: LLM resolver would refuse ({refusal}) -- "
                        "stopping before body check (exit 3)")
                    raise HarnessError(
                        "rebase-conflict",
                        f"predicted by merge-tree probe vs origin/master: "
                        f"{', '.join(conflict_files)}; resolver would refuse: {refusal}")
                log("phase2: LLM resolver active -- probe is advisory, falling through to rebase_onto()")

        copy, copy_degraded = _pr_copy(llm, git, gh, fixture, slug, cls, plan, log)
        summary, stripped = strip_manual_testing_section(copy["summary_md"])
        if stripped:
            copy["summary_md"] = summary
            log("phase2: stripped model-authored Manual Testing section from PR copy")
        copy["summary_md"], qualified = qualify_backlog_refs(copy["summary_md"])
        if qualified:
            log(f"phase2: qualified {qualified} bare backlog ref(s) as {BACKLOG_REPO}#N")
        copy["commit_subject"] = schemas.coerce_commit_subject(copy["commit_subject"], cls)
        check, body_check_degraded = _body_check(
            llm, git, gh, copy, copy_degraded, cls, log, worktree=worktree)
        if copy["summary_md"] and check.get("corrected_body"):
            copy["summary_md"] = check["corrected_body"]
            for f in check.get("findings", []):
                log(f"phase2 body-check finding: {f}")
        _inject_residual_phases(copy, plan, conf_files, log)
        log("phase2: starting scope check")
        _inject_scope_notes(copy, plan, files, diff, copy["summary_md"], log)
        log("phase2: starting commit ADR gate")
        _commit_with_adr_draft(git, log, "phase2", llm=llm, worktree=worktree,
                               out_dir=out_dir, res=res)
        log("phase2: starting commit (pre-commit hook)")
        sha = _commit_with_gate_remediation(
            git, worktree, f"{copy['commit_subject']}\n\n{copy['summary_md']}", log)
        rebase_line = f"REBASE=not run ({mode} mode)"
        if live:
            pre_rebase = git.head()
            conflict_resolved = None
            try:
                git.rebase_onto()  # pre-push policy: origin/master must be an ancestor of HEAD (bin/pre-push-adr-hook); merge, never rebase
            except HarnessError as e:
                if e.kind != "rebase-conflict":
                    raise
                # DETECTION-TIME flag: recorded before the resolution attempt begins, so a
                # run that dies mid-resolution still leaves evidence that this run touched a
                # conflict. Arming the flag only on success would invert the fail-closed
                # property -- the same reason the skill sets it in its Phase 2 detection arm
                # and merely confirms it in step 7.
                log(f"phase2: CONFLICT_FLAG=set (rebase conflict detected on {git.branch()}) "
                    "-- attempting stacked --onto auto-resolution")
                plain_conflicted = getattr(git, "last_conflict_files", ())
                log(f"phase2: conflicted paths (plain rebase) = {', '.join(plain_conflicted) or '-'}")
                conflict_resolved = git.autoresolve_stacked_rebase()
                if not conflict_resolved.resolved:
                    log(f"phase2: conflict auto-resolution declined -- {conflict_resolved.reason}")
                    onto_conflicted = getattr(git, "last_conflict_files", ())
                    log(f"phase2: conflicted paths (--onto) = {', '.join(onto_conflicted) or '-'}")
                    block_cause = _classify_rebase_block(
                        git, pre_rebase, plain_conflicted, onto_conflicted,
                        conflict_resolved.reason)
                    log(f"phase2: rebase block {block_cause}")
                    err = HarnessError(
                        "rebase-conflict",
                        f"{e.detail} | auto-resolve declined: {conflict_resolved.reason}",
                    )
                    err.block_cause = block_cause
                    raise err from e
                log("phase2: conflict auto-resolved via --onto "
                    f"{conflict_resolved.base_sha[:8]}; TREE-EQUIVALENT proved; "
                    f"manifest={conflict_resolved.manifest_path} "
                    f"notes={conflict_resolved.notes_path} "
                    f"POST_RESOLUTION_SHA={conflict_resolved.post_resolution_sha}")
                if conflict_resolved.collapse_warn:
                    log(f"phase2: {conflict_resolved.collapse_warn}")
            sha = git.head()
            if conflict_resolved is not None:
                auto_files = (f"; auto-resolved conflict in {', '.join(conflict_resolved.resolved_files)}"
                              if conflict_resolved.resolved_files else "")
                rebase_line = ("REBASE=conflict auto-resolved via --onto; TREE-EQUIVALENT; "
                               f"manifest={conflict_resolved.manifest_path}{auto_files}")
            else:
                log("phase2: merged origin/master")
                # the skill's two success spellings, verbatim
                rebase_line = ("REBASE=clean (HEAD already contains origin/master)"
                               if sha == pre_rebase else "REBASE=merged origin/master")
        res.meta_checks_ok = run_meta_checks_local(
            git, worktree or "", "origin/master", log, live=live,
            failures_out=res.meta_check_failures, llm=llm)
        if live:
            sha = git.head()  # refresh — remediation may have committed and moved HEAD
        pr_known = gh.pr_number() if (live and gh.pr_exists()) else None
        pushed = _push_with_adr_draft(git, log, "phase2", llm=llm, worktree=worktree,
                                      out_dir=out_dir, res=res, pr=pr_known)
        if pushed:
            sha = pushed
        if gh.pr_exists():
            pr = gh.pr_number()
            log(f"phase2: PR #{pr} exists — updated head to {sha or '(clean)'}")
        else:
            create_body = upsert_files_changed(copy["summary_md"], render_files_changed(diff))
            create_body = upsert_tests_changed(create_body, render_tests_changed(diff))
            create_body = _apply_backlog_closes(create_body, plan, log)
            create_body = _upsert_no_adr_markers(create_body, plan)
            pr = gh.pr_create(copy["title"], create_body, "master")
            log(f"phase2: pr_create intent recorded (title={copy['title']!r})")
        res.pr_number = pr
        state.checkpoint("pr-open", res)
        if live and pr and sha:
            bg_ci = ciwatch.start_background_watch(worktree, pr, sha, out_dir,
                                                   verify_head=True)
            if bg_ci is not None and getattr(bg_ci, "status", "") == "diverged":
                log(f"phase2: ERROR remote head {bg_ci.remote_sha[:8]} diverged from "
                    f"pushed {sha[:8]} with different content; failing closed")
                raise HarnessError("remote-head-diverged", bg_ci.evidence)
            if bg_ci is not None and getattr(bg_ci, "sha", sha) != sha:
                log(f"phase2: remote head moved to {bg_ci.sha[:8]} (tree-equivalent); "
                    "worktree synced, CI watch re-keyed")
                sha = bg_ci.sha
            if bg_ci is not None:
                log(f"phase2: background CI watch started for {sha[:8]} "
                    f"-> {os.path.basename(bg_ci.path)}")
        _post_status_label(gh, pr)
        meta = gh.pr_meta() or {"number": pr, "title": copy["title"], "body": copy["summary_md"]}

        # ---- Phase 4: review + security (gated bounded calls) ---------
        # Runs in the background through Phase 5 → 5.0 → 6; joined before Phase 5.5
        # builds its fidelity packet so phase4b_ran is truthful. Only the LLM calls and
        # the PR posts run on the
        # worker: log(), state.checkpoint() and the pr-copy degradation merge stay on
        # this thread, in _join_review, so audit.log and the state file keep their serial
        # order. The join happens before anything moves the head (fidelity remediation),
        # so the review still posts against the head it read.
        # Phase 4.5 snapshot: thread ids that exist BEFORE this run posts anything.
        # Taken before the review worker is submitted, so nothing this run posts can
        # enter it. Empty on any failure = Phase 4.5 acts on nothing.
        t_snap = time.monotonic()
        pre_posting_ids = gh.pr_thread_ids(pr)
        log(f"phase4.5 snapshot: {len(pre_posting_ids)} pre-existing thread(s) "
            f"in {time.monotonic() - t_snap:.2f}s")

        # ---- Phase 4.5: pre-existing trusted review threads --------------
        # Runs before the review worker starts, so a fix commit can never move the head
        # under the worker's posts, and Phase 4, 5, 5.0 and 5.5 all read the fixed tree.
        # Never blocks the run: only gate-path-edit and push-failed propagate.
        head_before_45 = git.head()
        res.thread_ingestion = _run_thread_ingestion_phase(
            gh, llm, git, worktree, pr, pre_posting_ids, out_dir, log, res)
        if git.head() != head_before_45:
            sha = git.head()
            files = git.changed_files()
            conf_files = git.conformance_files()
            diff = git.diff_vs_base()
            meta = gh.pr_meta() or meta
        review_pool = concurrent.futures.ThreadPoolExecutor(max_workers=1)
        review_future = review_pool.submit(ReviewPhase(llm, gh).run, meta, cls, plan)
        review_pool.shutdown(wait=False)
        review_joined = []

        def _join_review() -> None:
            if review_joined:
                return
            review_joined.append(True)
            findings, gates, scored, degraded_agents = review_future.result()
            # A degraded pr-copy joins the same list: the PR then carries the "Review
            # Unavailable" note, the terminal is DEGRADED, and arming is off — the title a
            # human must sanity-check was never model-reviewed.
            if copy_degraded:
                degraded_agents = list(degraded_agents) + [schemas.PR_COPY_PURPOSE]
            if body_check_degraded:
                degraded_agents = list(degraded_agents) + ["body-check"]
            res.findings = findings
            res.scored_findings = scored
            res.degraded_agents = degraded_agents
            code_review_purposes = {"review-agent-a", "review-agent-b", "review-agent-d"}
            expected_code = [p for p in ("review-agent-a", "review-agent-b", "review-agent-d")
                             if gates.get({"review-agent-a": "A", "review-agent-b": "B",
                                           "review-agent-d": "D"}[p])]
            code_degraded = [p for p in degraded_agents if p in code_review_purposes]
            res.phase4b_code_ran = bool(expected_code) and len(code_degraded) < len(expected_code)
            res.phase4b_reviewed_head = meta.get("headRefOid") or ""
            state.checkpoint("review", res, review_gates=gates, reviewed_head=meta.get("headRefOid") or "")
            log(f"phase4 gates={ {k: v for k, v in gates.items()} } findings: raw={len(scored)} surviving={len(findings)} scores={[s['score'] for s in scored]}")
            if degraded_agents:
                log(f"phase4 DEGRADED: unparseable review output from {', '.join(degraded_agents)}")
        join_review = _join_review

        # ---- Phase 5 + 5.0: verify + conformance -----------------------
        tracks = verifier.run(cls)
        phase5 = aggregate(tracks)
        timing = timing_log_line(tracks, getattr(verifier, "last_wall_seconds", None))
        if timing:
            log(timing)
        res.phase5 = phase5
        log(tracks_log_line(tracks, phase5))
        for line in fail_log_lines(tracks):
            log(line)
        resolutions: dict[str, str] = {}
        unresolved = conformance.check(plan, conf_files, diff, phase5_status=phase5,
                                       resolutions=resolutions,
                                       pr_body=gh.pr_body() or meta.get("body", ""),
                                       read_file=git.read_worktree_file)
        res.unresolved_conformance = unresolved
        _write_conformance_handoff(out_dir, unresolved)
        log(f"phase5.0 conformance: {unresolved or 'clean'}"
            + (f" resolved={resolutions}" if resolutions else ""))

        # ---- Phase 6: manual-testing clearance ------------------------
        body = gh.pr_body() or meta.get("body", "")
        clearance = manual_testing_clearance(body)
        if clearance == "UNKNOWN":
            if plan.found and not plan.truly_manual_rows:
                # Zero executable rows is a static-only plan: claiming automated coverage
                # would be false (.claude/rules/pr-body-test-claim.md). `== 0`, never
                # falsiness: None means no matrix was parsed, and that keeps the covered
                # sentinel because the static text asserts a matrix exists.
                static = plan.has_matrix and plan.executable_row_count == 0
                sentinel = MANUAL_TESTING_SENTINEL_STATIC if static else MANUAL_TESTING_SENTINEL
                body += f"\n\n## Manual Testing\n\n{sentinel}\n"
                clearance = "CLEARED"
                if static:
                    log("phase6: plan matrix has zero executable rows — static sentinel appended (CLEARED)")
                else:
                    log("phase6: plan matrix fully automated — sentinel appended (CLEARED)")
            elif plan.found:
                surviving_rows = _recheck_manual_rows(llm, probe, plan, cls, log, res)
                if not surviving_rows:
                    body += f"\n\n## Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n"
                    clearance = "CLEARED"
                    log(f"phase6: all {len(plan.truly_manual_rows)} truly-manual rows demoted — CLEARED")
                else:
                    body += ("\n\n## Manual Testing\n\n"
                             + manual_rows.render_rows(surviving_rows) + "\n")
                    clearance = "HELD"
                    log(f"phase6: {len(surviving_rows)} truly-manual plan rows — HELD")
            else:
                # plan-blind: bounded classification decides whether human judgment is needed
                items = llm.call("manual-classify", "haiku",
                                 llm_calls.manual_classify_prompt(
                                     "(no section; classify from diff summary whether any "
                                     "human-judgment verification is required)", cls),
                                 schemas.validate_manual_classification)
                manual = [i for i in items if i["category"] == "truly-manual"]
                if manual:
                    body += ("\n\n## Manual Testing\n\n"
                             + "\n".join(f"- [ ] {i['step']}" for i in manual) + "\n")
                    clearance = "HELD"
                else:
                    body += f"\n\n## Manual Testing\n\n{MANUAL_TESTING_SENTINEL}\n"
                    clearance = "CLEARED"
                log(f"phase6 (plan-blind): {len(manual)} truly-manual steps -> {clearance}")
        else:
            log(f"phase6: PR body already carries clearance state {clearance}")
        # Hold notice: carries only the plan's Decision paragraph(s) and is
        # positioned ahead of `## Manual Testing` — appending it after would
        # truncate manual_testing_clearance's scan window. The order of upserts
        # that follows (files_changed, tests_changed, gate_backtest) and the
        # single pr_edit_body call are unchanged.
        body = upsert_hold_notice(body, plan.hold_justification)
        # files-changed block is machine-generated: refresh it on every run so the
        # PR body's scope can't silently drift from the actual diff.
        body = upsert_files_changed(body, render_files_changed(diff))
        body = upsert_tests_changed(body, render_tests_changed(diff))
        _gb = gate_backtest_result(worktree or ".", diff, pr=pr, live=live,
                                   fixture=fixture, log=log)
        if _gb.block:
            body = upsert_gate_backtest(body, _gb.block)
        body = _apply_backlog_closes(body, plan, log)
        body = _upsert_no_adr_markers(body, plan)
        gh.pr_edit_body(pr, body)
        _check_backlog_closes(gh, pr, plan, log)
        _sweep_out_of_scope(gh, pr, plan, slug, log)

        # ---- Phase 5.5: plan-intent fidelity review --------------------
        # Pinned BEFORE the call: condition (12) compares the tree the reviewer saw
        # against HEAD at arming time, so capturing it after would always match.
        master_sha = _master_sha(worktree)
        reviewed_tree = git.head_tree()
        _run_fidelity(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
                      reviewed_tree, live, log, res, before_remediation=_join_review,
                      unresolved_conformance=res.unresolved_conformance,
                      meta_check_failures=res.meta_check_failures,
                      scored_findings=res.scored_findings)
        _join_review()
        # A remediation loop moved the head, up to MAX_FIDELITY_ROUNDS times. Phase 7
        # must watch CI for the last commit, which is what remediation_sha aliases after
        # the loop. A body-only round moved no head, so it never replaces sha.
        if rsha := _last_remediation_commit(res.fidelity):
            sha = rsha
            # Phase 5.0 re-run: the loop above moved the head, so the diff the first
            # pass saw is stale. A remediation that authored the planned file must
            # clear condition (3) on the SAME run, or the hold never releases.
            # Unconditional re-run is avoided on purpose: two extra git calls on every
            # clean run buy nothing, and the gate is "did the tree change", not "did
            # remediation run".
            files = git.changed_files()
            conf_files = git.conformance_files()
            diff = git.diff_vs_base()
            resolutions = {}
            unresolved = conformance.check(plan, conf_files, diff, phase5_status=phase5,
                                           resolutions=resolutions,
                                           pr_body=gh.pr_body() or body,
                                           read_file=git.read_worktree_file)
            res.unresolved_conformance = unresolved
            _write_conformance_handoff(out_dir, unresolved)
            log(f"phase5.0 conformance (post-remediation): {unresolved or 'clean'}"
                + (f" resolved={resolutions}" if resolutions else ""))

        # ---- Phase 6.7: manual-testing execution ----------------------
        # Runs after remediation so the probed tree is the tree that will merge,
        # and before ArmInputs so a tick reaches condition (1) this run.
        mt = manual_testing.run(
            pr=pr, worktree=worktree, body=gh.pr_body() or body,
            gh=gh, probe=probe, show_blob=manual_testing.show_blob_for(worktree),
            master_sha=master_sha, head_tree=git.head_tree,
            live=live, log=log,
        )
        if mt.ran:
            res.manual_testing = mt.to_dict()
        if not mt.ran:
            log(f"phase6.7: skipped ({mt.skipped_reason})")
        else:
            log(f"phase6.7: bringup={mt.bringup} rows={len(mt.rows)} "
                f"ticked={len(mt.ticked)} all_ticked={mt.all_ticked}"
                + (f" errors={';'.join(mt.errors)}" if mt.errors else ""))

        # ---- Phase 6.5: arming ----------------------------------------
        # Condition (15): snapshot any checks already in the fail bucket before
        # arming. Fail-open: any exception falls back to [] so the run continues.
        # bg_ci.failed is only populated once _write_outcome fires (usually after
        # Phase 7 has already started), so it is typically empty at arm time; the
        # fresh probe is therefore always run in addition to the background result.
        if live and pr and sha and worktree and os.path.isdir(worktree):
            sha, bg_ci = _reconcile_before_arm(git, log, gh, worktree, pr, sha,
                                               bg_ci, out_dir)
        _failed_checks: list[str] = []
        if live and pr:
            try:
                _bg_failed = (list(bg_ci.failed)
                              if bg_ci is not None and bg_ci.done.is_set()
                              and bg_ci.status == "failure"
                              else [])
                _probe_failed = ciwatch.probe_failed_checks(worktree, pr)
                _failed_checks = sorted(set(_bg_failed + _probe_failed))
            except Exception as _e:
                log(f"phase6.5: red-ci-check probe error — {_e}")
        # condition (16) inputs: pre-push flag and post-pr run
        _mc_branch_slug = git.branch().replace("/", "-")
        _mc_flag = f"/tmp/ibl5-meta-checks-prepush-{_mc_branch_slug}.failed"
        if live and pr:
            _mc_argv = [os.path.join(worktree or "", "bin", "run-meta-checks-local"),
                        "--stage", "post-pr", "--pr", str(pr)]
            try:
                _mc_proc = subprocess.run(_mc_argv, cwd=worktree, capture_output=True, text=True)
                _mc_post_rc = _mc_proc.returncode
            except OSError:
                _mc_post_rc = 2
        else:
            _mc_post_rc = int((fixture or {}).get("meta_checks_post_rc", 0))
        _mc_status = meta_checks_clearance(_mc_flag, _mc_post_rc)
        _gb = gate_backtest_result(worktree or ".", git.diff_vs_base(), pr=pr, live=live,
                                   fixture=fixture, log=log)
        inputs = ArmInputs(
            pr_body=gh.pr_body() or body, pr_title=meta.get("title", copy["title"]),
            pr_labels=gh.pr_labels(), classification=cls, findings=res.findings,
            unresolved_conformance=unresolved, phase5_status=phase5,
            plan_auto_merge_false=plan.auto_merge_false, headless=headless,
            dep_state_lookup=lambda n: gh.pr_state(n),
            # Phase 5.5 above produced these. None means INDETERMINATE (the reviewer
            # degraded or was not run), which holds condition (12) — it is never
            # silently promoted to a verdict word.
            # verdict 1, deliberately NOT the collapsed return value: select_fidelity_verdict
            # is what combines the two, and it tree-gates verdict 2. Passing the collapsed
            # value here would let an untethered verdict 2 satisfy condition (12) on its own.
            fidelity_verdict=res.fidelity.get("verdict_1"),
            fidelity_verdict_2=res.fidelity.get("verdict_2"),
            fidelity_tree_2=res.fidelity.get("reviewed_tree_2"),
            current_tree=git.head_tree(),   # read here, after every commit this run makes
            unresolved_findings=gh.unresolved_findings(pr) if live else [],
            conflict_resolved=(os.path.exists(conflict_flag_path(git.branch())) if live
                               else bool((fixture or {}).get("conflict_resolved", False))),
            conflict_verdict=(conflict_verdict_for(git.branch()) if live
                              else (fixture or {}).get("conflict_verdict")),
            degraded_agents=res.degraded_agents,
            plan_slug_drift=plan.slug_drift,
            failed_checks=_failed_checks,
            aggregator_required=(gh.aggregator_required(AGGREGATOR_CONTEXT) if live
                                 else bool((fixture or {}).get("aggregator_required", False))),
            meta_checks_status=_mc_status,
            gate_backtest_status=_gb.status,
            gate_backtest_reason=_gb.reason,
        )
        if not live and (fixture or {}).get("current_tree"):
            # replay-only seam, the checks_outcome pattern: live mode never reads it
            inputs.current_tree = fixture["current_tree"]
        if not live and (fixture or {}).get("red_ci_checks") is not None:
            # replay-only seam for condition (15): live mode never reads this path
            inputs.failed_checks = sorted(set((fixture or {}).get("red_ci_checks") or []))
        preview = evaluate(inputs)
        if not preview.holds:
            # only spend the add-only safety verdict when deterministic checks pass
            verdict = llm.call("safety-verdict", "haiku",
                               llm_calls.safety_verdict_prompt(cls, inputs.pr_title, preview),
                               schemas.validate_safety_verdict)
            inputs.llm_safety_holds = verdict["holds"]
        decision = evaluate(inputs)
        res.arm = decision
        for c in decision.conditions:
            if c.warning:
                log(f"phase6.5 WARNING ({c.name}): {c.warning}")
        log("phase6.5: " + ("ARMED" if decision.armed else
                            "HELD — " + "; ".join(f"({c.number}) {c.reason or c.name}"
                                                  for c in decision.holds)))
        if (live or state_dir is not None) and res.hold_repeat is None:
            res.hold_repeat = _record_hold_repeat(res, decision, slug, worktree,
                                                  _state_dir(out_dir, live, state_dir), log)
        fid = res.fidelity or {}
        fid["selected"], fid["selected_source"] = select_fidelity_verdict(
            inputs.fidelity_verdict, inputs.fidelity_verdict_2,
            inputs.fidelity_tree_2, inputs.current_tree)
        res.fidelity = fid
        state.checkpoint("fidelity", res)
        if pr:
            # Posted BEFORE arming on purpose: `--auto` merges immediately when the checks
            # are already green, and merge-digest-notify.yml reads the last marker comment
            # at merge time. A comment posted after the merge would arrive too late.
            vpath = fid.get("verdict_path") or fidelity.verdict_path(pr)
            digest = fidelity.digest_lines(worktree, master_sha, vpath, out_dir,
                                           fid.get("verdict_1") is not None)
            rsha = _last_remediation_commit(fid)
            ci_line = ("CI: local verification "
                       f"{getattr(res.phase5, 'value', res.phase5)}; "
                       "GitHub checks are watched after this comment")
            if rsha:
                ci_line += f"; remediation commit {rsha} is inside that watch"
            sticky = fidelity.compose_sticky(
                rebase_line, ci_line, fid, decision, digest,
                fidelity.findings_excerpt(vpath, fid.get("verdict_1") is not None),
                fidelity.terminal_line(fid.get("verdict_1"), fid.get("error_kind"),
                                       fid.get("remediation_sha"),
                                       fid.get("verdict_2"), fid.get("reviewed_tree_2"),
                                       fid.get("rounds_completed", 0)),
                diff_id=fid.get("diff_id", ""), plan_hash=fid.get("plan_hash", ""),
                posted_at=time.strftime("%Y-%m-%d %H:%M:%S %Z"))
            try:
                cid = gh.pr_sticky_verdict(pr, sticky)
            except (HarnessError, OSError, subprocess.SubprocessError):
                cid = ""
            res.sticky_comment_id = cid or None
            if not cid:
                # Arming is deliberately UNCHANGED by a failed post: the skill's own post is
                # `|| true`, and a new hold here would change condition semantics.
                res.sticky_error = "sticky-post-failed"
                log("phase6.5: sticky verdict comment not confirmed")
            # Digest at the top of the PR body, from the same rows the sticky printed.
            # Still BEFORE arming, and never able to change it: log-and-continue.
            try:
                drows = fidelity.digest_rows_for_display(digest, fid)
                if all(" unavailable — " in r for r in drows):
                    log("phase6.5: merge digest all degraded; PR body digest left as is")
                else:
                    cur = gh.pr_body_fresh() or ""
                    new = upsert_merge_digest(cur, render_merge_digest(drows))
                    if new != cur:
                        gh.pr_edit_body(pr, new)
                    log("phase6.5: merge digest upserted into PR body")
            except (HarnessError, OSError, subprocess.SubprocessError) as e:
                log(f"phase6.5: merge digest body write failed ({e.__class__.__name__}: {e})")
            # Review-owed decision: AFTER the sticky post (the script reads the body this
            # run composed and the tree it ends on) and BEFORE arming (a launched
            # /pr-review races an `--auto` merge no worse than the skill path does).
            # Fire-and-log: arming never reads the result, the sticky-post-failed contract.
            canned_ro = (fixture or {}).get("review_owed") if not live else None
            if isinstance(canned_ro, dict) and canned_ro.get("verdict"):
                # replay-only seam, the checks_outcome pattern: live mode never reads it
                ro = dict(canned_ro)
            else:
                ro = fidelity.fire_review_owed(worktree, master_sha, pr, sticky,
                                               inputs.current_tree, out_dir,
                                               live=live, log=log)
            fid["review_owed"] = ro
            log(f"phase6.5 review-owed: {ro.get('verdict')} ({ro.get('reason')})"
                + (f"; {ro['command']}" if ro.get("command") else ""))
        if decision.armed:
            gh.pr_merge_auto(pr)
        state.checkpoint("arm", res)

        if res.degraded_agents:
            current = gh.pr_body() or body
            if "## Review Unavailable" not in current:
                note = ("\n\n## Review Unavailable\n\n"
                        "The compiled post-plan harness could not parse the reply from: "
                        + ", ".join(res.degraded_agents)
                        + ". Those checks did not run; auto-merge was not armed. "
                          "Re-run the review or review this PR by hand before merging.\n")
                gh.pr_edit_body(pr, current + note)

        # ---- Phase 7/8: CI watch + confirm -----------------------------
        res.ci_head = sha or None
        if mode == "replay":
            fx_ci = (fixture or {}).get("checks_outcome")
            outcome = (ciwatch.CiOutcome(fx_ci["exit"], fx_ci.get("failed", []))
                       if fx_ci else ciwatch.derive_from_trace((fixture or {}).get("ci")))
        elif live and pr:
            if bg_ci is not None and bg_ci.sha == sha:
                log(f"phase7: reusing background CI watch for {sha[:8]}")
            else:
                log("phase7: watching CI (gh pr checks --watch)…")
            outcome = ciwatch.watch_or_reuse(worktree, pr, sha, out_dir, bg_ci,
                                             verify_head=True)
            if outcome.diverged:
                _fail_closed_on_divergence(gh, log, pr, "phase7", outcome.evidence,
                                           disarm=True)
            if outcome.head_sha and outcome.head_sha != sha:
                log(f"phase7: CI re-keyed to remote head {outcome.head_sha[:8]}")
                sha = outcome.head_sha
                res.ci_head = sha
        else:
            outcome = ciwatch.CiOutcome(-1, [], "isolated mode: no live PR, CI not watched")
        res.ci_outcome = {0: "green", 8: "failed"}.get(outcome.exit_code, "indeterminate")
        res.final_pr_state = gh.pr_state() if (mode == "replay" or live) else "N/A"
        log(f"phase7 ci: exit={outcome.exit_code} failed={outcome.failed} ({outcome.evidence})")

        # Phase 7 fix loop: runs after Phase 6.5 arming has executed and never reads or
        # writes `decision`. Replay enters only when the fixture scripts the re-watches,
        # so every legacy red-CI fixture keeps its pass-through.
        fix_enabled = ("ci_fix_rewatch" in (fixture or {}) if mode == "replay"
                       else bool(live and pr))
        if fix_enabled and outcome.exit_code == 8:
            sha, outcome = _ci_fix_loop(git, gh, llm, log, res, worktree=worktree, pr=pr,
                                        sha=sha, outcome=outcome, out_dir=out_dir,
                                        mode=mode, fixture=fixture,
                                        run_started=run_started)
            res.ci_head = sha or None
            res.ci_outcome = {0: "green", 8: "failed"}.get(outcome.exit_code,
                                                           "indeterminate")
            log(f"phase7 ci(after fix loop): exit={outcome.exit_code} "
                f"failed={outcome.failed}")

        if _should_resolve_behind(live, pr, decision, outcome):
            sha, outcome = _resolve_behind(git, gh, log, res, worktree, pr, sha,
                                           outcome, out_dir)
            res.ci_head = sha or None
            res.ci_outcome = {0: "green", 8: "failed"}.get(outcome.exit_code,
                                                           "indeterminate")

        res.terminal = _compute_terminal(res, decision.armed)

        # ---- Phase 9: retrospective (bounded, record-only) -------------
        # Never fatal: the PR is open and arming has executed, so FAILED here would
        # hand an armed PR to the full skill fallback.
        try:
            retro = llm.call("retrospective", "haiku",
                             llm_calls.retrospective_prompt(slug, res.terminal.value, decision,
                                                            len(res.findings), phase5, res.fidelity),
                             schemas.validate_retrospective)
        except HarnessError as e:
            retro = {"save": False, "error": e.kind}
            log(f"phase9: retrospective unavailable ({e.kind}) — terminal unchanged")
        res.retrospective = retro
        if retro.get("save"):
            log(f"phase9: memory-save intent recorded: {retro.get('name')}")
        elif not retro.get("error"):
            log("phase9: no durable lesson — nothing saved")

        # ---- Phase 10: preview environment ------------------------------
        log("phase10: preview environment skipped — headless harness")
    except HarnessError as e:
        merged = _already_shipped(e, git, gh, log)
        if merged is not None:
            res.terminal = TerminalState.ALREADY_SHIPPED
            res.pr_number = int(merged["number"])
            res.final_pr_state = "MERGED"
            log(f"already-shipped: diff vs origin/master is empty and PR "
                f"#{res.pr_number} is MERGED; suppressing {e.kind} ({(e.detail or '')[:120]})")
        else:
            res.terminal = TerminalState.FAILED
            res.error = f"{e.kind}: {e.detail}"
            res.error_kind = e.kind
            res.block_cause = getattr(e, "block_cause", None)
            _record_failure_context(res, e)
            log(f"FAILED: {res.error}")
    except usage_pause.UsagePause as p:
        res.terminal = TerminalState.FAILED
        res.error_kind = "usage-pause-dirty" if p.dirty else "usage-pause"
        res.error = f"{res.error_kind}: {p.purpose}"
        log(f"PAUSED: {res.error}")
    finally:
        # A Phase 5-5.5 failure can land while the background review is still running.
        # Wait for it so the review checkpoint lands as it did when Phase 4 ran first;
        # its own error must not replace the one that ended the run.
        if join_review is not None:
            try:
                join_review()
            except (Exception, usage_pause.UsagePause) as e:  # noqa: BLE001
                log(f"phase4: background review failed after the run failed ({e!r})")
        state.checkpoint("terminal", res)
        _phase11_cleanup(llm, log)
        ciwatch.reap_background_watch(bg_ci)
    return _finish(res, out_dir)


# Phase 5.0's two conformance signals, written into the run dir beside result.json.
# Run-dir-keyed, NOT $PPID-keyed: the skill path spells them /tmp/...-$PPID, and no
# PID the harness could pick would be the one another process looks under. Phase 8
# removed the rc=4 resume, so no skill session reads these today; they stay as the
# run's audit trail and as the shape the skill-path block in
# _phase-6.5-arm-auto-merge.md mirrors. Two suites read these names back rather than
# hardcoding them, and that is what keeps every side in sync: the Python half in
# tests/test_post_plan_now_fallback.py and the shell half in
# bin/test-postplan-arm-conditions (harness_handoff_names). Renaming a constant here
# without updating both greps breaks them loudly, which is the intent.
CONFORMANCE_DONE_NAME = "conformance-done"
CONFORMANCE_BRIDGE_NAME = "missing-tests"


# --- Bounded push-retry and BEHIND-resolution helpers ------------------------
META_CHECK_OUTPUT_CAP = 6_000
_MAX_PUSH_RETRIES = 3

# A round failure is transient when running the same round again could plausibly
# succeed: the CLI died, the model returned nothing usable, or it made no edits.
# Everything else -- a rebase conflict, a spent push cap, a denied local gate, an edit
# to a gate-owning path -- is terminal by the complement rule, because a retry changes
# nothing about it. A failed push keeps its own raise and never reaches the taxonomy.
_TRANSIENT_ROUND_KINDS = ("llm-invalid-output", "llm-tooled-cli", "llm-tooled-empty")
_TRANSIENT_ROUND_REASONS = ("no-edits",)
_MAX_ROUND_RETRIES = 1

# A body-only round: the fixer answered the finding in the PR body with its own raw
# `gh pr edit` and committed nothing. That is real work, so it takes a truthy stand-in
# sha and flows through the same re-review path a committed round does. Neither string
# below belongs in _TRANSIENT_ROUND_REASONS -- re-running the same fixer against a
# finding it has already answered in the body just re-answers it.
BODY_ONLY_SHA = "body-only"
BODY_FETCH_FAILED_REASON = "body-fetch-failed"


def _last_remediation_commit(fid: dict) -> str | None:
    """The last commit a remediation round actually authored, or None.

    fid["remediation_sha"] is a round label: it reads BODY_ONLY_SHA after a body-only
    round. Anything that treats it as a git object -- the Phase 6.5 remote-head check,
    the Phase 7 CI watch -- must use this instead, or it compares the PR head against
    the literal string "body-only" and fails closed as remote-head-diverged.
    """
    sha = fid.get("remediation_sha")
    if sha != BODY_ONLY_SHA:
        return sha or None
    for r in reversed(fid.get("rounds") or []):
        s = r.get("remediation_sha")
        if s and s != BODY_ONLY_SHA:
            return s
    return None


def _body_signature(body: str | None) -> str:
    """The comparable part of a PR body: everything outside the files-changed and merge-digest blocks.

    Phase 5.5 rewrites <!-- files-changed:begin -->..<!-- files-changed:end --> on every
    round, so that block churns whenever the diff grows and says nothing about whether
    the fixer touched the body. Strip it, then strip surrounding whitespace; what is
    left is the prose a human or an agent wrote. An unbalanced marker pair is left
    intact rather than guessed at -- the same bounds check upsert_files_changed uses.
    """
    text = body or ""
    begin = text.find(FILES_CHANGED_BEGIN)
    end = text.find(FILES_CHANGED_END)
    if begin != -1 and end != -1 and end > begin:
        text = text[:begin] + text[end + len(FILES_CHANGED_END):]
    begin = text.find(MERGE_DIGEST_BEGIN)
    end = text.find(MERGE_DIGEST_END)
    if begin != -1 and end != -1 and end > begin:
        text = text[:begin] + text[end + len(MERGE_DIGEST_END):]
    return text.strip()


def _round_model(round_num: int) -> str:
    """Sonnet fixes round 1; a round that did not clear the verdict escalates to Opus.

    Escalation is across rounds, never across retries: a flaky CLI call is not worth
    an Opus spawn.
    """
    return "sonnet" if round_num == 1 else "opus"
_MAX_BEHIND_RETRIES = 3
_DEP_CATCHUP_KEY = 9   # lost-work key outside the 1..3 push/BEHIND attempt keys


def _retry_key(branch: str, attempt: int) -> str:
    return re.sub(r"[^A-Za-z0-9._-]", "-", f"postplan-{branch}-r{attempt}")


# Failure shapes that mean "the branch diff vs origin/master is empty". On their own they
# are lost work; with the branch's PR already MERGED they are a finished run.
_EMPTY_DIFF_MARKERS = ("diff vs origin/master is empty", "nothing was compared",
                       "No commits between")


def _already_shipped(e: HarnessError, git, gh, log) -> dict | None:
    """{"number", "url"} of the merged PR when `e` is an empty-diff failure on a branch
    whose PR already merged, else None. Never relaxes the proof for a non-empty diff:
    the live diff vs origin/master must itself be empty, and a merged PR must exist."""
    if e.kind not in ("lostwork-unproved", "gh"):
        return None
    if not any(m in (e.detail or "") for m in _EMPTY_DIFF_MARKERS):
        return None
    try:
        if git.diff_vs_base("origin/master").strip():
            return None
        return gh.merged_pr()
    except Exception as exc:  # noqa: BLE001 - unknown means keep the original failure
        log(f"already-shipped: probe failed ({exc!r}); keeping {e.kind}")
        return None


def _refresh_and_reprove(git, log, phase: str, attempt: int) -> None:
    """Fetch remote tip, rebase, and re-prove no work was lost. Never pushes."""
    key = _retry_key(git.branch(), attempt)
    if not git.capture_lostwork_pre(key):
        raise HarnessError("lostwork-unproved",
                           f"{phase}: diff vs origin/master is empty before re-rebase")
    git.fetch_base("origin/master")
    git.rebase_onto("origin/master")
    ok, evidence = git.prove_lostwork(key)
    if not ok:
        raise HarnessError("lostwork-unproved",
                           f"{phase}: lost-work proof failed after re-rebase {attempt}: {evidence}")
    log(f"{phase}: re-rebase {attempt} TREE-EQUIVALENT at {git.head()[:8]}")


def _lease_snapshot(git, *, run_git=None):
    """(remote, branch, lease, pre_rebase_head) for a LiveGit push, else None.
    Must be taken BEFORE _refresh_and_reprove rewrites HEAD."""
    if not isinstance(git, LiveGit) or not git.push_remote:
        return None
    branch = git.branch()
    lease = gitutil.tracking_sha(branch, git.worktree, run_git=run_git,
                                 remote=git.push_remote)
    return git.push_remote, branch, lease, git.head()


def _reclaim_stale_lease(git, log, phase: str, snap, *, run_git=None) -> None:
    """Stale lease: refs/remotes/<remote>/<branch> no longer matches the remote.
    Probe the live tip and adopt it as the lease only when it carries no work this
    worktree lacks; otherwise fail closed. Runs AFTER _refresh_and_reprove so the
    ownership arms read a fresh origin/master. Never pushes."""
    remote, branch, lease, pre_head = snap
    ok, tip = gitutil.probe_remote_tip(remote, branch, git.worktree, run_git=run_git)
    if not ok:
        log(f"{phase}: could not probe {remote}/{branch}; keeping lease "
            f"{lease[:8] or 'none'}")
        return
    if tip == lease:
        log(f"{phase}: {remote}/{branch} still at lease {lease[:8] or 'none'}; "
            "rejection was not remote movement")
        return
    if not tip:
        _fail_closed_on_divergence(
            None, log, None, phase,
            f"{remote}/{branch} was deleted (lease {lease[:8]}); refusing to recreate it",
            disarm=False)
    why = gitutil.owned_remote_tip(lease, pre_head, tip, git.worktree, run_git=run_git)
    if not why:
        # the ownership helpers' `git fetch origin` may have moved the tracking ref to
        # the foreign tip; put it back before failing closed
        gitutil.restore_tracking_ref(remote, branch, lease, git.worktree, run_git=run_git)
        _fail_closed_on_divergence(
            None, log, None, phase,
            f"remote head {tip[:8]} diverged from lease {lease[:8] or 'none'} and "
            f"local {pre_head[:8]} with different content; refusing to push over it",
            disarm=False)
    # the ownership helpers' fetch may already have moved the tracking ref to tip
    cur = gitutil.tracking_sha(branch, git.worktree, run_git=run_git, remote=remote)
    if cur != tip and not gitutil.adopt_tracking_ref(remote, branch, tip, cur,
                                                     git.worktree, run_git=run_git):
        log(f"{phase}: refs/remotes/{remote}/{branch} moved during adopt; keeping lease")
        return
    log(f"{phase}: {remote}/{branch} moved {lease[:8] or 'none'} -> {tip[:8]} "
        f"({why}); lease refreshed")


def _run_thread_ingestion_phase(gh, llm, git, worktree, pr, pre_posting_ids, out_dir,
                                log, res) -> dict:
    """Phase 4.5 wrapper: same commit/push injection as the Phase 5.5 remediation.
    Swallows everything except gate-path-edit (a local commit touched a gate-owning
    path; shipping it later would bypass the gate) and push-failed (the tree and origin
    disagree; nothing downstream can reason about the head)."""
    t0 = time.monotonic()
    try:
        out = run_thread_ingestion(
            gh, llm, git, worktree or ".", pr, pre_posting_ids, out_dir, log,
            commit=lambda msg: _commit_with_gate_remediation(
                git, worktree, msg, log, phase="phase4.5"),
            push=lambda: _push_with_adr_draft(git, log, "phase4.5", llm=llm,
                                              worktree=worktree, out_dir=out_dir,
                                              res=res,
                                              pr=(pr if isinstance(git, LiveGit) else None)))
    except HarnessError as e:
        if e.kind in ("gate-path-edit", "push-failed"):
            raise
        log(f"phase4.5: thread ingestion failed ({e.kind}: {e.detail}); continuing, "
            "condition (11) keeps the hold")
        out = {"found": 0, "fixed": 0, "declined": 0, "skipped": 0, "last_sha": None,
               "error": f"{e.kind}: {e.detail}"}
    except Exception as e:  # noqa: BLE001 - never block the run on this step
        log(f"phase4.5: thread ingestion failed ({e!r}); continuing")
        out = {"found": 0, "fixed": 0, "declined": 0, "skipped": 0, "last_sha": None,
               "error": repr(e)}
    log(f"phase4.5: {out.get('found', 0)} trusted thread(s) found, {out.get('fixed', 0)} fixed, "
        f"{out.get('declined', 0)} declined, {out.get('skipped', 0)} skipped (error) "
        f"in {time.monotonic() - t0:.2f}s")
    return out


def _push_with_lease_retry(git, log, phase: str, *, pr=None, worktree=None,
                           gh_cmd=None, run_git=None) -> str:
    """Push with bounded stale-lease / stale-base retry. Returns pushed HEAD sha, or ""
    when disabled. Raises HarnessError("push-retry-cap") once the cap is spent.
    A stale lease on a LiveGit push also probes the branch's own remote tip after the
    re-rebase: an owned tip (tree-equivalent or patch-series-equivalent to HEAD, or
    equivalent to / an update-branch merge onto the old lease) becomes the new lease; a
    foreign or deleted tip raises remote-head-diverged without pushing.

    Two rejections get the same fetch + clean-rebase + lost-work-proof recovery: a
    stale lease (origin/<branch> moved) and a stale base (bin/pre-push-adr-hook refusing
    because origin/master moved and HEAD no longer contains it). Phase 5.5 is where the
    second one lands: the Phase 2 rebase is 20-40 minutes old by the time the
    remediation commit pushes. Any other denial re-raises unchanged."""
    if pr and worktree:
        branch = git.branch()
        expected = gitutil.tracking_sha(branch, worktree, run_git=run_git)
        if expected:
            r = gitutil.reconcile_remote_head(pr, expected, git.head(), branch, worktree,
                                              gh_cmd=gh_cmd, run_git=run_git)
            if r.action == "diverged":
                log(f"{phase}: ERROR {r.evidence}; refusing to push over it")
                raise HarnessError("remote-head-diverged", f"{phase}: {r.evidence}")
            if r.action == "synced":
                log(f"{phase}: {r.evidence}; nothing to push")
                return r.remote_sha
    for attempt in range(1, _MAX_PUSH_RETRIES + 1):
        try:
            git.push()
            return git.head()
        except HarnessError as e:
            if e.kind == "push-disabled":
                log(f"{phase}: push skipped — disabled outside an approved install")
                return ""
            if is_stale_lease(e):
                why = "stale lease"
            elif is_stale_base(e):
                why = "stale base (HEAD no longer contains origin/master)"
            else:
                raise
            if attempt == _MAX_PUSH_RETRIES:
                raise HarnessError(
                    "push-retry-cap",
                    f"{phase}: {why} after {_MAX_PUSH_RETRIES} push attempts: "
                    f"{(e.detail or '')[:300]}")
            log(f"{phase}: {why} (attempt {attempt}/{_MAX_PUSH_RETRIES}) — "
                "refetch, re-rebase, re-prove")
            snap = (_lease_snapshot(git, run_git=run_git)
                    if why == "stale lease" else None)
            _refresh_and_reprove(git, log, phase, attempt)
            if snap is not None:
                _reclaim_stale_lease(git, log, phase, snap, run_git=run_git)
    return ""  # unreachable


def _commit_with_adr_draft(git, log, phase: str, *, llm, worktree, out_dir, res) -> None:
    """One-shot ADR draft at the COMMIT site, run before commit_all() so the drafted
    ADR lands IN the Phase 2 commit. Stale-base retries are a push-time concept and
    cannot reach here at all: nothing has been pushed yet and no lease exists, which
    is the same structural guarantee _push_with_adr_draft gets from sitting outside
    _push_with_lease_retry. Exactly one draft per run (res.adr_drafted). Every drafter
    failure re-raises the ORIGINAL gate denial so the run still exits 3, never 1."""
    if not worktree:
        return
    rc, out = adr_draft.commit_gate(worktree)
    if rc == 0:
        return
    # Synthesised to carry the hook's marker, so classify_local_gate_denial() reads
    # "adr" on it exactly as it would on the real bin/pre-commit-hook denial.
    denial = HarnessError("local-gate",
                          f"{phase}: pre-commit-adr-gate: {out.strip()[:300]}")
    if res.adr_drafted:
        log(f"{phase}: ADR denial after this run's one draft ({res.adr_path}) "
            "- failing closed")
        raise denial
    log(f"{phase}: pre-commit ADR gate denied - drafting ADR "
        f"(model={adr_draft.MODEL_MAP[adr_draft.ADR_DRAFT_MODEL]})")
    plan_path = res.plan.path if (res.plan and res.plan.found) else None
    try:
        drafted = adr_draft.draft(llm, git, worktree, out_dir, log, phase=phase,
                                  plan_path=plan_path, check_mode="commit",
                                  commit=False)
    except HarnessError as e2:
        if e2.kind == SUBPROCESS_TIMEOUT:
            log(f"{phase}: ADR draft step timed out: {(e2.detail or '')[:300]}")
            raise
        if e2.kind == "adr-draft-gate" and "|" in (e2.detail or ""):
            # staged, then adr-check still failed: record the path for the DM
            rel, _sha, _ = e2.detail.split("|", 2)
            res.adr_drafted, res.adr_path = True, rel
            res.adr_draft_model = adr_draft.MODEL_MAP[adr_draft.ADR_DRAFT_MODEL]
        log(f"{phase}: ADR draft failed ({e2.kind}): {(e2.detail or '')[:300]} "
            "- failing closed")
        raise denial from e2
    res.adr_drafted, res.adr_path, res.adr_draft_model = (
        True, drafted.path, drafted.model)
    log(f"{phase}: ADR drafted at {drafted.path} model={drafted.model} "
        "- staged into the pending Phase 2 commit")


def _push_with_adr_draft(git, log, phase: str, *, llm, worktree, out_dir, res,
                         pr=None) -> str:
    """One-shot ADR draft around _push_with_lease_retry. Stale-lease and stale-base
    retries happen INSIDE the inner helper; this wrapper only ever sees a denial that
    survived them, so it can never draft on a stale base and the two cannot loop.
    Exactly one draft per run (res.adr_drafted). Every drafter failure re-raises the
    ORIGINAL local-gate error so the run still exits 3 (never 1: a skill re-run would
    hit the same hook)."""
    try:
        return _push_with_lease_retry(git, log, phase, pr=pr, worktree=worktree)
    except HarnessError as e:
        if e.kind != "local-gate" or not worktree:
            raise
        if classify_local_gate_denial(e.detail or "") != "adr":
            raise
        if res.adr_drafted:
            log(f"{phase}: ADR denial after this run's one draft ({res.adr_path}) "
                "- failing closed")
            raise
        log(f"{phase}: local-gate denial classified as adr - drafting ADR "
            f"(model={adr_draft.MODEL_MAP[adr_draft.ADR_DRAFT_MODEL]})")
        plan_path = res.plan.path if (res.plan and res.plan.found) else None
        try:
            drafted = adr_draft.draft(llm, git, worktree, out_dir, log,
                                      phase=phase, plan_path=plan_path)
        except HarnessError as e2:
            if e2.kind == SUBPROCESS_TIMEOUT:
                log(f"{phase}: ADR draft step timed out: {(e2.detail or '')[:300]}")
                raise
            if e2.kind == "adr-draft-gate" and "|" in (e2.detail or ""):
                # committed, then adr-check still failed: record the path for the DM
                rel, sha, _ = e2.detail.split("|", 2)
                res.adr_drafted, res.adr_path = True, rel
                res.adr_draft_model = adr_draft.MODEL_MAP[adr_draft.ADR_DRAFT_MODEL]
            log(f"{phase}: ADR draft failed ({e2.kind}): {(e2.detail or '')[:300]} "
                "- failing closed")
            raise e from e2
        res.adr_drafted, res.adr_path, res.adr_draft_model = (
            True, drafted.path, drafted.model)
        log(f"{phase}: ADR drafted at {drafted.path} model={drafted.model} "
            f"commit={drafted.sha[:12]} - re-pushing once")
        try:
            return _push_with_lease_retry(git, log, phase, pr=pr, worktree=worktree)
        except HarnessError as e3:
            if e3.kind == "local-gate":
                log(f"{phase}: hook still denied after the ADR draft "
                    f"(class={classify_local_gate_denial(e3.detail or '')}) - "
                    f"{drafted.path} stays committed locally; failing closed")
            raise


def _fail_closed_on_divergence(gh, log, pr, phase: str, evidence: str, *,
                               disarm: bool) -> None:
    """Remote PR head was rewritten with different content. Never push over it."""
    if disarm and pr:
        gh.pr_disable_auto_merge(pr)
    log(f"{phase}: ERROR {evidence}; failing closed (remote-head-diverged)")
    raise HarnessError("remote-head-diverged", f"{phase}: {evidence}")


def _reconcile_before_arm(git, log, gh, worktree, pr, sha, bg_ci, out_dir, *,
                          gh_cmd=None, run_git=None):
    """Pre-arm remote-head check. Returns (sha, bg_ci); raises on divergence."""
    r = gitutil.reconcile_remote_head(pr, sha, git.head(), git.branch(), worktree,
                                      gh_cmd=gh_cmd, run_git=run_git)
    if r.action == "diverged":
        ciwatch.reap_background_watch(bg_ci)
        _fail_closed_on_divergence(gh, log, pr, "phase6.5", r.evidence, disarm=False)
    if r.action == "synced":
        log(f"phase6.5: {r.evidence}; CI watch restarted on {r.remote_sha[:8]}")
        ciwatch.reap_background_watch(bg_ci)
        bg_ci = ciwatch.start_background_watch(worktree, pr, r.remote_sha, out_dir)
        return r.remote_sha, bg_ci
    return sha, bg_ci


def _resolve_behind(git, gh, log, res, worktree, pr, sha, outcome, out_dir):
    """Bounded BEHIND resolution. Returns (sha, outcome). When the cap is spent
    and the branch is still BEHIND, auto-merge stays armed and the PR is handed
    to .github/workflows/update-behind-prs.yml (ADR-0081); retry_cap is never
    set here. Divergence inside the loop still disarms (see the disarm=True
    call sites below)."""
    strict = gh.branch_protection_strict()
    for attempt in range(1, _MAX_BEHIND_RETRIES + 1):
        mss = gh.merge_state_status(pr)
        if mss != "BEHIND":
            return sha, outcome
        if not strict:
            log("phase7: BEHIND but required checks are not strict — "
                "leaving auto-merge armed")
            return sha, outcome
        log(f"phase7: BEHIND (re-rebase {attempt}/{_MAX_BEHIND_RETRIES})")
        if worktree and os.path.isdir(worktree):
            r = gitutil.reconcile_remote_head(pr, sha, git.head(), git.branch(), worktree)
            if r.action == "diverged":
                _fail_closed_on_divergence(gh, log, pr, "phase7", r.evidence, disarm=True)
            if r.action == "synced":
                log(f"phase7: {r.evidence}")
                sha = r.remote_sha
        _refresh_and_reprove(git, log, "phase7", attempt)
        sha = _push_with_lease_retry(git, log, "phase7", pr=pr,
                                     worktree=worktree) or git.head()
        _live_wt = bool(worktree and os.path.isdir(worktree))
        bg = ciwatch.start_background_watch(worktree, pr, sha, out_dir,
                                            **({'verify_head': True} if _live_wt else {}))
        outcome = ciwatch.watch_or_reuse(worktree, pr, sha, out_dir, bg,
                                         **({'verify_head': True} if _live_wt else {}))
        if outcome.diverged:
            ciwatch.reap_background_watch(bg)
            _fail_closed_on_divergence(gh, log, pr, "phase7", outcome.evidence, disarm=True)
        if outcome.head_sha:
            sha = outcome.head_sha
        ciwatch.reap_background_watch(bg)
        log(f"phase7 ci(re-rebase {attempt}): exit={outcome.exit_code} "
            f"failed={outcome.failed}")
        if outcome.exit_code != 0:
            return sha, outcome
    if gh.merge_state_status(pr) == "BEHIND":
        log(f"phase7: BEHIND cap hit after {_MAX_BEHIND_RETRIES} re-rebases — "
            "leaving auto-merge armed; .github/workflows/update-behind-prs.yml "
            "(ADR-0081) carries the PR to merge")
    return sha, outcome


# bin/automouse/run caps the whole post-plan at MAX_PP_SECS=5400. Leave 600s for
# Phase 9/10 and teardown; test_ci_fix_budget_below_max_pp_secs pins the relation.
_CI_FIX_WALL_BUDGET_SECS = 4800
_CI_FIX_MIN_ITER_SECS = 1200   # one Opus fix plus one CI cycle; less than this, stop
_CI_FIX_REWATCH_FLOOR_SECS = 300   # less than this after a push: record the head, skip the watch


def _phase7_stop(log, trail: list[str], attempt: int, sha, stage: str,
                 e: HarnessError, local_sha=None) -> str:
    """Log and record a Phase 7 attempt that stopped on an error. `stage` is
    "llm", "commit" or "push". `local_sha` is the local ci-fix commit, named in
    the unpushed-commit line. Returns the `last` outcome token."""
    last = f"error:{e.kind}"
    tail = " | ".join(_error_tail(e.output or e.detail))
    log(f"phase7 ci-fix attempt {attempt}: model={cifix.CI_FIX_MODEL_ID} "
        f"outcome={last} stage={stage} sha={str(sha)[:8]}")
    log(f"phase7 ci-fix {stage}-time denial: {tail or '(no output captured)'}")
    trail.append(f"attempt {attempt}: {last} ({stage}-time): {tail or 'no output captured'}")
    if stage in ("commit", "push"):
        log("ci-fix commit is LOCAL and unpushed; "
            "the next bin/post-plan-now run ships it"
            + (f" ({str(local_sha)[:12]})" if local_sha else ""))
    return last


def _phase7_rewatch(gh, log, res, worktree, pr, sha, out_dir, run_started):
    """Re-watch CI on a freshly pushed Phase 7 head. Returns (outcome, sha). Too little
    budget left skips the watch and hands back an indeterminate outcome."""
    remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
    if remaining - 120 < _CI_FIX_REWATCH_FLOOR_SECS:
        log(f"phase7 ci-fix: wall-clock budget exhausted after dep catch-up "
            f"({int(remaining)}s left); CI on {str(sha)[:8]} not re-watched")
        return ciwatch.CiOutcome(-1, [], "budget exhausted after dep catch-up"), sha
    bg = ciwatch.start_background_watch(worktree, pr, sha, out_dir,
                                        timeout=int(remaining - 120), verify_head=True)
    try:
        outcome = ciwatch.watch_or_reuse(worktree, pr, sha, out_dir, bg,
                                         timeout=int(remaining - 120), verify_head=True)
    finally:
        ciwatch.reap_background_watch(bg)
    if outcome.diverged:
        _fail_closed_on_divergence(gh, log, pr, "phase7", outcome.evidence, disarm=True)
    if outcome.head_sha and outcome.head_sha != sha:
        sha = outcome.head_sha
        res.ci_head = sha
    return outcome, sha


def _ci_fix_loop(git, gh, llm, log, res, *, worktree, pr, sha, outcome, out_dir,
                 mode, fixture, run_started):
    """Phase 7 CI fix loop — attempts to fix red CI with Opus.

    Returns (sha, outcome) where outcome reflects the post-fix CI state.
    """
    # 1. Entry guards
    if outcome.exit_code != 8:
        return sha, outcome
    if not outcome.failed:
        log("phase7 ci-fix: exit 8 with no failed-check names - nothing to target")
        return sha, outcome

    # 2. Signoff-only check
    kind, names = cifix.triage(outcome.failed)
    if kind == "green":
        log("phase7 ci-fix: only human-signoff failed - treating CI as green")
        return sha, ciwatch.CiOutcome(0, [], outcome.evidence + " (human-signoff dropped)")

    # 3. Dirty tree guard
    if git.is_dirty():
        log("phase7 ci-fix: skipped - dirty worktree")
        survivors = cifix.triage(outcome.failed)[1]
        try:
            gh.post_review_summary(pr, cifix.SURVIVOR_TITLE,
                                   cifix.survivor_comment(survivors or outcome.failed,
                                                          [], False))
        except (HarnessError, OSError, subprocess.SubprocessError):
            log("phase7 ci-fix: survivor comment not posted")
        return sha, outcome

    # 4. Replay rewatch queue
    queue = list((fixture or {}).get("ci_fix_rewatch") or []) if mode == "replay" else []

    # 5. Loop state
    attempt = 0
    probed = False
    last = None
    trail: list[str] = []
    dep_caught_up = False
    refused: list[tuple[int, str, str]] = []

    while True:
        kind, names = cifix.triage(outcome.failed)

        # Indeterminate or green — done; check exit_code first so a timeout with
        # failed==[] is never washed green by the triage path below.
        if outcome.exit_code != 8:
            return sha, outcome
        if kind == "green":
            log("phase7 ci-fix: only human-signoff failed - treating CI as green")
            return sha, ciwatch.CiOutcome(0, [], outcome.evidence + " (human-signoff dropped)")

        # Probe branch: fires on rollup-only or no-change
        if kind == "rollup-only" or last == "no-change":
            # Budget check before the probe
            remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
            if remaining < _CI_FIX_MIN_ITER_SECS:
                log(f"phase7 ci-fix: wall-clock budget exhausted ({int(remaining)}s left)")
                break
            if probed:
                break
            probed = True
            allow, why = baseline_guard.probe_decision(gh, pr, sha)
            if not allow:
                if why == "update-baselines-label":
                    baseline_guard.maybe_refire(gh, pr, sha, log)
                log(f"phase7 ci-fix rerun probe skipped: reason={why} sha={str(sha)[:8]}")
                break
            all_red_names = list(outcome.failed)
            refs = cifix.failed_job_refs(gh.pr_checks_json(pr), all_red_names)
            seen_run_ids = set()
            for run_id, _job_id in refs.values():
                if run_id not in seen_run_ids:
                    seen_run_ids.add(run_id)
                    gh.run_rerun_failed(run_id)
            # Re-watch
            if mode != "replay":
                for _ in range(10):
                    time.sleep(15)
                    fresh = gh.pr_checks_json(pr)
                    probed_names_set = set(all_red_names)
                    if any(c.get("name") in probed_names_set
                           and c.get("state") != "FAILURE" for c in fresh):
                        break
                remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
                probe_outcome = ciwatch.watch_live(
                    worktree, pr, timeout=max(300, int(remaining - 120)))
            else:
                if queue:
                    e = queue.pop(0)
                    probe_outcome = ciwatch.CiOutcome(
                        e["exit"], e.get("failed", []), "replay rewatch")
                else:
                    probe_outcome = ciwatch.CiOutcome(-1, [], "replay: ci_fix_rewatch exhausted")
            probe_kind, probe_names = cifix.triage(probe_outcome.failed
                                                   if probe_outcome.exit_code == 8 else [])
            if probe_outcome.exit_code == 8:
                probe_result = "still-red"
            elif probe_outcome.exit_code == 0:
                probe_result = "flaky-green"
            else:
                probe_result = "indeterminate"
            log(f"phase7 ci-fix rerun probe: outcome={probe_result} "
                f"sha={str(sha)[:8]} failed={list(probe_outcome.failed) if probe_outcome.exit_code == 8 else []}")
            if probe_outcome.exit_code == 0:
                try:
                    gh.post_review_summary(pr, cifix.FLAKY_TITLE,
                                           cifix.flaky_comment(all_red_names))
                except (HarnessError, OSError, subprocess.SubprocessError):
                    pass
                return sha, probe_outcome
            if probe_outcome.exit_code != 8:
                return sha, probe_outcome
            # Probe still red — continue loop
            outcome = probe_outcome
            last = None
            continue

        # Ceiling and budget
        if attempt >= cifix.MAX_CI_FIX_ATTEMPTS:
            break
        remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
        if remaining < _CI_FIX_MIN_ITER_SECS:
            log(f"phase7 ci-fix: wall-clock budget exhausted ({int(remaining)}s left)")
            break

        # Sync to remote head (live only)
        if mode != "replay":
            r = gitutil.reconcile_remote_head(pr, sha, git.head(), git.branch(), worktree)
            if r.action == "diverged":
                _fail_closed_on_divergence(gh, log, pr, "phase7", r.evidence, disarm=True)
            if r.action == "synced":
                sha = r.remote_sha

        # One-shot dependency catch-up: a bun advisory master already fixed clears on
        # a rebase, so try that before paying for an Opus attempt.
        if mode != "replay" and not dep_caught_up and cifix_ship.bun_audit_failed(names):
            dep_caught_up = True
            git.fetch_base("origin/master")
            if not cifix_ship.master_dep_files_changed(worktree):
                log("phase7 ci-fix: bun audit red; master has no package.json/bun.lock "
                    "change since merge-base - no catch-up")
            else:
                try:
                    _refresh_and_reprove(git, log, "phase7-dep", _DEP_CATCHUP_KEY)
                    sha = _push_with_adr_draft(
                        git, log, "phase7", llm=llm, worktree=worktree, out_dir=out_dir,
                        res=res, pr=(pr if isinstance(git, LiveGit) else None)) or git.head()
                except HarnessError as e:
                    if e.kind == "remote-head-diverged":
                        raise
                    last = _phase7_stop(log, trail, 0, sha, "catchup", e)
                    break
                res.ci_head = sha
                outcome, sha = _phase7_rewatch(gh, log, res, worktree, pr, sha,
                                               out_dir, run_started)
                trail.append(f"dep catch-up: "
                             f"{'green' if outcome.exit_code == 0 else 'still-red'}")
                log(f"phase7 ci-fix dep catch-up: exit={outcome.exit_code} "
                    f"failed={outcome.failed} sha={str(sha)[:8]}")
                continue

        # Fix attempt
        attempt += 1
        fix_dir = os.path.join(out_dir, f"ci-fix-{attempt}")
        os.makedirs(fix_dir, exist_ok=True)

        diff_path = os.path.join(fix_dir, "diff.patch")
        try:
            with open(diff_path, "w") as fh:
                fh.write(git.diff_vs_base())
        except OSError:
            diff_path = ""

        log_paths: dict[str, str] = {}
        refs = cifix.failed_job_refs(gh.pr_checks_json(pr), names)
        for name, (run_id, job_id) in refs.items():
            safe_name = re.sub(r"[^A-Za-z0-9]+", "-", name)
            log_dest = os.path.join(fix_dir, f"{safe_name}.log")
            gh.run_log_failed(run_id, job_id, log_dest)
            log_paths[name] = log_dest

        stage = "llm"
        try:
            remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
            carveout = rules_budget_carveout.snapshot(names, git, worktree)
            log(f"phase7 ci-fix attempt {attempt}: rules-budget carve-out "
                + (f"active for {', '.join(sorted(carveout.in_diff))}" if carveout.active
                   else f"inactive ({carveout.reason})"))
            llm.call_tooled("ci-fix", cifix.CI_FIX_MODEL,
                            cifix.ci_fix_prompt(pr, attempt, names, log_paths,
                                                diff_path, trail,
                                                dep_advisory=cifix_ship.bun_audit_failed(names),
                                                proposal_path=os.path.join(
                                                    fix_dir, cifix_ship.PROPOSAL_FILENAME),
                                                rules_carveout=(tuple(sorted(carveout.in_diff))
                                                                if carveout.active else ())),
                            cwd=worktree or ".", allowed_tools=cifix.CI_FIX_ALLOWED_TOOLS,
                            denied_tools=cifix.CI_FIX_DENIED_TOOLS, add_dirs=(fix_dir,),
                            timeout=int(min(TOOLED_TIMEOUT, remaining / 2)))
            remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
            stage = "commit"
            new = _commit_with_gate_remediation(git, worktree,
                    cifix.CI_FIX_COMMIT_MSG.format(n=attempt), log, phase="phase7")
        except HarnessError as e:
            if e.kind == "remote-head-diverged":
                raise
            last = _phase7_stop(log, trail, attempt, sha, stage, e)
            break

        # PR-body proposal: the agent may not run `gh pr edit`; the harness judges the
        # proposal and applies only a body that leaves every waiver and attestation alone.
        body_applied, baseline = False, None
        proposal = cifix_ship.read_proposal(fix_dir)
        if proposal is not None:
            verdict = cifix_ship.judge_proposal(gh.pr_body_fresh() or "", proposal,
                                                signature=_body_signature)
            if verdict.action == "refuse":
                log(f"phase7 ci-fix: body proposal refused ({verdict.reason})")
                refused.append((attempt, verdict.reason, proposal or ""))
            elif verdict.action == "apply":
                baseline = cifix_ship.meta_run_id(gh.pr_checks_json(pr))
                gh.pr_edit_body(pr, verdict.body)
                body_applied = True
                log("phase7 ci-fix: body proposal applied")

        if new == "" and body_applied:
            # Body-only round: the head did not move, so wait for a Meta checks run
            # newer than the edit. sha and res.ci_head stay as they are.
            meta = cifix_ship.wait_for_fresh_meta_run(
                lambda: gh.pr_checks_json(pr), baseline,
                deadline=run_started + _CI_FIX_WALL_BUDGET_SECS - 120)
            others = [n for n in outcome.failed if n != cifix_ship.META_CHECK_NAME]
            if meta == "indeterminate":
                outcome = ciwatch.CiOutcome(-1, [], "phase7: fresh Meta checks run not observed")
                last = "body-unconfirmed"
            else:
                if meta == "red":
                    others.append(cifix_ship.META_CHECK_NAME)
                outcome = ciwatch.CiOutcome(8 if others else 0, others,
                                            "phase7 body-only re-watch")
                last = "still-red" if others else "body-fixed"
        elif new == "":
            last = "no-change"
        else:
            gate_hits = fidelity.denied_gate_edits(git.changed_files(f"{new}^"))
            if gate_hits and rules_budget_carveout.permit(gate_hits, carveout, worktree,
                                                          new, log):
                gate_hits = []
            if gate_hits:
                last = "error:gate-path-edit"
                log(f"phase7 ci-fix: gate-path edit detected in fix commit {new[:12]}; "
                    "commit is LOCAL and unpushed; the next bin/post-plan-now run ships it")
                log(f"phase7 ci-fix attempt {attempt}: model={cifix.CI_FIX_MODEL_ID} "
                    f"outcome={last} sha={str(sha)[:8]}")
                trail.append(f"attempt {attempt}: {last}")
                break
            else:
                pre_push_head, head_before = new, git.head()
                try:
                    pushed = _push_with_adr_draft(
                        git, log, "phase7", llm=llm, worktree=worktree,
                        out_dir=out_dir, res=res,
                        pr=(pr if isinstance(git, LiveGit) else None))
                    # Only a head the push path moved (catch-up rebase, ADR draft)
                    # replaces the fix commit's own sha.
                    sha = pushed if pushed and pushed != head_before else new
                except HarnessError as push_err:
                    if push_err.kind == "remote-head-diverged":
                        raise
                    last = _phase7_stop(log, trail, attempt, sha, "push", push_err,
                                         local_sha=new)
                    break
                if sha != pre_push_head:
                    log(f"phase7 ci-fix: push caught up to origin/master; "
                        f"head {str(pre_push_head)[:8]} -> {str(sha)[:8]}; re-watching CI")
                remaining = _CI_FIX_WALL_BUDGET_SECS - (time.time() - run_started)
                if mode != "replay" and remaining - 120 < _CI_FIX_REWATCH_FLOOR_SECS:
                    res.ci_head = sha
                    last = "pushed-unwatched"
                    log(f"phase7 ci-fix: wall-clock budget exhausted after push "
                        f"({int(remaining)}s left); CI on {str(sha)[:8]} not re-watched")
                    log(f"phase7 ci-fix attempt {attempt}: model={cifix.CI_FIX_MODEL_ID} "
                        f"outcome={last} sha={str(sha)[:8]}")
                    trail.append(f"attempt {attempt}: {last}")
                    break
                res.ci_head = sha
                # Re-watch
                if mode != "replay":
                    bg = ciwatch.start_background_watch(worktree, pr, sha, out_dir,
                                                        timeout=int(remaining - 120),
                                                        verify_head=True)
                    try:
                        outcome = ciwatch.watch_or_reuse(worktree, pr, sha, out_dir, bg,
                                                         timeout=int(remaining - 120),
                                                         verify_head=True)
                    finally:
                        ciwatch.reap_background_watch(bg)
                    if outcome.diverged:
                        _fail_closed_on_divergence(gh, log, pr, "phase7",
                                                   outcome.evidence, disarm=True)
                    if outcome.head_sha and outcome.head_sha != sha:
                        sha = outcome.head_sha
                        res.ci_head = sha
                else:
                    if queue:
                        e = queue.pop(0)
                        outcome = ciwatch.CiOutcome(e["exit"], e.get("failed", []),
                                                    "replay rewatch")
                    else:
                        outcome = ciwatch.CiOutcome(-1, [], "replay: ci_fix_rewatch exhausted")

                new_kind, _ = cifix.triage(outcome.failed if outcome.exit_code == 8 else [])
                if outcome.exit_code != 8:
                    last = "fixed"
                else:
                    last = "still-red"

        log(f"phase7 ci-fix attempt {attempt}: model={cifix.CI_FIX_MODEL_ID} "
            f"outcome={last} sha={str(sha)[:8]}")
        trail.append(f"attempt {attempt}: {last}")

    # 6. Survivors
    survivors_kind, survivors = cifix.triage(outcome.failed if outcome.exit_code == 8 else [])
    if outcome.exit_code != 8:
        survivors = []
    else:
        survivors = survivors or list(outcome.failed)
    if survivors:
        try:
            gh.post_review_summary(pr, cifix.SURVIVOR_TITLE,
                                   cifix.survivor_comment(
                                       survivors, trail, probed,
                                       refused=[(a, r, cifix_ship.quote_proposal(t, _redact))
                                                for a, r, t in refused]))
        except (HarnessError, OSError, subprocess.SubprocessError):
            log("phase7 ci-fix: survivor comment not posted")
    log(f"phase7 ci-fix: ceiling reached - {len(trail)} attempt(s), survivors={survivors}")
    return sha, outcome


def _should_resolve_behind(live: bool, pr, decision, outcome) -> bool:
    """Call-site predicate for the Phase 7 BEHIND-resolution entry guard."""
    return bool(live and pr and decision.armed and outcome.exit_code == 0)


def _compute_terminal(res: RunResult, armed: bool) -> TerminalState:
    """Resolve terminal state after Phase 7; retry_cap beats armed."""
    if res.degraded_agents:
        return TerminalState.DEGRADED
    if res.retry_cap:
        return TerminalState.SHIPPED_HELD
    if armed:
        return TerminalState.SHIPPED_ARMED
    return TerminalState.SHIPPED_HELD


# --- Phase 2 local-gate remediation ------------------------------------------
# One denial class is mechanical: bin/pre-commit-hook rejecting a commit because a
# touched doc's last_verified was not bumped. In a harness run the agent already made
# that edit as part of implementing a plan and simply omitted the stamp, so bumping it
# is completing the on-touch rule, not certifying unread content. Every other class
# stays fail-closed on exit 3.
_DOC_FIX_SCRIPT = "bin/check-docs"
_DOC_FIX_FLAG = "--fix-dates"
_REMEDIATION_NOTE = "Auto-remediated: bumped last_verified for on-touch docs"


def _doc_gate_base(worktree: str) -> str:
    """The base bin/pre-commit-hook hands to check-docs, derived identically.

    bin/pre-commit-hook:54 --
        doc_base=$(git merge-base HEAD origin/master 2>/dev/null) || doc_base=""
    Deriving it the same way is what makes the fix and the gate read ONE changed set;
    a hardcoded "origin/master" would be a second, independently-drifting base.
    An empty result means the hook skips its doc gate entirely (fetch-less clone), so
    it also means there is nothing here to remediate.
    """
    proc = subprocess.run(["git", "-C", worktree, "merge-base", "HEAD", "origin/master"],
                          capture_output=True, text=True, errors="replace")
    return proc.stdout.strip() if proc.returncode == 0 else ""


def _remediate_doc_staleness(worktree: str, git, log) -> int:
    """Run the on-touch date bump the hook asked for, stage it, return how many docs
    moved. Returns 0 when nothing changed and -1 when remediation could not run."""
    base = _doc_gate_base(worktree)
    if not base:
        log("phase2: cannot remediate doc-staleness - no merge-base with origin/master")
        return -1
    argv = [os.path.join(worktree, _DOC_FIX_SCRIPT), _DOC_FIX_FLAG, f"--since={base}"]
    try:
        proc = subprocess.run(argv, cwd=worktree, capture_output=True,
                              text=True, errors="replace")
    except OSError as exc:
        log(f"phase2: cannot remediate doc-staleness - {_DOC_FIX_SCRIPT}: {exc}")
        return -1
    # A non-zero exit is NOT fatal: --fix-dates still reports findings it cannot fix
    # (a dead reference, a future date). The retried commit is the authority on
    # whether the gate cleared, so count what moved and let the hook decide.
    # run() called git.stage_all() before commit_all (Phase 5.5: commit_all's own
    # `add -A` ran before the hook refused), so the tree was clean against
    # the index; every path --fix-dates rewrote is now an UNSTAGED change. That is an
    # exact count with no parsing of the script's prose.
    out = subprocess.run(["git", "-C", worktree, "diff", "--name-only", "--", "*.md"],
                         capture_output=True, text=True, errors="replace").stdout
    n = len([p for p in out.splitlines() if p.strip()])
    if n == 0:
        log(f"phase2: {_DOC_FIX_SCRIPT} {_DOC_FIX_FLAG} bumped nothing "
            f"(exit {proc.returncode}) - failing closed")
        return 0
    git.stage_all()
    log(f"phase2: auto-remediated doc-staleness - bumped last_verified in {n} docs "
        f"(base: {base[:12]})")
    return n


def _pr_copy(llm, git, gh, fixture, slug, cls, plan, log) -> tuple[dict, bool]:
    """Phase 2 commit/PR copy: ({title, commit_subject, summary_md}, degraded).

    Re-run with an open PR and nothing to commit: the Sonnet call is skipped. Its title
    and body would reach neither a commit (commit_all returns "" on a clean index) nor
    the PR (pr_create is skipped when the PR exists). Condition (8) reads the title from
    gh.pr_meta(), never from this dict. The skip path still returns the live title so a
    later reader of copy["title"] cannot see a chore: title on a feat: PR. summary_md is
    "" there: the live body already carries the runner's Manual Testing section, and
    passing it back would log a false "stripped model-authored" line.

    Unparseable model output ("llm-invalid-output") falls back to the branch's own
    commit subject instead of failing the run, and returns degraded=True so the caller
    holds arming: an unreviewed title is exactly the feat-vs-chore judgment condition
    (8) depends on. Any other error kind still propagates.

    The model path returns schemas.coerce_pr_copy(copy, cls), so title, commit_subject and
    type agree and a diff with no GM-visible file (Classification.has_gm_visible False)
    can never open a feat: PR. The degraded path calls schemas.coerce_copy_type on the
    single subject string (the degraded dict has no type key). The skip path is
    deliberately left alone: its title is the live one, and retyping only the dict would
    let the pr_meta() fallback at the condition-(8) call site see chore: on a feat: PR.
    """
    if gh.pr_exists() and not git.has_changes_to_commit():
        head_subject = git.branch_head_subject()
        title = gh.pr_title() or head_subject or f"chore: {slug}"
        log("phase2: PR exists and tree is clean — pr-copy skipped")
        return {"title": title, "commit_subject": head_subject or title,
                "summary_md": ""}, False
    if fixture:
        plan_excerpt = (fixture.get("plan_content") or "")[:4000]
    elif plan.found and plan.path:
        with open(plan.path) as fh:
            plan_excerpt = fh.read()[:4000]
    else:
        plan_excerpt = ""
    try:
        copy = llm.call(schemas.PR_COPY_PURPOSE, "sonnet",
                        llm_calls.pr_copy_prompt(slug, cls, plan, plan_excerpt),
                        schemas.validate_pr_copy,
                        normalizer=schemas.normalize_pr_copy)
        return schemas.coerce_pr_copy(copy, cls), False
    except HarnessError as e:
        if e.kind != "llm-invalid-output":
            raise
        log(f"phase2: pr-copy DEGRADED ({(e.detail or '')[:200]}) — using commit subject")
    subject = git.branch_head_subject()
    if not re.match(r"^[a-z]+(\([^)]*\))?!?:", subject):
        # No conventional subject to borrow. feat: is the fail-closed type: it trips the
        # human-signoff hold. coerce_copy_type re-types docs/test/non-code diffs and turns
        # feat: into chore: on a tooling-only diff; arming is still held on this path by
        # degraded=True (copy_degraded), so the floor is never the only guard here.
        subject = f"feat: {subject or slug}"
    subject = schemas.coerce_copy_type(subject, cls)
    return {"title": subject, "commit_subject": subject,
            "summary_md": f"## Summary\n- {subject}\n"}, True


def _upsert_no_adr_markers(body: str, plan) -> str:
    """Prepend the plan's no-ADR marker lines to the top of the PR body.
    Plan-blind (plan None / plan.found False) or no markers => passthrough.
    Idempotent: a marker already present anywhere in the body is not re-added."""
    if plan is None or not plan.found:
        return body
    markers = getattr(plan, "no_adr_markers", None) or []
    to_prepend = [m for m in markers if m not in body]
    if not to_prepend:
        return body
    return "\n".join(to_prepend) + "\n\n" + body


def _apply_backlog_closes(body: str, plan, log) -> str:
    """Normalize closing keywords from the plan's `## Backlog issues` section.
    Plan-blind (plan.found False) or no section => both lists empty => passthrough."""
    issues = plan.backlog_issues if (plan and plan.found) else []
    closes = [n for k, n in issues if k == "closes"]
    refs = [n for k, n in issues if k == "refs"]
    new = normalize_backlog_closes(body, closes, refs)
    if new != body:
        log(f"phase2: backlog closes normalized (closes={closes} refs={refs})")
    return new


def _check_backlog_closes(gh, pr, plan, log) -> None:
    """Lever-3 self-check: ask GitHub which issues this PR will close and log a
    loud WARN on disagreement. Never raises; never blocks arming."""
    closes = [n for k, n in (plan.backlog_issues if plan and plan.found else [])
              if k == "closes"]
    if not closes:
        return
    try:
        base, refs = gh.pr_closing_refs(pr)
    except Exception as e:
        log(f"phase2: backlog-closes self-check skipped ({str(e)[:120]})")
        return
    msg = backlog_closes_mismatch(closes, base, refs)
    log(msg)


def _sweep_out_of_scope(gh, pr, plan, slug, log) -> list[int]:
    """File one backlog issue per `## Out of Scope` deferral. Additive: never raises,
    never touches arming state. Dedup lives in outofscope.file_deferral_issues."""
    if not (plan and plan.found and plan.deferral_hits):
        return []
    try:
        hits = [outofscope.DeferralHit(*t) for t in plan.deferral_hits]
        nums = outofscope.file_deferral_issues(
            gh, hits, slug, pr, log=log,
            plan_name=os.path.basename(plan.path) or f"{slug}.md")
        log(f"oos-sweep: {len(hits)} hits, {len(nums)} issues filed")
        return nums
    except Exception as exc:  # broad on purpose: the sweep is never a run failure
        log(f"oos-sweep: sweep failed ({type(exc).__name__}: {exc})")
        return []


def _inject_residual_phases(copy: dict, plan, files: list[str], log) -> list[str]:
    """Phase 2: upsert `## Residual Phases` into copy["summary_md"] from phase-omission items.

    Runs AFTER _body_check so an LLM-corrected body cannot drop the block, and BEFORE the
    commit so the commit body and the PR body carry it. Idempotent: an empty item list
    removes a stale block. Never raises on a plan-blind run (phase_omission_items returns
    [] when plan.found is False). Returns the items for the caller's log line. Exemption
    notes (`UNCHECKABLE-PHASE`, `NO-DIFF-PHASE`, `NO-DIFF-IGNORED`) are logged under
    `phase2 residual-phase-exempt:` and never enter the `## Residual Phases` block or the
    return value.
    """
    notes: list[str] = []
    items = conformance.phase_omission_items(plan, files, notes=notes)
    copy["summary_md"] = upsert_residual_phases(copy["summary_md"],
                                                render_residual_phases(items))
    for it in items:
        log(f"phase2 residual-phase: {it}")
    for note in notes:
        log(f"phase2 residual-phase-exempt: {note}")
    return items


def _inject_scope_notes(copy: dict, plan, files: list[str], diff_body: str, pr_body: str,
                        log) -> list[str]:
    """Phase 2: upsert `## Unplanned changes` into copy["summary_md"] from scope notes.

    Runs right after _inject_residual_phases. Advisory only: no hold. The one
    fail-closed path is a scope-check hang, which raises HarnessError(subprocess-timeout)
    out of scope_notes. `pr_body` is the body being authored, so a `## Declared scope` or
    `## Plan gaps` section in it clears its own note; the helper strips generated
    marker spans first, so this block never declares itself. Idempotent: an empty note
    list removes a stale block. Returns the notes for the caller's log line.
    """
    notes = scope_conformance.scope_notes(plan, files, diff_body, pr_body)
    copy["summary_md"] = upsert_scope_notes(copy["summary_md"], render_scope_notes(notes))
    for n in notes:
        log(f"phase2 scope-note: {n}")
    return notes


def _body_check(llm, git, gh, copy, copy_degraded, cls, log, worktree=None) -> tuple[dict, bool]:
    """Phase 2 body-vs-diff verification: (result, body_check_degraded).

    Returns ({}, False) without calling the LLM when pr-copy was degraded (the
    fallback body is a one-line stub with nothing to reconcile) or when the subject
    text resolves to empty.  On a degraded model reply, returns the mechanically-
    corrected body so number corrections survive the model failure.  Test-count
    mismatches are annotated in the returned corrected_body and appended to
    findings on both the LLM and degraded paths.
    """
    if copy_degraded:
        log("phase2: body-check skipped (pr-copy degraded)")
        return {}, False

    diff = git.diff_vs_base()
    ns = name_status_text(diff)
    nums = numstat_text(diff)

    # Choose subject text: write-back path vs read-only path (clean-tree rerun)
    if copy["summary_md"]:
        subject = copy["summary_md"]
    else:
        subject = gh.pr_body_fresh() or ""
    if not subject:
        log("phase2: body-check skipped (empty body)")
        return {}, False

    # Mechanical pass — runs regardless of whether the LLM pass later degrades
    mechanically_corrected = body_numbers.correct_body_numbers(subject, ns, nums)
    if mechanically_corrected != subject:
        log("phase2: body-check corrected numbers mechanically")

    # LLM pass
    degraded = False
    try:
        result = llm.call(
            "body-check", "sonnet",
            llm_calls.pr_body_check_prompt(
                body_numbers.body_prose_for_check(mechanically_corrected), ns),
            schemas.validate_body_check)
        log(f"phase2: body-check findings: {len(result.get('findings', []))}")
    except HarnessError as e:
        if e.kind not in ("llm-invalid-output", "llm-fixture-missing"):
            raise
        log(f"phase2: body-check DEGRADED ({(e.detail or '')[:200]})")
        result = {"corrected_body": mechanically_corrected, "findings": []}
        degraded = True

    # Test-count pass: mechanical, after the LLM so its rewrite cannot drop the note
    read_file = (None if worktree is None
                 else (lambda p: body_numbers.read_worktree_file(worktree, p)))
    annotated, tc_findings = body_numbers.annotate_test_count_mismatches(
        result.get("corrected_body", ""), diff, read_file)
    if tc_findings:
        result = {**result, "corrected_body": annotated,
                  "findings": list(result.get("findings", [])) + tc_findings}
        for f in tc_findings:
            log(f"phase2: body-check {f}")
    return result, degraded


def run_meta_checks_local(git, repo_root, base, log, *, body_file=None, live=True,
                         failures_out: list | None = None, llm=None) -> bool:
    if os.environ.get("PRE_PUSH_META_CHECKS_SKIP") == "1":
        log("phase2: meta-checks SKIPPED (PRE_PUSH_META_CHECKS_SKIP=1)")
        return True
    calls = getattr(git, "meta_checks_calls", None)
    if calls is not None:
        calls.append(("pre-push", git.pushes if hasattr(git, "pushes") else -1))
    if not live:
        return True
    if not repo_root:
        # No worktree path to run the gate from. Without this guard subprocess.run
        # raises FileNotFoundError out of chdir(""), aborting Phase 2 before the push.
        log("phase2: meta-checks SKIPPED (no worktree path)")
        return True
    runner = os.path.join(repo_root, "bin", "run-meta-checks-local")
    argv = [runner, "--stage", "pre-push", "--base", base]
    if body_file:
        argv += ["--body-file", body_file]
    branch_slug = git.branch().replace("/", "-")
    flag = f"/tmp/ibl5-meta-checks-prepush-{branch_slug}.failed"
    result = subprocess.run(argv, cwd=repo_root, capture_output=True, text=True)
    last_result = result
    rc = result.returncode
    if rc == 3:
        raise HarnessError("local-gate",
                           f"meta-checks filter-parse failure: {(result.stderr or '').strip()[:200]}",
                           cmd=" ".join(argv), output=(result.stderr or ""))
    if rc == 0:
        try:
            os.unlink(flag)
        except FileNotFoundError:
            pass
        return True
    # rc == 1: one bounded fix attempt
    # os.path.exists (not isdir) because .git is a regular file in bin/wt-new worktrees
    worktree = repo_root if os.path.exists(os.path.join(repo_root, ".git")) else None
    if worktree and _remediate_doc_staleness(worktree, git, log) > 0:
        git.commit_all(f"chore: auto-remediate doc staleness\n\n{_REMEDIATION_NOTE}")
        result2 = subprocess.run(argv, cwd=repo_root, capture_output=True, text=True)
        last_result = result2
        rc2 = result2.returncode
        if rc2 == 3:
            raise HarnessError("local-gate",
                               f"meta-checks filter-parse failure: {(result2.stderr or '').strip()[:200]}",
                               cmd=" ".join(argv), output=(result2.stderr or ""))
        if rc2 == 0:
            try:
                os.unlink(flag)
            except FileNotFoundError:
                pass
            return True
    # One bounded prose-fix pass (harness/prosefix.py) when check-prose-since is the
    # only failing gate. On failure it resets to its own head_before, so the flag,
    # log line, and pushed tree below are exactly what they would have been.
    if (llm is not None and worktree
            and prosefix.failed_check_names(last_result.stdout or "")
            == [prosefix.PROSE_CHECK]):
        if prosefix.attempt_prose_fix(
                git=git, llm=llm, repo=worktree, argv=argv,
                first_stdout=last_result.stdout or "",
                commit=lambda m: _commit_with_gate_remediation(git, worktree, m, log),
                log=log):
            try:
                os.unlink(flag)
            except FileNotFoundError:
                pass
            return True
    # Still failing: write flag file and push anyway
    output = (last_result.stdout or "").strip()
    names = [line.split("META-CHECK-FAILED:", 1)[1].strip()
             for line in output.splitlines()
             if line.startswith("META-CHECK-FAILED:")]
    failed_names = " ".join(names) or "unknown"
    if failures_out is not None:
        # bin/run-meta-checks-local prints one interleaved stream, so every failing
        # name carries the same tail excerpt -- the same thing a human reads.
        excerpt = output[-META_CHECK_OUTPUT_CAP:]
        failures_out.extend({"name": n, "output": excerpt} for n in (names or ["unknown"]))
    try:
        with open(flag, "w") as fh:
            fh.write(failed_names + "\n")
    except OSError:
        pass
    log(f"phase2: META-CHECKS FAILED ({failed_names}) — pushing anyway, auto-merge will not arm")
    return False


def _commit_with_gate_remediation(git, worktree: str | None, message: str, log,
                                  phase: str = "phase2") -> str:
    """Phase 2 commit with ONE bounded auto-remediation of the mechanical gate class.

    Only "doc-staleness" is remediable. "byte-budget" (bin/check-rules-byte-budget has
    no --fix flag; trimming prose is human judgment), "adr", and "unknown" all re-raise
    unchanged and land on exit 3 via _FAIL_CLOSED_KINDS.

    Bounded at exactly one retry: a second local-gate denial re-raises the ORIGINAL
    error, so res.error names the gate that actually blocked and nothing loops.

    ADR denials are structurally outside this wrapper -- bin/pre-push-adr-hook is a
    PUSH hook and run() calls git.push() well after commit_all(). The "adr" arm here is
    defence in depth against a locally-installed pre-commit variant, not the protection.

    Phase 5.5 remediation commits through here too, with phase="phase5.5" so its log
    lines don't read as Phase 2's.
    """
    if phase != "phase2":
        _log = log
        log = lambda m: _log(m.replace("phase2:", f"{phase}:", 1))  # noqa: E731
    try:
        return git.commit_all(message)
    except HarnessError as e:
        if e.kind != "local-gate":
            raise
        gate_class = classify_local_gate_denial(e.detail or "")
        log(f"phase2: local-gate denial classified as {gate_class}")
        # Guard on worktree, never on `live`: isolated mode builds a real LiveGit with
        # real commits while live is False, and that is the mode this path is
        # exercised end-to-end in. Replay mode passes worktree=None.
        if gate_class != "doc-staleness" or not worktree:
            raise
        if _remediate_doc_staleness(worktree, git, log) <= 0:
            raise
        try:
            return git.commit_all(f"{message}\n\n{_REMEDIATION_NOTE}")
        except HarnessError as e2:
            if e2.kind != "local-gate":
                raise
            log("phase2: doc-staleness remediation did not clear the gate "
                f"(retry denial: {(e2.detail or '')[:200]}) - failing closed")
            raise e from None


def _master_sha(worktree: str | None) -> str:
    if not worktree:
        return "origin/master"
    proc = subprocess.run(["git", "-C", worktree, "rev-parse", "origin/master"],
                          capture_output=True, text=True)
    return proc.stdout.strip() or "origin/master"


def _plan_hash(plan) -> str:
    """sha256 of the plan file's BYTES, or "" when there is no readable plan.

    Bytes, not decoded text: reading in text mode applies universal-newline
    translation, so a CRLF plan would hash differently from its own content and a
    carry-forward would be declined for a plan nobody edited. "" is the plan-blind
    value and always declines carry-forward.
    """
    if not getattr(plan, "found", False) or not getattr(plan, "path", ""):
        return ""
    try:
        with open(plan.path, "rb") as fh:
            return hashlib.sha256(fh.read()).hexdigest()
    except OSError:
        return ""


def _run_fidelity(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
                  reviewed_tree, live, log, res, before_remediation=None,
                  unresolved_conformance=(), meta_check_failures=(), scored_findings=()):
    """Phase 5.5. Returns (verdict_word_or_None, error_kind_or_'').

    `before_remediation` runs once, before `build_packet()`, so Phase 4 results are
    available when the fidelity packet is built. It is set to None after the call.

    Replay fixtures that carry no canned `plan-fidelity-review` keep the historical
    synthetic READY, so the pre-Phase-5.5 trace corpus stays green; a fixture opts into
    the real path simply by canning that purpose.
    """
    plan_hash = _plan_hash(plan)
    diff_id = fidelity.diff_patch_id(diff)
    canned = getattr(llm, "canned", None)
    if not live and isinstance(canned, dict) and "plan-fidelity-review" not in canned:
        log("phase5.5 fidelity: replay fixture carries no verdict - synthetic READY")
        res.fidelity = {"verdict_1": "READY", "error_kind": None,
                        "reviewed_tree": reviewed_tree,
                        "verdict_path": fidelity.verdict_path(pr),
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None,
                        "findings_round": 0,
                        "rounds": [], "rounds_completed": 0,
                        "backlog_issue_numbers": [],
                        "diff_id": diff_id, "plan_hash": plan_hash, "models": []}
        return "READY", ""
    # Carry-forward: when the branch diff under review (by patch-id, so a clean rebase
    # onto a moved master still matches) and the plan behind it are both unchanged from
    # what the sticky already records a terminal verdict for, reuse that verdict instead
    # of re-spawning the pinned Opus reviewer. Every degraded
    # shape declines and the full review runs, so this can only ever remove a spawn,
    # never manufacture a verdict. The sticky read is live-only; the replay
    # short-circuit above requires `not live`, so the two paths never overlap.
    prior_sticky = None
    if live and pr:
        try:
            prior_sticky = gh.pr_sticky_body(pr)
        except Exception:
            prior_sticky = None
    carried, decline_reason = fidelity.carry_forward_predicate(
        prior_sticky, diff_id, plan_hash)
    # The sticky composed after a carry still quotes verdict_path(pr). If /tmp lost it,
    # carrying would overwrite the prior sticky's real excerpt and digest with
    # placeholders, so decline and let the full review regenerate the file.
    if carried and not fidelity.verdict_file_usable(fidelity.verdict_path(pr)):
        carried, decline_reason = None, "verdict-file-missing"
    if carried:
        log(f"phase5.5 fidelity: review: carried forward (patch-id {diff_id[:12]})")
        res.fidelity = {"verdict_1": carried, "error_kind": None,
                        "reviewed_tree": reviewed_tree,
                        "verdict_path": fidelity.verdict_path(pr),
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None,
                        "findings_round": 0,
                        "rounds": [], "rounds_completed": 0,
                        "backlog_issue_numbers": [],
                        "diff_id": diff_id, "plan_hash": plan_hash, "models": [],
                        "carried_forward": True}
        return carried, ""
    log(f"phase5.5 fidelity: review: full (carry-forward declined: {decline_reason})")
    # Join Phase 4 before building the fidelity packet so phase4b_ran is truthful.
    if before_remediation is not None:
        before_remediation()
        before_remediation = None  # prevent double-call in the NOT READY guard below
    phase4b_ran = getattr(res, "phase4b_code_ran", False)
    _reviewed_head = getattr(res, "phase4b_reviewed_head", "")
    _findings = getattr(res, "findings", ()) or ()
    _lines = [f"- {f.path}:{f.line} (score {f.score or 0}) -- {(f.body or '')[:160]}"
              for f in _findings]
    if _reviewed_head:
        _lines.insert(0, f"reviewed_head: {_reviewed_head}")
    review_findings = "\n".join(_lines)
    try:
        packet = fidelity.build_packet(
            out_dir, master_sha, reviewed_tree, plan, diff, body, pr,
            phase4b_ran=phase4b_ran, worktree=worktree or ".",
            review_findings=review_findings)
    except HarnessError as e:
        log(f"phase5.5 fidelity: packet failed ({e.kind}) - verdict indeterminate")
        res.fidelity = {"verdict_1": None, "error_kind": e.kind,
                        "reviewed_tree": reviewed_tree,
                        "verdict_path": fidelity.verdict_path(pr),
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None,
                        "findings_round": 0,
                        "rounds": [], "rounds_completed": 0,
                        "backlog_issue_numbers": [],
                        "diff_id": diff_id, "plan_hash": plan_hash, "models": []}
        return None, e.kind
    verdict, err = fidelity.review(llm, out_dir, worktree or ".", packet, pr,
                                   reviewed_tree=reviewed_tree)
    log(f"phase5.5 fidelity: verdict={verdict or 'INDETERMINATE'}"
        + (f" error={err}" if err else "") + f" reviewed_tree={reviewed_tree[:12]}")
    res.fidelity = {"verdict_1": verdict, "error_kind": err or None,
                    "reviewed_tree": reviewed_tree,
                    "verdict_path": fidelity.verdict_path(pr),
                    "remediation_sha": None, "verdict_2": None,
                    "reviewed_tree_2": None,
                    "findings_round": 0,
                    "rounds": [], "rounds_completed": 0,
                    "backlog_issue_numbers": [],
                    "diff_id": diff_id, "plan_hash": plan_hash}
    rounds = []
    current_verdict_path = fidelity.verdict_path(pr)
    final_verdict, final_err = verdict, err
    for round_num in range(1, fidelity.MAX_FIDELITY_ROUNDS + 1):
        if final_verdict != "NOT READY":
            break
        head_before = git.head()
        model = _round_model(round_num)
        work = fidelity.build_work_list(current_verdict_path, unresolved_conformance,
                                        meta_check_failures,
                                        getattr(res, "scored_findings", ()) or scored_findings)
        rec = {"round": round_num, "model": model,
               "work_list_sizes": fidelity.work_list_sizes(work),
               "retries": 0, "outcome": None, "remediation_sha": None,
               "verdict": None, "reviewed_tree": None, "verdict_path": None}
        log(f"phase5.5 round {round_num}: model={model} work={rec['work_list_sizes']}")
        sha, terminal = None, False
        raw_before_round = ""  # pinned to attempt 0; used for the per-round restore
        # The retry lives INSIDE the iteration, so a retry can never advance round_num
        # and the fixer spawn count stays bounded at 2 * MAX_FIDELITY_ROUNDS.
        for attempt in range(_MAX_ROUND_RETRIES + 1):
            outcome: dict = {}
            # Snapshot BEFORE the fixer runs, and compare against a second read after
            # it: the fixer edits the body with its own `gh pr edit` subprocess, so the
            # adapter never sees the write. Comparing the live body against this
            # function's `body` parameter instead would report a body-only round on
            # every PR whose body was edited by anything else since Phase 4 wrote it.
            raw_before = gh.pr_body_fresh()
            sig_before = _body_signature(raw_before)
            if attempt == 0:
                raw_before_round = raw_before
            try:
                sha = fidelity.remediate(
                    llm, git, out_dir, worktree or ".", packet, current_verdict_path,
                    master_sha, log=log,
                    commit=lambda msg: _commit_with_gate_remediation(
                        git, worktree, msg, log, phase="phase5.5"),
                    # The same retrying push Phase 2 and both Phase 7 pushes (BEHIND, ci-fix) use, so a master that
                    # moved during the review + fix span gets one clean rebase per
                    # attempt instead of ending the loop on the hook's
                    # "does not contain origin/master".
                    push=lambda: _push_with_adr_draft(git, log, "phase5.5", llm=llm,
                                                      worktree=worktree, out_dir=out_dir,
                                                      res=res,
                                                      pr=(pr if isinstance(git, LiveGit) else None)),
                    pr_number=pr, model=model, work_list=work, outcome=outcome)
            except HarnessError as e:
                if raw_before_round and "## Manual Testing" in raw_before_round:
                    _live = gh.pr_body_fresh()
                    if _live:
                        _fixed, _rev = restore_manual_testing_section(_live, raw_before_round)
                        if _rev:
                            gh.pr_edit_body(pr, _fixed)
                            log(f"phase5.5 round {round_num}: reverted fixer edit to ## Manual Testing")
                if e.kind == "push-failed":
                    raise
                # Keep the hook's own words: "local-gate" alone does not say which hook
                # said no.
                why = " ".join((e.detail or "").split())[:300]
                gate = (f" class={classify_local_gate_denial(e.detail or '')}"
                        if e.kind == "local-gate" else "")
                # A rebase conflict, a spent retry cap or a gate-path edit lands here
                # AFTER the commit: the fix exists locally and origin never saw it. Say
                # so, so the human (or the next run, whose Phase 2 rebases and pushes
                # whatever HEAD carries) knows the work is not lost. None of these kinds
                # ends the run — the PR is open and held.
                local = ""
                try:
                    if git.head() != head_before:
                        local = (f" - remediation commit {git.head()[:12]} is LOCAL and "
                                 "unpushed; the next bin/post-plan-now run ships it")
                except Exception:  # noqa: BLE001 - diagnostics must never mask the denial
                    pass
                log(f"phase5.5 round {round_num} attempt {attempt + 1}: remediation "
                    f"unavailable ({e.kind}){gate}"
                    + (f" - {why}" if why else "") + local
                    + (f" - drafted ADR {res.adr_path} is committed locally"
                       if res.adr_path else ""))
                if e.kind in _TRANSIENT_ROUND_KINDS and attempt < _MAX_ROUND_RETRIES:
                    rec["retries"] += 1
                    continue
                rec["outcome"] = e.kind
                terminal = e.kind not in _TRANSIENT_ROUND_KINDS
                break
            if sha:
                rec["outcome"] = "committed"
                break
            raw_after = gh.pr_body_fresh()
            if raw_before and not raw_after:
                # `gh pr view` failures are swallowed by LiveGh._fetch_meta(), which
                # returns {} and so hands back "". A body that was non-empty and now
                # reads empty is therefore a failed fetch or a wiped body -- never a
                # fix worth re-reviewing. Fail loud and stop the run for a human.
                log(f"phase5.5 round {round_num}: PR body read back empty after "
                    f"remediation (was {len(raw_before)} chars) - treating as a failed "
                    f"fetch, not a body edit")
                rec["outcome"] = BODY_FETCH_FAILED_REASON
                terminal = True
                break
            if raw_before and _body_signature(raw_after) != sig_before:
                log(f"phase5.5 round {round_num}: body-only fix detected "
                    f"(no commit; PR body changed)")
                sha = BODY_ONLY_SHA
                rec["outcome"] = BODY_ONLY_SHA
                break
            reason = outcome.get("reason", "none")
            if reason in _TRANSIENT_ROUND_REASONS and attempt < _MAX_ROUND_RETRIES:
                rec["retries"] += 1
                continue
            rec["outcome"] = reason
            terminal = reason not in _TRANSIENT_ROUND_REASONS
            break
        rounds.append(rec)
        res.fidelity["rounds"] = rounds
        if not sha and raw_before_round and "## Manual Testing" in raw_before_round:
            _live = gh.pr_body_fresh()
            if _live:
                _fixed, _rev = restore_manual_testing_section(_live, raw_before_round)
                if _rev:
                    gh.pr_edit_body(pr, _fixed)
                    log(f"phase5.5 round {round_num}: reverted fixer edit to ## Manual Testing")
        if terminal:
            break
        if not sha:
            # Transient budget spent. The next round runs the stronger model under the
            # same cap; it does not extend it.
            continue
        # pr_body_fresh, never pr_body: the Phase 4 write at runner.py:446 leaves
        # _body_override set, so pr_body() here would hand back the harness's own copy
        # and silently overwrite whatever the remediation agent edited on GitHub.
        live_body = gh.pr_body_fresh() or body
        # The Manual Testing section is runner-owned and is the arming gate's input.
        # A fixer that rewords or drops it gets reverted here (#2489).
        live_body, restored = restore_manual_testing_section(live_body, raw_before_round)
        if restored:
            log(f"phase5.5 round {round_num}: reverted fixer edit to ## Manual Testing")
        refreshed_diff = git.diff_vs_base()
        body = upsert_files_changed(live_body, render_files_changed(refreshed_diff))
        body = upsert_tests_changed(body, render_tests_changed(refreshed_diff))
        body = _apply_backlog_closes(body, plan, log)
        body = _upsert_no_adr_markers(body, plan)
        gh.pr_edit_body(pr, body)
        # sha is either a real commit sha or BODY_ONLY_SHA. fidelity.re_review()'s only
        # test of it is `if not remediation_sha: return None, None, None`, so a non-empty
        # sentinel passes unchanged and the re-review runs against the same diff plus the
        # edited body. Its context block then reads "REMEDIATION_COMMIT: body-only",
        # which is the accurate label for a round that produced no commit. This is why
        # fidelity.re_review() needs no signature change.
        v_n = tree_n = path_n = None
        for rr_attempt in range(_MAX_ROUND_RETRIES + 1):
            v_n, tree_n, path_n = fidelity.re_review(
                llm, git, out_dir, worktree or ".", plan, master_sha, body, pr, sha,
                current_verdict_path, round_num=round_num, log=log)
            if v_n is not None:
                break
            # Count the extra attempt, never the first one, so retries stays the
            # number of re-runs and matches the fixer-side counter.
            if rr_attempt < _MAX_ROUND_RETRIES:
                rec["retries"] += 1
            log(f"phase5.5 round {round_num}: re-review indeterminate "
                f"(attempt {rr_attempt + 1}/{_MAX_ROUND_RETRIES + 1})")
        rec.update({"remediation_sha": str(sha), "verdict": v_n,
                    "reviewed_tree": tree_n, "verdict_path": path_n})
        if v_n is None:
            rec["outcome"] = "re-review-indeterminate"
        # Truthiness, never sha-shape: a body-only round records BODY_ONLY_SHA here and
        # counts, because a round that cleared a finding is a completed round whether the
        # fix landed in the tree or in the PR body.
        res.fidelity["rounds_completed"] = sum(1 for r in rounds if r["remediation_sha"])
        res.fidelity["remediation_sha"] = str(sha)
        res.fidelity["verdict_2"] = v_n
        res.fidelity["reviewed_tree_2"] = tree_n
        log(f"phase5.5 round {round_num}: model={model} retries={rec['retries']} "
            f"outcome={rec['outcome']} sha={str(sha)[:12]} "
            f"verdict={v_n or 'INDETERMINATE'}")
        if v_n is None:
            break
        final_verdict, final_err = v_n, ""
        current_verdict_path = path_n
        # The sticky quotes its findings and builds its merge digest from
        # fid["verdict_path"], so it has to move with current_verdict_path. Both advance
        # BELOW the indeterminate break, which is what keeps an empty re-review file from
        # becoming the source. Left pinned to verdict 1, the comment lists findings this
        # round already cleared, under a **Re-reviewed tree:** line that contradicts them.
        res.fidelity["verdict_path"] = path_n
        res.fidelity["findings_round"] = round_num
    res.fidelity["models"] = [r["model"] for r in rounds]
    # Notes come from whichever review produced the final verdict: the initial one or
    # a re-review round. current_verdict_path tracks that review's verdict file.
    if final_verdict == "READY WITH NOTES":
        notes = fidelity.extract_notes(llm, current_verdict_path, log=log, pr_number=pr)
        nums = fidelity.file_note_issues(gh, notes, pr, log=log)
        res.fidelity["backlog_issue_numbers"] = nums
        log(f"phase5.5 notes: {len(notes)} extracted, {len(nums)} backlog issues filed")
    return final_verdict, final_err


def _write_conformance_handoff(out_dir: str, unresolved: list[str]) -> None:
    """Publish the Phase 5.0 verdict into the run dir.

    The marker's EXISTENCE is the signal that Phase 5.0 reached its end; the
    bridge carries the items. The bridge is written unconditionally and is
    EMPTY (zero bytes) when clean, because /post-plan Phase 6.5 condition (3)
    tests it with `[ -s "$BRIDGE" ]` — a single stray newline there reads as
    "unresolved items exist" and reproduces the exact spurious hold this
    mechanism exists to remove. Build the payload per-item, never with
    "\\n".join(...) + "\\n".
    """
    open(os.path.join(out_dir, CONFORMANCE_DONE_NAME), "w").close()
    with open(os.path.join(out_dir, CONFORMANCE_BRIDGE_NAME), "w") as fh:
        fh.write("".join(f"{item}\n" for item in unresolved))


def _phase11_cleanup(llm, log) -> None:
    """Phase 11: remove the harness's own scratch. Never raises; never kills by pattern."""
    close = getattr(llm, "close", None)
    if close is None:
        log("phase11: no adapter scratch to remove")
        return
    try:
        close()
        log("phase11: LLM adapter temp cwd removed")
    except Exception as e:  # cleanup must never change the terminal state
        log(f"phase11: cleanup error ignored: {e!r}")


def _state_dir(out_dir: str, live: bool, override) -> str:
    """Return the directory in which per-slug state files are written.

    live runs use the harness's own out/state because bin/post-plan-now pins
    HARNESS to the main checkout, so every worktree's live run shares one record
    per slug; every other run writes under its own out_dir so a test or dry run
    can never overwrite a real PR's record.
    """
    if override is not None:
        return override
    if live:
        return os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", "state")
    return os.path.join(out_dir, "state")


def _finish(res: RunResult, out_dir: str) -> RunResult:
    if res.ledger:
        res.ledger.finished_at = time.time()
    with open(os.path.join(out_dir, "result.json"), "w") as fh:
        fh.write(res.to_json())
    with open(os.path.join(out_dir, "audit.log"), "w") as fh:
        fh.write("\n".join(res.audit) + "\n")
    return res


# Deterministic walls a full skill re-run cannot climb — see exit_code_for. A
# subprocess-timeout is one too: the skill re-run would hang on the same step.
_FAIL_CLOSED_KINDS = ("rebase-conflict", "local-gate", "remote-head-diverged",
                      "llm-usage-limit", "usage-pause-unconfirmed", "usage-pause-dirty",
                      SUBPROCESS_TIMEOUT)
PAUSE_EXIT = 75   # ADR-0143 reserved pause exit; only with an S marker on disk

# Per-class remedy for a local-gate denial. Every arm is still exit 3 -- naming the
# class only shortens the human's search, it never changes the verdict. "doc-staleness"
# reaching verdict_line at all means the one bounded auto-remediation already ran and
# the gate still said no.
_GATE_REMEDY = {
    "stale-base": ("origin/master moved and the bounded refetch + re-rebase did not catch "
                   "up: run `git fetch origin master && git rebase origin/master`, then "
                   "re-run bin/post-plan-now."),
    "adr": ("The harness's one ADR draft attempt did not clear the hook: write or fix "
            "the ADR for the decision-trigger surface by hand, then re-run "
            "bin/post-plan-now."),
    "byte-budget": ("The .claude/rules byte budget is over cap and check-rules-byte-budget "
                    "has no --fix flag: trim a rule (or move detail into a path-scoped "
                    "*-detail.md companion), then re-run bin/post-plan-now."),
    "doc-staleness": ("Auto-remediation ran and the gate still denied the commit: bump "
                      "last_verified by hand, then re-run bin/post-plan-now."),
    "unknown": "Clear the gate then re-run bin/post-plan-now.",
}


def exit_code_for(res: RunResult) -> int:
    """Process exit code from a terminal RunResult.
    3 = fail-closed sentinel: bin/post-plan-now MUST NOT escalate to the /post-plan
        skill session. Four kinds land here. `rebase-conflict` — a stacked-branch
        rebase a human must judge. `local-gate` — a pre-commit/pre-push hook denial
        (ADR trigger, stale doc, rules byte budget). `remote-head-diverged`: the PR
        branch was rewritten on GitHub with content the harness did not produce; a
        skill re-run would re-rebase and push over it. `llm-usage-limit`: the Claude
        CLI returned a session/rate/API limit message; a skill re-run would hit the
        same wall immediately. All four are deterministic walls the ~1M-token skill
        re-run cannot climb.
    1 = any other typed failure: bin/post-plan-now re-runs the full /post-plan skill.
    0 = shipped (armed or held), nothing to ship, or degraded.
    There is no 4: the harness owns Phase 5.5, and the launcher has no resume arm.
    usage-pause maps to 75; main() downgrades it to 3 when the marker is gone."""
    if res.terminal == TerminalState.FAILED and res.error_kind == "usage-pause":
        return PAUSE_EXIT
    if res.terminal == TerminalState.FAILED and res.error_kind in _FAIL_CLOSED_KINDS:
        return 3
    if res.terminal == TerminalState.DEGRADED:
        return 0          # PR open and held by (9); a skill re-run would re-review a PR a human must judge
    return 0 if res.terminal != TerminalState.FAILED else 1


def _pull_url_base(worktree: str | None) -> str:
    """`https://github.com/<owner>/<repo>/pull` for this worktree's origin, or "".

    Best-effort and never fatal: the verdict line degrades to a bare `PR #<n>`
    when origin is missing or shaped unexpectedly."""
    if not worktree:
        return ""
    try:
        url = subprocess.run(["git", "-C", worktree, "remote", "get-url", "origin"],
                             capture_output=True, text=True, timeout=10).stdout.strip()
    except Exception:
        return ""
    if url.endswith(".git"):
        url = url[:-4]
    # scp-style (git@github.com:o/r), ssh:// (this repo's actual origin), git://, https://
    m = re.match(r"^(?:git@github\.com:|(?:ssh|git|https)://(?:git@)?github\.com/)"
                 r"([^/]+/[^/]+)$", url)
    if not m:
        return ""
    return f"https://github.com/{m.group(1)}/pull"


_BLOCK_BUDGET = 1800          # bin/discord-dm cuts at 1900; the deferred DM adds a ~55-char prefix
_BLOCK_MAX_PATHS = 5
_BLOCK_MAX_PATH_LEN = 120

_TAIL_LINES = 3
_TAIL_LINE_LEN = 160
_TAIL_MAX = 300
_CMD_MAX = 160

_REDACT_RULES = (
    (re.compile(r"(?i)\b([a-z][a-z0-9+.-]*://)[^/\s@]+@"), r"\1***@"),
    (re.compile(r"\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{20,}"), "***"),
    (re.compile(r"\bgithub_pat_[A-Za-z0-9_]{20,}"), "***"),
    (re.compile(r"(?i)\b(bearer\s+)[A-Za-z0-9._~+/=-]+"), r"\1***"),
)
_STAGE_RE = re.compile(r"\b(phase\d+(?:\.\d+)?[a-z]?)\b")


def _redact(text: str) -> str:
    """Strip credentials from text that is about to reach the RESULT line, block, or DM."""
    for pattern, repl in _REDACT_RULES:
        text = pattern.sub(repl, text)
    return text


def _error_tail(text: str | None) -> list[str]:
    """Last few non-empty lines of `text`, redacted and bounded. Hooks and git print
    the deciding line last, so the tail (not the head) is what a reader needs."""
    lines = [" ".join(ln.split()) for ln in _redact(text or "").splitlines()]
    lines = [ln for ln in lines if ln][-_TAIL_LINES:]
    lines = [ln if len(ln) <= _TAIL_LINE_LEN else "…" + ln[-(_TAIL_LINE_LEN - 1):]
             for ln in lines]
    while len(" ".join(lines)) > _TAIL_MAX and len(lines) > 1:
        lines.pop(0)
    if lines and len(lines[0]) > _TAIL_MAX:
        lines[0] = "…" + lines[0][-(_TAIL_MAX - 1):]
    return lines


def _cmd_text(cmd: str | None) -> str:
    """Redacted, flattened command, cut to its head (the subcommand comes first)."""
    flat = " ".join(_redact(cmd or "").split())
    return flat if len(flat) <= _CMD_MAX else flat[:_CMD_MAX - 1] + "…"


def _stage_of(res: RunResult) -> str:
    m = _STAGE_RE.search(res.error or "")
    return m.group(1) if m else (res.error_kind or "unrecorded")


def _dedupe(items: list[str]) -> list[str]:
    seen: dict[str, None] = {}
    for it in items:
        if it and it not in seen:
            seen[it] = None
    return list(seen)


def _adr_trigger_paths(error: str | None) -> list[str]:
    """Files from bin/adr-check's `Decision-trigger surfaces detected:` list."""
    return _dedupe(re.findall(r"(?m)^\s*- \[[^\]]+\] (\S+) —", error or ""))


def _conflict_paths(error: str | None) -> list[str]:
    """Conflicted files: `Merge conflict in <path>` and the merge-tree probe form."""
    text = error or ""
    paths = re.findall(r"Merge conflict in (\S+)", text)
    for m in re.finditer(r"vs origin/master: ([^\n|]*)", text):
        paths.extend(p.strip() for p in m.group(1).split(","))
    return _dedupe(paths)


def _over_budget_paths(error: str | None) -> list[str]:
    """Per-file `FAIL  <path>  N bytes` lines; the `aggregate` line has no byte count after the path."""
    return _dedupe(re.findall(r"(?m)^FAIL  (\S+)  \d+ bytes", error or ""))


def _stale_doc_paths(error: str | None) -> list[str]:
    """Any `.md` path on a line that also mentions last_verified."""
    paths: list[str] = []
    for line in (error or "").splitlines():
        if "last_verified" in line:
            paths.extend(re.findall(r"[\w./-]+\.md", line))
    return _dedupe(paths)


def _path_lines(paths: list[str], limit: int) -> list[str]:
    """At most `limit` indented path lines, each cut to _BLOCK_MAX_PATH_LEN, plus `+N more`."""
    if limit <= 0:
        return []
    out = []
    for p in paths[:limit]:
        if len(p) > _BLOCK_MAX_PATH_LEN:
            p = p[:_BLOCK_MAX_PATH_LEN - 1] + "…"
        out.append(f"  {p}")
    if len(paths) > limit:
        out.append(f"  +{len(paths) - limit} more")
    return out


def human_block(res: RunResult, rc: int, worktree: str, log_path: str) -> str:
    """Plain-language message for an exit-3 stop: what broke, the exact fix, where the log is.

    Text only. `verdict_line` stays the one-line machine verdict; this is what a person
    reads. Paths come from the FULL res.error, never the 300-char `_flat` form. Returns
    "" for any rc but 3."""
    if rc != 3:
        return ""
    branch = res.slug or "This branch"
    leaf = (res.slug or "branch").rsplit("/", 1)[-1]
    wt = worktree or "(the worktree folder)"
    log = log_path or "(see the run log)"
    if res.error_kind in ("rebase-conflict", "remote-head-diverged", "llm-usage-limit",
                          "usage-pause-unconfirmed", "usage-pause-dirty"):
        key = res.error_kind
    elif res.error_kind == "local-gate":
        key = "gate-" + classify_local_gate_denial(res.error or "")
    else:
        key = "unknown"
    if key == "gate-adr" and res.adr_drafted:
        key = "gate-adr-drafted"

    err = res.error or ""
    paths: list[str] = []
    read_log = "Read the log named on the Log line below and fix what it reports"
    if key == "gate-adr":
        why = "This change adds a file that needs a decision record (ADR) before it can ship."
        paths = _adr_trigger_paths(err)
        steps = [f'bin/next-adr "{leaf}"',
                 "Fill in the file it prints, then add its row to the bottom of "
                 "ibl5/docs/decisions/README.md",
                 "bin/adr-check --commit"]
    elif key == "gate-adr-drafted":
        why = ("This change adds a file that needs a decision record (ADR). A draft is at "
               f"{res.adr_path}, but it did not pass the check yet.")
        paths = _adr_trigger_paths(err)
        steps = [f"Finish the draft at {res.adr_path} and make sure its row is at the "
                 "bottom of ibl5/docs/decisions/README.md",
                 "bin/adr-check --commit"]
    elif key == "gate-stale-base":
        why = "master moved on GitHub and this branch is behind it."
        steps = ["git fetch origin master", "git rebase origin/master"]
    elif key == "gate-byte-budget":
        why = "A rule file under .claude/rules is over its size limit."
        paths = _over_budget_paths(err)
        steps = ["Trim the file(s) above, or move detail into a *-detail.md file next to it",
                 "bin/check-rules-byte-budget"]
    elif key == "gate-doc-staleness":
        why = "A doc changed but its last_verified date was not updated."
        paths = _stale_doc_paths(err)
        steps = ["Set last_verified: to today's date in each doc above",
                 "bin/check-docs --since=master --no-staleness"]
    elif key == "gate-unknown":
        why = "A pre-commit or pre-push check refused the commit. The log shows which one."
        steps = [read_log]
    elif key == "rebase-conflict":
        why = "This branch and master both changed the same lines, so the rebase stopped."
        paths = _conflict_paths(err)
        steps = ["git fetch origin master", "git rebase origin/master",
                 "Fix each conflicted file, then run: git add <file> && git rebase --continue"]
    elif key == "remote-head-diverged":
        why = ("Someone pushed to this branch on GitHub, so GitHub's copy no longer "
               "matches this folder.")
        b = res.slug or "<branch>"
        steps = ["git fetch origin", f"git log --oneline HEAD..origin/{b}  (shows what was pushed)",
                 f"git rebase origin/{b}  (keeps their commits; to take GitHub's copy as is "
                 f"instead, run: git reset --hard origin/{b})"]
    elif key in ("llm-usage-limit", "usage-pause-unconfirmed"):
        why = "The Claude usage limit was reached before the ship step finished."
        steps = ["Wait for the limit to reset (the log shows the reset time)"]
    elif key == "usage-pause-dirty":
        why = ("The usage gate paused a model call while it was editing the worktree, "
               "so the edit may be half done.")
        steps = [f"cd {wt} && git status && git diff  (check the interrupted edit)",
                 "Keep or revert the change, then re-run bin/post-plan-now"]
    else:
        why = "The ship step stopped and the log has the reason."
        steps = [read_log]

    outcome = (f"PR #{res.pr_number} was not updated." if res.pr_number
               else "No PR opened.")

    cmd = _cmd_text(res.error_cmd)
    detail: list[str] = []
    if key in ("unknown", "remote-head-diverged"):
        detail.append(f"Stopped during: {_stage_of(res)}")
    if key in ("unknown", "gate-unknown", "remote-head-diverged"):
        if cmd:
            detail.append(f"Command: {cmd}")
        src = res.error_output_tail or err
        if key == "remote-head-diverged" and src.startswith("remote-head-diverged: "):
            src = src[len("remote-head-diverged: "):]
        tail = _error_tail(src)
        if tail:
            detail.append("Evidence:" if key == "remote-head-diverged" else "Last error lines:")
            detail.extend(f"> {t}" for t in tail)

    def render(limit: int, show_detail: bool) -> str:
        lines = [f"{branch} did not ship. {outcome}", "", f"Why: {why}"]
        lines.extend(_path_lines(paths, limit))
        if show_detail and detail:
            lines += [""] + detail
        lines += ["", "Fix:", f"  1. cd {wt}"]
        n = 1
        for s in steps:
            n += 1
            lines.append(f"  {n}. {s}")
        lines += [f"  {n + 1}. bin/post-plan-now", "",
                  "Or open Claude in that folder and ask it to fix the ship block."]
        if res.error_kind == "rebase-conflict" and res.block_cause:
            lines.append("Cause: " + " ".join(res.block_cause.split()))
        lines.append(f"Log: {log}")
        return "\n".join(lines)

    block = render(_BLOCK_MAX_PATHS, True)
    if len(block) > _BLOCK_BUDGET:
        block = render(0, True)
    if len(block) > _BLOCK_BUDGET:
        block = render(0, False)
    return block


BLOCKED_SHIP_FILE = "blocked-ship.txt"


def write_blocked_ship(out_dir: str, res: RunResult, rc: int, worktree: str) -> None:
    """Write the human block for bin/post-plan-now to read. rc != 3 writes nothing.
    Best effort: an OSError is swallowed so this text never changes the exit code."""
    if rc != 3:
        return
    block = human_block(res, rc, worktree or "", os.environ.get("POSTPLAN_LOG_PATH", ""))
    try:
        with open(os.path.join(out_dir, BLOCKED_SHIP_FILE), "w") as fh:
            fh.write(block + "\n")
    except OSError:
        pass


def _record_hold_repeat(res, decision, slug, worktree, state_dir, log) -> dict | None:
    """Advisory only: never raises, never reads or writes res.arm."""
    try:
        plan_path = res.plan.path if (res.plan and res.plan.found) else ""
        fp = holdrepeat.fingerprint(plan_path, worktree or os.getcwd())
        obs = holdrepeat.observe(state_dir, slug, armed=decision.armed,
                                 conditions=decision.conditions, fingerprint=fp,
                                 pr=res.pr_number,
                                 now=datetime.datetime.now(datetime.timezone.utc).isoformat())
        out = {"action": obs.action, "key": obs.key,
               "repeat_count": obs.repeat_count, "reasons": obs.reasons, "dm": ""}
        if obs.action == "repeat-dm":
            ok = _send_hold_repeat_dm(slug, res.pr_number, obs)
            out["dm"] = "sent" if ok else "failed"
            if ok:
                holdrepeat.mark_dm_sent(state_dir, slug, obs.key)
        elif obs.action == "repeat-silent":
            out["dm"] = "already-sent"
        log(f"phase6.5 hold-repeat: {obs.action} x{obs.repeat_count} dm={out['dm'] or '-'}")
        return out
    except Exception as exc:  # advisory; a bug here must not change the run
        log(f"phase6.5 hold-repeat: skipped ({exc})")
        return None


def _send_hold_repeat_dm(slug, pr, obs) -> bool:
    """DM the operator once per repeated structural hold. True only on exit 0."""
    reasons = "; ".join(f"({n}) {reason}" for n, _name, reason in obs.reasons)
    msg = (f"post-plan held twice on the same reason: {slug} PR #{pr if pr else 'none'}\n"
           f"Repeated hold (run {obs.repeat_count}): {reasons}\n"
           "The next re-run with the same plan, diff, and harness will be declined\n"
           "before it spends tokens. Fix the reason, or re-fire with:\n"
           "  bin/post-plan-now --force")
    repo_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
    dm_cmd = os.environ.get("HOLDREPEAT_DM_CMD") or os.path.join(repo_root, "bin", "discord-dm")
    try:
        proc = subprocess.run([dm_cmd, "--quiet", "--no-fallback", msg],
                              capture_output=True, text=True, timeout=60)
    except (OSError, subprocess.SubprocessError):
        return False
    return proc.returncode == 0


def _hold_repeat_note(res: RunResult) -> str:
    """Display-only RESULT-line suffix: a held run that repeated a structural hold."""
    arm, hr = res.arm, res.hold_repeat
    if arm is None or arm.armed or not hr:
        return ""
    if hr.get("action") not in ("repeat-dm", "repeat-silent"):
        return ""
    nums = ",".join(str(r[0]) for r in hr.get("reasons") or [])
    return f" hold-repeat={hr.get('repeat_count')}x ({nums}) dm={hr.get('dm')}"


HOLD_REASONS_CAP = 300


def _hold_reasons_note(res: RunResult) -> str:
    """Display-only RESULT-line suffix ` hold=(7) reason; (8) reason` for a held run.
    Same text as the audit.log `phase6.5: HELD` line, flattened, redacted, capped."""
    arm = res.arm
    if arm is None or arm.armed or not arm.holds:
        return ""
    text = "; ".join(f"({c.number}) {c.reason or c.name}" for c in arm.holds)
    text = " ".join(_redact(text).split())
    if len(text) > HOLD_REASONS_CAP:
        text = text[:HOLD_REASONS_CAP - 1] + "…"
    return f" hold={text}"


def _prose_hold_note(res: RunResult) -> str:
    """Display-only RESULT-line suffix naming the prose check as a hold cause.

    Non-empty only when the run is held, condition (16) is blocked, and the
    pre-push meta-check failures include the prose check. Condition (16) also
    blocks on post-pr failures and UNKNOWN state, which never populate
    meta_check_failures, so both signals are required.
    """
    arm = res.arm
    if arm is None or arm.armed:
        return ""
    if not any(c.number == 16 for c in arm.holds):
        return ""
    names = [f.get("name") for f in (res.meta_check_failures or [])]
    if prosefix.PROSE_CHECK not in names:
        return ""
    return f" held-by=prose-check ({prosefix.PROSE_CHECK} failed pre-push)"


def verdict_line(res: RunResult, rc: int, pull_base: str = "") -> str:
    """The one line a watcher greps for — printed FIRST, before the stats lines.

    Every terminal branch produces one, and every branch names its outcome in the
    words a `Monitor` filter actually looks for (RESULT / complete / FAILED /
    ERROR / conflict / `PR #` / `pull/`). A happy-path-only verdict is the same
    bug as no verdict: silence would still be indistinguishable from "running"."""
    # A HarnessError detail can be multi-line (captured stderr). The verdict must
    # stay ONE line — a watcher reads it with `head -1`, and each newline would
    # otherwise become its own Monitor event.
    def _flat(s: str, limit: int = 300) -> str:
        s = " ".join((s or "").split())
        return s[:limit] + "…" if len(s) > limit else s

    if rc == PAUSE_EXIT:
        return (f"RESULT: post-plan PAUSED at {res.error.split(': ', 1)[-1]} (usage gate); "
                "the usage-gate coordinator resumes it after the reset.")
    pr = ""
    if res.pr_number:
        pr = f" PR #{res.pr_number}"
        if pull_base:
            pr += f" {pull_base}/{res.pr_number}"

    if rc == 3:
        if res.error_kind == "rebase-conflict":
            # Name the conflicted path. Any branch can hit this arm (a plain branch whose
            # file master edited and the branch deleted, too), so "stacked" would mislead.
            detail = _flat(res.error)
            detail = f" {detail}" if detail else ""
            cause = _flat(res.block_cause or "")
            cause = f" Cause: {cause}." if cause else ""
            return ("RESULT: post-plan BLOCKED — rebase conflict, "
                    "human required; ERROR terminal=failed, no PR opened."
                    f"{detail}{cause} Resolve the rebase, then re-run bin/post-plan-now.")
        if res.error_kind == SUBPROCESS_TIMEOUT:
            detail = _flat(res.error) or "a phase-2 subprocess timed out"
            cmd = _cmd_text(res.error_cmd)
            cmd = f" Command: {cmd}." if cmd else ""
            return ("RESULT: post-plan BLOCKED — a phase-2 subprocess hung past its "
                    f"timeout and was killed; ERROR terminal=failed. {detail}.{cmd} "
                    "Find why that step hangs (audit.log names it on the last "
                    "'phase2: starting' line), then re-run bin/post-plan-now.")
        if res.error_kind == "local-gate":
            tail = " ".join(_error_tail(res.error_output_tail or res.error)) or "see gate output"
            cmd = _cmd_text(res.error_cmd)
            detail = f"Command: {cmd}. Error: {tail}" if cmd else tail
            # Classify on the FULL res.error, never on `detail`: _flat truncates at 300
            # chars and the hooks echo their guidance line LAST, so classifying the
            # flattened form would silently degrade a long byte-budget denial to
            # "unknown" and print the wrong remedy.
            gate_class = classify_local_gate_denial(res.error or "")
            drafted = (f" The harness drafted {res.adr_path} ({res.adr_draft_model}) "
                       "and the hook still denied; the draft is committed locally on "
                       "the branch for review." if res.adr_drafted else "")
            return (f"RESULT: post-plan BLOCKED — local pre-commit/pre-push gate denied "
                    f"the commit [class={gate_class}]; ERROR terminal=failed, no PR "
                    f"opened. {detail}{drafted} {_GATE_REMEDY[gate_class]}")
        if res.error_kind == "llm-usage-limit":
            detail = _flat(res.error or "")
            return (f"RESULT: post-plan BLOCKED — Claude usage limit reached (environmental); "
                    f"ERROR terminal=failed kind=llm-usage-limit. "
                    + (f"{detail} " if detail else "")
                    + "Re-run bin/post-plan-now after the limit resets.")
        if res.error_kind == "usage-pause-unconfirmed":
            detail = _flat(res.error or "")
            return (f"RESULT: post-plan BLOCKED — usage gate paused but the pause marker "
                    f"is missing; ERROR terminal=failed kind=usage-pause-unconfirmed. "
                    + (f"{detail} " if detail else "")
                    + "Re-run bin/post-plan-now after the limit resets.")
        if res.error_kind == "usage-pause-dirty":
            detail = _flat(res.error or "")
            return (f"RESULT: post-plan BLOCKED — usage gate paused a tooled edit mid-run; "
                    f"ERROR terminal=failed kind=usage-pause-dirty. "
                    + (f"{detail} " if detail else "")
                    + "Inspect `git status` in the worktree, then re-run bin/post-plan-now.")
        if res.error_kind == "remote-head-diverged":
            ev = res.error or ""
            if ev.startswith("remote-head-diverged: "):
                ev = ev[len("remote-head-diverged: "):]
            evidence = " ".join(_error_tail(ev)) or "no evidence recorded"
            cmd = _cmd_text(res.error_cmd)
            cmd_part = f" Command: {cmd}." if cmd else ""
            return (f"RESULT: post-plan BLOCKED — remote head diverged at {_stage_of(res)} "
                    f"(the PR branch changed on GitHub); ERROR terminal=failed "
                    f"kind=remote-head-diverged{pr}.{cmd_part} {evidence} "
                    "Fetch origin and inspect what was pushed, then rebase onto it or "
                    "reset to it, and re-run bin/post-plan-now.")
        # Unknown or None error_kind. Name the stage; say "cause unknown" only when no
        # command failed.
        stage = _stage_of(res)
        tail = " ".join(_error_tail(res.error_output_tail or res.error))
        cmd = _cmd_text(res.error_cmd)
        if cmd:
            return (f"RESULT: post-plan BLOCKED — rc=3 kind={res.error_kind or 'none'} at "
                    f"{stage}; ERROR terminal=failed{pr}. Command: {cmd}. Error: "
                    f"{tail or 'no output captured'} "
                    "Resolve the cause, then re-run bin/post-plan-now.")
        return ("RESULT: post-plan BLOCKED — rc=3 (rebase-conflict, local-gate, or "
                f"llm-usage-limit), cause unknown (stage: {stage}); ERROR terminal=failed, "
                "no PR opened. " + (f"{tail} " if tail else "")
                + "Resolve the cause, then re-run bin/post-plan-now.")
    if res.error_kind in ("push-retry-cap", "lostwork-unproved"):
        cause = ("push retry cap reached (stale lease after 3 attempts)"
                 if res.error_kind == "push-retry-cap"
                 else "lost-work proof failed on re-rebase")
        return (f"RESULT: post-plan BLOCKED — {cause}; ERROR terminal=failed "
                f"kind={res.error_kind}{pr}. {_flat(res.error) or 'no detail'} "
                "Re-run bin/post-plan-now.")
    if res.terminal == TerminalState.FAILED:
        return (f"RESULT: post-plan FAILED — ERROR terminal=failed "
                f"kind={res.error_kind or 'unknown'}: "
                f"{_flat(res.error) or 'no detail'}{pr}")
    if res.terminal == TerminalState.ALREADY_SHIPPED:
        return (f"RESULT: post-plan complete — terminal=already-shipped{pr} "
                "(PR already merged; branch diff vs master is empty); no action needed.")
    if res.terminal == TerminalState.NOTHING_TO_SHIP:
        return ("RESULT: post-plan complete — nothing to ship "
                "(clean tree, empty diff vs master); no PR opened.")

    armed = "armed" if (res.arm and res.arm.armed) else "HELD (human merges)"
    tail = ""
    if res.ci_outcome:
        tail += f" ci={res.ci_outcome}"
    if res.final_pr_state:
        tail += f" pr-state={res.final_pr_state}"
    # Surface auto-resolved files when present
    autoresolved_file = f"/tmp/postplan-conflict-files-{res.slug.replace('/', '-')}-autoresolved.txt"
    if os.path.exists(autoresolved_file):
        try:
            files = [l.strip() for l in open(autoresolved_file).read().splitlines() if l.strip()]
            if files:
                tail += f" auto-resolved conflict in {', '.join(files)}"
        except OSError:
            pass
    tail += _prose_hold_note(res)
    tail += _hold_repeat_note(res)
    return (f"RESULT: post-plan complete — terminal={res.terminal.value} "
            f"auto-merge={armed}{pr}{tail} findings={len(res.findings)}"
            f"{_hold_reasons_note(res)}")


def _settle_pause_marker(res: RunResult, rc: int) -> int:
    """Enforce exit 75 <=> S marker on disk. No gate context: rc unchanged."""
    ctx, _ = usage_pause.context_from_env()
    if ctx is None:
        if rc == PAUSE_EXIT:
            res.error_kind = "usage-pause-unconfirmed"
            return 3
        return rc
    if res.error_kind == "usage-pause-dirty":
        usage_pause.marker_clear(ctx)
        return rc
    if rc == PAUSE_EXIT:
        if usage_pause.marker_exists(ctx):
            return rc
        res.error_kind = "usage-pause-unconfirmed"
        return 3
    if usage_pause.marker_exists(ctx):
        usage_pause.marker_clear(ctx)
    return rc


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--mode", choices=["replay", "isolated"], default="replay")
    ap.add_argument("--fixture", help="fixture.json path (replay mode)")
    ap.add_argument("--worktree", help="git worktree path (isolated mode)")
    ap.add_argument("--out", default="out/run")
    ap.add_argument("--canned", help="canned LLM responses JSON (offline dry-run)")
    ap.add_argument("--interactive", action="store_true",
                    help="headless=False (golden condition 5 warns instead of blocking)")
    ap.add_argument("--live", action="store_true",
                    help="INSTALLED mode (isolated only): push to origin, execute "
                         "allowlisted gh mutations, watch CI")
    ap.add_argument("--plan", help="explicit plan file path (isolated only): overrides "
                                   "slug-derived variant selection")
    args = ap.parse_args()
    if args.live and args.mode != "isolated":
        ap.error("--live is only valid with --mode isolated")
    if args.plan and args.mode != "isolated":
        ap.error("--plan is only valid with --mode isolated")

    fixture = None
    if args.mode == "replay":
        if not args.fixture:
            ap.error("--fixture required in replay mode")
        with open(args.fixture) as fh:
            fixture = json.load(fh)
    elif not args.worktree:
        ap.error("--worktree required in isolated mode")

    _install_sigterm_handler()
    ledger = UsageLedger()
    if args.canned:
        with open(args.canned) as fh:
            llm = FixtureLlm(ledger, json.load(fh))
    else:
        llm = ClaudeCli(ledger, out_dir=args.out)

    res = run(fixture, args.out, llm, mode=args.mode, worktree=args.worktree,
              headless=not args.interactive, live=args.live, explicit_path=args.plan)
    t = ledger.totals()
    rc = exit_code_for(res)
    rc = _settle_pause_marker(res, rc)
    if rc != PAUSE_EXIT:
        usage_pause.ledger_clear()
    # First harness line (the launcher may write a `started` line above it), so
    # bin/watch-run can terminate on it without waiting for the launchd label to disappear.
    print(verdict_line(res, rc, _pull_url_base(args.worktree)))
    write_blocked_ship(args.out, res, rc, args.worktree)
    print(f"terminal={res.terminal.value} phase5={res.phase5} "
          f"armed={bool(res.arm and res.arm.armed)} findings={len(res.findings)}")
    print(f"llm: {t['llm_invocations']} calls, {t['gross_tokens']} gross tok, "
          f"{t['non_cached_tokens']} non-cached tok, ${t['cost_usd']}, {t['wall_seconds']}s")
    print(f"outputs: {args.out}/result.json, {args.out}/audit.log, {args.out}/actions.jsonl")
    print(f"usage-gate: {'on' if usage_pause.context_from_env()[0] else 'off'}")
    return rc


if __name__ == "__main__":
    sys.exit(main())
