---
description: Read-on-demand only (no auto-attach trigger) — explains why /post-plan Phase 6.5 condition (9) may run on Sonnet rather than Opus: it is bounded hold-enumeration against a named trigger list, not open-ended diff-triage. Includes the tripwire for when to revisit. Parent agent-tiering-detail.md points here for readers who reach the bounded-checklist rationale.
last_verified: 2026-08-08
paths: ".claude/rules/agent-tiering-bounded-checklist.md"
---

Read-on-demand companion to `agent-tiering.md` § Tiers (Opus row, "open-ended diff-triage") and `agent-tiering-detail.md`. Nothing here auto-attaches: `agent-tiering-detail.md` § Nested Sub-Agents points here, and that file attaches whenever `.claude/skills/**/*.md` is in play — which is exactly the context (`/post-plan` reading `_phase-6.5-arm-auto-merge.md`) where this rationale is needed.

## Bounded-checklist diff-triage (post-plan exception)

The Opus row's "open-ended diff-triage" means: read a diff you have no checklist for, reason
from scratch about what could be wrong, and decide what matters. That stays Opus — the
failure mode is missing what you didn't know to look for (`feedback_sonnet_proving_negatives`).

**`/post-plan` Phase 6.5 condition (9) is not that.** It is *bounded* hold-enumeration: read
the realized diff plus the carried Phase-3 flags (`HAS_MIGRATION`, `GOLDEN_CHANGED`,
`COUNT_*`) and enumerate holds against a **named trigger list** — introduced or expanded
SQL / POST-form / auth-gated surfaces; destructive or FK-ordering migrations and
column-rename sweeps; new or redesigned user-visible UI/UX; any change whose blast radius
or reversibility you cannot bound. It is framed as enumerating holds, **never** as certifying safe, and it biases hard to HOLD on any doubt.

That asymmetry is what licenses the Sonnet tier here. A checklist run below Opus fails by
**over**-holding (the PR waits for a human — already the default) or by missing an item on a
list it was handed. It cannot fail by silently certifying a diff safe, because it is never
asked to. Open-ended triage has no such floor. **Phase 4D is likewise rubric-scoring** —
findings arrive from the Phase-4 review agents and are scored against a fixed scale, then
threshold-filtered; the "is this finding real?" judgment happened upstream, in each agent's
own analysis of the diff.

This is a **scope correction, not a capability argument**: bounded checklist enumeration was
never the diff-triage the Opus row meant. `/pr-review` and `/security-audit` do open-ended
triage and run on Sonnet 4.6 by deliberate def/frontmatter pin (`agent-tiering.md`
§ Sonnet 4.6 pins) — sanctioned pins, not exceptions this section carves out.

**Tripwire to revisit:** a *measured* miss where a shipped diff was unsafe for a reason
**not** on condition (9)'s trigger list (a listed trigger that a run simply failed to spot is
a prompt bug, not a tier bug). Escalating then means copying Phase 7's pattern in
`.claude/skills/post-plan/_phase-7-ci-monitoring.md` and adding an `opus` key to `MODEL_MAP`
in `tools/postplan-harness/harness/adapters/llm.py`, which has none today.

