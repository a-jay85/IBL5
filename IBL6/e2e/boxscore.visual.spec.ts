import { test, expect } from './fixtures/base';

test.describe('Boxscore visual regression', () => {
  test('desktop full-page', async ({ page }) => {
    await page.goto('/2026-02-20-game-1/boxscore');
    await expect(page.getByTestId('boxscore-player-table')).toBeVisible();

    await expect(page).toHaveScreenshot('boxscore-desktop.png', {
      fullPage: true,
      animations: 'disabled',
    });
  });

  test('mobile full-page', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto('/2026-02-20-game-1/boxscore');
    await expect(page.getByTestId('boxscore-player-table')).toBeVisible();

    await expect(page).toHaveScreenshot('boxscore-mobile.png', {
      fullPage: true,
      animations: 'disabled',
    });
  });
});
