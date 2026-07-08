---
description: Rationale for adding coverage regression detection and PHPStan baseline drift detection to CI.
last_verified: 2026-07-07
---

# ADR-0018: Coverage Regression and Baseline Drift Detection

**Status:** Accepted
**Date:** 2026-05-04

## Context

Two CI gaps allowed regressions to creep in unnoticed:

1. **Coverage floor, not ratchet.** `ibl5/bin/check-coverage` fails only below 70%. A PR dropping coverage from 75% to 71% passes silently.
2. **PHPStan baseline is a black box.** `phpstan-baseline.neon` absorbs new errors without any per-rule count comparison. A stale-cache regeneration once surfaced 100+ extra baselined errors.

## Decision

Add two complementary detection mechanisms:

### Coverage regression detection
- New `CoverageComparator` class compares current coverage against a committed `coverage-baseline.json` file with configurable tolerance (default 0.5%).
- New `ibl5/bin/check-coverage-regression` CLI exits non-zero if coverage drops beyond tolerance.
- CI step runs after the existing threshold check in the `test` job.
- On master push, the `update-baselines` job regenerates the baseline JSON and commits it via the CI bot.

### PHPStan baseline drift detection
- New `PhpstanBaselineCounter` class counts entries per identifier in `.neon` baseline files.
- New `ibl5/bin/check-baseline-drift` CLI compares current counts against a committed `phpstan-baseline-counts.json` snapshot. Exits non-zero on any per-rule increase. Warns (exit 0) when decreases exceed 5.
- CI step runs after `composer run analyse` in the `phpstan` job.
- On master push, the same `update-baselines` job regenerates the snapshot.

Both CLI tools support `--update` mode for the CI auto-commit workflow, reusing the `secrets.CI_PAT` pattern from `e2e-tests.yml`.

## Alternatives Considered

- **Ratchet the floor itself** — auto-raising the 70% threshold when coverage exceeds it. More aggressive but risks flaky red CI when coverage fluctuates near the threshold. The regression detector handles gradual drift without floor-ratcheting friction.
- **Full neon parsing** — using a YAML/neon parser instead of regex. Overkill for counting `identifier:` lines; a regex is simpler and has no library dependency.
- **Per-file coverage gating** — tracking coverage per-file. More granular but significantly more complex. Project-level detection catches the macro drift we care about.

## Consequences

- Positive: PRs that silently drop coverage are now blocked.
- Positive: PHPStan baseline growth is visible and blocked per-identifier.
- Positive: baselines auto-update on master merge, requiring no manual maintenance.
- Negative: CI minute usage increases slightly (one extra PHPUnit coverage run on master push).
- Negative: two new committed JSON files (`coverage-baseline.json`, `phpstan-baseline-counts.json`) that update on master merges, creating auto-commits.
