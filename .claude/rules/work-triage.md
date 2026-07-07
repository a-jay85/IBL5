---
description: Triage every non-trivial unit of work as ad-hoc vs /plan before starting, with an ad-hoc safety mirror and Sonnet execution-routing for resolved, machine-verifiable edit chunks; the gateway that feeds the deployment funnel (ADR-0067).
last_verified: 2026-07-07
---

# Work Triage Rule

## Triage before non-trivial work

Before starting **any non-trivial unit of work** — whether you proposed it or the user assigned it — decide: implement **ad-hoc** (just do it, then `/ship`) or route through **`/plan`**. State the call and one line of why, then proceed. The user should never have to ask "is this big enough for a `/plan`?" — that judgment is yours to volunteer.

This is the **gateway** of the deployment funnel (ADR-0067): triage decides plan-vs-ad-hoc; everything downstream (`/post-plan`, auto-queue, auto-merge arming) flows from there.

## The ad-hoc bar

Ad-hoc-safe only when **all** hold:
- **Known blast radius** — you can name every file/behavior it touches.
- **An existing pattern to copy** — not novel infrastructure.
- **No multi-phase reasoning** — a single coherent change, not a sequence with intermediate decisions.
- **No unresolved design fork** — nothing where the codebase can't reveal the right choice.

If any are open, it wants a `/plan`.

**Resolve empirical unknowns first.** Run the cheap scan (existing occurrences, false-positive risk, how the thing is emitted) *before* declaring the verdict — the scan often collapses an apparent design fork into an ad-hoc change.

## Ad-hoc safety mirror

Even when the bar says ad-hoc, run a quick safety check — the same surfaces `/plan` Step 4 gate 14 holds for. If the change touches any of:
- a **security surface** (SQL, POST/form endpoint, auth/authz-gated route, user-facing output rendering),
- a **destructive or schema-tightening migration**,
- **new or redesigned user-visible UI/UX**, or
- a property needing **subjective human judgment** to confirm,

then prefer `/plan`, so the defense and its verification are designed up front. Whatever still ships ad-hoc is caught at PR time by `/post-plan` Phase 6.5 condition (9) — but designing it in the plan beats relying on the backstop.

## Execution routing: an ad-hoc verdict does not mean Opus edits inline

The plan-vs-ad-hoc verdict decides *whether to plan* — it does **not** decide *who executes the edits*. An ad-hoc verdict silently defaulting to "the Opus session implements inline" is the measured leak (2026-07-07 usage audit: ~90% of Opus main-thread tool calls were mechanical Bash/Read/Edit/Write, and 44% of interactive Opus sessions breached 150K peak context — the dumb-zone the delegation rules exist to prevent).

**Before making a chunk of edits, route the execution.** The chunk is **Sonnet-executable** when both hold — the same criterion as `/plan` Step 4 gate 13:

- **Design resolved** — you could write the full recipe now (files, exact changes, order); no edit re-opens a judgment call.
- **Machine-verifiable** — a test/linter/script exists (or ships with the chunk) that fails on a wrong edit.

When both hold, **hand off by default — do not pause for permission**: state the routing call in one line ("execution is Sonnet-suitable — delegating"), then spawn **one** Sonnet sub-agent with a delegation-packet-shaped prompt (scope, exact recipe, self-verify command it must run before returning, thin one-line report-back — the format in `.claude/skills/plan/SKILL.md` § Delegation packets). Design, the routing call itself, and final review of the returned diff stay on Opus — this routes *execution*, never understanding.

Stay inline (Opus edits directly) only when:
- the edits and the design are genuinely **entangled** — writing the recipe would mean making each edit-level judgment anyway, so the handoff buys nothing; or
- the chunk is **trivial** — a one-or-two-edit change where the sub-agent's fixed spawn cost (~3–5K tokens, `agent-tiering.md` § Skip the Agent) exceeds the work being moved.

Either way the routing decision is **stated, not silent** — one line, like the triage verdict. The user should see which way it went and be able to override in the moment.

## Calibration

Surface the verdict only when scope is **non-trivial or borderline**. Skip the ritual for obviously trivial edits (typo, one-line fix). 

**Headless:** no-op under headless/automouse (no user to recommend `/plan` to; automouse runs only pre-vetted plans). Governs interactive work-start only.
