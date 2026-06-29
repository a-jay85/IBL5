---
description: ADR for bin/refactor-flag CI gate that blocks refactor PRs without test coverage.
last_verified: 2026-06-29
---

# ADR-0021: Refactor PR Flag

**Status:** Accepted
**Date:** 2026-05-13

## Context

Refactor PRs without pre-implementation characterization tests are the dominant source of behavior-regression incidents in this codebase. The FA formula regression incident and the view materialization incident both landed green because the refactor moved code without locking down current behavior in a test first — existing tests asserted the new shape, not the old. The TDD-selectivity rule ("green-green for refactors") was enforced by author discipline only; CI let renames-only PRs with zero test changes through.

## Decision

Add a CI gate (`bin/refactor-flag`, wired into the consolidated `.github/workflows/pr-meta-checks.yml`) that detects refactor signals under `ibl5/classes/**` (file renames, method signature changes, visibility narrowing, class declaration removal, large deletions > 30 lines) and blocks merge when the same PR does not also add or modify a test file under `ibl5/tests/`. A bypass marker (`<!-- no-refactor-tests: reason ≥ 20 chars -->`) in the PR body overrides the gate for documented exceptions. An agent rule (`.claude/rules/refactor-flag.md`) documents the policy for agents.

## Alternatives Considered

- **Require explicit `[refactor]` tag on PR title** — manual, easily forgotten, no enforcement surface.
- **Measure pre-impl test coverage delta** — requires baseline coverage cache infrastructure, significantly more complex for marginal precision gain.
- **Require ADR on every refactor** — overweight; ADRs are for architectural decisions, not safety nets. The existing ADR gate already covers new enforcement surfaces.

## Consequences

- Positive: makes the "green-green" TDD rule mechanical rather than voluntary, preventing the class of incidents that led to this decision.
- Positive: mirrors the existing `bin/adr-check` structure, keeping CI gate patterns consistent and maintainable.
- Negative: false positives on pure file moves where the test filename doesn't substring-match the class name; mitigated by the bypass marker.
- Negative: false negatives on subtle behavior changes that don't match any of the five trigger patterns; mitigated by mutation testing (ADR for Plan 05) as second-line defense.

## References

- `bin/refactor-flag` — the CI gate script
- `.github/workflows/pr-meta-checks.yml` — the GitHub Actions workflow (the `refactor-flag` check, consolidated from the former `refactor-flag.yml`)
- `.claude/rules/refactor-flag.md` — agent rule documenting the policy
- `bin/adr-check` — the existing decision-trigger gate this mirrors
- `ibl5/tests/Cli/RefactorFlagCliTest.php` — test coverage for the gate
- `ibl5/tests/Cli/AdrCheckCliTest.php` — characterization test for the existing gate
