---
description: Triage every non-trivial unit of work as ad-hoc vs /plan before starting; ad-hoc bar, ad-hoc safety mirror, Sonnet execution-routing (trigger stays resident, reasoning in work-triage-detail.md), hard trigger (≥5 files), and calibration.
last_verified: 2026-08-08
---

# Work Triage Rule

## Triage before non-trivial work

Before starting **any non-trivial unit of work** — whether you proposed it or the user assigned it — decide: implement **ad-hoc** (just do it, then `/ship`) or route through **`/plan`**. State the call and one line of why, then proceed. Deployment context and rationale: `work-triage-detail.md` § Execution routing context.

## The ad-hoc bar

Ad-hoc-safe only when **all** hold:
- **Known blast radius** — you can name every file/behavior it touches.
- **An existing pattern to copy** — not novel infrastructure.
- **No multi-phase reasoning** — a single coherent change, not a sequence with intermediate decisions.
- **No unresolved design fork** — nothing where the codebase can't reveal the right choice.

If any are open, it wants a `/plan`.

**Resolve empirical unknowns first** (occurrences, false-positive risk) — the scan often collapses a design fork into ad-hoc.

## Ad-hoc safety mirror

Even when the bar says ad-hoc, run a quick safety check — the surfaces `/plan` Step 4 gate 14 holds the merge for, plus the ship-pipeline surface `/plan` Step 3 escalates to `plan-architect-xhigh`. If the change touches any of:
- a **security surface** (SQL, POST/form endpoint, auth/authz-gated route, user-facing output rendering),
- a **destructive or schema-tightening migration**,
- **new or redesigned user-visible UI/UX**,
- a **gate removal or weakening** in the ship-pipeline surface (`.claude/skills`, `.claude/rules`, `~/.claude/hooks`) — deletes, relaxes, or disables an enforcement mechanism, or **bootstrap hazard** (rewrites rules governing this change); *not* additive gates, prose edits, or plumbing, or
- a property needing **subjective human judgment** to confirm,

then prefer `/plan`, so the defense and its verification are designed up front. Why the PR-time backstop is not a substitute: `work-triage-detail.md` § Safety mirror backstop.

## Execution routing: an ad-hoc verdict does not mean Opus edits inline

The plan-vs-ad-hoc verdict decides *whether to plan*, not *who executes the edits*. Defaulting silently to inline Opus is the measured leak — see `work-triage-detail.md` § Execution routing context.

**Before making a chunk of edits, route the execution.** The chunk is **Sonnet-executable** when both hold — the same criterion as `/plan` Step 4 gate 13:

- **Design resolved** — you could write the full recipe now (files, exact changes, order); no edit re-opens a judgment call.
- **Machine-verifiable** — a test/linter/script exists (or ships with the chunk) that fails on a wrong edit.

When both hold, **hand off by default — do not pause for permission**: state the routing call in one line ("execution is Sonnet-suitable — delegating"), then spawn **one** Sonnet sub-agent (format: `.claude/skills/plan/_architect-contract.md` § Delegation packets for verbose phases). Design, routing call, and final diff review stay on Opus — this routes *execution*, never understanding.

Stay inline (Opus edits directly) only when: the edits are genuinely **entangled** with the design, or the chunk is **trivial**. Criteria and spawn-cost rationale: `work-triage-detail.md` § Inline vs. delegated.

### The hard trigger: ≥5 distinct files in one turn

**The numeric rule: the fifth distinct repo file you edit on the main thread within one user turn is the handoff point.** Four files is a change; five is a sweep. Route the remainder to one `subagent_type: "sonnet-4-6"` sub-agent (omit `model`) before making that fifth edit — don't wait to be stopped.

Enforced by `~/.claude/hooks/plan-gate-edit.sh` **§ Check 1**, which **denies** the Edit/Write — the gate cannot be read past, and its deny message carries the routing instruction and the escape hatch. The same hook also denies *Reads* under an unrelated check (the cross-worktree straddle gate); only Check 1 is the sweep trigger. Gate properties, escape hatch, and self-test: `work-triage-detail.md` § Hard trigger.

## Execution routing: repeat-polling is a spend bug

Never poll on the main thread — a poll loop re-reads full context per call. Use `run_in_background: true` + Monitor, or ScheduleWakeup matched to expected completion time.

**Then name the completion signal before writing the watcher** — process exit, job label gone, or the producer's own verdict line. An mtime/size on a file the producer appends to incrementally is **not** one; and if the producer already computes a verdict, read it rather than recomputing. Full rationale: `work-triage-detail.md` § Repeat-polling.

## Calibration

**Skip** for obviously trivial edits (typo, one-line fix). **Headless:** no-op under headless/automouse.
