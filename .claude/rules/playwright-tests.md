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

- **MAMP must be running** — E2E tests hit the real local server (`http://localhost/ibl5/`), unlike PHPUnit tests which use mocks
- **`.env.test` must exist** with valid credentials — copy from `.env.test.example`

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

### Flow test with season-phase-aware skipping
```typescript
import { test, expect } from '../fixtures/auth';

test.describe('Module flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Module');

    // Skip if season phase doesn't support this feature
    const body = await page.locator('body').textContent();
    const featureClosed = body?.includes('is closed') || body?.includes('period');
    if (featureClosed) {
      test.skip(true, 'Feature is closed for the current season phase');
    }
  });

  test('interaction works', async ({ page }) => {
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

```typescript
// Page loads with expected title
await expect(page).toHaveTitle(/IBL/i);

// Element is visible
await expect(page.locator('.ibl-data-table').first()).toBeVisible();

// Element contains text
await expect(page.locator('.ibl-title')).toContainText(/standings/i);

// Element is NOT visible (e.g., login prompt when authenticated)
await expect(page.getByText('Sign In')).not.toBeVisible();

// Element count
await expect(page.locator('.trading-roster')).toHaveCount(2);

// Count is at least N
expect(await page.locator('input[type="checkbox"]').count()).toBeGreaterThan(0);

// No PHP errors in page body
expect(body, `PHP error on ${url}`).not.toContain('Fatal error');
```

## Season-Phase-Aware Skipping

Some features are only available during certain season phases (e.g., trading, free agency, draft). **Never hardcode skips** — detect the phase at runtime from page content:

```typescript
// CORRECT — runtime detection
const body = await page.locator('body').textContent();
const tradesClosed = body?.includes('Trading is closed') || body?.includes('trades are closed');
if (tradesClosed) {
  test.skip(true, 'Trades are currently closed for the season');
}

// WRONG — hardcoded skip
test.skip(true, 'Skip until offseason');
```

## DO:
1. Check for PHP errors on every page you visit in smoke tests
2. Use the auth fixture for authenticated tests
3. Use `storageState: { cookies: [], origins: [] }` for public tests
4. Detect season phase at runtime and skip dynamically
5. Use stable CSS classes or accessible roles for locators
6. Keep smoke tests fast — one assertion per test, no complex interactions
7. Add new pages to the PHP error check loop when adding smoke tests

## DON'T:
1. **Don't** call login inside tests — use the auth fixture
2. **Don't** hardcode `test.skip()` — use runtime phase detection
3. **Don't** use `.only` — it will fail in CI (`forbidOnly: true`)
4. **Don't** use fragile structural selectors
5. **Don't** mutate production data (create trades, submit forms) without cleanup
6. **Don't** assume MAMP is running — tests will fail with connection errors if it's not
8. **Don't** import from `@playwright/test` for authenticated tests — import from `../fixtures/auth`

## Completion Criteria

Before considering an E2E test task complete:

1. **Run the full E2E suite**: `cd ibl5 && bun run test:e2e`
2. **Verify all tests pass** (season-phase skips are expected, not failures)
3. **No `.only`** in any spec file
4. **PHP error coverage** — every smoke file includes PHP error pattern checks
5. **Correct auth pattern** — public tests use empty storageState, authenticated tests import from fixtures/auth
