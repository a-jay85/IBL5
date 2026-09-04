---
description: Historical archive: completed/declined CI workflow simplification entries, extracted from ci-backlog.md.
last_verified: 2026-09-04
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

### 6.2 `ibl5/scripts/` excluded from phpstan; script fatals degrade silently
*(discovered 2026-07-31 during #1753)*
**Location:** `ibl5/phpstan.neon` `paths:` (currently lists `classes`, `phpstan-rules`, and extension-less `bin/` scripts — zero mention of `scripts`); no `php -l` sweep exists anywhere in `.github/` or `bin/`.
**Problem:** A broken class reference in any `ibl5/scripts/*.php` file exits 255 while a guard unit test (e.g. `SimRecapContextGuardTest`) reports OK — the guard test is regex-only over source text and cannot catch a class-resolution failure. In prod, that fatal degrades silently: `simRecapContext.php` (example) would return exit 255 and the caller wraps the failure as `{}`, shipping a roster-blind recap. Proven by mutation during the #1753 audit. Also pending: a stale ADR-0092 citation in `ibl5/scripts/simRecapQueue.php`'s docblock (correct ADR is 0093) — fold into this PR if it does not trip `bin/check-docs` freshness, otherwise its own trivial fix.
**Suggested direction:** Add `scripts` to `ibl5/phpstan.neon` `paths:` and generate a baseline (`ibl5/phpstan-baseline.neon`) to absorb pre-existing findings. Optionally add a `php -l` sweep. Ad-hoc — an existing pattern (add path + generate baseline) covers this; no `/plan` needed.
**Risk if untouched:** Any syntax or class-reference error in `ibl5/scripts/*.php` is invisible to CI. It surfaces only as a prod degradation (the exact bug class PR #1753 fixed).
**Closes gap:** #1 (static-analysis half) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Status (2026-08-05):** ✅ Implemented — PR #1759 (`phpstan-scripts-dir-coverage`): `scripts` dir added to `ibl5/phpstan.neon` `paths:`; phpstan baseline generated; ADR-0092→0093 docblock fix included.

### 6.3 `SimRecapPayload` accepts `game_of_that_day < 1`; `SimSummaryRepository` silently drops those rows
*(discovered 2026-07-31 during #1753)*
**Location:** `ibl5/classes/SimRecap/SimRecapPayload.php` (`requireInt` with no lower bound on `game_of_that_day`); `ibl5/classes/SimRecap/SimSummaryRepository.php` (`COALESCE(bst.game_of_that_day, 0) = gr.game_of_that_day` silently drops rows where `game_of_that_day = 0`).
**Problem:** The new `…MismatchDropped` test (PR #1753) pins the silent drop as *expected behavior*. The correct fix is an ingest-time lower-bound check so that `game_of_that_day < 1` is rejected at `SimRecapPayload::fromJson()`. An open design fork must be resolved first: **fail-closed vs. warn**, and what to do when box scores land *after* the recap.
**Suggested direction:** Resolve the fail-closed-vs-warn fork (lean fail-closed with a structured error); add a `requireInt` lower bound; revisit `testFindDisplayableGameRecapsMismatchDropped` to test the rejection, not the silent drop. This item is the plan's own deferred "ingest-time reconciliation of `game_of_that_day`" Out-of-Scope item.
**Risk if untouched:** `game_of_that_day = 0` silently drops recap rows; the test suite treats this as expected, so future regressions in this path pass CI green.
**Closes gap:** #8 from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Tracked here** by PR #1753 audit origin, not by theme (no existing backlog covers payload-validation gaps).
**Status (2026-08-08):** ✅ Implemented — PR #1800 (`game-of-that-day-validation-floor`): `requirePositiveInt()` helper added to `SimRecapPayload`; applied to `game_of_that_day`; 7 PHPUnit test methods (9 cases: rejection, error message, per-element index, boundary, over-rejection, int-type-before-range delegation order); design fork (fail-closed vs. warn) resolved fail-closed at DTO boundary; `SimSummaryRepository.php:392` left unchanged (fork B resolved by fork A). (#1800)

### 8.1 `pr-canary-check` sticky comment cites a non-existent merge queue
*(discovered 2026-08-23 during #1949)*
**Location:** `bin/pr-canary-check` — the sticky-comment body builder, footer line (helper for `.github/workflows/pr-canary.yml`).
**Problem:** The advisory footer read "The merge queue runs the authoritative full suite." This repo has no merge queue and can never have one: GitHub's native merge queue requires organization ownership, and `a-jay85/IBL5` is a User-owned repo on the free personal tier (`mergeQueue: null` on a live GraphQL query; already recorded at ADR-0081:22). The sentence names a mechanism that does not exist, so a reader trusting it looks for an authoritative suite that never runs — the authority actually lives in the required PR checks (`Tests and Analysis`, `E2E Tests`) plus the `human-signoff` gate. Same class of stale premise as the `ibl5/docs/STRATEGIC_PRIORITIES.md` line corrected in this PR.
**Suggested direction:** Replace the merge-queue clause with the real authority — the required PR checks. One-line string change; no test asserts on the string (`grep -rn "authoritative full suite" bin/ .github/` returns the source line only), so the edit is behaviour-preserving for every caller.
**Risk if untouched:** Every canary comment on every PR repeats a false statement about the repo's own merge pipeline, which is exactly the premise that cost three plans and a merged-then-reverted PR (#1254 -> #1268) the last time it went unchallenged.
**Status (2026-08-24):** ✅ Implemented — fix shipped in PR #1949 (`retire-rebase-prs-workflow`): merge-queue clause replaced with real authority (required PR checks `Tests and Analysis`, `E2E Tests`, plus the `human-signoff` gate); `STRATEGIC_PRIORITIES.md` stale-premise correction included. Merged 2026-08-24.

### 9.1 scp trailing-slash causes `dist/` to nest inside existing remote `dist/`
*(discovered 2026-09-01 during #2044)*
**Location:** `.github/workflows/main.yml` "Deploy IBLbot dist to server" step (fixed this pass).
**Problem:** A trailing slash on the scp source path (`dist/`) combined with a target that names an existing remote directory (`…:www/ibl5/IBLbot/dist/`) causes scp to place the source contents *inside* the already-existing directory — silently deploying into a nested `dist/dist/` rather than replacing `dist/`. Stale code stays live.
**Occurrence scan:** `grep -n 'scp' .github/workflows/main.yml` at the time of the audit returned two lines:
- Line 90: CSS deploy — targets a full file path (not a directory); not affected.
- Line 125 (now fixed): IBLbot `dist/` — was the live occurrence.
**Fix applied:** Removed trailing slash from source (`dist/` → `dist`); changed target from `…:www/ibl5/IBLbot/dist/` to `…:www/ibl5/IBLbot/` so scp places `dist` inside it correctly.
**Prevention ladder:**
- Rung 0: no existing gate covers scp path semantics.
- Rung 1: no existing gate to extend.
- Rung 2 (warranted): a `scp-trailing-slash` rule doc under `.claude/rules/` warning that a trailing slash on the scp source copies *contents into* the target rather than placing the named directory — cheap, plan-reviewable, zero gate overhead.
- Rungs 3-5: not warranted (meta-tooling-bar conditions don't hold for a naming-convention rule).
**Landing rung:** 2. **Artifact owed:** `scp-trailing-slash.md` written in `.claude/rules/`.
**Status (2026-09-04):** ✅ Implemented — `scp-trailing-slash.md` rule doc written in `.claude/rules/`.

### 9.2 PR body `## Manual Testing` falsely claimed test coverage
*(discovered 2026-09-01 during #2044)*
**Location:** PR #2044 body `## Manual Testing` section (corrected this pass); also `## Summary` "before any SSH or scp step executes" (corrected to "before the IBLbot scp and deploy steps execute").
**Problem:** Hand-authored PR body text that contradicted the diff — claiming "all changes are covered by unit and E2E tests" when the change is static/structural (no unit or E2E tests exist for it), and overstating blast-radius isolation (6 SSH steps run before `Build IBLbot`, so a build failure does not precede *all* SSH steps).
**Occurrence scan:** PR body claims are ephemeral; scan not applicable — checked the one PR in scope.
**Prevention ladder:**
- Rung 0: no existing gate.
- Rung 1: no existing gate to extend.
- Rung 2 (warranted): a `pr-body-test-claim` rule doc under `.claude/rules/` specifying that `## Manual Testing` must match the plan's Verification Matrix typing — if the matrix has no test rows, the body must say "verification is static" rather than "covered by tests".
- Rungs 3-5: not warranted.
**Landing rung:** 2. **Artifact owed:** `pr-body-test-claim.md` written in `.claude/rules/`.
**Status (2026-09-04):** ✅ Implemented — `pr-body-test-claim.md` rule doc written in `.claude/rules/`.
