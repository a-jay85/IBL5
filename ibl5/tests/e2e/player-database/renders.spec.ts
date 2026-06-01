import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Verify PlayerDatabase page renders after constructor parameter removal (PR 936).
// PlayerDatabaseView previously required a $service parameter that was never used;
// the ctor was simplified to no-arg. This spec confirms the module still renders.
test.use({ storageState: publicStorageState() });

test('PlayerDatabase page renders with search form', async ({ page }) => {
  await page.goto('modules.php?name=PlayerDatabase');
  await expect(page.locator('form[name="Search"]')).toBeVisible();
  await assertNoPhpErrors(page, 'on PlayerDatabase page load');
});
