---
description: Batched main-thread edits are capped at a hard numeric ≥3-distinct-files-per-turn limit, enforced by a PreToolUse deny rather than a warning.
last_verified: 2026-07-22
---

# ADR-0091: Hard numeric sweep-delegation gate (≥3 files per turn)

**Status:** Accepted
**Date:** 2026-07-22

## Context

`work-triage.md` § execution routing already directed the Opus main thread to hand resolved, machine-verifiable edit chunks to a Sonnet sub-agent, and `output-guard.sh` already emitted a `systemMessage` when context crossed the ≥125K dumb-zone threshold. Both were ignored on 2026-07-21: a 30-reference / 18-file rename was swept inline on Opus *after* the dumb-zone warning had fired. The failure mode is not ignorance of the rule — it is batch momentum overriding a judgment call mid-sweep. A warning that can be read past is not a control.

## Decision

The **third distinct file** edited on the main thread within one user turn is the handoff point: route the remainder to one `subagent_type: "sonnet-4-6"` sub-agent. This is enforced, not documented — `~/.claude/hooks/plan-gate-edit.sh` § Check 1 **denies** the `Edit`/`Write` PreToolUse call (exit 2). The gate is scoped by three properties, each grounded in an empirical probe of the PreToolUse payload: sub-agent calls are exempt (they carry `agent_id`; main-thread calls do not), state is keyed per user turn on `prompt_id` rather than per session, and the count is of distinct files rather than tool calls. It fails open on any malformed payload. The escape hatch is `touch /tmp/claude-sweep-override-<prompt_id>` (example), to be used out loud with a stated reason when the edits are genuinely entangled with the design.

## Alternatives Considered

- **Strengthen the prose rule** — reword `work-triage.md` more forcefully. Rejected because: the prose rule already existed and was the thing that failed.
- **A louder warning (`systemMessage`)** — escalate the existing dumb-zone notice. Rejected because: the dumb-zone `systemMessage` is precisely the mechanism that was read past.
- **Gate on model (`model == opus`)** — deny only when the expensive model is sweeping. Rejected because: the PreToolUse payload exposes no `model` field. `agent_id` proved strictly better, exempting *all* delegates.
- **Key state on `transcript_path` or `session_id`** — the pattern `output-guard.sh` uses. Rejected because: the probe showed sub-agents inherit the parent's *both*, so delegate edits would have counted against the main thread and bricked the delegation the gate exists to force.
- **A new dedicated hook** — a standalone `sweep-delegation-gate.sh`. Rejected because: `.claude/rules/meta-tooling-bar.md` § extend-before-add — `plan-gate-edit.sh` is already the only `Edit|Write` PreToolUse hook, so extending it costs no new file and no new registration.

## Consequences

- Positive: the routing rule is now unreadable-past. A denied tool call forces the delegation decision to be made explicitly.
- Positive: the sub-agent exemption is load-bearing and locked in by test — the forced delegate is never itself blocked.
- Negative: the limit counts *any* path, including `/tmp` scratch and out-of-repo hook edits, so legitimate 3-file design turns (authoring a hook, its test, and its rule doc together) trip it and need the override. `SWEEP_LIMIT` is a single constant if the threshold wants raising.

## References

- `.claude/rules/work-triage.md` — § "The hard trigger: ≥3 distinct files in one turn".
- `.claude/rules/work-triage-detail.md` — § Hard trigger: full gate properties and the measured incident.
- `.claude/rules/meta-tooling-bar.md` — the extend-before-add bar that placed this in an existing hook.
- `~/.claude/hooks/plan-gate-edit.sh` § Check 1 — the enforcement (outside the repo tree).
- `~/.claude/hooks/test-plan-gate-edit.sh` — 10-case self-test; live-fire verified 2026-07-22.
