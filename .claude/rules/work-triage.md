---
description: Triage non-trivial work as ad-hoc vs /plan; ad-hoc bar, safety mirror, Sonnet execution routing, hook-enforced triggers.
last_verified: 2026-10-03
---

# Work Triage Rule

## Triage before non-trivial work

Before starting **any non-trivial unit of work**, whether you proposed it or the user assigned it, decide: implement **ad-hoc** (just do it, then ship or hold per `workflow-continuity.md` § Post-Plan) or route through **`/plan`**. State the call and one line of why, then proceed.

## The ad-hoc bar

Ad-hoc-safe only when **all** hold:
- **Known blast radius** — you can name every file/behavior it touches.
- **An existing pattern to copy** — not novel infrastructure.
- **No multi-phase reasoning** — a single coherent change, not a sequence with intermediate decisions.
- **No unresolved design fork** — nothing where the codebase can't reveal the right choice.

If any are open, it wants a `/plan`.

**Resolve empirical unknowns first** (occurrences, false-positive risk) — the scan often collapses a design fork into ad-hoc.

## Ad-hoc safety mirror

Even when the bar says ad-hoc, run a quick safety check. If the change touches any of:
- a **security surface** (SQL, POST/form endpoint, auth/authz-gated route, user-facing output rendering),
- a **destructive or schema-tightening migration**,
- **new or redesigned user-visible UI/UX**,
- a **gate removal or weakening** (input an executable gate, meaning a hook deny, a `bin/check-*` condition, or a Phase 6.5 arming condition, previously rejected now passes) or a **bootstrap hazard** (it rewrites the rule governing its own merge); prose that keeps the decision procedure, additive gates, and plumbing changes don't count, or
- a property needing **subjective human judgment** to confirm,

then prefer `/plan`, so the defense and its verification are designed up front. Which mechanisms count, and why the PR-time backstop isn't a substitute: `work-triage-detail.md` § Safety mirror backstop.

## Execution routing: an ad-hoc verdict does not mean Opus edits inline

The plan-vs-ad-hoc verdict decides *whether to plan*, not *who executes the edits*. Defaulting silently to inline Opus is the measured leak — see `work-triage-detail.md` § Execution routing context.

**Before making a chunk of edits, route the execution.** The chunk is **Sonnet-executable** when both hold — the same criterion as `/plan` Step 4 gate 13:

- **Design resolved** — you could write the full recipe now (files, exact changes, order); no edit re-opens a judgment call.
- **Machine-verifiable** — a test/linter/script exists (or ships with the chunk) that fails on a wrong edit.

When both hold, **hand off by default — do not pause for permission**: state the routing call in one line ("execution is Sonnet-suitable — delegating"), then spawn **one** Sonnet sub-agent (format: `.claude/skills/plan/_architect-contract.md` § Delegation packets for verbose phases). Design, routing call, and final diff review stay on Opus — this routes *execution*, never understanding.

Stay inline (Opus edits directly) only when: the edits are genuinely **entangled** with the design, or the chunk is **trivial**. Criteria and spawn-cost rationale: `work-triage-detail.md` § Inline vs. delegated.

### The hard trigger: ≥5 distinct files in one turn

Hook-enforced by `~/.claude/hooks/plan-gate-edit.sh` § Check 1 — the deny message carries the routing instruction and escape hatch. Full gate properties and self-test: `work-triage-detail.md` § Hard trigger.

## Execution routing: a `/plan` verdict routes to `bin/plan-now`, never inline

Hook-enforced by `~/.claude/hooks/plan-gate-skill.sh` — denies inline `Skill(plan)`. Exemptions and escape hatch: `work-triage-detail.md` § `/plan` verdict routing.

## Execution routing: repeat-polling is a spend bug

Never poll on the main thread — a poll loop re-reads full context per call. Use `run_in_background: true` + Monitor, or ScheduleWakeup matched to expected completion time.

Name the completion signal before writing the watcher. File mtime or size is never one. Valid signals: `work-triage-detail.md` § The readiness predicate.

## Calibration

**Skip** for obviously trivial edits (typo, one-line fix). **Headless:** no-op under headless/automouse.
