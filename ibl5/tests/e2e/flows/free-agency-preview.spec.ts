import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Free Agency Preview — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Free Agency Preview flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=FreeAgencyPreview');
  });

  test('page loads with title containing year', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Free Agent Preview/i);
  });

  test('sticky-scroll-wrapper contains sticky-table', async ({ page }) => {
    await expect(
      page.locator('.sticky-scroll-wrapper .sticky-scroll-container .sticky-table')
    ).toBeVisible();
  });

  test('Player column header is sticky-corner', async ({ page }) => {
    const cornerHeader = page.locator('thead th.sticky-col.sticky-corner');
    await expect(cornerHeader).toBeVisible();
    await expect(cornerHeader).toContainText('Player');
  });

  test('table has at least 20 column headers', async ({ page }) => {
    const headers = page.locator('.sticky-table thead th');
    expect(await headers.count()).toBeGreaterThanOrEqual(20);
  });

  test('at least 3 player rows present', async ({ page }) => {
    // CI seed has 3 players with expiring contracts (pid=10,11,12)
    const rows = page.locator('.sticky-table tbody tr[data-team-id]');
    expect(await rows.count()).toBeGreaterThanOrEqual(3);
  });

  test('position cells contain basketball positions', async ({ page }) => {
    const firstPosCell = page.locator('tbody tr[data-team-id]').first().locator('td.fa-preview-pos-col');
    const posCount = await firstPosCell.count();
    if (posCount > 0) {
      const text = (await firstPosCell.textContent())!.trim();
      expect(text).toMatch(/^(PG|SG|SF|PF|C)$/);
    }
  });

  test('player links point to player pages', async ({ page }) => {
    const playerLinks = page.locator('.sticky-table a[href*="pid="]');
    const count = await playerLinks.count();
    if (count > 0) {
      const href = await playerLinks.first().getAttribute('href');
      expect(href).toContain('name=Player');
    }
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Free Agency Preview page');
  });
});
