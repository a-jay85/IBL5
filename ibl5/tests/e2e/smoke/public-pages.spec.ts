import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Public pages — no authentication required.
// Uses the public fixture with appState for automatic state restore.

test.describe('Public page smoke tests', () => {
  // Ensure trivia mode is off so modules render normally
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  test('homepage loads', async ({ page }) => {
    await page.goto('index.php');
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
    await page.goto('modules.php?name=Team&op=team&teamid=1');
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
    const table = page.locator('.ibl-data-table, .sticky-table, table').first();
    await expect(table).toBeVisible();
  });

  test('no PHP errors on key public pages', async ({ page }) => {
    const urls = [
      'index.php',
      'modules.php?name=Standings',
      'modules.php?name=SeasonLeaderboards',
      'modules.php?name=CareerLeaderboards',
      'modules.php?name=DraftHistory',
      'modules.php?name=CapSpace',
      'modules.php?name=Player&pa=showpage&pid=1',
      'modules.php?name=Team&op=team&teamid=1',
      'modules.php?name=Topics',
    ];

    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
