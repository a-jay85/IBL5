---
description: Playwright E2E testing rules, Docker requirements, and actionability pitfalls.
paths: ibl5/tests/e2e/**/*.ts
last_verified: 2026-04-11
---

# Playwright E2E Testing Rules

## Commands

```bash
# Run all E2E tests
cd ibl5 && bun run test:e2e

# Run with visible browser
cd ibl5 && bun run test:e2e:headed

# Run interactive UI mode
cd ibl5 && bun run test:e2e:ui

# Run specific test file
cd ibl5 && bunx playwright test tests/e2e/smoke/public-pages.spec.ts
```

## Debugging Flaky Tests

When tests fail intermittently (race conditions, resource contention, parallel worker conflicts), re-run with a single worker to isolate the issue:

```bash
cd ibl5 && bun run test:e2e:serial
```

This runs with `--workers=1 --retries=2` (CI-matching retries, single worker). Use this to determine whether a failure is a genuine bug or a parallelism artifact before investigating further.

## Prerequisites

- **Docker must be running** — E2E tests hit the local server (`http://main.localhost/ibl5/`), unlike PHPUnit tests which use mocks
- **`.env.test` must exist** with valid credentials — copy from `.env.test.example`
- **CSS must be rebuilt after branch switches** — `css:watch` may not detect source changes from `git checkout`. Run `bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css` if tests depend on CSS from another branch

## Test Categories

| Category | Directory | Purpose | Auth |
|----------|-----------|---------|------|
| Smoke | `smoke/` | Verify pages load and render key elements | Public or authenticated |
| Flow | `flows/` | Test multi-step user interactions | Authenticated |

**When to use each:**
- **Smoke** — adding a new page, refactoring a module's View, verifying no PHP errors after changes
- **Flow** — testing interactive features (trading, depth chart entry, free agency bidding)

## File Structure Templates

### Public smoke test
```typescript
import { test, expect } from '@playwright/test';

// Public pages — no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

test.describe('Module smoke tests', () => {
  test('page loads', async ({ page }) => {
    await page.goto('modules.php?name=ModuleName');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=ModuleName');
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" found`).not.toContain(pattern);
    }
  });
});
```

### Authenticated smoke test
```typescript
import { test, expect } from '../fixtures/auth';

// Authenticated page smoke tests — these use stored auth state.
test.describe('Module auth smoke tests', () => {
  test('protected page loads', async ({ page }) => {
    await page.goto('modules.php?name=ProtectedModule');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });
});
```

### Flow test with state control
```typescript
import { test, expect } from '../fixtures/auth';

test.describe('Module flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    // Set the state this test needs — automatically restored after each test
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Module');
  });

  test('interaction works', async ({ page }) => {
    // Test steps...
  });
});
```

### Public test with cookie-based state control
```typescript
import { test, expect } from '../fixtures/public';

test.describe('Public module flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Module');
  });

  test('page loads', async ({ page }) => {
    // Test steps...
  });
});
```

**WARNING:** Never use `setState()` from `helpers/test-state` in tests — it modifies the database directly and races with parallel workers. The `public` fixture's `appState` uses per-request cookies instead.

## Auth Patterns

| Scenario | Import from | Extra setup |
|----------|-------------|-------------|
| Public page (no state control) | `@playwright/test` | `test.use({ storageState: { cookies: [], origins: [] } })` |
| Public page (with state control) | `../fixtures/public` | None (uses cookie-based appState) |
| Authenticated page | `../fixtures/auth` | None (uses stored auth state + cookie-based appState) |

**Never** call the login flow inside a test — always use the auth fixture. The `auth.setup.ts` project runs first and saves browser state to `playwright/.auth/user.json`.

## PHP Error Detection (Mandatory)

Every smoke test file **must** check for PHP errors on the pages it visits. Use this pattern:

```typescript
const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

// In a test:
const body = await page.locator('body').textContent();
for (const pattern of PHP_ERROR_PATTERNS) {
  expect(body, `PHP error "${pattern}" found on ${url}`).not.toContain(pattern);
}
```

A dedicated "no PHP errors" test (like in `public-pages.spec.ts`) is preferred — it loops through all URLs the file covers.

## Locator Best Practices

**Preference order** (most stable to least):
1. Accessible roles: `page.getByRole('button', { name: /display/i })`
2. Text content: `page.getByText('Trading')`
3. Labels: `page.getByLabel('Username')`
4. Test IDs: `page.getByTestId('trade-form')` (if added to HTML)
5. Semantic CSS classes: `page.locator('.ibl-data-table')`
6. Structural selectors: `page.locator('div > table:nth-child(2)')` (avoid)

**Stable IBL5 CSS classes safe for locators:**
- `.ibl-title` — page title headings
- `.ibl-data-table` — data tables
- `.ibl-card` — card components
- `.trading-team-select` — trading team selection table
- `.trading-roster` — trading roster tables
- `.trade-offer-card` — trade offer cards
- `.player-stats-card` — player stats card

**Avoid:**
- Fragile structural selectors (`table:nth-child(3) tr:first-child td`)
- Layout-dependent selectors that break on responsive changes
- Selectors tied to PHP-Nuke legacy markup (table-based layout wrappers)

## Assertion Patterns

**Prefer web-first assertions** — they auto-retry until the condition is met or timeout is reached. Manual `expect(await loc.count())` does NOT retry and causes flaky tests when elements haven't rendered yet.

```typescript
// Page loads with expected title
await expect(page).toHaveTitle(/IBL/i);

