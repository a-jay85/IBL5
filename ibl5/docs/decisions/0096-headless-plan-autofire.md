---
description: /plan-prompt fires its drafted prompt as a detached headless Sonnet 4.6 run via bin/plan-now, with the user-facing forks pre-resolved in-session and bin/check-plan as the quality discriminator.
last_verified: 2026-07-27
---

# ADR-0096: `/plan-prompt` auto-fires the planning run headlessly

**Status:** Accepted
**Date:** 2026-07-27

## Context

`/plan-prompt` exists to move a planning run off an expensive Opus session: it distills the design conversation into a `/plan` prompt for a cheap Sonnet orchestrator, with a Step-3 architect-tier directive that keeps the *design* on Opus. But the skill stopped at the clipboard — the human then had to open a fresh session and paste. That last manual step is where the offload leaks: the paste is easy to defer, and a deferred paste means the design context decays in a session that keeps growing. The repo already had both halves of the fix in production — `bin/post-plan-now`'s launchd detach and `bin/bug-pipeline-tick`'s headless `claude -p "/plan …" --model claude-sonnet-4-6` — but nothing wired them together for a *design-derived* plan.

The hazard in doing so is specific: `/plan` Step 3.5 resolves `needs-user-input` forks with `AskUserQuestion`, and a headless run has nobody to ask. `bug-pipeline-tick` gets away with it because it plans a Discord one-liner with no prior design session; `/plan-prompt` is the opposite case — the session that drafts the prompt is exactly the one that knows which forks are live.

## Decision

`/plan-prompt` Step 5 fires its drafted prompt via `bin/plan-now`, a launchd-detached headless Sonnet 4.6 `/plan` run, instead of stopping at the clipboard (the `pbcopy` stays, so a failed fire degrades to the old manual paste rather than losing the draft). Two mechanisms cover the missing human: **(a)** `/plan-prompt` Step 4.5 is a gate requiring the drafting session to resolve user-facing forks with `AskUserQuestion` *while a human is present* and bake the answers into the prompt as hard constraints; **(b)** `bin/plan-now` appends a headless coda instructing the run never to ask, and to record any surviving fork plus `auto_merge: false` (which `bin/check-plan` gate `[H]` then forces to carry a justification). The job runs `bin/check-plan` on the produced plan and logs a PASS/FAIL verdict — `check-plan` already fails on unresolved decisions (`DECIDE` / `TBD` / "subject to …"), which is precisely the failure mode of removing the human, so it is the discriminator for "did headless degrade this plan". Default disposition is `--queue` (**amended 2026-07-27 — see Consequences**; originally `--implement`): a plan that the job itself verified is queued for automouse, matching `/plan` Step 5's own default. `--implement` is the opt-out.

## Alternatives Considered

