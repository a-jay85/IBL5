import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.describe('GM Contact List flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/GM Contact List/i);
  });

  test('contact table is visible with expected columns', async ({ page }) => {
    const table = page.locator('.contact-table, .ibl-data-table').first();
    await expect(table).toBeVisible();

    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Team');
    expect(headerText).toContain('Name');
  });

  test('table has team rows with data-team-id', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('team cells contain team names', async ({ page }) => {
    const table = page.locator('.contact-table, .ibl-data-table').first();
    const teamCells = table.locator('.ibl-team-cell--colored, td').first();
    await expect(teamCells).toBeVisible();
  });

  test('table is sortable', async ({ page }) => {
    const sortableTable = page.locator('.sortable');
    await expect(sortableTable.first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on GM Contact List');
  });
});