// Element is visible (auto-retries)
await expect(page.locator('.ibl-data-table').first()).toBeVisible();

// Element exists in DOM but isn't visible (<option>, <datalist>, hidden inputs)
await expect(page.locator('select option').first()).toBeAttached();

// Element contains text
await expect(page.locator('.ibl-title')).toContainText(/standings/i);

// Element is NOT visible (e.g., login prompt when authenticated)
await expect(page.getByText('Sign In')).not.toBeVisible();

// Exact element count (auto-retries)
await expect(page.locator('.trading-roster')).toHaveCount(2);

// At least one element exists (auto-retries) — use instead of manual count > 0
await expect(page.locator('input[type="checkbox"]').first()).toBeVisible();

// At least N elements (no web-first equivalent — manual count is OK here)
expect(await page.locator('option').count()).toBeGreaterThanOrEqual(28);

// No PHP errors in page body
expect(body, `PHP error on ${url}`).not.toContain('Fatal error');
```

**Anti-patterns to avoid:**
```typescript
// ❌ No auto-retry — flaky in slow CI
expect(await loc.count()).toBeGreaterThan(0);
expect(await loc.count()).toBe(1);

// ✅ Auto-retries until timeout
await expect(loc.first()).toBeVisible();
await expect(loc).toHaveCount(1);

// ❌ <option> elements are never "visible"
await expect(selectLocator.locator('option').first()).toBeVisible();

// ✅ Use toBeAttached() for DOM-only elements
await expect(selectLocator.locator('option').first()).toBeAttached();
```

## State Control for Season-Phase-Dependent Tests

Tests that depend on application state (season phase, trading open/closed, trivia mode, etc.) should **set the state they need** rather than detecting and skipping. This uses the `test-state.php` endpoint (gated by `E2E_TESTING=1`).

**Authenticated tests** — use the `appState` fixture from `../fixtures/auth`:
```typescript
// appState sets settings and auto-restores after each test
test.beforeEach(async ({ appState, page }) => {
  await appState({ 'Current Season Phase': 'Free Agency' });
  await page.goto('modules.php?name=FreeAgency');
});
```

**Public tests** — use the `public` fixture with cookie-based `appState` (same auto-restore, no DB races):
```typescript
import { test, expect } from '../fixtures/public';

