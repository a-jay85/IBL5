import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { assertSortableTablePage } from '../helpers/sortable-table-page';

// All-Star Appearances — public page.
test.use({ storageState: publicStorageState() });

test.describe('All-Star Appearances flow', () => {
  test('page loads with title, table, and player rows', async ({ page }) => {
    await assertSortableTablePage(page, {
      url: 'modules.php?name=AllStarAppearances',
      minRows: 2,
      expectedTitle: /All-Star Appearances/i,
    });
  });

  test('player links navigate to player page', async ({ page }) => {
    await page.goto('modules.php?name=AllStarAppearances');
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    // Navigate to the player page and verify it loads
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from All-Star Appearances');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });
});
