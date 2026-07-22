---
description: Read-on-demand detail for work-triage — measurement context for the inline-Opus leak, hard-trigger gate properties (sub-agent exemption, per-turn scoping, escape hatch), and repeat-polling spend rationale.
last_verified: 2026-07-22
paths:
  - ".claude/rules/work-triage.md"
  - "~/.claude/hooks/plan-gate-edit.sh"
---

# Work Triage — Detail

Read-on-demand companion to `work-triage.md` (always-loaded).

## Execution routing context

The measured leak (2026-07-07): ~90% of Opus main-thread calls were mechanical; 44% of sessions breached 150K context — the dumb-zone delegation rules exist to prevent this. An ad-hoc verdict silently defaulting to "the Opus session implements inline" is exactly what the Sonnet-execution-routing rule guards against.

## Hard trigger

### Why a numeric rule

The prose routing guidance in `work-triage.md` is a judgment call, and judgment loses to batch momentum — measured 2026-07-21: a 30-reference / 18-file rename was swept inline on Opus *after* the ≥125K dumb-zone `systemMessage` had already fired and been ignored. A warning you can read past is not a control.

### Gate properties

`~/.claude/hooks/plan-gate-edit.sh` § Check 1 enforces the ≥3-distinct-files rule by **denying** the Edit/Write tool call. Properties worth knowing:

- **Sub-agent edits are exempt** (`agent_id` present in the PreToolUse payload). The delegate you spawn in response is never blocked — otherwise the gate would brick the delegation it exists to force. Note sub-agents share the parent's `session_id` *and* `transcript_path`, so `agent_id` is the only usable discriminator.
- **Per user turn, not per session** (keyed on `prompt_id`). Three unrelated one-file edits across a long session are not a sweep and don't trip it.
- **Distinct files, not calls** — editing one file ten times counts once.
- **Repo files only** — a path counts only when it resolves inside a git working tree; `/tmp` scratch and `~/.claude` hook/settings edits never accrue, so they can't push a later repo file over the line. A new file in a not-yet-created repo subdirectory still counts, because the check walks up to the nearest existing ancestor directory.
- **Fails open** on a malformed payload; never blocks editing because a field was missing.
- **Escape hatch, deliberately loud:** `touch /tmp/claude-sweep-override-<prompt_id>` (example) releases it for that turn. Legitimate when the edits are genuinely *entangled* with the design — for example authoring a rule doc, its detail companion, and the ADR recording the decision together. Using it silently defeats the gate: **say out loud that you're overriding and why**, in the same turn.

## Repeat-polling

A poll loop re-reads the full context every call (~81K tokens); eight 60s checks burns ~650K tokens vs ~81K for one deferred check. **Never poll on the main thread.**

**Instead:** `run_in_background: true` + Monitor (re-invokes on completion, main thread free), or ScheduleWakeup matched to the expected completion time (one ~480s wakeup for a CI run beats eight 60s checks). Avoid repeated `gh pr checks` / `gh run watch` inline loops — both re-read full context per call.

**Headless exception:** no re-invocation in headless/automouse — block until exit or structure as sequenced plan phases. See `agent-tiering-detail.md`.
