---
description: Playwright E2E testing rules, Docker requirements, and actionability pitfalls.
paths: ibl5/tests/e2e/**/*.ts
last_verified: 2026-06-29
---

# Playwright E2E Testing Rules

## Commands

```bash
cd ibl5 && bun run test:e2e          # all tests
cd ibl5 && bun run test:e2e:headed   # visible browser
cd ibl5 && bun run test:e2e:ui       # interactive UI mode
cd ibl5 && bunx playwright test tests/e2e/smoke/public-pages.spec.ts   # one file
cd ibl5 && bun run test:e2e:serial   # --workers=1 --retries=2, to isolate flakes
```

Use `test:e2e:serial` to decide whether an intermittent failure is a genuine bug or a parallelism artifact **before** investigating further.

## Prerequisites (before any E2E work)

1. **Docker running:** `docker compose ps` — if down, `docker compose up -d`.
2. **`.env.test` exists** with valid credentials — copy from `.env.test.example` if missing.
3. **Rebuild CSS after a branch switch:** `css:watch` may miss source changes from `git checkout`. Run `bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css`. Per `.claude/rules/css-auto-rebuild.md` this is the sanctioned recovery exception to the no-manual-build rule, not a routine step.

## Test Categories

| Category | Directory | Purpose | When |
|----------|-----------|---------|------|
| Smoke | `smoke/` | Pages load, key elements render, no PHP errors | New page, View refactor, post-change error check |
| Flow | `flows/` | Multi-step interactions (auth) | Trading, depth chart, free-agency bidding |

## File Structure Templates

```typescript
// PUBLIC SMOKE — no auth
import { test, expect } from '@playwright/test';
import { publicStorageState } from '../helpers/public-storage-state';
test.use({ storageState: publicStorageState() });   // prevents DevAutoLogin

test('page loads', async ({ page }) => {
  await page.goto('modules.php?name=ModuleName');
  await expect(page.locator('.ibl-title').first()).toBeVisible();
});

// AUTHENTICATED SMOKE — stored auth state
import { test, expect } from '../fixtures/auth';
test('protected page loads', async ({ page }) => {
  await page.goto('modules.php?name=ProtectedModule');
  await expect(page.getByText('Sign In')).not.toBeVisible();
  await expect(page.locator('.ibl-data-table').first()).toBeVisible();
});

// FLOW with state control — appState auto-restores after each test
import { test, expect } from '../fixtures/auth';        // or '../fixtures/public' for public+cookie state
test.beforeEach(async ({ appState, page }) => {
  await appState({ 'Allow Trades': 'Yes' });
  await page.goto('modules.php?name=Module');
});
```

## Auth Patterns

| Scenario | Import from | Extra setup |
|----------|-------------|-------------|
| Public, no state control | `@playwright/test` | `test.use({ storageState: publicStorageState() })` (from `../helpers/public-storage-state`) |
| Public, with state control | `../fixtures/public` | None (cookie-based `appState`) |
| Authenticated | `../fixtures/auth` | None (stored auth state + cookie `appState`) |

**Never** call the login flow inside a test — use the auth fixture. `auth.setup.ts` runs first and saves state to `playwright/.auth/user.json`.

### Shared server session — never mutate it; test the isolation invariant

All authenticated workers share ONE server-side PHP session (the pinned PHPSESSID in `playwright/.auth/user.json`). Per-context cookie jars are isolated; **the server session is not — anything an authenticated test writes into `$_SESSION` leaks into every other concurrent authenticated test.** This is why the auth fixture forbids logout tests, and what broke PR #878 (a test switching `?league=olympics` wrote `$_SESSION['current_league']`, flipping every parallel test into Olympics context).

For any **global mode/context switch** (league, impersonation, admin-mode, feature flag) read by shared infrastructure:

1. **Production code** persists the switch per-request (cookie + in-memory), never in `$_SESSION`. See `League\LeagueContext::setLeague()`.
2. **Tests** cover the *negative invariant*: open a second context sharing the same storageState and assert the switch in context A does NOT change what context B resolves. Canonical: `tests/e2e/flows/league-context-isolation.spec.ts`. Assert against the RAW server response (`context.request.get(...)`) when the signal lives in markup Alpine/HTMX strips from the live DOM.

## PHP Error Detection (Mandatory)

Every smoke test **must** check for PHP errors on the pages it visits:

```typescript
const PHP_ERROR_PATTERNS = ['Fatal error', 'Warning:', 'Parse error', 'Uncaught', 'Stack trace:'];
const body = await page.locator('body').textContent();
for (const pattern of PHP_ERROR_PATTERNS) {
  expect(body, `PHP error "${pattern}" found on ${url}`).not.toContain(pattern);
}
```

