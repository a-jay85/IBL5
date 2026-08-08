---
description: Historical archive: completed/declined CI workflow simplification entries, extracted from ci-backlog.md.
last_verified: 2026-08-08
---

# CI Workflow Simplification Backlog — Archive

Read-only historical record of ✅ Implemented / 🚫 Declined findings. For OPEN items see ../ci-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### 2.1 smoke-prod.yml — four near-identical notify jobs
**Location:** `.github/workflows/smoke-prod.yml` — jobs `rollback-and-notify`, `notify-scheduled-failure`, `notify-ibl6-degradation`, `notify-inconclusive`.
**Problem:** Four separate jobs each boot a fresh runner solely to SSH-tunnel one `curl` DM; they differ only in the trigger condition and message string. (Same notify shape recurs in `main.yml`, `mutation.yml`, `db-backup.yml`.)
**Suggested direction:** Collapse the three notify-only jobs into one job that branches on the `smoke` outcome via `if:` (keep `rollback-and-notify` separate — it mutates git). Best done **after** 1.2 lands so the merged job calls the `notify-discord` composite.
**Risk if untouched:** Notify logic forks across 4 jobs; a message-format change is repeated.
**Status (2026-07-11):** ✅ Implemented — three notify-only jobs collapsed into one `notify` job with `always()` guard; message selects branch via `if/elif/else` on `SMOKE_RESULT`/`IBL5_INCONCLUSIVE`. `auto_merge: false` — deploy/notify path is prod-only, unreachable from CI. (#1423)

### 2.2 migration-safety.yml — three jobs each rebuild a full DB stack
**Location:** `.github/workflows/migration-safety.yml` — jobs `idempotency-check`, `schema-parity-check`, `schema-completeness`.
**Problem:** All three spin up an independent MariaDB 10.11 service, run an independent composer install, and apply the full migration stack from zero. `idempotency-check` and `schema-completeness` both apply the same full stack; the latter just adds FK/table/column assertions afterward.
**Suggested direction:** Merge `idempotency-check` into `schema-completeness` (one DB build, then both sets of assertions). Keep `schema-parity-check` separate — it needs two DBs by design. Costs some intra-workflow parallelism; nets fewer runner-minutes and one fewer composer install.
**Risk if untouched:** Three full migration runs per push to a migrations file; setup duplication (mitigated once 1.1 lands).
**Status (2026-07-11):** ✅ Implemented — folded `idempotency-check`'s bash-apply→PHP-seed→`migrate --status` idempotency assertion into `schema-completeness` (shared MariaDB service + `setup-php-env`); removed the standalone job and dropped it from the `gate` job's `needs`. `schema-parity-check` kept separate (two DBs). Green-green — all assertions preserved. (#1422)

### 3.1 audit-js — `npm audit` without a prior install
**Location:** `.github/workflows/tests.yml`, job `audit-js`.
**Problem:** Runs `npm audit --audit-level=high` with no `npm ci`/`npm install` first, relying on the bare runner Node — it may pass vacuously (no lockfile-resolved tree to scan). This is a **correctness** gap, not just tidiness.
**Suggested direction:** Install deps (or point at the lockfile) before `npm audit`; or move JS audit onto the Bun toolchain the rest of the repo uses.
**Risk if untouched:** A high-severity JS advisory could slip through unflagged.
**Status (2026-07-11):** ✅ Implemented — added `npm ci` step before `npm audit` in `.github/workflows/tests.yml`; audit now scans the resolved lockfile tree. (#1419)

### 3.2 db-backup.yml — manual MariaDB wait loop on top of a health-check
**Location:** `.github/workflows/db-backup.yml`, job `backup`, step "Wait for verify MariaDB to be ready".
**Problem:** A 30×5s manual retry loop runs even though the service container already declares `--health-retries=10`; by the time steps run the service is healthy. Dead wait.
**Suggested direction:** Drop the loop; rely on the service health-check (as other DB-using workflows do).
**Risk if untouched:** Up to 150s of wasted wall-time per nightly run; misleading "wait" step.
**Status (2026-07-11):** ✅ Implemented — dropped the 30×5s `mysqladmin ping` loop from `.github/workflows/db-backup.yml`; MariaDB readiness is guaranteed by the service container health-check. (#1419)

### 3.3 lighthouse-audit.yml re-runs a full-site collect
**Location:** `.github/workflows/lighthouse-audit.yml` vs `.github/workflows/lighthouse-baseline.yml`.
**Problem:** Both do a full-site `lhci collect` with `numberOfRuns=1` over the same URL set (`bin/lighthouse-audit-urls`). The weekly audit could consume the `lighthouse-baseline-manifest` artifact the baseline workflow already uploads, instead of re-collecting.
**Suggested direction:** Have the weekly audit download + report on the latest baseline manifest where freshness allows; re-collect only if the artifact is stale/absent.
**Risk if untouched:** Duplicate 120-min-budget LHCI collect weekly. (Low priority — distinct outputs, see Axis 4.)
**Status (2026-07-11):** ✅ Implemented — `lighthouse-audit.yml` now downloads the `lighthouse-baseline-manifest` artifact and, when present and ≤7 days old, skips Docker + `lhci collect` and generates the report from the downloaded manifest; absent/stale falls back to the unchanged full-collect path. (#1421)

### 3.4 Inconsistent change-detection across workflows
**Location:** `dorny/paths-filter@v4` in `codeql.yml`/`engine.yml`/`eslint.yml`; `bin/website-affecting` git-diff in `e2e-tests.yml`/`lighthouse.yml`; static `paths:` filters elsewhere.
**Problem:** Three different mechanisms answer "did relevant files change?". Harder to reason about why a given workflow did/didn't run.
**Suggested direction:** Standardize where semantics allow (note `bin/website-affecting` encodes domain logic a static filter can't; not all are interchangeable). Modest payoff — defer unless it causes a miss.
**Risk if untouched:** Cognitive overhead; subtle trigger-gap bugs.
**Status (2026-07-11):** ✅ Implemented — 🟩 (per-workflow audit done; mechanisms are intentional, no standardization applied).
**Audit outcome:** The three mechanisms are NOT interchangeable — each workflow uses the correct one. `dorny/paths-filter` (codeql/engine/eslint) is language/tool-scoped: run CodeQL only on JS/TS changes, engine CI only on Go changes, ESLint only on e2e/tooling changes. `bin/website-affecting` (e2e-tests/lighthouse `src`) encodes domain logic — "does this diff affect app rendering?" — via deny-regex + carve-outs + CI-meta-exempt list that a static glob cannot replicate; switching those to dorny would mis-fire (PHP-only PRs would wrongly skip, CI-meta edits would wrongly trigger). Switching codeql/engine/eslint to `website-affecting` would also be wrong (a PHP-only PR would trigger CodeQL). Static `paths:` on `on: push` triggers is a GitHub Actions constraint (scripts can't run in `on:` triggers), so dorny/website-affecting are inherently PR-only. Deliverable was rationale comments added to each affected workflow (#1424) documenting why each mechanism is the right one; no mechanical change was semantically valid.

### 6.1 CI path-filter coupling splits sim-recap producer from consumer
*(discovered 2026-07-31 during #1753)*
**Location:** `.github/workflows/tests.yml` `changes:` job — the `src` filter gates `db-integration` on `**.php`; the `shell` filter gates `harness-tests` on `bin/**`. The sim-recap producer (`ibl5/scripts/simRecap*`, `ibl5/classes/SimRecap/**`) and consumer (`bin/sim-recap-*`) are on opposite sides of this boundary.
**Problem:** A PHP-only change to the sim-recap producer runs zero prompt/tick tests. A `bin/`-only change to the consumer runs zero DB integration tests. The filter split is what let the traded-player regression survive PR review — CI stayed green because the producer and consumer were never tested together.
**Suggested direction:** Extend the `changes:` coupling declaration so that a change to `bin/sim-recap-*` triggers `db-integration`, and a change to `ibl5/scripts/simRecap*` or `ibl5/classes/SimRecap/**` triggers `harness-tests`. Prefer the general cross-coupling pattern over a sim-recap special case.
**Risk if untouched:** Every future sim-recap PR that touches one surface only (PHP or shell) silently omits half the test coverage. The named regression (traded player attributed to wrong team) would not have been caught by CI.
**Closes gap:** #4 from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Status (2026-08-08):** ✅ Implemented — added a general `both:` paths-filter group whose members trigger **both** jobs, widened the two job conditions with `|| needs.changes.outputs.both == 'true'`, and added `bin/test-path-filters` to `harness-tests` so the coupling cannot silently regress. `auto_merge: false` — the change is to the merge-gate machinery itself. 🟦. (#1801)

### 6.2 `ibl5/scripts/` excluded from phpstan; script fatals degrade silently
*(discovered 2026-07-31 during #1753)*
**Location:** `ibl5/phpstan.neon` `paths:` (currently lists `classes`, `phpstan-rules`, and extension-less `bin/` scripts — zero mention of `scripts`); no `php -l` sweep exists anywhere in `.github/` or `bin/`.
**Problem:** A broken class reference in any `ibl5/scripts/*.php` file exits 255 while a guard unit test (e.g. `SimRecapContextGuardTest`) reports OK — the guard test is regex-only over source text and cannot catch a class-resolution failure. In prod, that fatal degrades silently: `simRecapContext.php` (example) would return exit 255 and the caller wraps the failure as `{}`, shipping a roster-blind recap. Proven by mutation during the #1753 audit. Also pending: a stale ADR-0092 citation in `ibl5/scripts/simRecapQueue.php`'s docblock (correct ADR is 0093) — fold into this PR if it does not trip `bin/check-docs` freshness, otherwise its own trivial fix.
**Suggested direction:** Add `scripts` to `ibl5/phpstan.neon` `paths:` and generate a baseline (`ibl5/phpstan-baseline.neon`) to absorb pre-existing findings. Optionally add a `php -l` sweep. Ad-hoc — an existing pattern (add path + generate baseline) covers this; no `/plan` needed.
**Risk if untouched:** Any syntax or class-reference error in `ibl5/scripts/*.php` is invisible to CI. It surfaces only as a prod degradation (the exact bug class PR #1753 fixed).
**Closes gap:** #1 (static-analysis half) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Status (2026-08-05):** ✅ Implemented — PR #1759 (`phpstan-scripts-dir-coverage`): `scripts` dir added to `ibl5/phpstan.neon` `paths:`; phpstan baseline generated; ADR-0092→0093 docblock fix included.
