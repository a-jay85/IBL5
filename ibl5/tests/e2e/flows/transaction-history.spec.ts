import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });

const BASE = 'modules.php?name=TransactionHistory';

test.describe('Transaction History redirect', () => {
  test('old URL lands on Search with transactions preset', async ({ page }) => {
    await page.goto(BASE);
    await expect(page).toHaveURL(/name=Search&preset=transactions/);
    await expect(page.locator('select[name="preset"]')).toHaveValue('transactions');
    await expect(page.locator('.search-results .search-result').first()).toBeVisible();
    await assertNoPhpErrors(page, 'after TransactionHistory redirect');
  });

  test('legacy filter params are dropped on redirect', async ({ page }) => {
    await page.goto(BASE + '&cat=2&year=2025&month=1');
    await expect(page).toHaveURL(/name=Search&preset=transactions$/);
    await expect(page).not.toHaveURL(/cat=/);
  });

  test('History menu link points at the preset URL', async ({ page }) => {
    await page.goto('modules.php?name=Topics');
    const link = page.locator('.nav-dropdown-item', { hasText: 'Transaction History' }).first();
    const href = await link.getAttribute('href');
    expect(href).toContain('name=Search&preset=transactions');
  });
});