A dedicated "no PHP errors" test that loops over all the file's URLs (like `public-pages.spec.ts`) is preferred.

## Locator Best Practices

**Preference order** (stable → fragile): `getByRole` → `getByText` → `getByLabel` → `getByTestId` → semantic CSS class (`.ibl-data-table`) → structural selectors (avoid).

**Stable IBL5 classes:** `.ibl-title`, `.ibl-data-table`, `.ibl-card`, `.trading-team-select`, `.trading-roster`, `.trade-offer-card`, `.player-stats-card`.

**Avoid:** fragile structural selectors (`table:nth-child(3) tr:first-child td`), layout-dependent selectors that break on responsive changes, PHP-Nuke legacy table-layout wrappers.

## Assertion Patterns

**Prefer web-first assertions** — they auto-retry until the condition holds or times out. Manual `expect(await loc.count())` does NOT retry → flaky when elements haven't rendered.

```typescript
await expect(page).toHaveTitle(/IBL/i);
await expect(page.locator('.ibl-data-table').first()).toBeVisible();
await expect(page.locator('select option').first()).toBeAttached();   // DOM-only: <option>, <datalist>, hidden inputs
await expect(page.locator('.ibl-title')).toContainText(/standings/i);
await expect(page.getByText('Sign In')).not.toBeVisible();
await expect(page.locator('.trading-roster')).toHaveCount(2);
expect(await page.locator('option').count()).toBeGreaterThanOrEqual(28);   // "at least N" has no web-first form — manual OK
```

**Anti-patterns:**
```typescript
expect(await loc.count()).toBeGreaterThan(0);   // ❌ no retry → flaky in slow CI; use await expect(loc.first()).toBeVisible()
expect(await loc.count()).toBe(1);              // ❌ use await expect(loc).toHaveCount(1)
await expect(sel.locator('option').first()).toBeVisible();   // ❌ <option> never "visible"; use .toBeAttached()
```

## State Control for Phase-Dependent Tests

Tests depending on app state (season phase, trading open, trivia mode…) **set the state they need** rather than detecting-and-skipping, via the `test-state.php` endpoint (gated by `E2E_TESTING=1`). Authenticated → `appState` from `../fixtures/auth`; public → `appState` from `../fixtures/public` (cookie-based, no DB races). Both auto-restore after each test.

**WARNING:** Never use `setState()` from `helpers/test-state` in a test — it writes the DB directly and races with parallel workers. Always use the `appState` fixture.

**Allowlisted settings:** `Current Season Phase`, `Current Season Ending Year`, `Allow Trades`, `Allow Waiver Moves`, `Show Draft Link`, `Trivia Mode`, `ASG Voting`, `EOY Voting`, `Free Agency Notifications`. Always include `'Current Season Ending Year': '2026'` when tests depend on CI seed data.

**Serial mode:** Prefer splitting a spec into read-only (`smoke/`/`flows/`) and submission (`flows/*-submission.spec.ts`) files over file-level `test.describe.configure({ mode: 'serial' })`. Use serial only within one `describe` where tests genuinely share state. Canonical: `voting.spec.ts` / `voting-submission.spec.ts`.

## DO

1. Check PHP errors on every page a smoke test visits; add new pages to the error-check loop.
2. Use the auth fixture for authenticated tests; `publicStorageState()` for public tests.
3. Use `appState` to set required state (include `'Current Season Ending Year': '2026'` for seed-dependent tests).
4. Use stable CSS classes or accessible roles for locators.
5. Keep smoke tests fast — one assertion per test, no complex interactions.
6. Register `page.route()` mocks **before** `page.goto()`/any navigation — routes only intercept requests made after registration.

## DON'T

1. **Don't** call login inside tests — use the auth fixture.
2. **Don't** skip tests due to season phase — use `appState`; never `setState()` (races with parallel workers).
3. **Don't** use fragile structural selectors — use roles/text/stable classes.
4. **Don't** mutate production data (trades, form submits) without cleanup — `appState` for reversible settings, or `afterEach`/`afterAll` for created data.
5. **Don't** import from `@playwright/test` for authenticated tests — import from `../fixtures/auth`.
6. **Don't** use `toBeVisible()`/`toHaveText()` on locators matching multiple elements — strict mode throws. Use `.first()`, `.nth(n)`, or `.count()`.
7. **Don't** use `boundingBox()` to verify CSS like `width: fit-content` — parent layout skews it. Use `page.evaluate(() => getComputedStyle(el).property)`.
8. **Don't** import `Page` from `../fixtures/auth` (it exports only `test`/`expect`) — `import type { Page } from '@playwright/test'`.
9. **Don't** use `link.click()` for page-to-page navigation — the managed nav-wait times out under parallel load. Extract `getAttribute('href')` and `page.goto(href)`.
10. **Don't** use `test.skip()` — set prerequisites via `appState` + CI seed.
11. **Don't** use `.catch(() => false)` to swallow visibility errors — use `await expect().toBeVisible()` with a timeout (exception: `phase-gating-public.spec.ts`, where absence IS the test).
12. **Don't** use bare `return` without a preceding assertion.
13. **Don't** write dual-path `if/else` inside a test — split into focused tests with explicit `appState` prerequisites.
14. **Don't** write `if (count > 0) { assert }` with no else — it silently passes when the element is absent. Use a hard assertion.

