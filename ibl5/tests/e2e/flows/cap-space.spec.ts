import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Cap Space — public page.
test.use({ storageState: publicStorageState() });

test.describe('Cap Space flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=CapSpace');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Cap Info/i);
  });

  test('cap space table is visible', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table, table').first();
    await expect(table).toBeVisible();
  });

  test('table has expected salary columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table').first();
    await expect(table).toBeVisible();
    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Team');
  });

  test('team rows have data-team-id attributes', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('MLE/LLE status indicators are present', async ({ page }) => {
    const table = page.locator('.ibl-data-table, .sticky-table').first();
    await expect(table).toBeVisible();
    const headerText = await table.locator('thead').textContent();
    // Should contain MLE and LLE columns
    expect(headerText).toContain('MLE');
    expect(headerText).toContain('LLE');
  });

  test('sticky scroll wrapper exists for wide table', async ({ page }) => {
    // Cap space uses sticky table pattern
    const wrapper = page.locator('.sticky-scroll-wrapper');
    await expect(wrapper.first()).toBeVisible();
  });

  test('table is sortable', async ({ page }) => {
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Cap Space page');
  });
});
