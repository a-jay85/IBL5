import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { assertSortableTablePage } from '../helpers/sortable-table-page';

test.describe('GM Contact List flow', () => {
  test('page loads with title, table, and 28 team rows', async ({ page }) => {
    await assertSortableTablePage(page, {
      url: 'modules.php?name=GMContactList',
      minRows: 28,
      expectedTitle: /GM Contact List/i,
    });
  });

  test('team link navigates to team page', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    const teamLink = page.locator('.ibl-data-table a[href*="name=Team"], .contact-table a[href*="name=Team"]').first();
    const href = await teamLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from GM Contact List link');
  });
});
