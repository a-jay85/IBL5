import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Season Leaderboards — public, no authentication required.
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
    await expect(rows.first()).toBeVisible();
  });

  test('table has sticky rank and name columns', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    await expect(table.locator('.sticky-col-1').first()).toBeVisible();
    await expect(table.locator('.sticky-col-2').first()).toBeVisible();
  });

  test('sorted column is highlighted', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    const sortedCol = table.locator('th.sorted-col');
    await expect(sortedCol.first()).toBeVisible();
  });

  test('changing sort category updates results', async ({ page }) => {
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();
    const defaultSortedText = await table.locator('th.sorted-col').first().textContent();

    await page.locator('select[name="sortby"]').selectOption('REB');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const newSortedText = await page.locator('.ibl-data-table').first().locator('th.sorted-col').first().textContent();
    expect(newSortedText).not.toBe(defaultSortedText);
  });

  test('filtering by team shows only that team players', async ({ page }) => {
    const teamSelect = page.locator('select[name="team"]');
    await teamSelect.selectOption('2');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const teamRows = page.locator('.ibl-data-table').first().locator('tbody tr[data-team-id]');
    await expect(teamRows.first()).toBeVisible();

    const rowCount = await teamRows.count();
    for (let i = 0; i < rowCount; i++) {
      expect(await teamRows.nth(i).getAttribute('data-team-id')).toBe('2');
    }
  });

  test('year filter reduces row count', async ({ page }) => {
    await page.locator('select[name="year"]').selectOption('2026');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rows2026 = page.locator('.ibl-data-table tbody tr');
    await expect(rows2026.first()).toBeVisible();
    const count2026 = await rows2026.count();
    expect(count2026).toBeGreaterThanOrEqual(5);

    await page.locator('select[name="year"]').selectOption('2025');
    await page.locator('.ibl-filter-form__submit').click();

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const rows2025 = page.locator('.ibl-data-table tbody tr');
    await expect(rows2025.first()).toBeVisible();
    expect(await rows2025.count()).toBeLessThan(count2026);
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
