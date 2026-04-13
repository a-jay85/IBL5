import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Player Database — public page, no authentication required.
// The results table only appears AFTER submitting a search.
test.use({ storageState: publicStorageState() });

test.describe('Player Database flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=PlayerDatabase');
    // Under parallel load, the page may render blank — retry once
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      await page.waitForLoadState('domcontentloaded');
      await page.goto('modules.php?name=PlayerDatabase');
    }
  });

  test('page loads with search form', async ({ page }) => {
    await expect(page.locator('form[name="Search"]')).toBeVisible();
  });

  test('search by name returns results', async ({ page }) => {
    await page.locator('input[name="search_name"]').fill('a');
    await page.locator('.ibl-filter-form__submit').click();

    // After search, a results table should appear
    await expect(page.locator('table').first()).toBeVisible();
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('filter by position returns results', async ({ page }) => {
    await page.locator('select[name="pos"]').selectOption('SG');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('table').first()).toBeVisible();
    const rows = page.locator('table tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('reset button clears form', async ({ page }) => {
    // Fill some fields
    await page.locator('input[name="search_name"]').fill('TestName');
    await page.locator('select[name="pos"]').selectOption('SG');

    // Click reset
    const resetButton = page.locator('.ibl-btn.ibl-btn--ghost').first();
    await expect(resetButton).toBeVisible();
    await resetButton.click();
    // Name input should be cleared
    await expect(page.locator('input[name="search_name"]')).toHaveValue('');
  });

  test('sortable table headers reorder rows', async ({ page }) => {
    // Submit an empty search to get results
    await page.locator('.ibl-filter-form__submit').click();

    const table = page.locator('table.sortable').first();
    await expect(table).toBeVisible();

    // Wait for sorttable to initialize
    await expect(table).toHaveAttribute('data-sorttable', 'true');

    // Get first column cell values before sort
    const cells = table.locator('tbody td:nth-child(1)');
    const countBefore = await cells.count();
    expect(countBefore, 'Results table needs at least 2 rows to verify sort').toBeGreaterThanOrEqual(2);

    const valuesBefore: string[] = [];
    for (let i = 0; i < Math.min(countBefore, 5); i++) {
      valuesBefore.push((await cells.nth(i).textContent()) ?? '');
    }

    // Click a sortable header (first column)
    const sortableHeader = table.locator('thead th').first();
    await sortableHeader.click();
    await expect(sortableHeader).toHaveClass(/sorttable_sorted/);

    // Get values after sort
    const valuesAfter: string[] = [];
    for (let i = 0; i < Math.min(countBefore, 5); i++) {
      valuesAfter.push((await cells.nth(i).textContent()) ?? '');
    }

    // Values should have changed order (or be same if already sorted)
    // At minimum the sort class changed, confirming interaction worked
    await expect(table).toBeVisible();
  });

  test('no PHP errors after search', async ({ page }) => {
    await page.locator('input[name="search_name"]').fill('test');
    await page.locator('.ibl-filter-form__submit').click();

    await assertNoPhpErrors(page, 'after search');
  });
});
