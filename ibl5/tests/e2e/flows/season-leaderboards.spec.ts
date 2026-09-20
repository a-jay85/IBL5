import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import type { Page } from '@playwright/test';

// The filter form sits inside hx-boost, so a submit swaps content in place
// instead of navigating. Wait for the POST response, then let the retrying
// assertions in each test cover the gap between response and DOM swap.
async function submitFilters(page: Page): Promise<void> {
  await Promise.all([
    page.waitForResponse((r) => r.url().includes('SeasonLeaderboards') && r.request().method() === 'POST'),
    page.locator('.ibl-filter-form__submit').click(),
  ]);
}

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
    await submitFilters(page);

    const sortedCol = page.locator('.ibl-data-table').first().locator('th.sorted-col').first();
    await expect(sortedCol).toBeVisible();
    await expect(sortedCol).not.toHaveText(defaultSortedText ?? '');
  });

  test('filtering by team shows only that team players', async ({ page }) => {
    const teamSelect = page.locator('select[name="team"]');
    await teamSelect.selectOption('2');
    await submitFilters(page);

    const table = page.locator('.ibl-data-table').first();
    await expect(table.locator('tbody tr[data-team-id]:not([data-team-id="2"])')).toHaveCount(0);
    await expect(table.locator('tbody tr[data-team-id="2"]').first()).toBeVisible();
  });

  test('year filter reduces row count', async ({ page }) => {
    const rows = page.locator('.ibl-data-table tbody tr');

    await page.locator('select[name="year"]').selectOption('2026');
    await submitFilters(page);

    await expect(rows.first()).toBeVisible();
    await expect.poll(() => rows.count()).toBeGreaterThanOrEqual(5);
    const count2026 = await rows.count();

    await page.locator('select[name="year"]').selectOption('2025');
    await submitFilters(page);

    await expect.poll(() => rows.count()).toBeLessThan(count2026);
    await expect(rows.first()).toBeVisible();
  });

  test('limit input controls row count', async ({ page }) => {
    await page.locator('input[name="limit"]').fill('5');
    await submitFilters(page);

    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect.poll(() => rows.count()).toBeLessThanOrEqual(5);
    await expect(rows.first()).toBeVisible();
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