## Mandatory: No Skips, No Silent Passes

DON'Ts 10–14 (the most common anti-patterns) are mechanically enforced by `bin/check-e2e-hygiene` (CI: the `e2e-hygiene` check in `.github/workflows/pr-meta-checks.yml`, consolidated from the former `e2e-hygiene.yml`). Exceptions go in `.e2e-hygiene-skip-allowlist` (file-level) or inline `// e2e-hygiene-allow: <reason >= 20 chars>`. Banned forms:

```typescript
if (count === 0) { test.skip(true, 'No data'); return; }      // BANNED — hides failures
if (!(await form.isVisible().catch(() => false))) return;     // BANNED — swallows errors
if (count === 0) return;                                       // BANNED — bare return
if (count > 0) { assertA(); } else { assertB(); }             // BANNED — dual-path
if (count > 0) { await expect(el).toBeVisible(); }            // BANNED — true-only guard
```

## Shared Helpers

- **`helpers/navigation.ts`** `gotoWithRetry(page, url)` — retries up to 5× with back-off when PHP's built-in server returns blank pages under parallel load. Use instead of bare `page.goto()` in load-sensitive tests (e.g. mobile).
- **`smoke/htmx.spec.ts`** nav-marker pattern — set `data-htmx-marker` on `nav.fixed` before an action, check it persists to verify an HTMX swap (no full reload). Only works for inline-rendering forms (search, depth chart), not `HX-Redirect` forms.

## CI Workflow Notes

E2E runs in `.github/workflows/e2e-tests.yml`:

- **PHP built-in server** with `ibl5/router.php` replaces Apache (handles the single `api/v1/*` rewrite; returns `false` otherwise).
- **Seed data** in `ibl5/tests/e2e/fixtures/ci-seed.sql` — update when new tests need new tables/rows.
- **Bcrypt hashes contain `$`** that bash expands in unquoted heredocs. Use single-quoted heredocs (`<<'EOF'`) with `getenv()` in PHP, or pipe from PHP directly. Never put a bcrypt hash in a shell var used in an unquoted heredoc.
- **Secrets:** `IBL_TEST_USER`, `IBL_TEST_PASS` as repo secrets.

## Worktree & Environment Gotchas

- **`bin/e2e-wt.sh <name>`** runs Playwright from the worktree's `ibl5/` — test files and `BASE_URL` both resolve to the worktree, so TS changes are picked up with no extra steps.
- **Rebuild CSS after a branch switch** (see Prerequisites #3).
- **Login/registration tests can trip auth throttling** (`auth_users_throttling` accumulates failures). If `auth.setup.ts` fails with "Too many login attempts": `DELETE FROM auth_users_throttling WHERE 1=1;`. CI is unaffected (fresh DB per run).

## Completion Criteria

1. Run the full suite: `cd ibl5 && bun run test:e2e`.
2. All pass — no skips, no silent passes, no `.only`.
3. Every smoke file includes PHP error-pattern checks.
4. Public tests use empty storageState; authenticated tests import from `fixtures/auth`.

## Visual Regression Manifest

`ibl5/tests/e2e/vr-manifest.ts` is the single source of truth for VR coverage; `visual-regression.spec.ts` consumes it — never add rows to the spec directly.

**Filenames** are derived mechanically by `snapshotFilename()` (desktop suffix and `default` state are elided):
```
{name}.png · {name}-mobile.png · {name}-{state}.png · {name}-tab-{tab.key}.png
{name}-{state}-mobile.png · {name}-tab-{tab.key}-mobile.png
```

**Add a module:** one `VrRow` in `VR_MANIFEST` (set `viewports`/`states`/`htmxTabs`), then run with `--update-snapshots` to generate the baseline PNG.

**Coverage:** `bin/check-vr-coverage` reports rows missing dimensions; new gaps fail CI (exit 1), existing gaps in `ibl5/tests/e2e/vr-coverage-baseline.json` are advisory. `bin/check-vr-coverage --update-baseline` acknowledges current gaps.
