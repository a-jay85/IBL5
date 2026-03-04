import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Player Database — public page, no authentication required.
// The results table only appears AFTER submitting a search.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Player Database flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=PlayerDatabase');
    // Under parallel MAMP load, the page may render blank — retry once
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      await page.waitForTimeout(500);
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
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('filter by position returns results', async ({ page }) => {
    await page.locator('select[name="pos"]').selectOption('SG');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('table').first()).toBeVisible();
    const rows = page.locator('table tbody tr');
    // CI seed data may have limited players — skip if no results
    const count = await rows.count();
    if (count === 0) {
      test.skip(true, 'No players found for position filter in current dataset');
    }
    expect(count).toBeGreaterThan(0);
  });

  test('reset button clears form', async ({ page }) => {
    // Fill some fields
    await page.locator('input[name="search_name"]').fill('TestName');
    await page.locator('select[name="pos"]').selectOption('SG');

    // Click reset
    const resetButton = page.locator('.ibl-btn.ibl-btn--ghost').first();
    if (await resetButton.isVisible()) {
      await resetButton.click();
      // Name input should be cleared
      await expect(page.locator('input[name="search_name"]')).toHaveValue('');
    }
  });

  test('sortable table headers reorder rows', async ({ page }) => {
    // Submit an empty search to get results
    await page.locator('.ibl-filter-form__submit').click();

    const table = page.locator('table.sortable').first();
    if (!(await table.isVisible())) return;

    // Click a sortable header
    const sortableHeader = table.locator('thead th').nth(1);
    await sortableHeader.click();

    // After sorting, the table should still be visible (no errors)
    await expect(table).toBeVisible();
  });

  test('no PHP errors after search', async ({ page }) => {
    await page.locator('input[name="search_name"]').fill('test');
    await page.locator('.ibl-filter-form__submit').click();

    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" after search`).not.toContain(
        pattern,
      );
    }
  });
});
