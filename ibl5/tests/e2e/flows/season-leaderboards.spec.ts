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
