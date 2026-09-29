import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Career Leaderboards — public, no authentication required.
// ---- Career Leaderboards: trivia off (normal) ----

test.describe('Career Leaderboards flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards&tab=career');
  });

  test('page loads with filter form', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('select[name="phase"]')).toBeVisible();
    await expect(page.locator('input[name="mode"][value="totals"]')).toBeChecked();
    await expect(page.locator('input[name="mode"][value="averages"]')).toBeEnabled();
    await expect(page.locator('select[name="sortby"]')).toBeVisible();
    await expect(page.locator('input[name="retirees"][role="switch"]')).toBeChecked();
    await expect(page.locator('input[name="display"]')).toBeVisible();
    await expect(page.locator('.ibl-filter-form__submit')).toHaveText('Search');
  });

  test('first visit shows the form without running a search', async ({ page }) => {
    await expect(page.locator('.ibl-filter-form')).toBeVisible();
    await expect(page.locator('.ibl-data-table')).toHaveCount(0);
  });

  test('submitted URL runs the default search', async ({ page }) => {
    // Regular Season, Totals, PTS, retirees on, limit 50 are the defaults once submitted.
    await page.goto('modules.php?name=Leaderboards&tab=career&submitted=1');
    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
    await expect(page.locator('.ibl-data-table th.sorted-col').first()).toHaveText('PTS');
  });

  test('averages is disabled after selecting Rookie Game', async ({ page }) => {
    const averages = page.locator('input[name="mode"][value="averages"]');
    await page.locator('select[name="phase"]').selectOption('rookie');
    await expect(averages).toBeDisabled();
    await expect(page.locator('input[name="mode"][value="totals"]')).toBeChecked();

    await page.locator('select[name="phase"]').selectOption('regular');
    await expect(averages).toBeEnabled();
  });

  test('selecting Rookie Game while on Averages falls back to Totals', async ({ page }) => {
    await page.locator('input[name="mode"][value="averages"]').check();
    await page.locator('select[name="phase"]').selectOption('sophomore');
    await expect(page.locator('input[name="mode"][value="averages"]')).toBeDisabled();
    await expect(page.locator('input[name="mode"][value="totals"]')).toBeChecked();
  });

  test('toggling Averages swaps the PTS label to PPG and keeps the percentage options enabled', async ({ page }) => {
    const ppg = page.locator('select[name="sortby"] option[value="PPG"]');
    const fgp = page.locator('select[name="sortby"] option[value="FGP"]');

    await expect(ppg).toHaveText('PTS');
    await expect(fgp).toBeEnabled();

    await page.locator('input[name="mode"][value="averages"]').check();
    await expect(ppg).toHaveText('PPG');
    await expect(fgp).toBeEnabled();

    await page.locator('input[name="mode"][value="totals"]').check();
    await expect(ppg).toHaveText('PTS');
    await expect(fgp).toBeEnabled();
  });

  test('switching back to Totals keeps a selected percentage sort', async ({ page }) => {
    await page.locator('input[name="mode"][value="averages"]').check();
    await page.locator('select[name="sortby"]').selectOption('FGP');
    await page.locator('input[name="mode"][value="totals"]').check();
    await expect(page.locator('select[name="sortby"]')).toHaveValue('FGP');
  });

  test('Totals + FG% is selectable and sorts by FG%', async ({ page }) => {
    await page.locator('input[name="mode"][value="totals"]').check();
    const fgp = page.locator('select[name="sortby"] option[value="FGP"]');
    await expect(fgp).toBeEnabled();
    await page.locator('select[name="sortby"]').selectOption('FGP');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);

    await expect(page).toHaveURL(/sortby=FGP/);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await expect(page.locator('.ibl-data-table th.sorted-col').first()).toHaveText('FG%');
    await expect(page.locator('select[name="sortby"]')).toHaveValue('FGP');
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

  test('sortby changes the sorted column header', async ({ page }) => {
    await page.locator('select[name="phase"]').selectOption('regular');
    await page.locator('select[name="sortby"]').selectOption('PPG');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const firstSortedText = await page.locator('.ibl-data-table th.sorted-col').first().innerText();
    expect(firstSortedText).toBe('PTS');

    await page.locator('select[name="sortby"]').selectOption('REB');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const secondSortedText = await page.locator('.ibl-data-table th.sorted-col').first().innerText();
    expect(secondSortedText).toContain('REB');
    expect(secondSortedText).not.toBe(firstSortedText);
  });

  test('display limit caps row count', async ({ page }) => {
    await page.locator('select[name="phase"]').selectOption('regular');
    await page.locator('input[name="display"]').fill('3');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rowCount = await page.locator('.ibl-data-table tbody tr').count();
    expect(rowCount).toBeGreaterThanOrEqual(1);
    expect(rowCount).toBeLessThanOrEqual(3);
  });

  test('phase drives query: Regular Season returns more rows than Playoffs', async ({ page }) => {
    await page.locator('select[name="phase"]').selectOption('regular');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table tbody tr').first()).toBeVisible();
    const regularSeasonRows = await page.locator('.ibl-data-table tbody tr').count();

    await page.locator('select[name="phase"]').selectOption('playoffs');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table tbody tr').first()).toBeVisible();
    const playoffRows = await page.locator('.ibl-data-table tbody tr').count();

    // Seed has 24 regular-season career rows vs 2 playoff career rows — switching
    // phase re-runs against a different table, so the counts must differ.
    expect(playoffRows).toBeGreaterThan(0);
    expect(playoffRows).toBeLessThan(regularSeasonRows);
  });

  test('retired switch toggles results', async ({ page }) => {
    // Raise display limit so the single seeded retiree is not truncated below
    // the default top-N cutoff (default ranks would leave both counts equal).
    // Wait for the HTMX-boosted GET response between submissions — otherwise
    // the row count reads the previous render.
    await page.locator('input[name="display"]').fill('500');
    await page.locator('input[name="retirees"]').setChecked(true);
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    await page.locator('input[name="display"]').fill('500');
    await page.locator('input[name="retirees"]').setChecked(false);
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
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

  test('submit keeps the selected filters and stays on the career tab', async ({ page }) => {
    await page.locator('select[name="phase"]').selectOption('playoffs');
    await page.locator('input[name="mode"][value="averages"]').check();
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=career') && r.request().method() === 'GET'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);

    await expect(page).toHaveURL(/tab=career/);
    await expect(page.locator('select[name="phase"]')).toHaveValue('playoffs');
    await expect(page.locator('input[name="mode"][value="averages"]')).toBeChecked();
    await expect(page.locator('select[name="sortby"] option[value="PPG"]')).toHaveText('PPG');
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
    await page.goto('modules.php?name=Leaderboards&tab=career');
  });

  test('module shows inactive message when trivia mode is on', async ({ page }) => {
    await expect(page.getByText("Module isn't active")).toBeVisible();
  });
});
