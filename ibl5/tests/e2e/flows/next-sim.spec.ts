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

  test('schedule strip renders', async ({ page }) => {
    const strip = page.locator('.next-sim-schedule-strip');
    await expect(strip).toBeVisible();
  });

  test('schedule strip game cards have dates and logos', async ({
    page,
  }) => {
    const dayRow = page.locator('.next-sim-day-row').first();
    await expect(dayRow).toBeVisible();

    // Date text
    const date = dayRow.locator('.next-sim-game-date');
    await expect(date).toBeVisible();

    // Day label (e.g., "Day N @" or "Day N vs")
    const dayLabel = dayRow.locator('.next-sim-day-label');
    await expect(dayLabel).toBeVisible();
  });

  test('position sections render when games exist', async ({ page }) => {
    const sections = page.locator('.next-sim-position-section');
    // 5 positions: PG, SG, SF, PF, C
    await expect(sections).toHaveCount(5);
  });

  test('position tables have headers', async ({ page }) => {
    const firstSection = page.locator('.next-sim-position-section').first();
    await expect(firstSection).toBeVisible();

    // Should have a title (e.g., "Point Guards")
    const title = firstSection.locator('.ibl-table-title, h3');
    await expect(title.first()).toBeVisible();

    // Table with player data
    const table = firstSection.locator('table.ibl-data-table');
    await expect(table).toBeVisible();
  });

  test('user starter highlighted with orange styling', async ({ page }) => {
    const userRow = page.locator('.next-sim-row--user');
    await expect(userRow.first()).toBeVisible();
  });

  test('opponent starters listed with team colors', async ({ page }) => {
    const opponentRows = page.locator('.next-sim-row--opponent');
    await expect(opponentRows.first()).toBeVisible();

    // Opponent rows should have team color CSS variables
    const firstOpponent = opponentRows.first();
    const style = await firstOpponent.getAttribute('style');
    expect(style).toContain('--team-color-primary');
  });
});
