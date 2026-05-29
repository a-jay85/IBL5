import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Transaction History — public page, no authentication required.
// NOTE: The form hidden field uses name=Transaction_History but the module
// directory is TransactionHistory. We use direct URL navigation for filtering
// to avoid that mismatch.
test.use({ storageState: publicStorageState() });

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
    // Navigate directly with a known seed year
    await page.goto(`${BASE}&year=2026`);

    // After load, the year dropdown should have the selected value
    await expect(page.locator('select[name="year"]')).toHaveValue('2026');
  });

  test('reset link navigates to unfiltered state', async ({ page }) => {
    const resetLink = page.locator('.txn-reset');
    await expect(resetLink).toBeVisible();
    const href = await resetLink.getAttribute('href');
    await page.goto(href!);

    // Should load unfiltered page with transactions
    await expect(page.locator('.txn-table')).toBeVisible();
  });

  test('month filter shows only that month', async ({ page }) => {
    // Navigate directly with year+month to get February 2026 transactions
    await page.goto(`${BASE}&year=2026&month=2`);

    // At least one row must be present — fails if filter is a no-op
    await expect(page.locator('.txn-table tbody tr').first()).toBeVisible();

    // Every visible date cell must contain 'Feb' and '2026'
    const dateCells = page.locator('td.date-cell');
    const cellCount = await dateCells.count();
    for (let i = 0; i < cellCount; i++) {
      const text = await dateCells.nth(i).textContent();
      expect(text, `date cell ${i} should contain 'Feb'`).toContain('Feb');
      expect(text, `date cell ${i} should contain '2026'`).toContain('2026');
    }

    // The March transaction must be absent
    await expect(
      page.locator('body'),
    ).not.toContainText('changes position from SF');
  });

  test('no PHP errors on load and after filtering', async ({ page }) => {
    // Check initial page
    await assertNoPhpErrors(page, 'on Transaction History page');

    // Filter and check again
    await page.goto(`${BASE}&cat=2`);

    await assertNoPhpErrors(page, 'on filtered Transaction History page');
  });
});