test.describe('Public module flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Module');
  });
});
```

**WARNING:** Never use `setState()` from `helpers/test-state` in tests — it modifies the database directly and races with parallel workers.

**Allowlisted settings:** `Current Season Phase`, `Current Season Ending Year`, `Allow Trades`, `Allow Waiver Moves`, `Show Draft Link`, `Trivia Mode`, `ASG Voting`, `EOY Voting`, `Free Agency Notifications`.

**Serial mode:** Prefer splitting a spec file into read-only (`smoke/` or `flows/`) and submission (`flows/*-submission.spec.ts`) files rather than applying file-level `test.describe.configure({ mode: 'serial' })`. Use serial mode only within a single `describe` block where tests genuinely share state (e.g., a multi-step submission flow). See `voting.spec.ts` / `voting-submission.spec.ts` split as the canonical example.

## DO:
1. Check for PHP errors on every page you visit in smoke tests
2. Use the auth fixture for authenticated tests
3. Use `storageState: { cookies: [], origins: [] }` for public tests
4. Use `appState` fixture (from `../fixtures/auth` or `../fixtures/public`) to set required state — always include `'Current Season Ending Year': '2026'` when tests depend on CI seed data
5. Use stable CSS classes or accessible roles for locators
6. Keep smoke tests fast — one assertion per test, no complex interactions
7. Add new pages to the PHP error check loop when adding smoke tests
8. Register `page.route()` mocks **before** `page.goto()` or any navigation — routes only intercept requests made after registration

## DON'T:
1. **Don't** call login inside tests — use the auth fixture
2. **Don't** skip tests due to season phase — use `appState` (from fixtures) to set the state you need. Never use `setState()` directly — it races with parallel workers
3. **Don't** use `.only` — it will fail in CI (`forbidOnly: true`)
4. **Don't** use fragile structural selectors
5. **Don't** mutate production data (create trades, submit forms) without cleanup
6. **Don't** assume Docker is running — tests will fail with connection errors if it's not
7. **Don't** import from `@playwright/test` for authenticated tests — import from `../fixtures/auth`
8. **Don't** use `toBeVisible()` or `toHaveText()` on locators that match multiple elements — Playwright strict mode throws. Use `.first()`, `.nth(n)`, or check `.count()` instead
9. **Don't** use `boundingBox()` to verify CSS properties like `width: fit-content` — parent layout context affects the bounding box. Use `page.evaluate(() => getComputedStyle(el).property)` to check computed CSS values directly
10. **Don't** import `Page` type from `../fixtures/auth` — it only exports `test` and `expect`. Import `Page` separately: `import type { Page } from '@playwright/test'`
11. **Don't** use `link.click()` for page-to-page navigation — it triggers a Playwright-managed navigation wait that can time out under concurrent load from parallel workers. Instead, extract the href with `getAttribute('href')` and use `page.goto(href)`, which handles navigation more reliably
12. **Don't** use `test.skip()` — set prerequisites via `appState` + CI seed instead
13. **Don't** use `.catch(() => false)` to swallow visibility errors — use `await expect().toBeVisible()` with a timeout (exception: `phase-gating-public.spec.ts` where testing element absence is the purpose)
14. **Don't** use bare `return` without a preceding assertion — every code path must assert something
15. **Don't** write dual-path `if/else` inside tests — split into separate focused tests with explicit `appState` prerequisites
16. **Don't** write `if (count > 0) { assert }` with no else — the test silently passes when the element is absent. Use a hard assertion instead

## Mandatory: No Skips, No Silent Passes

Every E2E test must either **pass with a real assertion** or **fail loudly**. No `test.skip()`. No bare `return`. No `.catch(() => false)` + early exit. No `if (count > 0) { assert }` without an else.

**Set prerequisites, don't detect state.** Use `appState` (from `../fixtures/auth` or `../fixtures/public`) to set `Current Season Phase` and `Current Season Ending Year`. CI seed data (`ci-seed.sql`) provides all test data for year 2026. Tests that need specific data should set `'Current Season Ending Year': '2026'` so the app resolves CI-seed players, schedule, and settings.

```typescript
// CORRECT — each test has one purpose, one setup, one assertion path
test('feature X works', async ({ appState, page }) => {
  await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
  await page.goto('modules.php?name=Module');
  await expect(page.locator('.feature-x')).toBeVisible();
});

// BANNED — test.skip hides failures
if (count === 0) { test.skip(true, 'No data'); return; }

// BANNED — .catch(() => false) swallows errors
if (!(await form.isVisible().catch(() => false))) return;

// BANNED — bare return without assertion
if (count === 0) return;

// BANNED — dual-path if/else in one test
if (count > 0) { assertA(); } else { assertB(); }

// BANNED — true-only guard (silently passes when absent)
if (count > 0) { await expect(el).toBeVisible(); }
```

## Shared Helpers

- **`helpers/navigation.ts`**: `gotoWithRetry(page, url)` — retries up to 5 times with back-off when PHP's built-in server returns blank pages under parallel load. Use instead of bare `page.goto()` in parallel-load-sensitive tests (e.g., mobile tests).
- **`smoke/htmx.spec.ts`**: Nav marker pattern — set `data-htmx-marker` on `nav.fixed` before an action, then check if the attribute persists to verify HTMX swap (no full reload). Only works for inline-rendering forms (search, depth chart), not forms that redirect via `HX-Redirect`.

## CI Workflow Notes

The E2E tests run in GitHub Actions via `.github/workflows/e2e-tests.yml`. Key details:

- **PHP built-in server** with `ibl5/router.php` replaces Apache. The router handles the single `api/v1/*` rewrite rule; returns `false` for everything else.
- **Seed data** lives in `ibl5/tests/e2e/fixtures/ci-seed.sql`. When adding new E2E tests that require additional data (new tables, new rows), update this file.
- **Bcrypt hashes contain `$` characters** that bash expands in unquoted heredocs (`<<EOF`). When writing CI steps that handle bcrypt hashes, use either: (a) single-quoted heredocs (`<<'EOF'`) with `getenv()` in PHP, or (b) pipe from PHP directly. Never store a bcrypt hash in a shell variable and use it in an unquoted heredoc.
- **Secrets required:** `IBL_TEST_USER` and `IBL_TEST_PASS` must be configured as GitHub repository secrets.

## Worktree & Environment Gotchas

- **`bin/e2e-wt.sh <name>` runs Playwright from the worktree's `ibl5/` dir** — test files and `BASE_URL` both resolve to the worktree, so TS test changes are picked up without any extra steps.
- **Rebuild CSS after switching branches.** `css:watch` may not detect source file changes from `git checkout`. Run `bunx @tailwindcss/cli -i design/input.css -o themes/IBL/style/style.css` after switching.
- **E2E tests that submit login/registration forms can trigger auth throttling.** The `auth_users_throttling` table accumulates failed attempts. If `auth.setup.ts` fails with "Too many login attempts", clear: `DELETE FROM auth_users_throttling WHERE 1=1;`. CI is unaffected (fresh DB per run).

## Completion Criteria

Before considering an E2E test task complete:

1. **Run the full E2E suite**: `cd ibl5 && bun run test:e2e`
2. **Verify all tests pass** — no skips, no silent passes
3. **No `.only`** in any spec file
4. **PHP error coverage** — every smoke file includes PHP error pattern checks
5. **Correct auth pattern** — public tests use empty storageState, authenticated tests import from fixtures/auth
