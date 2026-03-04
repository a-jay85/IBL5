import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Compare Players — public, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

/** Fill both player inputs from datalist and submit the comparison form. */
async function submitComparison(page: Page): Promise<void> {
  const [player1Name, player2Name] = await page
    .locator('datalist#player-names option')
    .evaluateAll((els) => els.slice(0, 2).map((el) => (el as HTMLOptionElement).value));

  await page.locator('#Player1').fill(player1Name);
  await page.locator('#Player2').fill(player2Name);
  await page.locator('.ibl-filter-form__submit').click();
  await expect(page.locator('.ibl-data-table').first()).toBeVisible();
}

test.describe('Compare Players flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=ComparePlayers');
  });

  test('page loads with comparison form', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('#Player1')).toBeVisible();
    await expect(page.locator('#Player2')).toBeVisible();
  });

  test('player datalist is populated', async ({ page }) => {
    const datalist = page.locator('datalist#player-names option');
    expect(await datalist.count()).toBeGreaterThan(0);
  });

  test('form has submit button with Compare text', async ({ page }) => {
    const submitBtn = page.locator('.ibl-filter-form__submit');
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toContainText('Compare');
  });

  test('comparing two players shows ratings table', async ({ page }) => {
    await submitComparison(page);

    const allTitles = await page.locator('.ibl-title').allTextContents();
    expect(allTitles.some((t) => t.includes('Current Ratings'))).toBe(true);
  });

  test('comparison shows season stats table', async ({ page }) => {
    await submitComparison(page);

    const allTitles = await page.locator('.ibl-title').allTextContents();
    expect(allTitles.some((t) => t.includes('Current Season Stats'))).toBe(true);
  });

  test('comparison shows career stats table', async ({ page }) => {
    await submitComparison(page);

    const allTitles = await page.locator('.ibl-title').allTextContents();
    expect(allTitles.some((t) => t.includes('Career Stats'))).toBe(true);
  });

  test('each comparison table has exactly 2 player rows', async ({ page }) => {
    await submitComparison(page);

    const tables = page.locator('.ibl-data-table');
    const tableCount = await tables.count();
    // Should have 3 tables: ratings, season stats, career stats
    expect(tableCount).toBeGreaterThanOrEqual(3);

    for (let i = 0; i < Math.min(tableCount, 3); i++) {
      const rows = tables.nth(i).locator('tbody tr');
      expect(await rows.count()).toBe(2);
    }
  });

  test('invalid player name shows error', async ({ page }) => {
    await page.locator('#Player1').fill('Nonexistent Player XYZ123');
    await page.locator('#Player2').fill('Another Fake Player ABC456');
    await page.locator('.ibl-filter-form__submit').click();

    const emptyState = page.locator('.ibl-empty-state');
    await expect(emptyState).toBeVisible();
  });

  test('no PHP errors on compare players pages', async ({ page }) => {
    // Check form page
    let body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Compare Players form page`,
      ).not.toContain(pattern);
    }

    // Check results page with valid players
    await submitComparison(page);

    body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Compare Players results page`,
      ).not.toContain(pattern);
    }
  });
});
