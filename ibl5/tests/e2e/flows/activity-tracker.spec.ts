import { test, expect } from '../fixtures/base';
import { publicStorageState } from '../helpers/public-storage-state';
import { assertSortableTablePage } from '../helpers/sortable-table-page';

// Activity Tracker — public page.
test.use({ storageState: publicStorageState() });

test.describe('Activity Tracker flow', () => {
  test('page loads with title, table, and 28 team rows', async ({ page }) => {
    await assertSortableTablePage(page, {
      url: 'modules.php?name=ActivityTracker',
      minRows: 28,
      expectedTitle: /Activity Tracker/i,
    });
  });

  test('team cells link to Team module', async ({ page }) => {
    await page.goto('modules.php?name=ActivityTracker');
    const firstRowLink = page.locator('tr[data-team-id] a[href*="name=Team"]').first();
    await expect(firstRowLink).toBeVisible();
  });
});
