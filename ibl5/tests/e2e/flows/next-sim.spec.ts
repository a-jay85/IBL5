import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// NextSim — authenticated page showing upcoming matchup data.
// CI seed (year 2026) provides games in the sim window.

test.describe('NextSim flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=NextSim');
  });

  test('page loads without PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on NextSim page');
  });

  test('user starter highlighted with orange styling', async ({ page }) => {
    const userRow = page.locator('.next-sim-row--user');
    await expect(userRow.first()).toBeVisible();
  });

  test('opponent starters listed with team colors', async ({ page }) => {
    const opponentRows = page.locator('.next-sim-row--opponent');
    await expect(opponentRows.first()).toBeVisible();

    // Opponent rows should have team color CSS variables resolving to real hex values
    const firstOpponent = opponentRows.first();
    const style = (await firstOpponent.getAttribute('style')) ?? '';
    expect(
      style,
      'opponent row must carry a resolved team color, not an empty --team-color-primary declaration',
    ).toMatch(/--team-color-primary:\s*#[0-9a-fA-F]{3,8}/);
  });
});
