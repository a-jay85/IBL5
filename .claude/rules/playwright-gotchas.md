---
description: Playwright E2E gotchas: CSRF/session isolation, hx-boost waits, PRG races, hidden-element fill, axe a11y, URL-flip blast radius, global-table ownership, seed/test-state traps, CI shard load.
paths: ibl5/tests/e2e/**/*.ts
last_verified: 2026-09-16
---

# Playwright E2E Gotchas

Hard-won pitfalls, each one broke a real PR. Core rules and templates: `playwright-tests.md`. When one of these bites again, extend the entry here rather than re-learning it.

## Sync & navigation

- **`request.post()` and `page` are different PHP sessions.** A CSRF token read from the page is rejected by `request.post()` (the standalone `request` fixture has no shared cookies or session state with the page). Submit CSRF-protected forms via `button.click()`; tamper hidden inputs with `evaluate()` if you need a different payload. Never hand a page token to `request.post()`.
- **Forms inside `hx-boost` never navigate.** `themes/IBL/theme.php` wraps `#site-content` in `hx-boost="true"`, so `waitForLoadState('load')` resolves against the pre-click DOM and stale counts pass. Use `helpers/htmx-swap.ts` `assertHtmxSwap()` for `op=api` swaps, or wrap the click in `Promise.all([page.waitForResponse(r => r.url().includes('<Module>') && r.request().method() === 'POST'), locator.click()])` then `expect.poll` the changed value.
- **`waitForURL(pattern)` after a submit-click can match the STALE pre-click URL.** In PRG (post-redirect-get) flows the `beforeEach` URL often already matches the pattern, so the wait returns before the redirect lands and a one-shot read (`getAttribute`) reads the OLD document. Failed 100% reproducibly in `depth-chart-entry-submission.spec.ts`. Fix: start the wait before the click (`Promise.all([page.waitForURL(p), locator.click()])`), never one-shot read after it (use `expect(async () => {...}).toPass()` or a destination-only assertion), and `waitForLoadState('networkidle')` before navigating away — otherwise the in-flight redirect's own AJAX dies with `net::ERR_ABORTED`.
- **Serial `describe` blocks hide failures.** Once one test fails, the rest of the block is skipped, so CI's "1 failed" is a lower bound. Fix the first, re-run, expect the next to surface.

## Selectors

