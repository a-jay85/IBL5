import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Career Leaderboards — public, no authentication required.
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

  test('sort_cat changes the sorted column header', async ({ page }) => {
    await page.locator('select[name="boards_type"]').selectOption('Regular Season Totals');
    await page.locator('select[name="sort_cat"]').selectOption('Points');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const firstSortedText = await page.locator('.ibl-data-table th.sorted-col').first().innerText();
    expect(firstSortedText).toBe('PTS');

    await page.locator('select[name="sort_cat"]').selectOption('Total Rebounds');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const secondSortedText = await page.locator('.ibl-data-table th.sorted-col').first().innerText();
    expect(secondSortedText).toContain('REB');
    expect(secondSortedText).not.toBe(firstSortedText);
  });

  test('display limit caps row count', async ({ page }) => {
    await page.locator('select[name="boards_type"]').selectOption('Regular Season Totals');
    await page.locator('input[name="display"]').fill('3');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rowCount = await page.locator('.ibl-data-table tbody tr').count();
    expect(rowCount).toBeGreaterThanOrEqual(1);
    expect(rowCount).toBeLessThanOrEqual(3);
  });

  test('board type drives query: Regular Season has rows, Playoff Totals has none', async ({ page }) => {
    await page.locator('select[name="boards_type"]').selectOption('Regular Season Totals');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table tbody tr').first()).toBeVisible();

    await page.locator('select[name="boards_type"]').selectOption('Playoff Totals');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await expect(page.locator('.ibl-data-table tbody tr')).toHaveCount(0);
  });

  test('include/exclude retirees toggle changes results', async ({ page }) => {
    // Raise display limit so the single seeded retiree is not truncated below
    // the default top-N cutoff (default ranks would leave both counts equal).
    // Wait for the HTMX-boosted POST response between submissions — otherwise
    // the row count reads the previous render.
    await page.locator('input[name="display"]').fill('500');
    await page.locator('select[name="active"]').selectOption('0');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    await page.locator('input[name="display"]').fill('500');
    await page.locator('select[name="active"]').selectOption('1');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('CareerLeaderboards') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    // Poll the row count to let HTMX finish the swap before we read.
    await expect
      .poll(
        async () =>
          page.locator('.ibl-data-table').first().locator('tbody tr').count(),
      )
      .toBeLessThan(withRetireesCount);
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
