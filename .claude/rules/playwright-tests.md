---
paths: ibl5/tests/e2e/**/*.ts
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

### Public test with manual state control
```typescript
import { test, expect } from '@playwright/test';
import { setState, type Settings } from '../helpers/test-state';

test.use({ storageState: { cookies: [], origins: [] } });
test.describe.configure({ mode: 'serial' });

test.describe('Public module flow', () => {
  let restoreSettings: Settings;

  test.beforeEach(async ({ request, page }) => {
    const result = await setState(request, { 'Trivia Mode': 'Off' });
    restoreSettings = result.previous;
    await page.goto('modules.php?name=Module');
  });

  test.afterEach(async ({ request }) => {
    await setState(request, restoreSettings);
  });

  test('page loads', async ({ page }) => {
    // Test steps...
  });
});
```

## Auth Patterns

| Scenario | Import from | Extra setup |
|----------|-------------|-------------|
| Public page | `@playwright/test` | `test.use({ storageState: { cookies: [], origins: [] } })` |
| Authenticated page | `../fixtures/auth` | None (uses stored auth state) |

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

**Public tests** — use `setState` directly with manual restore:
```typescript
import { setState, type Settings } from '../helpers/test-state';

let restoreSettings: Settings;
test.beforeEach(async ({ request }) => {
  const result = await setState(request, { 'Trivia Mode': 'Off' });
  restoreSettings = result.previous;
});
test.afterEach(async ({ request }) => {
  await setState(request, restoreSettings);
});
```

**Allowlisted settings:** `Current Season Phase`, `Allow Trades`, `Allow Waiver Moves`, `Show Draft Link`, `Trivia Mode`, `ASG Voting`, `EOY Voting`, `Free Agency Notifications`.

**Serial mode:** When multiple `describe` blocks in the same file set the same setting to different values, use `test.describe.configure({ mode: 'serial' })` at the file level to prevent interleaving.

## DO:
1. Check for PHP errors on every page you visit in smoke tests
2. Use the auth fixture for authenticated tests
3. Use `storageState: { cookies: [], origins: [] }` for public tests
4. Use `appState` fixture (authenticated) or `setState` helper (public) to set required state
5. Use stable CSS classes or accessible roles for locators
6. Keep smoke tests fast — one assertion per test, no complex interactions
7. Add new pages to the PHP error check loop when adding smoke tests
8. Register `page.route()` mocks **before** `page.goto()` or any navigation — routes only intercept requests made after registration

## DON'T:
1. **Don't** call login inside tests — use the auth fixture
2. **Don't** skip tests due to season phase — use `appState` or `setState` to set the state you need
3. **Don't** use `.only` — it will fail in CI (`forbidOnly: true`)
4. **Don't** use fragile structural selectors
5. **Don't** mutate production data (create trades, submit forms) without cleanup
6. **Don't** assume MAMP is running — tests will fail with connection errors if it's not
7. **Don't** import from `@playwright/test` for authenticated tests — import from `../fixtures/auth`
8. **Don't** use `toBeVisible()` or `toHaveText()` on locators that match multiple elements — Playwright strict mode throws. Use `.first()`, `.nth(n)`, or check `.count()` instead
9. **Don't** use `boundingBox()` to verify CSS properties like `width: fit-content` — parent layout context affects the bounding box. Use `page.evaluate(() => getComputedStyle(el).property)` to check computed CSS values directly
10. **Don't** import `Page` type from `../fixtures/auth` — it only exports `test` and `expect`. Import `Page` separately: `import type { Page } from '@playwright/test'`
11. **Don't** use `link.click()` for page-to-page navigation — it triggers a Playwright-managed navigation wait that can time out when MAMP is under concurrent load from parallel workers. Instead, extract the href with `getAttribute('href')` and use `page.goto(href)`, which handles navigation more reliably

## CI Workflow Notes

The E2E tests run in GitHub Actions via `.github/workflows/e2e-tests.yml`. Key details:

- **PHP built-in server** with `ibl5/router.php` replaces Apache. The router handles the single `api/v1/*` rewrite rule; returns `false` for everything else.
- **Seed data** lives in `ibl5/tests/e2e/fixtures/ci-seed.sql`. When adding new E2E tests that require additional data (new tables, new rows), update this file.
- **Bcrypt hashes contain `$` characters** that bash expands in unquoted heredocs (`<<EOF`). When writing CI steps that handle bcrypt hashes, use either: (a) single-quoted heredocs (`<<'EOF'`) with `getenv()` in PHP, or (b) pipe from PHP directly. Never store a bcrypt hash in a shell variable and use it in an unquoted heredoc.
- **Secrets required:** `IBL_TEST_USER` and `IBL_TEST_PASS` must be configured as GitHub repository secrets.

## Completion Criteria

Before considering an E2E test task complete:

1. **Run the full E2E suite**: `cd ibl5 && bun run test:e2e`
2. **Verify all tests pass** (season-phase skips are expected, not failures)
3. **No `.only`** in any spec file
4. **PHP error coverage** — every smoke file includes PHP error pattern checks
5. **Correct auth pattern** — public tests use empty storageState, authenticated tests import from fixtures/auth
