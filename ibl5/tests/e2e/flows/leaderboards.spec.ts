import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Leaderboards — public, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Season Leaderboards flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');

    // Skip all tests if module is hidden (e.g. Trivia Mode is on)
    const body = await page.locator('body').textContent();
    if (body?.includes("Module isn't active")) {
      test.skip(true, 'SeasonLeaderboards module is not active (Trivia Mode may be on)');
    }
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

    // Default sort is PPG — should have sorted-col class
    const sortedCol = table.locator('th.sorted-col');
    expect(await sortedCol.count()).toBeGreaterThanOrEqual(1);
  });

  test('changing sort category updates results', async ({ page }) => {
    // Get default sorted column text
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();
    const defaultSortedText = await table.locator('th.sorted-col').first().textContent();

    // Change sort to REB (value 2) and submit
    await page.locator('select[name="sortby"]').selectOption('2');
    await page.locator('.ibl-filter-form__submit').click();

    // Wait for page reload
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();

    const newSortedText = await page.locator('.ibl-data-table').first().locator('th.sorted-col').first().textContent();
    expect(newSortedText).not.toBe(defaultSortedText);
  });

  test('filtering by team shows only that team players', async ({ page }) => {
    // Pick the first non-"All" team option
    const teamSelect = page.locator('select[name="team"]');
    const options = teamSelect.locator('option');
    const optionCount = await options.count();

    // Skip "All" (index 0), pick the first real team
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
        // All rows should have the same team ID
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
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Season Leaderboards page`,
      ).not.toContain(pattern);
    }
  });
});

test.describe('Career Leaderboards flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');

    // Skip all tests if module is hidden (e.g. Trivia Mode is on)
    const body = await page.locator('body').textContent();
    if (body?.includes("Module isn't active")) {
      test.skip(true, 'CareerLeaderboards module is not active (Trivia Mode may be on)');
    }
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
    // Select a different board type option
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
    // Submit with retirees included (default: active=0 means "Yes include")
    await page.locator('select[name="active"]').selectOption('0');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    // Submit with retirees excluded (active=1 means "No")
    await page.locator('select[name="active"]').selectOption('1');
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const withoutRetireesCount = await page.locator('.ibl-data-table').first().locator('tbody tr').count();

    // Both should have results, and counts should differ to confirm filter works
    expect(withRetireesCount).toBeGreaterThan(0);
    expect(withoutRetireesCount).toBeGreaterThan(0);
    expect(withRetireesCount).not.toBe(withoutRetireesCount);
  });

  test('no PHP errors on career leaderboards', async ({ page }) => {
    // Check initial page
    let body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Career Leaderboards form page`,
      ).not.toContain(pattern);
    }

    // Check results page
    await page.locator('.ibl-filter-form__submit').click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Career Leaderboards results page`,
      ).not.toContain(pattern);
    }
  });
});
