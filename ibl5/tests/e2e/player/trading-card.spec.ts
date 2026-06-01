import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Verify trading card back renders cleanly after removing the 4 always-zero
// playoff Double-Doubles/Triple-Doubles properties (PR 936).
// The <td> cells remain in the DOM but render as empty string, not '0'.
test.use({ storageState: publicStorageState() });

test.describe('Trading card back — DD/TD playoff cells', () => {
  test.beforeEach(async ({ page }) => {
    // pid=1 exists in ci-seed.sql (ibl_plr primary key row 1)
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
  });

  test('card back renders without PHP errors', async ({ page }) => {
    await expect(page.locator('.trading-card-back')).toBeAttached();
    await assertNoPhpErrors(page, 'on player trading card page');
  });

  test('Double-Doubles playoff cells are empty (not "0")', async ({ page }) => {
    const ddRow = page.locator('.trading-card-back .highs-table tr').filter({ hasText: 'Double-Doubles' });
    await expect(ddRow).toBeAttached();
    // Cells: 0=label, 1=RS Ssn, 2=RS Car, 3=Playoff Ssn, 4=Playoff Car
    await expect(ddRow.locator('td').nth(3)).toHaveText('');
    await expect(ddRow.locator('td').nth(4)).toHaveText('');
  });

  test('Triple-Doubles playoff cells are empty (not "0")', async ({ page }) => {
    const tdRow = page.locator('.trading-card-back .highs-table tr').filter({ hasText: 'Triple-Doubles' });
    await expect(tdRow).toBeAttached();
    await expect(tdRow.locator('td').nth(3)).toHaveText('');
    await expect(tdRow.locator('td').nth(4)).toHaveText('');
  });
});
