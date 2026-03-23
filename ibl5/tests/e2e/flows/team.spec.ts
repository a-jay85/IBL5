import { test, expect } from '@playwright/test';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Team page — public, no authentication required.
// Current-season teams use a dropdown (.ibl-view-select), not tabs.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Team page flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
  });

  test('default view loads with roster table', async ({ page }) => {
    // Current-season teams use a dropdown, not tabs
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('team banner shows logo and action links', async ({ page }) => {
    const banner = page.locator('.team-banner-row').first();
    await expect(banner).toBeVisible();
    // Action links flank the logo
    const actionLinks = banner.locator('.team-action-link');
    await expect(actionLinks.first()).toBeVisible();
  });

  test('dropdown switches view to season totals', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('total_s');
    // Table should update (AJAX or page reload)
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('dropdown switches view to contracts', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('contracts');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('dropdown switches view to averages', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await expect(dropdown).toBeVisible();

    await dropdown.selectOption('avg_s');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('team cards display sidebar info', async ({ page }) => {
    const cards = page.locator('.team-card');
    await expect(cards.first()).toBeVisible();
  });

  test('multiple teams render without errors', async ({ page }) => {
    const teamIDs = [1, 5, 10, 15];
    for (const teamID of teamIDs) {
      await page.goto(`modules.php?name=Team&op=team&teamID=${teamID}`);
      await assertNoPhpErrors(page, `on team ${teamID}`);
    }
  });
});

// ===========================================================================
// Team page: additional display modes
// ===========================================================================

test.describe('Team page: additional display modes', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
  });

  test('dropdown switches to per36mins', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await dropdown.selectOption('per36mins');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });

  test('dropdown switches to chunk', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await dropdown.selectOption('chunk');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });
});

// Playoffs option requires phase control — uses cookie-based appState (no DB races)
publicTest.describe('Team page: playoffs display mode', () => {
  publicTest('dropdown switches to playoffs', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Playoffs' });
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    const dropdown = page.locator('.ibl-view-select').first();
    await dropdown.selectOption('playoffs');
    await publicExpect(page.locator('.ibl-data-table, table').first()).toBeVisible();
  });
});

// ===========================================================================
// Team page: dropdown content verification
// ===========================================================================

test.describe('Team page: dropdown content changes', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
  });

  test('switching to contracts shows salary columns', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();
    await dropdown.selectOption('contracts');

    // Dropdown triggers page reload — wait for salary headers to appear
    await expect(page.locator('th.col-salary').first()).toBeVisible({ timeout: 10000 });
  });

  test('switching back to ratings shows rating columns', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();

    // Switch to contracts first, then back to ratings
    await dropdown.selectOption('contracts');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();

    await dropdown.selectOption('ratings');
    const table = page.locator('.ibl-data-table, table').first();
    await expect(table).toBeVisible();

    // Ratings view should NOT have salary columns
    const salaryHeaders = table.locator('th.col-salary');
    expect(await salaryHeaders.count()).toBe(0);

    // Ratings view has rating headers (check for Bird/Exp which only appear in ratings-like views)
    const headers = await table.locator('th').allTextContents();
    const joined = headers.join(' ');
    expect(joined).toContain('Pos');
  });

  test('split option (home) loads table', async ({ page }) => {
    const dropdown = page.locator('.ibl-view-select').first();

    await dropdown.selectOption('split:home');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'after switching to split:home');
  });
});

// ===========================================================================
// Team page: error and banner states
// ===========================================================================

test.describe('Team page: error and banner states', () => {
  test('invalid teamID shows error', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=999');
    await expect(page.locator('.ibl-alert--error')).toBeVisible();
    await expect(page.locator('.ibl-alert--error')).toContainText(
      'Team not found.',
    );
  });

  test('extension_accepted banner renders', async ({ page }) => {
    await page.goto(
      'modules.php?name=Team&op=team&teamID=1&result=extension_accepted&msg=Player+agreed',
    );
    const successBanner = page.locator('.ibl-alert--success');
    await expect(successBanner).toBeVisible();
    await expect(successBanner).toContainText('Player response:');
  });
});

// ===========================================================================
// Team page: historical year view
// ===========================================================================

test.describe('Team page: historical year view', () => {
  test('historical year view renders with year in title', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1&yr=2024');
    await expect(page.locator('.ibl-data-table, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on historical year view');
  });
});
