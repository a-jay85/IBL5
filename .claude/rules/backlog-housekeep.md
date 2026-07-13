---
description: When you implement or discover a backlog item, ship backlog housekeeping with the change via /backlog-housekeep; bin/check-docs is the backstop.
paths: ibl5/docs/backlog/**/*.md
last_verified: 2026-07-13
---

# Backlog Housekeeping

**Trigger.** When this change implements or resolves a backlog item — or surfaces new ones — backlog housekeeping ships **with** it, in the same PR, not as a follow-up.

**How.** Run `/backlog-housekeep`. Inside `/post-plan` (which cannot call the Skill tool), Read `.claude/skills/backlog-housekeep/SKILL.md` and follow it inline — `/post-plan` Phase 2.5 wires this.

**What it does.** Flips the resolved item's status, stamps newly-surfaced items with provenance, sweeps sibling and cross-backlog redundancy, archives done items behind a dated pointer, and reconciles the `ibl5/docs/backlog/README.md` index — the 7-op checklist lives in the skill, not here.

**Backstop.** `bin/check-docs --since=<base>` fails a PR that archives an item without its sibling archive file or its dated pointer, or that marks a body-status item done inline without archiving it. It is diff-scoped (changed backlog files only) — the skill does the reasoning; the gate is the false-positive-free structural check. Do not restate its rules here.
