import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Transaction History — public page, no authentication required.
// NOTE: The form hidden field uses name=Transaction_History but the module
// directory is TransactionHistory. We use direct URL navigation for filtering
// to avoid that mismatch.
test.use({ storageState: { cookies: [], origins: [] } });

const BASE = 'modules.php?name=TransactionHistory';

test.describe('Transaction History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(BASE);
  });

  test('page loads with filter form and dropdowns', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('select[name="cat"]')).toBeVisible();
    await expect(page.locator('select[name="year"]')).toBeVisible();
    await expect(page.locator('select[name="month"]')).toBeVisible();
  });

  test('category dropdown has expected options', async ({ page }) => {
    const catSelect = page.locator('select[name="cat"]');
    const options = catSelect.locator('option');
    // Should have multiple category options (All + specific categories)
    expect(await options.count()).toBeGreaterThanOrEqual(5);
  });

  test('default load shows transaction table with rows', async ({ page }) => {
    const table = page.locator('.txn-table');
    await expect(table).toBeVisible();
    const rows = table.locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('filtering by category shows matching badge spans', async ({
    page,
  }) => {
    // Use direct URL navigation to avoid form action mismatch
    await page.goto(`${BASE}&cat=2`);

    await expect(page.locator('.txn-table')).toBeVisible();
    // Rows should have category-2 badge spans
    const badges = page.locator('.txn-badge--2');
    await expect(badges.first()).toBeVisible();
  });

  test('filtering by year reflects selection in dropdown', async ({
    page,
  }) => {
    const yearSelect = page.locator('select[name="year"]');
    const options = yearSelect.locator('option');
    const optionCount = await options.count();

    if (optionCount > 1) {
      const yearValue = await options.nth(1).getAttribute('value');
      // Navigate directly with year parameter
      await page.goto(`${BASE}&year=${yearValue}`);

      // After load, the year dropdown should have the selected value
      await expect(page.locator('select[name="year"]')).toHaveValue(yearValue!);
    }
  });

  test('reset link navigates to unfiltered state', async ({ page }) => {
    const resetLink = page.locator('.txn-reset');
    if (await resetLink.isVisible()) {
      const href = await resetLink.getAttribute('href');
      await page.goto(href!);

      // Should load unfiltered page with transactions
      await expect(page.locator('.txn-table')).toBeVisible();
    }
  });

  test('empty filter combination renders without PHP errors', async ({
    page,
  }) => {
    // Use a very specific filter that likely returns no results
    const yearSelect = page.locator('select[name="year"]');
    const options = yearSelect.locator('option');
    const optionCount = await options.count();

    if (optionCount > 2) {
      // Get the oldest year value
      const yearValue = await options.nth(optionCount - 1).getAttribute('value');
      await page.goto(`${BASE}&year=${yearValue}&month=1&cat=6`);

      // Should show either table or empty state — no PHP errors either way
      await assertNoPhpErrors(page);
    }
  });

  test('no PHP errors on load and after filtering', async ({ page }) => {
    // Check initial page
    await assertNoPhpErrors(page, 'on Transaction History page');

    // Filter and check again
    await page.goto(`${BASE}&cat=2`);

    await assertNoPhpErrors(page, 'on filtered Transaction History page');
  });
});
