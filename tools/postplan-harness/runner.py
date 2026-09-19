#!/usr/bin/env python3
"""Compiled post-plan runner — the phase sequencer.

Code owns: sequencing, classification, conformance, verification aggregation,
all fifteen arming conditions, numbered 1–15 as in the skill ((11) unresolved review-thread
findings via bin/lib/pr-armable.sh, (12) the Phase 5.5 plan-fidelity verdict, (13) the
plan-slug-drift hold, (14) the conflict-resolved flag, (15) already-red CI checks), the Phase 5.5 sticky verdict comment,
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
import json
import os
import re
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import ciwatch, conformance, fidelity, llm_calls, manual_rows, schemas, statefile
from harness.armable import (ArmInputs, conflict_flag_path, evaluate,
                             manual_testing_clearance, select_fidelity_verdict)
from harness.classify import (classify, files_from_diff, modified_files_from_diff,
                              render_files_changed, render_manual_confirmation,
                              render_reviewer_verification, strip_manual_testing_section,
                              upsert_files_changed, upsert_manual_confirmation,
                              upsert_reviewer_verification)
from harness.planfile import locate_plan, split_hold_justification
from harness.review import ReviewPhase
from harness.state import (HarnessError, RunResult, TerminalState, UsageLedger)
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.adapters.gitad import LiveGit, ReplayGit, classify_local_gate_denial
from harness.adapters.llm import ClaudeCli, FixtureLlm
from harness.adapters.probe import FixtureProbe, LiveProbe
from harness.adapters.verify import LiveVerify, ReplayVerify, aggregate

_BADGE_FALLBACK = (
    "<!-- postplan-status -->\n**post-plan is running**\n\n"
    "Started outside `bin/post-plan-now`, so there is no launchd job to probe.\n"
    "<!-- postplan-label:  -->\n"
)


def _post_status_badge(gh, pr):
    if not pr:
        return
    body = os.environ.get("POSTPLAN_BADGE_BODY") or _BADGE_FALLBACK
    try:
        gh.pr_status_badge(pr, body)
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


def _discharge_hold_sentences(llm, probe, justification: str, log) -> tuple[str, list]:
    """Split a hold justification into (residual_text, discharged).

    residual_text: str  — the **Decision:** block plus every sentence the
                          classifier returned as `decision`; equal to the full
                          input when any fallback fires.
    discharged:    list — dicts {"text", "category", "probe"|None, "rationale"}
                          for non-decision sentences.

    Fallback to (justification, []) on: empty input, exception, schema
    rejection, empty residual from non-empty input.  A decision-only section
    (no candidate lines) also returns (justification, []) without calling the
    LLM — the token cost guard this design exists to enforce.
    """
    text = (justification or "").strip()
    if not text:
        return (justification or ""), []

    decision_block, candidate_lines = split_hold_justification(justification)

    if not candidate_lines:
        # Decision-only section — skip the LLM call entirely.
        return justification, []

    try:
        items = llm.call(
            "hold-discharge", "sonnet",
            llm_calls.hold_discharge_prompt(candidate_lines),
            schemas.validate_hold_discharge,
        )
    except Exception as exc:  # noqa: BLE001
        log(f"phase6 hold-discharge: LLM call failed ({exc!r}) — keeping full justification")
        return justification, []

    # Build residual: Decision-exempt block + classifier-returned `decision` lines.
    residual_parts: list[str] = []
    if decision_block:
        residual_parts.append(decision_block)
    discharged: list[dict] = []
    by_n: dict[int, list] = {}
    for item in items:
        by_n.setdefault(item["n"], []).append(item)

    for idx, line in enumerate(candidate_lines, 1):
        matches = by_n.get(idx, [])
        if not matches:
            # Missing entry — count mismatch; fall back.
            log(f"phase6 hold-discharge: no item for sentence {idx} — keeping full justification")
            return justification, []
        for item in matches:
            if item["category"] == "decision":
                residual_parts.append(line)
            else:
                discharged.append({
                    "text": line,
                    "category": item["category"],
                    "probe": item.get("probe"),
                    "rationale": item.get("rationale"),
                })

    residual = "\n".join(residual_parts) if residual_parts else ""

    # Safety: never produce empty residual from non-empty input — that would
    # cause upsert_manual_confirmation to REMOVE the hold notice from a PR that
    # is still held.
    if not residual.strip() and text:
        log("phase6 hold-discharge: empty residual from non-empty input — keeping full justification")
        return justification, []

    return residual, discharged


def run(fixture: dict | None, out_dir: str, llm, *, mode: str = "replay",
        worktree: str | None = None, headless: bool = True,
        plans_dir: str | None = None, live: bool = False,
        explicit_path: str | None = None,
        probe=None, state_dir: str | None = None) -> RunResult:
    os.makedirs(out_dir, exist_ok=True)
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
        git = LiveGit(worktree, push_remote="origin" if live else None)
        slug = git.branch()
        gh = LiveGh(out_dir, worktree, slug) if live else RecordingGh(out_dir)
        verifier = LiveVerify(worktree)
        if probe is None:
            probe = LiveProbe(repo_root=worktree)
        plan = locate_plan(slug, plans_dir=plans_dir, explicit_path=explicit_path)

    res = RunResult(terminal=TerminalState.FAILED, slug=slug, plan=plan,
                    ledger=ledger, audit=audit)
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
        cls = classify(files, diff, git.modified_files())
        res.classification = cls
        log("phase3 classify:\n" + cls.summary())

        copy, copy_degraded = _pr_copy(llm, git, gh, fixture, slug, cls, plan, log)
        summary, stripped = strip_manual_testing_section(copy["summary_md"])
        if stripped:
            copy["summary_md"] = summary
            log("phase2: stripped model-authored Manual Testing section from PR copy")
        copy["commit_subject"] = schemas.coerce_commit_subject(copy["commit_subject"], cls)
        sha = _commit_with_gate_remediation(
            git, worktree, f"{copy['commit_subject']}\n\n{copy['summary_md']}", log)
        rebase_line = f"REBASE=not run ({mode} mode)"
        if live:
            pre_rebase = git.head()
            conflict_resolved = None
            try:
                git.rebase_onto()  # pre-push policy: branch must sit on origin/master
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
                conflict_resolved = git.autoresolve_stacked_rebase()
                if not conflict_resolved.resolved:
                    log(f"phase2: conflict auto-resolution declined -- {conflict_resolved.reason}")
                    raise HarnessError(
                        "rebase-conflict",
                        f"{e.detail} | auto-resolve declined: {conflict_resolved.reason}",
                    ) from e
                log("phase2: conflict auto-resolved via --onto "
                    f"{conflict_resolved.base_sha[:8]}; TREE-EQUIVALENT proved; "
                    f"manifest={conflict_resolved.manifest_path} "
                    f"notes={conflict_resolved.notes_path} "
                    f"POST_RESOLUTION_SHA={conflict_resolved.post_resolution_sha}")
                if conflict_resolved.collapse_warn:
                    log(f"phase2: {conflict_resolved.collapse_warn}")
            sha = git.head()
            if conflict_resolved is not None:
                rebase_line = ("REBASE=conflict auto-resolved via --onto; TREE-EQUIVALENT; "
                               f"manifest={conflict_resolved.manifest_path}")
            else:
                log("phase2: rebased onto origin/master")
                # the skill's two success spellings, verbatim
                rebase_line = ("REBASE=clean (HEAD already contains origin/master)"
                               if sha == pre_rebase else "REBASE=rebased onto origin/master")
        try:
            git.push()
        except HarnessError as e:
            if e.kind != "push-disabled":
                raise
            log("phase2: push skipped — disabled outside an approved install")
        if gh.pr_exists():
            pr = gh.pr_number()
            log(f"phase2: PR #{pr} exists — updated head to {sha or '(clean)'}")
        else:
            create_body = upsert_files_changed(copy["summary_md"], render_files_changed(diff))
            pr = gh.pr_create(copy["title"], create_body, "master")
            log(f"phase2: pr_create intent recorded (title={copy['title']!r})")
        res.pr_number = pr
        state.checkpoint("pr-open", res)
        if live and pr and sha:
            bg_ci = ciwatch.start_background_watch(worktree, pr, sha, out_dir)
            if bg_ci is not None:
                log(f"phase2: background CI watch started for {sha[:8]} "
                    f"-> {os.path.basename(bg_ci.path)}")
        _post_status_badge(gh, pr)
        meta = gh.pr_meta() or {"number": pr, "title": copy["title"], "body": copy["summary_md"]}

        # ---- Phase 4: review + security (gated bounded calls) ---------
        # Runs in the background under Phase 5 → 5.0 → 6 → the Phase 5.5 review call; none
        # of those read Phase 4's output. Only the LLM calls and the PR posts run on the
        # worker: log(), state.checkpoint() and the pr-copy degradation merge stay on
        # this thread, in _join_review, so audit.log and the state file keep their serial
        # order. The join happens before anything moves the head (fidelity remediation),
        # so the review still posts against the head it read.
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
                degraded_agents = list(degraded_agents) + ["pr-copy"]
            res.findings = findings
            res.scored_findings = scored
            res.degraded_agents = degraded_agents
            state.checkpoint("review", res, review_gates=gates, reviewed_head=meta.get("headRefOid") or "")
            log(f"phase4 gates={ {k: v for k, v in gates.items()} } findings: raw={len(scored)} surviving={len(findings)} scores={[s['score'] for s in scored]}")
            if degraded_agents:
                log(f"phase4 DEGRADED: unparseable review output from {', '.join(degraded_agents)}")
        join_review = _join_review

        # ---- Phase 5 + 5.0: verify + conformance -----------------------
        tracks = verifier.run(cls)
        phase5 = aggregate(tracks)
        unavailable = [t.name for t in tracks if t.status == "unavailable"]
        res.phase5 = phase5
        log("phase5 tracks: " + ", ".join(f"{t.name}={t.status}" for t in tracks)
            + f" -> PHASE5_VERIFY_STATUS={phase5}"
            + (f" (fidelity degraded: {unavailable} unavailable)" if unavailable else ""))
        unresolved = conformance.check(plan, files, diff, phase5_status=phase5)
        res.unresolved_conformance = unresolved
        _write_conformance_handoff(out_dir, unresolved)
        log(f"phase5.0 conformance: {unresolved or 'clean'}")

        # ---- Phase 6: manual-testing clearance ------------------------
        body = gh.pr_body() or meta.get("body", "")
        clearance = manual_testing_clearance(body)
        if clearance == "UNKNOWN":
            if plan.found and not plan.truly_manual_rows:
                body += "\n\n## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
                clearance = "CLEARED"
                log("phase6: plan matrix fully automated — sentinel appended (CLEARED)")
            elif plan.found:
                surviving_rows = _recheck_manual_rows(llm, probe, plan, cls, log, res)
                if not surviving_rows:
                    body += "\n\n## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
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
                    body += "\n\n## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
                    clearance = "CLEARED"
                log(f"phase6 (plan-blind): {len(manual)} truly-manual steps -> {clearance}")
        else:
            log(f"phase6: PR body already carries clearance state {clearance}")
        # Hold justification: split into residual (decisions) + discharged
        # (automatable sentences).  Residual goes to manual_confirmation, which is
        # positioned by upsert_manual_confirmation ahead of `## Manual Testing` —
        # appending it after would truncate manual_testing_clearance's scan window.
        # Discharged sentences get a separate `## Reviewer verification` block
        # positioned after Manual Testing.  Order of the three upserts is load-
        # bearing: manual_confirmation first, reviewer_verification second,
        # files_changed last; exactly one pr_edit_body call.
        residual, discharged = _discharge_hold_sentences(
            llm, probe, plan.hold_justification, log)
        body = upsert_manual_confirmation(
            body, render_manual_confirmation(residual))
        body = upsert_reviewer_verification(
            body, render_reviewer_verification(discharged))
        # files-changed block is machine-generated: refresh it on every run so the
        # PR body's scope can't silently drift from the actual diff.
        body = upsert_files_changed(body, render_files_changed(diff))
        gh.pr_edit_body(pr, body)

        # ---- Phase 5.5: plan-intent fidelity review --------------------
        # Pinned BEFORE the call: condition (12) compares the tree the reviewer saw
        # against HEAD at arming time, so capturing it after would always match.
        master_sha = _master_sha(worktree)
        reviewed_tree = git.head_tree()
        _run_fidelity(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
                      reviewed_tree, live, log, res, before_remediation=_join_review)
        _join_review()
        # A remediation loop moved the head, up to MAX_FIDELITY_ROUNDS times. Phase 7
        # must watch CI for the last commit, which is what remediation_sha aliases after
        # the loop.
        if res.fidelity.get("remediation_sha"):
            sha = res.fidelity["remediation_sha"]

        # ---- Phase 6.5: arming ----------------------------------------
        # Condition (15): snapshot any checks already in the fail bucket before
        # arming. Fail-open: any exception falls back to [] so the run continues.
        # bg_ci.failed is only populated once _write_outcome fires (usually after
        # Phase 7 has already started), so it is typically empty at arm time; the
        # fresh probe is therefore always run in addition to the background result.
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
            degraded_agents=res.degraded_agents,
            plan_slug_drift=plan.slug_drift,
            failed_checks=_failed_checks,
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
            rsha = fid.get("remediation_sha")
            ci_line = ("CI: local verification "
                       f"{getattr(res.phase5, 'value', res.phase5)}; "
                       "GitHub checks are watched after this comment")
            if rsha:
                ci_line += f"; remediation commit {rsha} is inside that watch"
            sticky = fidelity.compose_sticky(
                rebase_line, ci_line, fid, decision, digest,
                fidelity.findings_excerpt(vpath, fid.get("verdict_1") is not None),
                fidelity.terminal_line(fid.get("verdict_1"), fid.get("error_kind"), rsha,
                                       fid.get("verdict_2"), fid.get("reviewed_tree_2"),
                                       fid.get("rounds_completed", 0)))
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
            outcome = ciwatch.watch_or_reuse(worktree, pr, sha, out_dir, bg_ci)
        else:
            outcome = ciwatch.CiOutcome(-1, [], "isolated mode: no live PR, CI not watched")
        res.ci_outcome = {0: "green", 8: "failed"}.get(outcome.exit_code, "indeterminate")
        res.final_pr_state = gh.pr_state() if (mode == "replay" or live) else "N/A"
        log(f"phase7 ci: exit={outcome.exit_code} failed={outcome.failed} ({outcome.evidence})")

        res.terminal = (TerminalState.DEGRADED if res.degraded_agents else
                        TerminalState.SHIPPED_ARMED if decision.armed
                        else TerminalState.SHIPPED_HELD)

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
        res.terminal = TerminalState.FAILED
        res.error = f"{e.kind}: {e.detail}"
        res.error_kind = e.kind
        log(f"FAILED: {res.error}")
    finally:
        # A Phase 5-5.5 failure can land while the background review is still running.
        # Wait for it so the review checkpoint lands as it did when Phase 4 ran first;
        # its own error must not replace the one that ended the run.
        if join_review is not None:
            try:
                join_review()
            except Exception as e:  # noqa: BLE001
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

    Re-run with an open PR and nothing to commit: the Haiku call is skipped. Its title
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
        return llm.call("pr-copy", "haiku",
                        llm_calls.pr_copy_prompt(slug, cls, plan, plan_excerpt),
                        schemas.validate_pr_copy), False
    except HarnessError as e:
        if e.kind != "llm-invalid-output":
            raise
        log(f"phase2: pr-copy DEGRADED ({(e.detail or '')[:200]}) — using commit subject")
    subject = git.branch_head_subject()
    if not re.match(r"^[a-z]+(\([^)]*\))?!?:", subject):
        # No conventional subject to borrow. feat: is the fail-closed type: it trips the
        # human-signoff hold, and coerce_commit_subject re-types docs/test/non-code diffs.
        subject = f"feat: {subject or slug}"
    subject = schemas.coerce_commit_subject(subject, cls)
    return {"title": subject, "commit_subject": subject,
            "summary_md": f"## Summary\n- {subject}\n"}, True


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


def _read_text(path: str) -> str:
    try:
        with open(path) as fh:
            return fh.read()
    except OSError:
        return ""


def _run_fidelity(llm, out_dir, worktree, git, gh, plan, diff, body, pr, master_sha,
                  reviewed_tree, live, log, res, before_remediation=None):
    """Phase 5.5. Returns (verdict_word_or_None, error_kind_or_'').

    `before_remediation` runs once, just before the first remediation commit, so the
    background Phase 4 review finishes before the head moves.

    Replay fixtures that carry no canned `plan-fidelity-review` keep the historical
    synthetic READY, so the pre-Phase-5.5 trace corpus stays green; a fixture opts into
    the real path simply by canning that purpose.
    """
    canned = getattr(llm, "canned", None)
    if not live and isinstance(canned, dict) and "plan-fidelity-review" not in canned:
        log("phase5.5 fidelity: replay fixture carries no verdict - synthetic READY")
        res.fidelity = {"verdict_1": "READY", "error_kind": None,
                        "reviewed_tree": reviewed_tree,
                        "verdict_path": fidelity.verdict_path(pr),
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None,
                        "rounds": [], "rounds_completed": 0,
                        "backlog_issue_numbers": []}
        return "READY", ""
    try:
        packet = fidelity.build_packet(
            out_dir, master_sha, reviewed_tree, plan, diff, body, pr,
            phase4b_ran=False, worktree=worktree or ".")
    except HarnessError as e:
        log(f"phase5.5 fidelity: packet failed ({e.kind}) - verdict indeterminate")
        res.fidelity = {"verdict_1": None, "error_kind": e.kind,
                        "reviewed_tree": reviewed_tree,
                        "verdict_path": fidelity.verdict_path(pr),
                        "remediation_sha": None, "verdict_2": None,
                        "reviewed_tree_2": None,
                        "rounds": [], "rounds_completed": 0,
                        "backlog_issue_numbers": []}
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
                    "rounds": [], "rounds_completed": 0,
                    "backlog_issue_numbers": []}
    rounds = []
    current_verdict_path = fidelity.verdict_path(pr)
    final_verdict, final_err = verdict, err
    if final_verdict == "NOT READY" and before_remediation is not None:
        before_remediation()
    for round_num in range(1, fidelity.MAX_FIDELITY_ROUNDS + 1):
        if final_verdict != "NOT READY":
            break
        try:
            sha = fidelity.remediate(
                llm, git, out_dir, worktree or ".", packet, current_verdict_path,
                master_sha, log=log,
                commit=lambda msg: _commit_with_gate_remediation(
                    git, worktree, msg, log, phase="phase5.5"))
        except HarnessError as e:
            if e.kind == "push-failed":
                raise
            # Keep the hook's own words: "local-gate" alone does not say which hook said no.
            why = " ".join((e.detail or "").split())[:300]
            gate = (f" class={classify_local_gate_denial(e.detail or '')}"
                    if e.kind == "local-gate" else "")
            log(f"phase5.5 round {round_num}: remediation unavailable ({e.kind}){gate}"
                + (f" - {why}" if why else ""))
            sha = None
        if not sha:
            break
        body = upsert_files_changed(body, render_files_changed(git.diff_vs_base()))
        gh.pr_edit_body(pr, body)
        v_n, tree_n, path_n = fidelity.re_review(
            llm, git, out_dir, worktree or ".", plan, master_sha, body, pr, sha,
            current_verdict_path, round_num=round_num, log=log)
        rounds.append({"remediation_sha": str(sha), "verdict": v_n,
                       "reviewed_tree": tree_n, "verdict_path": path_n})
        res.fidelity["rounds"] = rounds
        res.fidelity["rounds_completed"] = len(rounds)
        res.fidelity["remediation_sha"] = str(sha)
        res.fidelity["verdict_2"] = v_n
        res.fidelity["reviewed_tree_2"] = tree_n
        log(f"phase5.5 round {round_num}: sha={str(sha)[:12]} "
            f"verdict={v_n or 'INDETERMINATE'} tree={(tree_n or '')[:12]}")
        if v_n is None:
            break
        final_verdict, final_err = v_n, ""
        current_verdict_path = path_n
    # Notes come from whichever review produced the final verdict: the initial one or
    # a re-review round. current_verdict_path tracks that review's verdict file.
    if final_verdict == "READY WITH NOTES":
        notes = fidelity.extract_notes(llm, current_verdict_path, log=log)
        nums = fidelity.file_note_issues(gh, notes, pr, _read_text(current_verdict_path),
                                         log=log)
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


# Both are deterministic walls a full skill re-run cannot climb — see exit_code_for.
_FAIL_CLOSED_KINDS = ("rebase-conflict", "local-gate")

# Per-class remedy for a local-gate denial. Every arm is still exit 3 -- naming the
# class only shortens the human's search, it never changes the verdict. "doc-staleness"
# reaching verdict_line at all means the one bounded auto-remediation already ran and
# the gate still said no.
_GATE_REMEDY = {
    "adr": "Write the ADR for the decision-trigger surface, then re-run bin/post-plan-now.",
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
        skill session. Two kinds land here. `rebase-conflict` — a stacked-branch
        rebase a human must judge. `local-gate` — a pre-commit/pre-push hook denial
        (ADR trigger, stale doc, rules byte budget). Both are deterministic, so the
        ~1M-token skill re-run would hit the identical wall and buy nothing.
    1 = any other typed failure: bin/post-plan-now re-runs the full /post-plan skill.
    0 = shipped (armed or held), nothing to ship, or degraded.
    There is no 4: the harness owns Phase 5.5, and the launcher has no resume arm."""
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

    pr = ""
    if res.pr_number:
        pr = f" PR #{res.pr_number}"
        if pull_base:
            pr += f" {pull_base}/{res.pr_number}"

    if rc == 3:
        if res.error_kind == "rebase-conflict":
            return ("RESULT: post-plan BLOCKED — rebase conflict on a stacked branch, "
                    "human required; ERROR terminal=failed, no PR opened. "
                    "Resolve the rebase, then re-run bin/post-plan-now.")
        if res.error_kind == "local-gate":
            detail = _flat(res.error) or "see gate output"
            # Classify on the FULL res.error, never on `detail`: _flat truncates at 300
            # chars and the hooks echo their guidance line LAST, so classifying the
            # flattened form would silently degrade a long byte-budget denial to
            # "unknown" and print the wrong remedy.
            gate_class = classify_local_gate_denial(res.error or "")
            return (f"RESULT: post-plan BLOCKED — local pre-commit/pre-push gate denied "
                    f"the commit [class={gate_class}]; ERROR terminal=failed, no PR "
                    f"opened. {detail} {_GATE_REMEDY[gate_class]}")
        # Unknown or None error_kind — name both possible causes so the human knows where to look
        return ("RESULT: post-plan BLOCKED — rc=3 (rebase-conflict or local-gate), "
                "cause unknown; ERROR terminal=failed, no PR opened. "
                "Resolve the rebase or clear the local gate, then re-run bin/post-plan-now.")
    if res.terminal == TerminalState.FAILED:
        return (f"RESULT: post-plan FAILED — ERROR terminal=failed "
                f"kind={res.error_kind or 'unknown'}: "
                f"{_flat(res.error) or 'no detail'}{pr}")
    if res.terminal == TerminalState.NOTHING_TO_SHIP:
        return ("RESULT: post-plan complete — nothing to ship "
                "(clean tree, empty diff vs master); no PR opened.")

    armed = "armed" if (res.arm and res.arm.armed) else "HELD (human merges)"
    tail = ""
    if res.ci_outcome:
        tail += f" ci={res.ci_outcome}"
    if res.final_pr_state:
        tail += f" pr-state={res.final_pr_state}"
    return (f"RESULT: post-plan complete — terminal={res.terminal.value} "
            f"auto-merge={armed}{pr}{tail} findings={len(res.findings)}")


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
    # First line, so `head -1 <log>` is the whole verdict and bin/watch-run can
    # terminate on it without waiting for the launchd label to disappear.
    print(verdict_line(res, rc, _pull_url_base(args.worktree)))
    print(f"terminal={res.terminal.value} phase5={res.phase5} "
          f"armed={bool(res.arm and res.arm.armed)} findings={len(res.findings)}")
    print(f"llm: {t['llm_invocations']} calls, {t['gross_tokens']} gross tok, "
          f"{t['non_cached_tokens']} non-cached tok, ${t['cost_usd']}, {t['wall_seconds']}s")
    print(f"outputs: {args.out}/result.json, {args.out}/audit.log, {args.out}/actions.jsonl")
    return rc


if __name__ == "__main__":
    sys.exit(main())
