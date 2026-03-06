import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Public pages — no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

const EXTENDED_PUBLIC_URLS = [
  'modules.php?name=Schedule',
  'modules.php?name=Injuries',
  'modules.php?name=PlayerDatabase',
  'modules.php?name=ProjectedDraftOrder',
  'modules.php?name=DraftPickLocator',
  'modules.php?name=FreeAgencyPreview',
  'modules.php?name=ContractList',
  'modules.php?name=PlayerMovement',
  'modules.php?name=LeagueStarters',
  'modules.php?name=ComparePlayers',
  'modules.php?name=SeasonHighs',
  'modules.php?name=SeriesRecords',
  'modules.php?name=FranchiseHistory',
  'modules.php?name=ActivityTracker',
];

/**
 * Navigate and verify the page actually rendered content.
 * Under parallel load, PHP's built-in server can return blank HTML.
 * Retries up to 4 times with increasing back-off before failing.
 */
async function gotoWithRetry(
  page: import('@playwright/test').Page,
  url: string,
): Promise<void> {
  for (let attempt = 0; attempt < 5; attempt++) {
    if (attempt > 0) await page.waitForTimeout(attempt * 1000);
    try {
      await page.goto(url, { timeout: 15_000 });
    } catch {
      continue;
    }
    const body = await page.locator('body').innerText();
    if (body.trim().length >= 20) return;
  }
  throw new Error(`Page returned blank content after 5 attempts: ${url}`);
}

test.describe('Extended public page smoke tests', () => {
  test('schedule loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=Schedule');
    await expect(
      page.locator('.schedule-container, .ibl-data-table, table').first(),
    ).toBeVisible();
  });

  test('injuries page loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=Injuries');
    await expect(page.locator('.ibl-title, h2, h3').first()).toBeVisible();
  });

  test('player database loads with form', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=PlayerDatabase');
    await expect(page.locator('form[name="Search"]')).toBeVisible();
  });

  test('projected draft order loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=ProjectedDraftOrder');
    await expect(
      page.locator('.ibl-title, .ibl-data-table, table').first(),
    ).toBeVisible();
  });

  test('draft pick locator loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=DraftPickLocator');
    await expect(page.locator('.ibl-title, table').first()).toBeVisible();
  });

  test('free agency preview loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=FreeAgencyPreview');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('contract list loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=ContractList');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('player movement loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=PlayerMovement');
    await expect(
      page.locator('.ibl-title, .ibl-data-table, table, h2, h3').first(),
    ).toBeVisible();
  });

  test('league starters loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=LeagueStarters');
    await expect(
      page.locator('.ibl-data-table, table').first(),
    ).toBeVisible();
  });

  test('compare players loads with form', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=ComparePlayers');
    await expect(
      page.locator('input[name="Player1"], input[name="player1"]').first(),
    ).toBeVisible();
  });

  test('season highs loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=SeasonHighs');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('series records loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=SeriesRecords');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('franchise history loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=FranchiseHistory');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('activity tracker loads', async ({ page }) => {
    await gotoWithRetry(page,'modules.php?name=ActivityTracker');
    await expect(
      page.locator('.ibl-data-table, table, .ibl-title').first(),
    ).toBeVisible();
  });

  test('no PHP errors on extended public pages', async ({ page }) => {
    for (const url of EXTENDED_PUBLIC_URLS) {
      await page.goto(url);
      const body = await page.locator('body').textContent();
      for (const pattern of PHP_ERROR_PATTERNS) {
        expect(body, `PHP error "${pattern}" found on ${url}`).not.toContain(
          pattern,
        );
      }
    }
  });
});
