---
description: Docs-only changes may open a PR and arm auto-merge directly, skipping /post-plan's review/security/E2E teeth, gated by a mechanical predicate.
last_verified: 2026-06-22
---

# ADR-0067: Docs-only light ship path

**Status:** Accepted
**Date:** 2026-06-22

## Context

Every verified-complete interactive change fires `bin/post-plan-now --auto` → full `/post-plan` (code review, security audit, E2E, CI watch) before auto-merge arms. `ship.md`'s Core invariant deliberately routes *all* arming through `/post-plan` Phase 6.5 because those agentic teeth cannot run from chat context. But for a pure documentation change — Markdown only — none of those teeth apply: there is no code to review, no SQL/auth/output surface to audit, no app behavior to E2E. Running the heavy pipeline (including a Docker-rebuilt E2E stack) to land a prose edit is pure overhead, and it was the only path to auto-merge.

## Decision

Add a **docs-only light ship path**: when a branch's full change set (committed-ahead ∪ uncommitted) is exclusively Markdown and touches none of the merge-gate/arming machinery, `/ship` and the interactive auto-fire may open the PR and arm `gh pr merge --auto` **directly**, skipping `/post-plan`. Eligibility is decided by a single mechanical predicate, `bin/ship-light-eligible` (exit 0 eligible / 1 ineligible / 2 error) — never a subjective "feels small" judgment — and is consumed by both `.claude/commands/ship.md` and `.claude/rules/workflow-continuity.md`. Skipping `/post-plan` does **not** skip CI: the branch-protection required contexts (`Tests and Analysis`, `E2E Tests`, `human-signoff`) all run on a docs-only PR and gate the `--auto` merge (their `pull_request` triggers carry no `paths` filter, and each has an `if: always()` gate job that reports the required context even when its work skips). Because `check-docs` (dead-reference / `last_verified` validation) is **not** itself a required context, the light path runs `bin/check-docs --since=origin/master` locally and refuses to arm if it fails — so doc validity is enforced before merge, not left to a non-blocking CI status. The merge-gate machinery is excluded from eligibility so a gate edit can never ride the path it defines: both the *arming* machinery (`ship.md`, `workflow-continuity.md`, `auto-commit.md`, `commit-conventions.md`, the `post-plan` skill) and the *gate-authoring / review / security* machinery (`plan.md` and `_plan-verification.md`, which author the hold criteria deciding what future changes need review; `pr-review.md` / `security-audit.md` and the shared `.claude/commands/_*.md` components, which define the review and audit teeth). An unreviewed edit to any of these could otherwise arm itself or silently weaken the gates protecting everything else.

## Alternatives Considered

- **Status quo (all via `/post-plan`)** — every change runs the full pipeline. Rejected because: post-plan's review/security/E2E add zero signal for a prose-only diff, so the cost is unjustified.
- **Route low-risk → `/ship --no-merge`** — open the PR, human merges. Rejected because: it does not auto-merge, so it still relies on the user's attention to land trivial docs.
- **Subjective size/complexity classifier** — Claude judges "is this small/low-risk". Rejected because: a misclassification auto-merges unreviewed code; the safe predicate is mechanical (does the diff touch any surface post-plan checks?), and prose size is irrelevant to safety.

## Consequences

- Positive: documentation lands fast without the heavy pipeline, while CI required checks still gate the merge.
- Positive: the file-path predicate is mechanical and testable (`bin/ship-light-eligible` + its Cli test), not a judgment call that can drift.
- Negative: it narrows `ship.md`'s "never arm directly" Core invariant to a carved-out, machine-checked exception — one more code path to keep correct, justified by the self-gating exclusion and the still-required CI checks.

## References

- `bin/ship-light-eligible` — the eligibility predicate (single source of truth).
- `ibl5/tests/Cli/ShipLightEligibleCliTest.php` — its tests.
- `.claude/commands/ship.md` — the `/ship` light path.
- `.claude/rules/workflow-continuity.md` — the interactive auto-fire routing.
