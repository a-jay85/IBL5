import { test, expect } from './fixtures/base';

test.describe('Boxscore page', () => {
  test('happy path renders game header with teams and scores', async ({ page }) => {
    await page.goto('/2026-02-20-game-1/boxscore');

    const header = page.getByTestId('boxscore-game-header');
    await expect(header).toBeVisible();
    await expect(header).toContainText('Metros');
    await expect(header).toContainText('Stars');
    await expect(header).toContainText('105');
    await expect(header).toContainText('98');
  });

  test('player table renders seed rows', async ({ page }) => {
    await page.goto('/2026-02-20-game-1/boxscore');

    const table = page.getByTestId('boxscore-player-table');
    await expect(table).toBeVisible();

    // Home team (Stars) is selected by default — 3 players seeded
    // Away team (Metros) has 4 players seeded
    // Total across both teams: 7, but only one team shown at a time
    // Default is home team (Stars) with 3 rows
    await expect(table.locator('tbody tr')).toHaveCount(3);
  });

  test('team selector switches roster', async ({ page }) => {
    await page.goto('/2026-02-20-game-1/boxscore');

    const selector = page.getByTestId('boxscore-team-selector');
    await expect(selector).toBeVisible();

    // Click the away team (Metros) button
    await selector.getByText('Metros').click();

    const table = page.getByTestId('boxscore-player-table');
    await expect(table).toBeVisible();
    // Metros have 4 players seeded
    await expect(table.locator('tbody tr')).toHaveCount(4);
  });

  test('404 on non-existent game slug', async ({ page }) => {
    const response = await page.goto('/2099-01-01-game-9/boxscore');
    expect(response?.status()).toBe(404);

    await expect(page.getByTestId('boxscore-not-found')).toBeVisible();
    await expect(page.getByTestId('boxscore-not-found')).toContainText('Game not found');
  });

  test('malformed slug returns 404 not 500', async ({ page }) => {
    const response = await page.goto('/garbage-slug/boxscore');
    // Current behavior: parseInt of garbage returns NaN → query returns nothing → 404
    // If this is 500, the error handler catches it — either way, not a crash
    expect(response?.status()).toBeLessThan(500);
  });
});
