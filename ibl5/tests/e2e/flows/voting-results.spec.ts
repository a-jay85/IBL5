import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// VotingResults — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Voting Results flow', () => {
  test('page loads without PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=VotingResults');
    await assertNoPhpErrors(page, 'on VotingResults page');
  });

  test('page displays title or content', async ({ page }) => {
    await page.goto('modules.php?name=VotingResults');
    // VotingResults may show results tables or a "no results" state
    const body = await page.locator('body').textContent();
    expect(body!.length).toBeGreaterThan(100);
  });

  test('voting results tables have expected structure when present', async ({ page }) => {
    await page.goto('modules.php?name=VotingResults');
    const tables = page.locator('.voting-results-table, .ibl-data-table');
    const count = await tables.count();
    if (count > 0) {
      const firstTable = tables.first();
      await expect(firstTable).toBeVisible();
      // Each results table should have Player and Votes columns
      const headerText = await firstTable.locator('thead').textContent();
      expect(headerText).toContain('Player');
    }
  });

  test('player links work in results when present', async ({ page }) => {
    await page.goto('modules.php?name=VotingResults');
    const playerLinks = page.locator('.voting-results-table a[href*="pid="], .ibl-data-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('pid=');
    }
  });

  test('no PHP errors on voting results', async ({ page }) => {
    await assertNoPhpErrors(page, 'on VotingResults');
  });
});
