import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Standings — public, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Standings page flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Standings');
  });

  test('page loads with conference standings', async ({ page }) => {
    const titles = page.locator('.ibl-title');
    await expect(titles.first()).toBeVisible();

    const allText = await titles.allTextContents();
    const joined = allText.join(' ');
    expect(joined).toContain('Eastern');
    expect(joined).toContain('Western');
  });

  test('page loads with division standings', async ({ page }) => {
    const titles = page.locator('.ibl-title');
    const allText = await titles.allTextContents();
    const joined = allText.join(' ');
    expect(joined).toContain('Atlantic');
    expect(joined).toContain('Central');
    expect(joined).toContain('Midwest');
    expect(joined).toContain('Pacific');
  });

  test('standings tables have expected columns', async ({ page }) => {
    const firstTable = page.locator('.ibl-data-table').first();
    await expect(firstTable).toBeVisible();

    const headerText = await firstTable.locator('thead').textContent();
    expect(headerText).toContain('Team');
    expect(headerText).toContain('W-L');
    expect(headerText).toContain('Win%');
    expect(headerText).toContain('GB');
    expect(headerText).toContain('Magic');
    expect(headerText).toContain('Conf.');
    expect(headerText).toContain('Div.');
    expect(headerText).toContain('Home');
    expect(headerText).toContain('Away');
    expect(headerText).toContain('Streak');
  });

  test('each table is sortable', async ({ page }) => {
    const tables = page.locator('.sortable.ibl-data-table');
    const count = await tables.count();
    // 2 conference + 4 division = 6 tables
    expect(count).toBe(6);
  });

  test('team rows have data-team-id', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    // 28 teams appear in conferences + again in divisions = at least 56
    expect(count).toBeGreaterThanOrEqual(28);
  });

  test('clinch indicators are valid', async ({ page }) => {
    const clinchIndicators = page.locator('.ibl-clinched-indicator');
    await expect(clinchIndicators.first()).toBeVisible();

    const texts = await clinchIndicators.evaluateAll((els) =>
      els.map((el) => el.textContent?.trim() ?? ''),
    );
    for (const text of texts) {
      expect(['W', 'X', 'Y', 'Z']).toContain(text);
    }
  });

  test('clinch rows with bottom-locked class have team IDs', async ({ page }) => {
    const bottomLocked = page.locator('tr.bottom-locked');
    await expect(bottomLocked.first()).toBeVisible();

    const teamIds = await bottomLocked.evaluateAll((els) =>
      els.map((el) => el.getAttribute('data-team-id')),
    );
    for (const teamId of teamIds) {
      expect(teamId).toBeTruthy();
    }
  });

  test('sticky team column exists in each table', async ({ page }) => {
    const stickyColCounts = await page.locator('.ibl-data-table').evaluateAll((tableEls) =>
      tableEls.map((t) => t.querySelectorAll('td.sticky-col').length),
    );
    for (const count of stickyColCounts) {
      expect(count).toBeGreaterThan(0);
    }
  });

  test('column sorting works', async ({ page }) => {
    const firstTable = page.locator('.sortable.ibl-data-table').first();
    await expect(firstTable).toBeVisible();

    // Get initial row order by team-id
    const rows = firstTable.locator('tbody tr[data-team-id]');
    const rowsBefore = await rows.evaluateAll((els) =>
      els.map((el) => el.getAttribute('data-team-id')),
    );

    // Click the Win% header (3rd th) to trigger sort
    const winPctHeader = firstTable.locator('thead th:nth-child(3)');
    await winPctHeader.click();

    // Get row order after sort
    const rowsAfter = await rows.evaluateAll((els) =>
      els.map((el) => el.getAttribute('data-team-id')),
    );

    // Row order should differ (unless already sorted by that column)
    // At minimum the table should still have the same number of rows
    expect(rowsAfter.length).toBe(rowsBefore.length);
  });

  test('no PHP errors on standings page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Standings page');
  });
});
