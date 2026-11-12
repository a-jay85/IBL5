---
description: Agents start worktree Docker stacks through bin/wt-stack and stop only the stacks they started.
last_verified: 2026-11-12
---

# ADR-0242: On-Demand Worktree Docker Stacks

**Status:** Accepted
**Date:** 2026-11-12

## Context

Agents started worktree Docker stacks with plain `bin/wt-up` and never stopped them. Idle stacks piled up and used memory on the Mac. Agents could not tell a stack they had started from one the user had started by hand, so no agent could safely shut a stack down.

## Decision

Agents start a worktree stack with `bin/wt-stack start <slug>` and stop it with `bin/wt-stack stop <slug>` when their step ends. `start` records the PHP container ID in `~/.cache/ibl5-wt-stack/<slug>`. `stop` runs `bin/wt-down <slug> --force` only when that marker matches the live container. A stack with no marker, or one the user re-ran with `bin/wt-up`, keeps running. Main-stack names are refused. `bin/test-wt-stack` covers these paths.

## Alternatives Considered

- Add an ownership flag to `bin/wt-up` and `bin/wt-down`. Rejected because both scripts serve the user directly, and an agent-only flag would mix two jobs in each.
- Stop every worktree stack when an agent finishes. Rejected because it would kill stacks the user started and is still using.
- Leave stacks running and prune idle ones on a schedule. Rejected because a timer cannot tell whether a user still needs a stack.

## Consequences

- Positive: only the main stack runs all the time. Worktree stacks run while an agent needs them.
- Positive: an agent never stops a stack the user started by hand.
- Negative: `bin/wt-stack start` re-runs `bin/wt-up` on a stack it already owns, which re-seeds that stack's data.
- Negative: a crash can leave a marker file behind. `stop` drops a marker whose container ID no longer matches.

## References

- `bin/wt-stack`
- `bin/test-wt-stack`
- `.claude/review-shared/scripts/wt-bring-up.sh`
- `tools/postplan-harness/harness/manual_testing.py`
- `.claude/rules/workflow-continuity.md`
