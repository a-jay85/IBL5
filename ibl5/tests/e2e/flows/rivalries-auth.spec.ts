import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Logged-in user highlight.
// CI setup (setup-docker-e2e/action.yml) wires IBL_TEST_USER as gm_username for
// teamid 1 (Metros), so the 1-2 franchise rivalry row carries user-team-row.

test.describe('Rivalries — logged-in user', () => {
  test('logged-in GM sees their franchise rivalry highlighted', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries');
    await assertNoPhpErrors(page);

    const highlighted = page.locator('tr.user-team-row');
    await expect(highlighted).toHaveCount(1);
    await expect(highlighted.locator('a[href*="op=teams&a=1&b=2"]')).toHaveCount(1);
  });
});
