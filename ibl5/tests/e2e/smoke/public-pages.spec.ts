import { test, expect } from '@playwright/test';

// Public pages — no authentication required.
// These use the base test (not the auth fixture) so they run without login.

test.use({ storageState: { cookies: [], origins: [] } });

const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

test.describe('Public page smoke tests', () => {
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
    // Player page uses a card layout — check for the player name heading
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
    // Career leaderboards shows a form on initial load — verify the form is present
    await expect(page.getByRole('button', { name: /display/i })).toBeVisible();
  });

  test('draft history loads', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('cap space loads', async ({ page }) => {
    await page.goto('modules.php?name=CapSpace');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
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
