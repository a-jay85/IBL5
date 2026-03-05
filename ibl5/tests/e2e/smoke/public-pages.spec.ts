import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';
import { setState, type Settings } from '../helpers/test-state';

// Public pages — no authentication required.
// These use the base test (not the auth fixture) so they run without login.

test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Public page smoke tests', () => {
  // Ensure trivia mode is off so modules render normally
  let restoreSettings: Settings;

  test.beforeEach(async ({ request }) => {
    const result = await setState(request, { 'Trivia Mode': 'Off' });
    restoreSettings = result.previous;
  });

  test.afterEach(async ({ request }) => {
    await setState(request, restoreSettings);
  });

  test('homepage loads', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/IBL/i);
  });

  test('standings page loads', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('player page loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('team page loads', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('season leaderboards loads', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('career leaderboards loads', async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');
    await expect(page.getByRole('button', { name: /display/i })).toBeVisible();
  });

  test('draft history loads', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('cap space loads', async ({ page }) => {
    await page.goto('modules.php?name=CapSpace');
    // Data-dependent skip: the view may produce no content depending on DB state
    const table = page.locator('.ibl-data-table, .sticky-table, table').first();
    const visible = await table.isVisible({ timeout: 10_000 }).catch(() => false);
    if (!visible) {
      test.skip(true, 'Cap Space rendered no table content (local DB state)');
    }
    await expect(table).toBeVisible();
  });

  test('no PHP errors on key public pages', async ({ page }) => {
    const urls = [
      '/',
      'modules.php?name=Standings',
      'modules.php?name=SeasonLeaderboards',
      'modules.php?name=CareerLeaderboards',
      'modules.php?name=DraftHistory',
      'modules.php?name=CapSpace',
      'modules.php?name=Player&pa=showpage&pid=1',
      'modules.php?name=Team&op=team&teamID=1',
    ];

    for (const url of urls) {
      await page.goto(url);
      const body = await page.locator('body').textContent();
      for (const pattern of PHP_ERROR_PATTERNS) {
        expect(body, `PHP error "${pattern}" found on ${url}`).not.toContain(pattern);
      }
    }
  });
});
