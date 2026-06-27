---
description: Derive the Playwright Docker image tag from each package's package.json at runtime so the image and the installed client cannot drift; the drift guard becomes a no-hardcoded-literal regression guard.
last_verified: 2026-06-27
---

# ADR-0070: Single-source the Playwright version from each package.json

**Status:** Accepted
**Date:** 2026-06-27

## Context

The Playwright version was hand-mirrored across ~13 literal `mcr.microsoft.com/playwright:v<ver>-jammy` occurrences in three CI surfaces (`ibl5/bin/visual-regression.sh`, `.github/actions/setup-docker-e2e/action.yml`, `.github/workflows/e2e-tests.yml`) plus version strings in `ibl5/package.json`, `ibl5/bun.lock`, and `IBL6/package.json`. Dependabot bumps only `ibl5/package.json` and its lockfiles, so a `@playwright/test` bump (PR #1210, 1.60.0→1.61.1) left every other surface stale, producing two failures: the "Playwright version drift guard" job failed against its hardcoded `EXPECTED`, and every E2E/VR job failed because tests installed client 1.61.1 but ran inside the stale `v1.60.0-jammy` image (browser-binary mismatch). The lockstep was real but enforced by hand-mirroring, which Dependabot cannot maintain.

The precise root cause: the test *client* already tracked `package.json` (bun/npm install resolved the bump); only the Docker *image tag* stayed a frozen literal.

## Decision

Derive the Docker image tag from each package's own `package.json` at runtime, so the image tag follows the same source the client already follows.

- A single helper `bin/playwright-image-tag <package.json>` echoes the full image ref, reused by `ibl5/bin/visual-regression.sh`, the `setup-docker-e2e` composite action, the IBL6 E2E jobs, and the regression test — one copy of the version-to-tag logic.
- ibl5 jobs derive from `ibl5/package.json`: the `setup-docker-e2e` action derives once and exports `PW_IMAGE` via `$GITHUB_ENV`, which propagates to the rest of each calling job.
- IBL6 jobs derive from `IBL6/package.json` via a per-job step (they call the action without `with-playwright`).
- The "Playwright version drift guard" job is repurposed into a regression guard (`bin/check-playwright-pinning`) that fails only if a hardcoded image literal is re-introduced into a derivation surface. Its job id and name are unchanged.
- `ibl5/bun.lock` is dropped from guarding: CI runs `bun install` non-frozen, so the lockfile is a cache key, not a correctness gate; a stale entry is a harmless cache miss.
- IBL6 stays manually version-managed and is intentionally NOT added to `.github/dependabot.yml`; only its derivation is fixed so an ibl5 bump cannot break it.

## Alternatives Considered

- **Add IBL6 to Dependabot too.** Rejected (owner decision): IBL6 is a separate app on a deliberate manual cadence; coupling it to Dependabot was not wanted. Fixing only the derivation removes the breakage risk without forcing version coupling.
- **Inline the derive `grep` in each surface.** Rejected: duplicates the regex across five places that drift. A single helper is the true single source and is directly unit-tested.
- **Per-job derive step for ibl5 jobs instead of the composite export.** Rejected: the action is the single derivation point those jobs already share; `$GITHUB_ENV` propagation is documented and a propagation failure is fail-safe (empty image → red E2E, blocks merge).
- **Delete the drift guard.** Rejected: re-hardcoding a literal would silently re-introduce the failure class. The repurposed guard preserves that protection.

## Consequences

- Positive: a Dependabot `@playwright/test` bump in `/ibl5` self-heals across every CI surface; E2E/VR go green and auto-merge can proceed.
- Positive: ibl5 and IBL6 Playwright versions may now legitimately differ; the old guard's cross-package version-equality requirement is gone.
- Positive: the regression guard plus `bin/test-playwright-version` (a bumped temp `package.json` mapping to a bumped tag) prove self-heal mechanically, without a real bump.
- Negative/risk: ibl5-job derivation depends on composite-action `$GITHUB_ENV` propagation. Mitigated: it is documented behavior and fail-safe (a failure yields a red E2E that this very PR would surface, since master 1.60.0 makes the derived tag byte-identical to the prior literal).

## References

- `bin/playwright-image-tag` — the derive helper.
- `bin/check-playwright-pinning` — the repurposed regression guard.
- `bin/test-playwright-version` — the self-heal + guard-reject regression test.
- `.github/workflows/e2e-tests.yml`, `.github/actions/setup-docker-e2e/action.yml`, `ibl5/bin/visual-regression.sh` — the three derivation surfaces.
- `.github/dependabot.yml` — unchanged; IBL6 deliberately excluded.
