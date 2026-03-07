import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Leaderboards — public, no authentication required.
// Serial: trivia-on and trivia-off blocks set the same setting (Trivia Mode).
test.describe.configure({ mode: 'serial' });

// ---- Season Leaderboards: trivia off (normal) ----

test.describe('Season Leaderboards flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=SeasonLeaderboards');
  });

  test('page loads with filter form', async ({ page }) => {
    const form = page.locator('.ibl-filter-form');
    await expect(form).toBeVisible();

    await expect(page.locator('select[name="team"]')).toBeVisible();
    await expect(page.locator('select[name="year"]')).toBeVisible();
    await expect(page.locator('select[name="sortby"]')).toBeVisible();
    await expect(page.locator('input[name="limit"]')).toBeVisible();
  });

  test('default results table present', async ({ page }) => {
    const table = page.locator('.ibl-data-table');
    await expect(table.first()).toBeVisible();
    const rows = table.first().locator('tbody tr');
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('table has sticky rank and name columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    expect(await table.locator('.sticky-col-1').count()).toBeGreaterThan(0);
    expect(await table.locator('.sticky-col-2').count()).toBeGreaterThan(0);
  });

  test('sorted column is highlighted', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    const sortedCol = table.locator('th.sorted-col');
    expect(await sortedCol.count()).toBeGreaterThanOrEqual(1);
  });

  test('changing sort category updates results', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();
    const defaultSortedText = await table.locator('th.sorted-col').first().textContent();

    await page.locator('select[name="sortby"]').selectOption('2');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const newSortedText = await page.locator('.ibl-data-table').first().locator('th.sorted-col').first().textContent();
    expect(newSortedText).not.toBe(defaultSortedText);
  });

  test('filtering by team shows only that team players', async ({ page }) => {
    const teamSelect = page.locator('select[name="team"]');
    const options = teamSelect.locator('option');
    const optionCount = await options.count();

    if (optionCount > 1) {
      const teamValue = await options.nth(1).getAttribute('value');
      await teamSelect.selectOption(teamValue!);
      await page.locator('.ibl-filter-form__submit').click();

      await expect(page.locator('.ibl-data-table').first()).toBeVisible();

      const teamRows = page.locator('.ibl-data-table').first().locator('tbody tr[data-team-id]');
      const teamIds = await teamRows.evaluateAll((els) =>
        els.map((el) => el.getAttribute('data-team-id')),
      );
      if (teamIds.length > 0) {
        for (const id of teamIds) {
          expect(id).toBe(teamIds[0]);
        }
      }
    }
  });

  test('limit input controls row count', async ({ page }) => {
    await page.locator('input[name="limit"]').fill('5');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    expect(await rows.count()).toBeLessThanOrEqual(5);
  });

  test('no PHP errors on season leaderboards', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Season Leaderboards page');
  });
});

// ---- Season Leaderboards: trivia on ----

test.describe('Season Leaderboards: trivia mode', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'On' });
    await page.goto('modules.php?name=SeasonLeaderboards');
  });

  test('module shows inactive message when trivia mode is on', async ({ page }) => {
    await expect(page.getByText("Module isn't active")).toBeVisible();
  });
});

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
    expect(await rows.count()).toBeGreaterThan(0);
  });

  test('table has sticky rank and name columns', async ({ page }) => {
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const table = page.locator('.ibl-data-table').first();
    expect(await table.locator('.sticky-col-1').count()).toBeGreaterThan(0);
    expect(await table.locator('.sticky-col-2').count()).toBeGreaterThan(0);
  });

  test('sorted column is highlighted', async ({ page }) => {
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const sortedCol = page.locator('.ibl-data-table').first().locator('th.sorted-col');
    expect(await sortedCol.count()).toBeGreaterThanOrEqual(1);
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
      expect(await rows.count()).toBeGreaterThan(0);
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
