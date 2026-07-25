---
description: Retire the IBL6 SvelteKit frontend after porting its one page (the game boxscore) into IBL5 as a PHP module; the site stays server-rendered PHP with HTMX and there is no second frontend stack.
last_verified: 2026-07-24
---

# ADR-0095: Retire the IBL6 SvelteKit frontend; the site stays PHP-rendered with HTMX

**Status:** Accepted
**Date:** 2026-07-24

## Context

IBL6 was a SvelteKit application (root `IBL6/`) launched as the first step of a documented strategy (STRATEGIC_PRIORITIES priority #2) to incrementally replace IBL5's server-rendered PHP pages with a modern JavaScript frontend stack. In practice it shipped exactly one user-facing page — the game boxscore at `ibl6.iblhoops.net/{date}-game-{n}/boxscore` — served by a pm2-managed Node process on the production box, reading the same shared MariaDB as IBL5. Standing up that single page carried a disproportionate, permanent maintenance surface: a second language/runtime/build toolchain (Node, npm, Vite, svelte-check, vitest, Playwright), a dedicated Docker image (`docker/Dockerfile.ibl6`) and compose service, a GHCR image-publish workflow, IBL6-specific CI jobs (type-check/unit, visual-regression, E2E) wired into the e2e/tests aggregation gates, an on-box deploy pipeline (stop/build/scp/pm2-restart plus health-check recovery in `main.yml`), a notify-only production smoke path (`smoke-prod.yml`, `bin/smoke-prod --scope=ibl6`), and cross-references throughout the tooling (`bin/website-affecting`, `bin/playwright-image-tag`, config constants). Every one of those surfaces is code that needs upkeep and can carry its own bugs.

The forcing function: the boxscore page was cheaply re-implementable as a native PHP module inside IBL5 using the established Repository/Service/View module pattern (copying the SeasonHighs module) and the existing client-side table sorter (`ibl5/jslib/sorttable.js`), where HTMX already covers the interactivity IBL6 was reaching for. Reproducing that one page in-stack removed the only reason the entire second stack existed.

PR 1 (`feat:`, `ibl6-retirement-1-boxscore-php-port`) ported the boxscore to a PHP module (`modules.php?name=GameBoxscore&date=<YYYY-MM-DD>&game=<int>`) and flipped `BoxScoreUrlBuilder` to emit that internal URL, so IBL6 stopped receiving new-link traffic the moment PR 1 deployed. This decision (PR 2) records the resulting strategic reversal and decommissions IBL6.

## Decision

Abandon the "second frontend stack" strategy. The IBL5 site stays server-rendered PHP with HTMX for interactivity; there will be no parallel SvelteKit or JS-framework frontend. Concretely:

1. Delete the IBL6 application (`IBL6/`) and every piece of infrastructure that existed solely to build, ship, test, deploy, or smoke it: the `build-ibl6-image.yml` workflow; the IBL6 jobs, path filters, and `needs:` entries in `e2e-tests.yml`, `tests.yml`, `smoke-prod.yml`, and `main.yml` (aggregation job ids and names preserved, because branch protection keys on them); the IBL6 arm of the scheduled `npm-audit-fix.yml` sweep and the `/IBL6` npm entry in `dependabot.yml`; the `ibl6` service in `docker-compose.yml` and `docker-compose.ci.yml` and `docker/Dockerfile.ibl6`; the `--scope=ibl6` path in `bin/smoke-prod`; the pm2 watchdog `ibl5/bin/ibl6-healthcheck` (example); and the IBL6 references in `bin/website-affecting` / `bin/playwright-image-tag` and their tests.
2. Remove the now-dead `IBL6_BASE_URL` constant from `ibl5/config.php.example` (and from the untracked local/prod `ibl5/config.php`, which is gitignored and therefore outside this diff).
3. Rewrite STRATEGIC_PRIORITIES to record the PHP-plus-HTMX stance and drop the "replace PHP with SvelteKit" priority.
4. Preserve old inbound links: after this PR merges, the pm2 Node app on the box is replaced by a reverse-proxy 301 redirect mapping `https://ibl6.iblhoops.net/{date}-game-{n}/boxscore` to `https://iblhoops.net/ibl5/modules.php?name=GameBoxscore&date={date}&game={n}` (documented in OPERATIONS_RUNBOOK; executed by hand on the box, out of repo and CI reach).

This decision supersedes only the IBL6-specific *provisions* of four prior ADRs (0038, 0039, 0070, 0089); their IBL5 cores stand unchanged and none of those ADRs is rewritten. That narrowed relationship is enumerated under `## Lineage` below — deliberately not `## Supersedes`, which would make `bin/check-docs`'s bidirectional integrity check demand a (false) `Superseded by ADR-0095` status line on each of them.

## Alternatives Considered

- Keep IBL6 and continue the SvelteKit migration. Rejected: after roughly 1.4K LOC and a full CI/deploy/smoke apparatus, the stack delivered one page that PHP-plus-HTMX renders natively. The migration's premise (that PHP pages are a dead end) did not hold; the maintenance cost was ongoing and the upside had not materialized.
- Leave `IBL6/` in the tree but stop deploying it (freeze). Rejected: dead code that still trips CI path filters, Dependabot, and the aggregation gates keeps paying upkeep and can silently break — a freeze is the worst of both, cost without use.
- Serve a `410 Gone` for old `ibl6.iblhoops.net` boxscore links instead of a 301. Rejected (owner decision): the content still exists at the new PHP URL, so a permanent redirect preserves external and IBLbot-emitted links and search equity; `410` would strand them.
- Fold the decommission into PR 1. Rejected: PR 1 is a `feat:` that must merge and deploy (flipping live link generation) before the old stack can be safely torn down; splitting keeps PR 1's port reviewable and lets the teardown be verified against a deployed new URL.

## Consequences

- Positive: one language/runtime/build/test/deploy/smoke stack instead of two; the CI aggregation runs two fewer job families; Dependabot no longer manages a second `package.json`; the meta-tooling surface shrinks.
- Positive: the boxscore is now a first-class IBL5 module, consistent with every other page (Repository/Service/View, `sorttable.js`, HTMX), and covered by the same PHPUnit/E2E/visual-regression harness.
- Negative/risk: old `ibl6.iblhoops.net` links break the instant the pm2 app is torn down unless the reverse-proxy 301 is in place. Mitigated by making the redirect a mandatory OPERATIONS_RUNBOOK post-merge step (out of repo and CI reach), and by PR 1 having already repointed all in-app link generation so no new IBL6 links are produced.
- Neutral: the IBLbot Discord app retains its own `IBL6_BASE_URL`; any links it still emits resolve through the same box-level 301. Cleaning IBLbot is a separate follow-up, out of scope here.

## Lineage

This ADR supersedes the IBL6-specific provisions of four prior decisions **only**. Each of those ADRs keeps its IBL5 core in force and none is rewritten — the decision log is immutable record. Recorded here rather than under `## Supersedes` because none of the four is *wholly* superseded (`bin/check-docs:521`).

- **Narrows ADR-0070** (single-source the Playwright version): the IBL6-derivation provisions — IBL6 E2E jobs deriving the image tag from `IBL6/package.json`, and "IBL6 stays manually version-managed, intentionally outside Dependabot" — are moot, because those jobs no longer exist. ADR-0070's IBL5 derivation, the `bin/playwright-image-tag` helper, and the no-hardcoded-literal regression guard remain in force.
- **Narrows ADR-0038** (smoke inconclusive state): the IBL6 notify-only smoke path (a single check that "can never be inconclusive") is removed. ADR-0038's IBL5 inconclusive/rollback semantics stand.
- **Narrows ADR-0039** (smoke on-box loopback gate): the IBL6 on-box `/health` loopback smoke and its restart recovery are removed. ADR-0039's IBL5 on-box loopback prober stands.
- **Narrows ADR-0089** (scheduled npm-audit-fix): the sweep's IBL6 arm is removed — the workflow now covers `ibl5` and `ibl5/IBLbot` only. ADR-0089's schedule, PR-per-run mechanics, and IBL5/IBLbot coverage stand.
- **Completes the two-PR program opened by PR 1** (`ibl6-retirement-1-boxscore-php-port`, merged 2026-07-24): PR 1 ported the page, this PR removes the stack it came from.
