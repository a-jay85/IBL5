import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// OneOnOneGame — public page, fan mini-game.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('One-on-One Game flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=OneOnOneGame');
  });

  test('page loads with title', async ({ page }) => {
    const body = await page.locator('body').textContent();
    expect(body).toContain('One-on-One');
  });

  test('player selection dropdowns are available', async ({ page }) => {
    // Two player select dropdowns for Player One and Player Two
    const selects = page.locator('select');
    const count = await selects.count();
    expect(count).toBeGreaterThanOrEqual(2);
  });

  test('player dropdowns have options', async ({ page }) => {
    const options = page.locator('select').first().locator('option');
    const count = await options.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('begin match button is present', async ({ page }) => {
    const button = page.getByRole('button', { name: /begin|match|play/i });
    await expect(button).toBeVisible();
  });

  test('review old game section is present', async ({ page }) => {
    const reviewButton = page.getByRole('button', { name: /review|old game/i });
    await expect(reviewButton).toBeVisible();
  });

  test('no PHP errors on game page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on OneOnOneGame page');
  });
});