- **Keep drafting only** — status quo, human pastes into a fresh session. Rejected because: the manual step is the leak the skill was built to close, and it is the step most likely to be skipped.
- **Fold the launcher into `bin/post-plan-now`** — one detached-run script with a mode flag. Rejected because: it fires on a genuinely different event (start-of-work planning vs end-of-work shipping), takes a different input (a prompt file vs a branch), and bolting a second mode on would strain that script's single responsibility — the `meta-tooling-bar` add-new conditions are met.
- **Auto-queue the plan for automouse by default** — full hands-off design→implement→PR. Rejected at first because: it would put a plan no human ever read onto the auto-merge path; the escalation over "human pastes and watches" seemed too large for a default. **[SUPERSEDED 2026-07-27 — adopted; see the Consequences amendment.]** The objection assumed the queue decision rested on the model's self-report. It now rests on this job's own three-way gate, and `auto_merge: false` still holds any PR a human should sign off on.
- **Let the headless run call `AskUserQuestion` and time out** — rely on the fork surfacing somewhere. Rejected because: a fork that only appears as a stalled unattended run is worse than one recorded in the plan with auto-merge held.
- **Inline the whole job body in the plist XML** (`post-plan-now`'s shape). Rejected because: the multi-KB prompt block is arbitrary markdown, and the plan-dir diff plus check-plan verdict are multi-line bash — escaping both through XML is how quoting bugs get in. `bin/plan-now` writes a runner script and passes the prompt **by path**, expanded inside the job.

## Consequences

- Positive: a design conversation ends in a plan on disk without a human-mediated paste, and the expensive session stops there instead of orchestrating.
- Positive: the quality question ("did losing the human degrade the plan?") gets a machine answer from `bin/check-plan`, stronger than `bug-pipeline-tick`'s "a new file appeared" heuristic.
- Positive: forks now get resolved at the moment the human is present, rather than by an unattended agent guessing.
- Amendment (2026-07-27, after the first two real runs): the verdict is only as good as the *identity* of the plan it names. Those two runs overlapped, and `bug-pipeline-tick`'s plan-dir diff — which is not concurrency-safe — made the second job log a PASSED verdict for the *other* job's plan while its own went unchecked. The run now self-reports `PLAN_FILE: <abs path>` (coda-instructed, printed before it writes); the dir diff survives only as a labelled unverified guess that never produces a green, and a nonzero `claude` exit is its own `RESULT failed` because `check-plan` can pass on a truncated plan.
- Amendment (2026-07-27, same day, after the identity fix above): the default flips to **`--queue`**, and **`bin/plan-now` — not the run it fires — makes the call.** The original `--implement` default bought human review at the cost of the leak the skill exists to close: a plan that waits for a paste-equivalent decays exactly like a prompt that waits for a paste. What made auto-queue unsafe was never the absence of a reader, it was that the queue call lived in the coda, so an instruction-following model queued on the strength of its *own* check-plan claim — and the identity bug proved that claim can name the wrong file. The queue call now sits in the runner behind three conditions it verifies itself: `claude` exited 0 (a truncated plan can pass `check-plan`), the plan was identified by its own `PLAN_FILE:` line, and `check-plan` passed *on that file*. Fail any one and the plan lands on disk for a human, which is the old default reached by failure rather than by policy. What review remains is where it always actually was: `auto_merge: false` (`check-plan` gate `[H]`) holds the PR, and `/post-plan` Phase 6.5 enumerates holds on the realized diff. `--implement` stays, as the opt-out for a design whose author wants to read it first.
- Negative: a plan can be written by a run nobody watched, and now implemented by one too. Mitigated by the three-way gate above, by `auto_merge: false` on anything needing signoff, and by the PR itself being the review surface — not eliminated. The exposure this adds over the original default is a *clean, `check-plan`-passing* plan implementing something a human would have rejected on sight; `/plan-prompt` Step 4.5 (resolve user-facing forks while a human is present) is what narrows that, and it is now load-bearing rather than merely prudent.
- Negative: one more `bin/` script to maintain, and one more launchd label shape to recognize when auditing stray jobs.

## References

- `bin/plan-now` — the launcher; header documents the two patterns it assembles.
- `.claude/skills/plan-prompt/SKILL.md` — Step 4.5 (fork pre-resolution gate) and Step 5 (fire).
- `bin/post-plan-now` — the launchd RunAtLoad one-shot detach pattern.
- `bin/bug-pipeline-tick` — `drive_ready_for_plan()`, the production headless `/plan` invocation and plan-dir-diff detection.
- `bin/check-plan` — gates `[7]` (unresolved decisions) and `[H]` (hold justification), the discriminator this ADR relies on.
- `.claude/rules/agent-tiering.md` and `.claude/rules/agent-tiering-detail.md` § `/plan` orchestrator model — why the orchestrator is Sonnet and the architect tier directive is load-bearing.
- `.claude/rules/meta-tooling-bar.md` — the extend-before-add bar answered in Alternatives.
