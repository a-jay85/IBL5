import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Olympics admin page — verify pipeline loads in Olympics context.
test.describe('Olympics admin smoke tests', () => {
  test('updateAllTheThings loads in Olympics context', async ({ page }) => {
    await page.goto('scripts/updateAllTheThings.php?league=olympics');
    // Verify the page indicates Olympics mode in the initialization section
    await expect(page.locator('body')).toContainText('Olympics');
    await assertNoPhpErrors(page, 'on Olympics pipeline');
  });
});
