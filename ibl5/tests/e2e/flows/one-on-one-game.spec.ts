import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// OneOnOneGame — public page, fan mini-game.
test.use({ storageState: publicStorageState() });

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

  test('submitting a match produces results', async ({ page }) => {
    // The page has a form with two visible select dropdowns and a submit button
    const form = page.locator('form').filter({ has: page.getByRole('button', { name: /begin/i }) });
    const selects = form.locator('select');
    const selectCount = await selects.count();
    expect(selectCount).toBeGreaterThanOrEqual(2);

    const firstSelect = selects.nth(0);
    const secondSelect = selects.nth(1);

    // Get options from first select (skip any blank/placeholder)
    const options = firstSelect.locator('option');
    const optionCount = await options.count();
    expect(optionCount).toBeGreaterThanOrEqual(2);

    // Select different players in each dropdown using index
    await firstSelect.selectOption({ index: 1 });
    await secondSelect.selectOption({ index: Math.min(2, optionCount - 1) });

    // Submit the form
    await page.getByRole('button', { name: /begin/i }).click();

    // Wait for results page — verify error message disappears or results appear
    await page.waitForLoadState('networkidle');
    const body = await page.locator('body').textContent();
    // Results should contain score or game info (not just the form)
    const hasResults =
      body?.includes('Score') ||
      body?.includes('Winner') ||
      body?.includes('won') ||
      body?.includes('pts') ||
      body?.includes('Final') ||
      body?.includes('Game ID') ||
      body?.includes('Quarter');
    expect(hasResults).toBe(true);
    await assertNoPhpErrors(page, 'on OneOnOneGame results page');
  });

  test('no PHP errors on game page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on OneOnOneGame page');
  });
});
