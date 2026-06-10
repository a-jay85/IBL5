---
description: Isolate destructive global-state E2E specs into a dedicated non-sharded Playwright project and CI job to prevent cross-shard reader/mutator collisions.
last_verified: 2026-06-09
---

# ADR-0052: Non-sharded mutator isolation job for destructive global-state E2E specs

**Status:** Accepted
**Date:** 2026-06-09

## Context

Two E2E specs — `ibl5/tests/e2e/smoke/updater-awards.spec.ts` and `ibl5/tests/e2e/flows/league-control-panel.spec.ts` — drive the full-season updater and mutate **global** DB rows (schedules, standings, sims, awards, season phase) that every other spec treats as read-only. In CI these specs pass only because the four shards each run against an isolated DB and the two files happen to land on different shards — a latent reshuffle-race identical to the #884/#886 class documented in ADR-0033. A single-DB local full-suite run reproducibly collides: a reader shard sees an in-flight mutator's torn state and fails. The fix must partition the test graph at the job level so the mutators never share a DB with the readers.

## Decision

Add a dedicated Playwright project named `mutators` in `ibl5/playwright.config.ts` whose `testMatch` captures exactly those two spec files. Exclude both files from the `chromium` project's `testIgnore` so the sharded run is strictly reader-only. Add a CI job `e2e-mutators` in `.github/workflows/e2e-tests.yml` that runs `--project=mutators --workers=1` with its own fresh Docker DB (via the existing `.github/actions/setup-docker-e2e` composite action). Wire `e2e-mutators` into both `gate.needs` and `merge-reports.needs` so a red mutators job blocks merge and its blob artifact is included in the merged HTML report. All future destructive global-state specs must be added to the `mutators` project, not `chromium`.

## Alternatives Considered

- **Config project ordering / `fullyParallel: false`** — orders tests only within a single file; does not prevent cross-shard co-location of a reader and a mutator. Rejected.
- **Mutators-as-`dependency` of chromium** — Playwright runs dependency projects before dependents, which would rewrite the seeded DB that the readers consume before they run. Rejected.
- **chromium-as-`dependency` of mutators** — Playwright would rerun the entire ~1 000-test chromium project as a prerequisite on every shard before running the mutators, multiplying wall-clock time 4×. Rejected.
- **Per-spec `test.describe.serial` annotations only** — serialises tests within one file but does not prevent the sharded runner from co-locating a reader shard with a mutator shard on a shared DB. Rejected.

## Consequences

- Positive: the sharded `chromium` run is strictly reader-only; reshuffle no longer risks a reader/mutator collision.
- Positive: the mutators run serially (`--workers=1`) in one job against a fresh DB, mirroring the isolation contract that applies to all global-state specs.
- Positive: a red mutators job blocks merge via `gate.needs`; its results appear in the merged HTML report via `merge-reports.needs`.
- Negative: one additional CI job per PR (~one updater run's wall-clock time, not 9× across shards).
- Negative: a bare local `bin/e2e-wt.sh` (no `--project`) still runs both projects against one DB and will collide; developers must use the two-run protocol (chromium-only on fresh seed, then mutators-only on fresh seed) to reproduce CI conditions locally.

## References

- `ibl5/playwright.config.ts` — `mutators` project definition; `chromium` `testIgnore` extension.
- `.github/workflows/e2e-tests.yml` — `e2e-mutators` job; `gate.needs` and `merge-reports.needs` wiring; `--project=chromium` pin on the shard run.
- `ibl5/tests/e2e/smoke/updater-awards.spec.ts` — mutator spec (destructive updater runs).
- `ibl5/tests/e2e/flows/league-control-panel.spec.ts` — mutator spec (Finals MVP insert + season-phase flip).
- `bin/e2e-wt.sh` — local full-suite runner; runs all projects against one DB unless `--project` is passed.
- `ibl5/docs/decisions/0033-non-destructive-sibling-test-database.md` — DB-level sibling isolation for PHPUnit (the #884/#886 reshuffle-race precedent; different layer from this ADR).
- `ibl5/docs/decisions/0043-empty-fga-source-isolation.md` — engine-instrument isolation (related by name only; different constraint).
