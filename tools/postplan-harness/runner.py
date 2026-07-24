#!/usr/bin/env python3
"""Compiled post-plan runner — the phase sequencer.

Code owns: sequencing, classification, conformance, verification aggregation,
all ten arming conditions, CI-watch interpretation, terminal states, side-effect
gating, and the audit log. Bounded LLM calls own: PR copy, review/security
judgment, finding scoring, plan-blind manual-step classification, the add-only
safety verdict, and the retrospective.

Modes:
  replay           — point-in-time fixture from a historical trace; gh mutations
                     are recorded intents (out/actions.jsonl), never executed.
  isolated         — live git worktree + live verify, gh mutations record-only.
  isolated --live  — the INSTALLED mode (approved 2026-07-16): push to origin,
                     execute the six allowlisted gh mutations (still audited to
                     actions.jsonl), watch CI. Phase 10 preview and Phase 9
                     memory WRITES stay on the interactive side — Phase 9 here
                     records an intent only.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness import ciwatch, conformance, llm_calls, schemas
from harness.armable import ArmInputs, evaluate, manual_testing_clearance
from harness.classify import classify, files_from_diff, modified_files_from_diff
from harness.planfile import locate_plan
from harness.review import ReviewPhase
from harness.state import (HarnessError, RunResult, TerminalState, UsageLedger)
from harness.adapters.ghad import LiveGh, RecordingGh
from harness.adapters.gitad import LiveGit, ReplayGit
from harness.adapters.llm import ClaudeCli, FixtureLlm
from harness.adapters.verify import LiveVerify, ReplayVerify, aggregate


def run(fixture: dict | None, out_dir: str, llm, *, mode: str = "replay",
        worktree: str | None = None, headless: bool = True,
        plans_dir: str | None = None, live: bool = False) -> RunResult:
    os.makedirs(out_dir, exist_ok=True)
    ledger = llm.ledger
    audit: list[str] = []

    def log(msg: str) -> None:
        audit.append(f"[{time.strftime('%H:%M:%S')}] {msg}")

    if mode == "replay":
        assert fixture is not None
        git, gh = ReplayGit(fixture), RecordingGh(out_dir, fixture)
        verifier = ReplayVerify(fixture)
        slug = fixture.get("slug", "unknown")
        plan = locate_plan(slug, content_override=fixture.get("plan_content") or None)
    else:
        assert worktree
        git = LiveGit(worktree, push_remote="origin" if live else None)
        slug = git.branch()
        gh = LiveGh(out_dir, worktree, slug) if live else RecordingGh(out_dir)
        verifier = LiveVerify(worktree)
        plan = locate_plan(slug, plans_dir=plans_dir)

    res = RunResult(terminal=TerminalState.FAILED, slug=slug, plan=plan,
                    ledger=ledger, audit=audit)
    log(f"phase1 plan: found={plan.found} auto_merge_false={plan.auto_merge_false} "
        f"matrix={plan.has_matrix} critical_files={len(plan.critical_files)}")

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

        if fixture:
            plan_excerpt = (fixture.get("plan_content") or "")[:4000]
        elif plan.found and plan.path:
            with open(plan.path) as fh:
                plan_excerpt = fh.read()[:4000]
        else:
            plan_excerpt = ""
        copy = llm.call("pr-copy", "haiku",
                        llm_calls.pr_copy_prompt(slug, cls, plan, plan_excerpt),
                        schemas.validate_pr_copy)
        sha = git.commit_all(f"{copy['title']}\n\n{copy['summary_md']}")
        if live:
            git.rebase_onto()      # pre-push policy: branch must sit on origin/master
            sha = git.head()
            log("phase2: rebased onto origin/master")
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
            pr = gh.pr_create(copy["title"], copy["summary_md"], "master")
            log(f"phase2: pr_create intent recorded (title={copy['title']!r})")
        res.pr_number = pr
        meta = gh.pr_meta() or {"number": pr, "title": copy["title"], "body": copy["summary_md"]}

        # ---- Phase 4: review + security (gated bounded calls) ---------
        findings, gates = ReviewPhase(llm, gh).run(meta, cls, plan)
        res.findings = findings
        log(f"phase4 gates={ {k: v for k, v in gates.items()} } surviving_findings={len(findings)}")

        # ---- Phase 5 + 5.0: verify + conformance -----------------------
        tracks = verifier.run(cls)
        phase5 = aggregate(tracks)
        unavailable = [t.name for t in tracks if t.status == "unavailable"]
        res.phase5 = phase5
        log("phase5 tracks: " + ", ".join(f"{t.name}={t.status}" for t in tracks)
            + f" -> PHASE5_VERIFY_STATUS={phase5}"
            + (f" (fidelity degraded: {unavailable} unavailable)" if unavailable else ""))
        unresolved = conformance.check(plan, files)
        res.unresolved_conformance = unresolved
        log(f"phase5.0 conformance: {unresolved or 'clean'}")

        # ---- Phase 6: manual-testing clearance ------------------------
        body = gh.pr_body() or meta.get("body", "")
        clearance = manual_testing_clearance(body)
        if clearance == "UNKNOWN":
            if plan.found and not plan.truly_manual_rows:
                body += "\n\n## Manual Testing\n\nNo manual testing needed — verified by automated tests.\n"
                gh.pr_edit_body(pr, body)
                clearance = "CLEARED"
                log("phase6: plan matrix fully automated — sentinel appended (CLEARED)")
            elif plan.found:
                body += ("\n\n## Manual Testing\n\n"
                         + "\n".join(f"- [ ] {r}" for r in plan.truly_manual_rows) + "\n")
                gh.pr_edit_body(pr, body)
                clearance = "HELD"
                log(f"phase6: {len(plan.truly_manual_rows)} truly-manual plan rows — HELD")
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
                gh.pr_edit_body(pr, body)
                log(f"phase6 (plan-blind): {len(manual)} truly-manual steps -> {clearance}")
        else:
            log(f"phase6: PR body already carries clearance state {clearance}")

        # ---- Phase 6.5: arming ----------------------------------------
        inputs = ArmInputs(
            pr_body=gh.pr_body() or body, pr_title=meta.get("title", copy["title"]),
            pr_labels=gh.pr_labels(), classification=cls, findings=findings,
            unresolved_conformance=unresolved, phase5_status=phase5,
            plan_auto_merge_false=plan.auto_merge_false, headless=headless,
            dep_state_lookup=lambda n: gh.pr_state(n),
        )
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
        if decision.armed:
            gh.pr_merge_auto(pr)

        # ---- Phase 7/8: CI watch + confirm -----------------------------
        if mode == "replay":
            fx_ci = (fixture or {}).get("checks_outcome")
            outcome = (ciwatch.CiOutcome(fx_ci["exit"], fx_ci.get("failed", []))
                       if fx_ci else ciwatch.derive_from_trace((fixture or {}).get("ci")))
        elif live and pr:
            log("phase7: watching CI (gh pr checks --watch)…")
            outcome = ciwatch.watch_live(worktree, pr)
        else:
            outcome = ciwatch.CiOutcome(-1, [], "isolated mode: no live PR, CI not watched")
        res.ci_outcome = {0: "green", 8: "failed"}.get(outcome.exit_code, "indeterminate")
        res.final_pr_state = gh.pr_state() if (mode == "replay" or live) else "N/A"
        log(f"phase7 ci: exit={outcome.exit_code} failed={outcome.failed} ({outcome.evidence})")

        res.terminal = (TerminalState.SHIPPED_ARMED if decision.armed
                        else TerminalState.SHIPPED_HELD)

        # ---- Phase 9: retrospective (bounded) ---------------------------
        retro = llm.call("retrospective", "haiku",
                         llm_calls.retrospective_prompt(slug, res.terminal.value, decision,
                                                        len(findings), phase5),
                         schemas.validate_retrospective)
        res.retrospective = retro
        if retro.get("save"):
            log(f"phase9: memory-save intent recorded: {retro.get('name')}")
        else:
            log("phase9: no durable lesson — nothing saved")
    except HarnessError as e:
        res.terminal = TerminalState.FAILED
        res.error = f"{e.kind}: {e.detail}"
        res.error_kind = e.kind
        log(f"FAILED: {res.error}")
    return _finish(res, out_dir)


def _finish(res: RunResult, out_dir: str) -> RunResult:
    if res.ledger:
        res.ledger.finished_at = time.time()
    with open(os.path.join(out_dir, "result.json"), "w") as fh:
        fh.write(res.to_json())
    with open(os.path.join(out_dir, "audit.log"), "w") as fh:
        fh.write("\n".join(res.audit) + "\n")
    return res


def exit_code_for(res: RunResult) -> int:
    """Process exit code from a terminal RunResult.
    3 = rebase-conflict fail-closed sentinel: bin/post-plan-now MUST NOT escalate to
        the /post-plan skill session; a human resolves the stacked-branch rebase.
    1 = any other typed failure.  0 = success / nothing-to-ship."""
    if res.terminal == TerminalState.FAILED and res.error_kind == "rebase-conflict":
        return 3
    return 0 if res.terminal != TerminalState.FAILED else 1


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
    args = ap.parse_args()
    if args.live and args.mode != "isolated":
        ap.error("--live is only valid with --mode isolated")

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
        llm = ClaudeCli(ledger)

    res = run(fixture, args.out, llm, mode=args.mode, worktree=args.worktree,
              headless=not args.interactive, live=args.live)
    t = ledger.totals()
    print(f"terminal={res.terminal.value} phase5={res.phase5} "
          f"armed={bool(res.arm and res.arm.armed)} findings={len(res.findings)}")
    print(f"llm: {t['llm_invocations']} calls, {t['gross_tokens']} gross tok, "
          f"{t['non_cached_tokens']} non-cached tok, ${t['cost_usd']}, {t['wall_seconds']}s")
    print(f"outputs: {args.out}/result.json, {args.out}/audit.log, {args.out}/actions.jsonl")
    return exit_code_for(res)


if __name__ == "__main__":
    sys.exit(main())
