---
description: Unifies the deployment funnel so every PR-opening path flows through /post-plan; triage is a loaded rule, /plan auto-queues, /ship drops merge-intent tokens.
last_verified: 2026-07-03
---

# ADR-0067: Unified deployment funnel — one pipeline through /post-plan

**Status:** Accepted
**Date:** 2026-06-22

## Context

The in-Claude deployment funnel (`/plan`, `/post-plan`, `/ship`, `/queue`, `commit-commands`) grew piecemeal. Its decision points were scattered across three mechanisms with inconsistent handoffs: the plan-vs-ad-hoc triage lived only in a per-project memory note (no enforcement surface); `/ship --no-merge` opened a raw PR via `commit-push-pr` with zero code review / security audit / verification, coupling safety to *merge intent* instead of to the work; and `/plan` emitted no queue-safety verdict and never auto-queued, leaving the `/plan → /queue` handoff fully manual. The intent is that Claude auto-routes each unit of work to the right funnel exit (auto-merge / queue / post-plan / plan / ship) without the user hand-picking the branch.

## Decision

Collapse the funnel to a single pipeline with one variable:

- **Triage is a loaded rule.** `.claude/rules/work-triage.md` decides plan-vs-ad-hoc before any non-trivial work, with an ad-hoc safety mirror (the gate-14 surfaces). It is the single gateway.
- **Every PR-opening path flows through `/post-plan`.** `/post-plan` always opens the PR and runs review + audit + verification; **auto-merge arming is the only variable**, decided by Phase 6.5's conditions.
- **`/plan` auto-queues queue-safe plans.** When `bin/check-plan` passes (which already enforces no-unresolved-decisions), `/plan` auto-invokes `bin/automouse-queue` unless the session opts to implement now. Queue-safety is independent of `auto_merge`.
- **`/ship` collapses to a thin `/post-plan` wrapper.** The `--no-merge` / `--merge` tokens and the merge-intent resolution are removed; `/ship` fires bare `bin/post-plan-now` after its precondition gate. A reviewed-but-held PR still happens automatically whenever Phase 6.5 holds the work — no token to remember.

## Alternatives Considered

- **Docs-only direct-arm bypass (PR #1193).** A light path that armed `gh pr merge --auto` directly, skipping `/post-plan`, for Markdown-only diffs. Rejected and closed: a second arming path violates the single-pipeline model, and `/post-plan` already self-scales to near-nothing on a prose diff (Phase 3 `DOCS_ONLY`, Phase 4/5 agent + suite skips).
- **Route `/ship --no-merge` through post-plan-with-hold.** Keep the token but make it open a reviewed-but-held PR. Rejected in favor of removing the token entirely: the held-PR outcome already emerges automatically when work is not arming-safe, and the user never reaches for the flag (see the zero-token-default preference).

## Consequences

- Positive: one front door for work; consistent review/audit/verification on every PR; one arming authority (Phase 6.5); less for the user to remember.
- Positive: triage is enforced in-repo (a rule), not recalled from memory.
- Negative: the deliberate hold of an *arming-eligible* PR purely to eyeball it is gone — accepted, because that outcome contradicts "safe → ship automatically," and `auto_merge: false` still covers planned work that genuinely wants a human at merge.

## References

- `.claude/rules/work-triage.md` — the triage gateway rule (added in this PR).
- `.claude/rules/workflow-continuity.md` — the implementation → `/post-plan` auto-fire handoff.
- `.claude/skills/plan/SKILL.md` — `/plan` (auto-queue added in PR B).
- `.claude/skills/ship/SKILL.md` — `/ship` (simplified in PR C).
- `.claude/skills/post-plan/SKILL.md` — Phase 6.5, the single arming authority.
- `bin/automouse-queue` — the queue command `/plan` invokes.
