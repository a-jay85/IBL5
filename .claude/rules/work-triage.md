---
description: Triage work as ad-hoc vs /plan; ad-hoc bar, safety mirror, execution routing and its inline-edit trial, hook-enforced triggers.
last_verified: 2026-11-19
---

# Work Triage Rule

## Triage before non-trivial work

Before **any non-trivial unit of work**, yours or assigned, decide: implement **ad-hoc** (do it, then ship or hold per `workflow-continuity.md` § Post-Plan) or route through **`/plan`**. State the call and one line of why, then proceed.

## The ad-hoc bar

Ad-hoc-safe only when **all** hold:
- **Known blast radius.** You can name every file/behavior it touches.
- **An existing pattern to copy.** No novel infrastructure.
- **No multi-phase reasoning.** One coherent change with no decisions midway.
- **No unresolved design fork.** The codebase reveals the right choice.

If any are open, it wants a `/plan`.

**Resolve empirical unknowns first** (occurrences, false-positive risk). The scan often settles a design fork as ad-hoc.

## Ad-hoc safety mirror

Even when the bar says ad-hoc, run a quick safety check. If the change touches any of:
- a **security surface** (SQL, POST/form endpoint, auth/authz-gated route, user-facing output rendering),
- a **destructive or schema-tightening migration**,
- **new or redesigned user-visible UI/UX**,
- a **gate removal or weakening** (input an executable gate, meaning a hook deny, a `bin/check-*` condition, or a Phase 6.5 arming condition, previously rejected now passes) or a **bootstrap hazard** (it rewrites the rule governing its own merge); prose that keeps the decision procedure, additive gates, and plumbing changes don't count, or
- a property needing **subjective human judgment** to confirm,

then prefer `/plan` to design the defense and its check up front. What counts, and why the PR-time backstop falls short: `work-triage-detail.md` § Safety mirror backstop.

## Execution routing: an ad-hoc verdict does not mean Opus edits inline

The verdict decides *whether to plan*. Who edits is a separate call.

**Route each chunk of edits before making it.** It is **Sonnet-executable** when both hold (same test as `/plan` Step 4 gate 13):

- **Design resolved.** You could write the full recipe now (files, exact changes, order). No edit re-opens a judgment call.
- **Machine-verifiable.** A test/linter/script exists (or ships with the chunk) that fails on a wrong edit.

**Inline-edit trial until 2026-11-23.** When both hold and the chunk touches **under 5 distinct files**, Opus edits inline and runs its check. At 5+ files, hand off by default to **one** Sonnet sub-agent without asking. State the routing call in one line. Packet format, review command, and limits: `work-triage-inline-trial.md`.

One sweep, one agent. Independent chunks get one agent each, in one message. A chunk of 5+ files stays inline only when **entangled** with the design or **trivial** (`work-triage-detail.md` § Inline vs. delegated).

### Hook-enforced routing

Hooks deny these, and each deny message gives the route: 5+ distinct files in one turn (`plan-gate-edit.sh` Check 1), inline `Skill(plan)` (`skill-gate.sh`), and waits on job files or post-plan runs (`wait-guard.py`). Detail: `work-triage-detail.md` §§ Hard trigger, `/plan` verdict routing.

Never poll on the main thread. Use `run_in_background: true` + Monitor, or a ScheduleWakeup timed to the finish. When the wait ends in an owner step, a detached script follows up (`bin/pr-refire-after-job`). Name the completion signal first; file mtime or size is never one (`work-triage-detail.md` § The readiness predicate).

## Calibration

**Skip** for trivial edits (typo, one-line fix). **Headless/automouse:** no-op.
