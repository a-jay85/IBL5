import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Draft Pick Locator — public page.
test.use({ storageState: publicStorageState() });

test.describe('Draft Pick Locator flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DraftPickLocator');
  });

  test('has at least 28 team rows', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(28);
  });

  test('own picks and traded picks are distinguished', async ({ page }) => {
    const ownPicks = page.locator('.draft-pick-own');
    const tradedPicks = page.locator('.draft-pick-traded');
    const ownCount = await ownPicks.count();
    const tradedCount = await tradedPicks.count();
    expect(ownCount + tradedCount).toBeGreaterThan(0);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft Pick Locator page');
  });
});
