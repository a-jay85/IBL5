import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.describe('Team Stats flow', () => {
  test('page renders tables during Regular Season', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('page renders tables during Playoffs', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Playoffs',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('page renders tables during Preseason', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Preseason',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    // Preseason may have no data — tables still render with empty rows
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('all 5 section headings visible', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const body = await page.locator('body').textContent();
    const expectedSections = [
      'Team Offense Totals',
      'Team Defense Totals',
      'Team Offense Averages',
      'Team Defense Averages',
      'Differentials',
    ];

    for (const section of expectedSections) {
      expect(
        body,
        `Section "${section}" should be visible`,
      ).toContain(section);
    }
  });

  test('each section has a data table with rows', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const tables = page.locator('.ibl-data-table');
    expect(await tables.count()).toBeGreaterThanOrEqual(5);

    for (let i = 0; i < 5; i++) {
      const rows = tables.nth(i).locator('tbody tr');
      expect(
        await rows.count(),
        `Table ${i} should have at least one row`,
      ).toBeGreaterThan(0);
    }
  });

  test('sortable tables support client-side sorting', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const sortableTable = page.locator('.ibl-data-table.sortable').first();
    await expect(sortableTable).toBeVisible();

    const header = sortableTable.locator('thead th').nth(2);
    await header.click();

    await expect(sortableTable).toBeVisible();

    await header.click();
    await expect(sortableTable).toBeVisible();
  });

  test('tables have team rows matching expected count', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');

    const firstTable = page.locator('.ibl-data-table').first();
    await expect(firstTable).toBeVisible();
    const rows = firstTable.locator('tbody tr');
    expect(await rows.count()).toBeGreaterThanOrEqual(20);
  });

  test('no PHP errors', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=TeamOffDefStats');
    await assertNoPhpErrors(page, 'on Team Stats page');
  });
});
