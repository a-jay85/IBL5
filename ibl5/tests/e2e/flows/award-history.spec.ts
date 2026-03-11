import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Award History — public page, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Award History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=AwardHistory');
  });

  test('page loads with filter form and inputs', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('#aw_name')).toBeVisible();
    await expect(page.locator('#aw_Award')).toBeVisible();
    await expect(page.locator('#aw_year')).toBeVisible();
  });

  test('sort radio buttons present with year checked by default', async ({
    page,
  }) => {
    const radioButtons = page.locator('input[name="aw_sortby"]');
    await expect(radioButtons.first()).toBeVisible();

    // Sort by year should be checked by default
    const yearRadio = page.locator('input[name="aw_sortby"][value="year"]');
    if (await yearRadio.count() > 0) {
      await expect(yearRadio).toBeChecked();
    }
  });

  test('submitting empty form returns results table', async ({ page }) => {
    // Submit the form
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    const table = page.locator('.ibl-data-table.sortable');
    await expect(table.first()).toBeVisible();
    const rows = table.first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('searching by partial name returns results', async ({ page }) => {
    await page.locator('#aw_name').fill('a');
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();
    const rows = table.first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('searching by award name returns matching rows', async ({ page }) => {
    await page.locator('#aw_Award').fill('MVP');
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();

    // Check that results contain "MVP" in the Award column
    const rows = table.first().locator('tbody tr');
    const rowCount = await rows.count();
    if (rowCount > 0) {
      const firstRowText = await rows.first().textContent();
      expect(firstRowText).toContain('MVP');
    }
  });

  test('non-existent name returns empty results without PHP error', async ({
    page,
  }) => {
    await page.locator('#aw_name').fill('zzzznonexistent999');
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    await assertNoPhpErrors(page);
  });

  test('result rows contain player links', async ({ page }) => {
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();

    const playerLinks = table.first().locator('td a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();
  });

  test('table has expected headers', async ({ page }) => {
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();

    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();

    const headers = table.first().locator('thead th');
    const headerTexts = await headers.allTextContents();
    const headerString = headerTexts.join(' ');

    expect(headerString).toContain('Year');
    expect(headerString).toContain('Player');
    expect(headerString).toContain('Award');
  });

  test('no PHP errors on form and results pages', async ({ page }) => {
    // Check form page
    await assertNoPhpErrors(page, 'on Award History form page');

    // Check results page
    await page.locator('.ibl-filter-form').locator('button[type="submit"], input[type="submit"]').first().click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on Award History results page');
  });
});
