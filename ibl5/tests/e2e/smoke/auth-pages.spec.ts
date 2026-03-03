import { test, expect } from '../fixtures/auth';

// Authenticated page smoke tests — these use stored auth state.

test.describe('Authenticated page smoke tests', () => {
  test('trading page loads', async ({ page }) => {
    await page.goto('modules.php?name=Trading');
    await expect(page.locator('.ibl-title')).toContainText(/trading/i);
  });

  test('trading team select table is visible', async ({ page }) => {
    await page.goto('modules.php?name=Trading');
    await expect(page.locator('.trading-team-select')).toBeVisible();
  });

  test('depth chart entry page loads', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    // Should not show login prompt — user is authenticated
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });
});