- **Nav login forms render on every page**, including anonymous ones, each with its own `input[name="_csrf_token"]` and `button[type="submit"]`. Bare selectors hit 3+ elements and trip strict mode. Scope to the content form: `page.locator('form:has(input[name="user_email"]) button[type="submit"]')`.
- **`form[action*="op=mark"]` also matches `op=mark_all`.** With sibling op values where one prefixes the other, `.first()` follows DOM order and can click the wrong form. Use ends-with `form[action$="op=mark"]` or a class-scoped selector.
- **Nav dropdowns carry standings text.** The hidden "Season" menu includes team names and "Eastern Conference"/"Western Conference", so `body.textContent()` negative assertions fail on Olympics pages. Scope absence checks to the content element (`.ibl-data-table`, the module's class), never `body`.
- **`assertNoPhpErrors` false-positives on `.ibl-alert--warning` pages.** It substring-matches `Warning:`. On pages that legitimately render a warning alert, check only fatal markers instead: `not.toContain('Fatal error')` and `not.toContain('Stack trace:')`, plus a module-specific render assertion.

## Auth & state

- **Loop→standalone conversions: no content assertions on auth-gated pages.** A `publicStorageState()` test for a module that 302s anonymous users should only `goto` + `assertNoPhpErrors`. Content assertions belong in the authenticated spec where the module renders.
- **User-team dependence.** Local `.env.test` `IBL_TEST_USER` may own a different team than CI's `testadmin`, so "form must be visible" passes in CI and fails locally. The one allowlisted skip: `contract-extension-submission.spec.ts::readExtensionForm` returns `null` and the test skips with the reason. Prefer seeding ownership over adding another.
- **Cookie `appState` vs DB `setState`.** The `_test_overrides` cookie is read by page middleware only. Code that reads `ibl_settings` through a repository (e.g. `updateAllTheThings.php` via `getSetting()`) never sees it. That is the single case for `setState()` from `helpers/test-state.ts`: restore in `afterEach`, and keep such tests out of parallel-shared state. Everywhere else the `appState` fixture rule in `playwright-tests.md` stands.

## Seed & test-state traps

- **FA quick offers: MLE/LLE bypass the soft cap, a max contract does not.** `FreeAgencyCapCalculator` counts pending FA offers as committed salary. `test-state.php?action=reset-fa-offers` seeds pids 10/11/12 all on Metros, leaving ~278 of soft space, so a max offer (needs ~1275) is rejected with no `offer_success` redirect and the test times out. Seed only the pid under test: `reset-fa-offers&pid=N`.
- **`reset-trade-offers` only UPDATEs the IDOR-reserved rows.** If offers 7/8 were deleted mid-run the UPDATE is a no-op, `getTradesByOfferId(7)` is empty, and `counterTradeOffer()` redirects `result=already_processed` before the IDOR check. If an IDOR test in `flows/trading-submission.spec.ts` sees `already_processed` instead of `error=`, re-seed first: `bin/wt-down <slug> && bin/wt-up <slug> --seed`.
- **Local seed ≠ CI seed.** `bin/wt-up --seed` and `bin/db-test-up` load a local seed that differs from `fixtures/ci-seed.sql` in schedule rows and power rankings. Some tests only pass in CI (Team Schedule playoff labels, home/visitor).

## CI & environment

- **Same-shard slow URLs need coordinated timeouts.** Two specs in one shard hitting `modules.php?name=Schedule` double the concurrent load, so adding tests to one makes the other time out. `grep -r "modules.php?name=<Module>" ibl5/tests/e2e/` for siblings, set the same `test.setTimeout(60000)` in each with a comment naming the other file, and use `gotoWithRetry` plus a container `toBeVisible()` before axe or assertions.
- **"New regression or pre-existing?"** Before investigating, run the failing spec from the main checkout's `ibl5/` directory with `BASE_URL` pointed at the master Docker stack's hostname (`bun run test:e2e -- --project=chromium tests/e2e/flows/<spec>.spec.ts`). If it fails there too, it is not your regression.
- **Sub-agents cannot run E2E.** A spawned agent gets its own worktree without the task worktree's Docker stack or `.env.test`, so auth setup fails on missing `IBL_TEST_USER`/`IBL_TEST_PASS`. Run `bin/wt-up --seed` and `bin/e2e-wt` as direct Bash calls from the parent session.

## Forms — hidden elements & fill()

- **`fill()`, `selectOption()`, `.check()` enforce visibility.** `display:none` or `visibility:hidden` on a form element causes "Element is not visible" after `actionTimeout` — not a silent skip. Before hiding a form element, grep specs for every `fill|selectOption|check|click` that targets it and rewrite each to drive the visible wrapper (stepper, toggle, slider) instead.
- **CSS `input` selector includes `type="hidden"`.** `input, select, textarea` matches ALL inputs including hidden; `.first().toBeVisible()` can fail on the wrong element. Use `input:not([type="hidden"]), select, textarea` in visibility assertions. Forms with ONLY hidden inputs + `<button>` have no visible input at all — assert on the `<button>` instead.
- **`fill()` silently truncates at `maxlength`.** No exception or warning. Check the View for `maxlength` before sizing test passwords (e.g., login form has `maxlength="20"`).

## Multi-token CSRF & Discord CI trap

- **Admin pages render a DebugMenu form before the module form.** `PageLayout::header()` emits a per-action CSRF token in the debug chrome; a bare first-token grep grabs the wrong one. Scope `fetchToken` to the form by name — slice body at `name="<formName>"` before matching `_csrf_token`. Standalone pages (single token) are safe.
- **Discord webhook → HTTP 400 in CI.** `config/discord.config.example.php` (copied verbatim by CI) has placeholder URLs; `sendCurlPOST` throws on non-2xx (no-op only under `isPhpUnit()`). Fix at config layer: drop the `testing` key from the example so callers get `$webhooks['testing'] ?? null` → null → no-op. Code-level try/catch guards trip the 100% Infection MSI gate on changed lines in `classes/`.

## Shared global-table ownership

- **One `mode:'serial'` spec owns each global DB table.** Adding any spec file can newly co-locate two global-table mutators in one shard → race under `fullyParallel`. `bin/check-e2e-fa-offers-owner` (E2E Hygiene Gate) enforces a single serial owner for FA-offer mutation. Validate with per-shard `--list` check, not `--repeat-each`.
- **Never share `auth-isolated` fixture between a mutating spec and a state-assertion spec.** Real form submissions leave DB rows mutated; a spec asserting "all-clear" on the same team then fails. Add a dedicated isolated team to `ibl5/tests/e2e/fixtures/ci-seed.sql` with a matching `auth-<purpose>.ts` fixture.

## Seed grounding & blast radius

- **`--grep` skips the setup project → DB never seeds → 500.** `--grep` filters out setup dependencies. Scope by project + path instead: `bin/e2e-wt <slug> --project=mutators ibl5/tests/e2e/<spec>.spec.ts`.
- **"0 failed" proves nothing unless the spec appears under `passed` by name.** A large `did not run` bucket hides specs that never executed.
- **URL-flip blast radius needs a TypeScript grep.** E2E specs use CSS attribute selectors (`[href*="boxscore"]`); a PHP-only grep misses them entirely. Add: `grep -rn 'href\*=' ibl5/tests/e2e/` alongside the PHP grep when auditing a URL format change.
- **`ON DUPLICATE KEY UPDATE` must reset ALL mutable state columns.** If a seed row has a nullable state column (`read_at`, `completed_at`) and the UPDATE clause only resets immutable columns, a test-mutated value survives reseed and fails the second run.

## Accessibility (axe)

- **`empty-table-header` requires visible text, not `aria-label`.** axe-core's rule checks `has-visible-text` only; `aria-label` on an empty `<th>` does NOT satisfy it. Use `<span class="sr-only">Label</span>` inside the `<th>`. `.sr-only` is in `ibl5/design/base.css`.
- **`sorttable.js` adds `role="columnheader"` to every `<th>`**, so all headers in sortable tables trigger the rule even without explicit role attributes.
- **Heading element promotions (`h3`→`h2`) can silently pick up compound selectors.** Run `grep -rn 'h[0-9]\.ibl-title' ibl5/design/` before any heading promotion — if the TARGET element has a compound `h2.ibl-title` selector but the source does not, the promotion is not VR-neutral. Use `aria-level="2"` on `h3` instead.
