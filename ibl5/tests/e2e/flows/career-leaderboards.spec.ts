import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Career Leaderboards — public, no authentication required.
// Serial: trivia-on and trivia-off blocks set the same setting (Trivia Mode).
test.describe.configure({ mode: 'serial' });

// ---- Career Leaderboards: trivia off (normal) ----

test.describe('Career Leaderboards flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=CareerLeaderboards');
  });

  test('page loads with filter form', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('select[name="boards_type"]')).toBeVisible();
    await expect(page.locator('select[name="sort_cat"]')).toBeVisible();
    await expect(page.locator('select[name="active"]')).toBeVisible();
    await expect(page.locator('input[name="display"]')).toBeVisible();
  });

  test('form submission shows results', async ({ page }) => {
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('table has sticky rank and name columns', async ({ page }) => {
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const table = page.locator('.ibl-data-table').first();
    await expect(table.locator('.sticky-col-1').first()).toBeVisible();
    await expect(table.locator('.sticky-col-2').first()).toBeVisible();
  });

  test('sorted column is highlighted', async ({ page }) => {
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const sortedCol = page.locator('.ibl-data-table').first().locator('th.sorted-col');
    await expect(sortedCol.first()).toBeVisible();
  });

  test('changing board type works', async ({ page }) => {
    const boardTypeSelect = page.locator('select[name="boards_type"]');
    const options = boardTypeSelect.locator('option');
    const optionCount = await options.count();

    if (optionCount > 1) {
      await boardTypeSelect.selectOption({ index: 1 });
      await page.locator('.ibl-filter-form__submit').click();

      await expect(page.locator('.ibl-data-table').first()).toBeVisible();
      const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
      await expect(rows.first()).toBeVisible();
    }
  });

  test('include/exclude retirees toggle changes results', async ({ page }) => {
    await page.locator('select[name="active"]').selectOption('0');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    await page.locator('select[name="active"]').selectOption('1');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withoutRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    expect(withRetireesCount).toBeGreaterThan(0);
    expect(withoutRetireesCount).toBeGreaterThan(0);
    expect(withRetireesCount).not.toBe(withoutRetireesCount);
  });

  test('no PHP errors on career leaderboards', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Career Leaderboards form page');

    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on Career Leaderboards results page');
  });
});

// ---- Career Leaderboards: trivia on ----

test.describe('Career Leaderboards: trivia mode', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'On' });
    await page.goto('modules.php?name=CareerLeaderboards');
  });

  test('module shows inactive message when trivia mode is on', async ({ page }) => {
    await expect(page.getByText("Module isn't active")).toBeVisible();
  });
});
