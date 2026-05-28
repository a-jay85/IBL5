import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Series Records — public page.
test.use({ storageState: publicStorageState() });

test.describe('Series Records flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=SeriesRecords');
  });

  test('table has team rows', async ({ page }) => {
    const rows = page.locator('.sticky-table tbody tr');
    // CI seed has ~3 teams; production has 28
    expect(await rows.count()).toBeGreaterThanOrEqual(2);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Series Records page');
  });
});
