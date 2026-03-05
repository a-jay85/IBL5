import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Olympics admin page — verify pipeline loads in Olympics context.
test.describe('Olympics admin smoke tests', () => {
  test('updateAllTheThings loads in Olympics context', async ({ page }) => {
    await page.goto('scripts/updateAllTheThings.php?league=olympics');
    const body = await page.locator('body').textContent();
    // Verify the page indicates Olympics mode in the initialization section
    expect(body).toContain('Olympics');
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" on Olympics pipeline`).not.toContain(pattern);
    }
  });
});
