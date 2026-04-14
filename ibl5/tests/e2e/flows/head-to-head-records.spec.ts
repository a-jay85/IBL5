import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });

test.describe('Head-to-Head Records flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=HeadToHeadRecords');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Head-to-Head Records/i);
  });

  test('filter form is visible', async ({ page }) => {
    await expect(page.locator('.ibl-filter-form')).toBeVisible();
  });

  test('matrix table or empty state is visible', async ({ page }) => {
    const table = page.locator('.h2h-table');
    const emptyState = page.locator('.h2h-empty-state');
    const tableVisible = await table.isVisible().catch(() => false);
    const emptyVisible = await emptyState.isVisible().catch(() => false);
    expect(tableVisible || emptyVisible).toBe(true);
  });

  test('table uses page-sticky wrapper when present', async ({ page }) => {
    const table = page.locator('.h2h-table');
    if (await table.isVisible().catch(() => false)) {
      await expect(page.locator('.sticky-scroll-wrapper.page-sticky')).toBeVisible();
    } else {
      await expect(page.locator('.h2h-empty-state')).toBeVisible();
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Head-to-Head Records page');
  });
});
