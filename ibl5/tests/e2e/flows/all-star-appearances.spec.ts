import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// All-Star Appearances — public page.
test.use({ storageState: publicStorageState() });

test.describe('All-Star Appearances flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=AllStarAppearances');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/All-Star Appearances/i);
  });

  test('appearances table is visible with correct headers', async ({ page }) => {
    // CI seed has All-Star awards — table must render
    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();

    const headerText = await table.first().locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Appearances');
  });

  test('table has at least 2 rows with player data', async ({ page }) => {
    // CI seed: 'Test Player' (3 appearances) + 'Stars Guard' (2 appearances)
    const rows = page.locator('.ibl-data-table tbody tr');
    expect(await rows.count()).toBeGreaterThanOrEqual(2);
  });

  test('player links navigate to player page', async ({ page }) => {
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    // Navigate to the player page and verify it loads
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from All-Star Appearances');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('appearances count cells have highlight styling', async ({ page }) => {
    const highlights = page.locator('.ibl-stat-highlight');
    await expect(highlights.first()).toBeVisible();
  });

  test('appearances values are positive integers', async ({ page }) => {
    const highlights = page.locator('.ibl-stat-highlight');
    const count = await highlights.count();
    expect(count).toBeGreaterThanOrEqual(1);

    for (let i = 0; i < count; i++) {
      const text = await highlights.nth(i).textContent();
      const value = parseInt(text?.trim() ?? '', 10);
      expect(value).toBeGreaterThan(0);
    }
  });

  test('table is sortable', async ({ page }) => {
    await expect(page.locator('table.sortable').first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on All-Star Appearances page');
  });
});
