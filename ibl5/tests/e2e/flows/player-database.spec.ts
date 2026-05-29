import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { gotoWithRetry } from '../helpers/navigation';

// Player Database — public page, no authentication required.
// The results table only appears AFTER submitting a search.
test.use({ storageState: publicStorageState() });

test.describe('Player Database flow', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=PlayerDatabase');
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

  test('active-only excludes retirees', async ({ page }) => {
    // "Retirees?" is a <select name="active">: Yes=1 (include retirees, no filter),
    // No=0 (exclude → ibl_plr.retired = 0). Retired players render as a separator
    // row, so count whole tbody rows rather than a stat cell.
    await page.locator('select[name="active"]').selectOption('1');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('table.sortable').first()).toBeVisible();
    const allCount = await page.locator('table.sortable tbody tr').count();

    // Now navigate back and submit with active=0 (exclude retirees)
    await gotoWithRetry(page, 'modules.php?name=PlayerDatabase');
    await page.locator('select[name="active"]').selectOption('0');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('table.sortable').first()).toBeVisible();
    const activeOnlyCount = await page.locator('table.sortable tbody tr').count();

    expect(activeOnlyCount).toBeLessThan(allCount);
  });

  test('experience range filter narrows results', async ({ page }) => {
    // Submit with no filters to get the unfiltered count
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('table.sortable').first()).toBeVisible();
    const unfilteredCount = await page.locator('table.sortable tbody tr td:nth-child(5)').count();

    // Navigate back, apply exp_max=2, resubmit
    await gotoWithRetry(page, 'modules.php?name=PlayerDatabase');
    await page.locator('input[name="exp_max"]').fill('2');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('table.sortable').first()).toBeVisible();

    const expCells = page.locator('table.sortable tbody tr td:nth-child(5)');
    const filteredCount = await expCells.count();

    expect(filteredCount).toBeLessThan(unfilteredCount);

    // Every row must have Exp <= 2
    for (let i = 0; i < filteredCount; i++) {
      const text = (await expCells.nth(i).textContent()) ?? '';
      expect(Number(text.trim()), `Row ${i} Exp value "${text}" exceeds max of 2`).toBeLessThanOrEqual(2);
    }
  });

  test('sort by Exp column produces non-increasing order on first click', async ({ page }) => {
    // Submit empty search to get all results
    await page.locator('.ibl-filter-form__submit').click();

    const table = page.locator('table.sortable').first();
    await expect(table).toBeVisible();
    await expect(table).toHaveAttribute('data-sorttable', 'true');

    const expCells = table.locator('tbody tr td:nth-child(5)');
    const rowCount = await expCells.count();
    expect(rowCount, 'Need at least 2 rows to verify sort order is meaningful').toBeGreaterThanOrEqual(2);

    // jslib/sorttable.js sorts DESCENDING (highest first) on the initial click,
    // tagging the header with sorttable_sorted_reverse.
    const expHeader = table.locator('thead th').filter({ hasText: 'Exp' });
    await expHeader.click();
    await expect(expHeader).toHaveClass(/sorttable_sorted_reverse/);

    // Read Exp values after sort and assert non-increasing
    const sortedValues: number[] = [];
    for (let i = 0; i < rowCount; i++) {
      const text = (await expCells.nth(i).textContent()) ?? '';
      sortedValues.push(Number(text.trim()));
    }
    for (let i = 1; i < sortedValues.length; i++) {
      expect(
        sortedValues[i],
        `Exp column not sorted descending: row ${i} value ${sortedValues[i]} > row ${i - 1} value ${sortedValues[i - 1]}`,
      ).toBeLessThanOrEqual(sortedValues[i - 1]);
    }
  });

  test('no PHP errors after search', async ({ page }) => {
    await page.locator('input[name="search_name"]').fill('test');
    await page.locator('.ibl-filter-form__submit').click();

    await assertNoPhpErrors(page, 'after search');
  });
});
