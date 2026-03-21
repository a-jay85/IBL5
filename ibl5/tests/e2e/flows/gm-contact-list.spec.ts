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

  test('table has rows for all 28 teams', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    // CI seed has 28 real teams
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
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

  test('clicking sort header does not error', async ({ page }) => {
    const sortHeader = page.locator('.sortable thead th').first();
    await sortHeader.click();
    // After clicking, table should still be visible (no JS error broke the page)
    await expect(page.locator('.sortable').first()).toBeVisible();
    await assertNoPhpErrors(page, 'after sorting GM Contact List');
  });

  test('team link navigates to team page', async ({ page }) => {
    const teamLink = page.locator('.ibl-data-table a[href*="name=Team"], .contact-table a[href*="name=Team"]').first();
    const href = await teamLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from GM Contact List link');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on GM Contact List');
  });
});
