---
description: Triage every non-trivial unit of work as ad-hoc vs /plan before starting, with an ad-hoc safety mirror and Sonnet execution-routing for resolved, machine-verifiable edit chunks; the gateway that feeds the deployment funnel (ADR-0067).
last_verified: 2026-07-22
---

# Work Triage Rule

## Triage before non-trivial work

Before starting **any non-trivial unit of work** — whether you proposed it or the user assigned it — decide: implement **ad-hoc** (just do it, then `/ship`) or route through **`/plan`**. State the call and one line of why, then proceed. The user should never have to ask "is this big enough for a `/plan`?" — that judgment is yours to volunteer.

This is the **gateway** of the deployment funnel (ADR-0067): everything downstream flows from this call.

## The ad-hoc bar

Ad-hoc-safe only when **all** hold:
- **Known blast radius** — you can name every file/behavior it touches.
- **An existing pattern to copy** — not novel infrastructure.
- **No multi-phase reasoning** — a single coherent change, not a sequence with intermediate decisions.
- **No unresolved design fork** — nothing where the codebase can't reveal the right choice.

If any are open, it wants a `/plan`.

**Resolve empirical unknowns first** (occurrences, false-positive risk) — the scan often collapses a design fork into ad-hoc.

## Ad-hoc safety mirror

Even when the bar says ad-hoc, run a quick safety check — the same surfaces `/plan` Step 4 gate 14 holds for. If the change touches any of:
- a **security surface** (SQL, POST/form endpoint, auth/authz-gated route, user-facing output rendering),
- a **destructive or schema-tightening migration**,
- **new or redesigned user-visible UI/UX**, or
- a property needing **subjective human judgment** to confirm,

then prefer `/plan`, so the defense and its verification are designed up front. Whatever still ships ad-hoc is caught at PR time by `/post-plan` Phase 6.5 condition (9) — but designing it in the plan beats relying on the backstop.

## Execution routing: an ad-hoc verdict does not mean Opus edits inline

The plan-vs-ad-hoc verdict decides *whether to plan*, not *who executes the edits*. Defaulting silently to inline Opus is the measured leak — see `work-triage-detail.md` § Execution routing context.

**Before making a chunk of edits, route the execution.** The chunk is **Sonnet-executable** when both hold — the same criterion as `/plan` Step 4 gate 13:

- **Design resolved** — you could write the full recipe now (files, exact changes, order); no edit re-opens a judgment call.
- **Machine-verifiable** — a test/linter/script exists (or ships with the chunk) that fails on a wrong edit.

When both hold, **hand off by default — do not pause for permission**: state the routing call in one line ("execution is Sonnet-suitable — delegating"), then spawn **one** Sonnet sub-agent (format: `.claude/skills/plan/SKILL.md` § Delegation packets). Design, routing call, and final diff review stay on Opus — this routes *execution*, never understanding.

Stay inline (Opus edits directly) only when:
- the edits and the design are genuinely **entangled** — writing the recipe would mean making each edit-level judgment anyway, so the handoff buys nothing; or
- the chunk is **trivial** — a one-or-two-edit change where the sub-agent's fixed spawn cost (~3–5K tokens, `agent-tiering-detail.md` § Skip the Agent) exceeds the work being moved.

Either way the routing decision is **stated, not silent** — one line, like the triage verdict. The user should see which way it went and be able to override in the moment.

### The hard trigger: ≥3 distinct files in one turn

**The numeric rule: the third distinct repo file you edit on the main thread within one user turn is the handoff point.** Two files is a change; three is a sweep. Route the remainder to one `subagent_type: "sonnet-4-6"` sub-agent (omit `model`) before making that third edit — don't wait to be stopped.

This is enforced by `~/.claude/hooks/plan-gate-edit.sh` — the hook **denies** the Edit/Write so the gate cannot be read past. Escape hatch: `touch /tmp/claude-sweep-override-<prompt_id>` (example) (say why, out loud). Full gate properties (incl. what counts as a repo file): `work-triage-detail.md` § Hard trigger.

Self-test: `bash ~/.claude/hooks/test-plan-gate-edit.sh`.

## Execution routing: repeat-polling is a spend bug

Never poll on the main thread — a poll loop re-reads full context per call. Use `run_in_background: true` + Monitor, or ScheduleWakeup matched to expected completion time. Full rationale: `work-triage-detail.md` § Repeat-polling.

## Calibration

Surface the verdict only when scope is **non-trivial or borderline**. Skip the ritual for obviously trivial edits (typo, one-line fix). 

**Headless:** no-op under headless/automouse (no user to recommend `/plan` to; automouse runs only pre-vetted plans). Governs interactive work-start only.
