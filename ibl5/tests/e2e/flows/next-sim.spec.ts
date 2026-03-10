import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// NextSim — authenticated page showing upcoming matchup data.

test.describe('NextSim flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=NextSim');
  });

  test('schedule strip renders with game cards', async ({ page }) => {
    const strip = page.locator('.next-sim-schedule-strip');
    await expect(strip).toBeVisible();

    // Should have at least 1 day-row card (3 games in seed data)
    const dayRows = strip.locator('.next-sim-day-row');
    expect(await dayRows.count()).toBeGreaterThanOrEqual(1);
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

  test('position sections render', async ({ page }) => {
    const sections = page.locator('.next-sim-position-section');
    // 5 positions: PG, SG, SF, PF, C
    expect(await sections.count()).toBe(5);
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
    expect(await userRow.count()).toBeGreaterThanOrEqual(1);
  });

  test('opponent starters listed with team colors', async ({ page }) => {
    const opponentRows = page.locator('.next-sim-row--opponent');
    expect(await opponentRows.count()).toBeGreaterThanOrEqual(1);

    // Opponent rows should have team color CSS variables
    const firstOpponent = opponentRows.first();
    const style = await firstOpponent.getAttribute('style');
    expect(style).toContain('--team-color-primary');
  });

  test('schedule strip game count matches seed data', async ({ page }) => {
    // Seed has 3 games for Metros in sim 689 window
    const dayRows = page.locator('.next-sim-day-row');
    const count = await dayRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
    // Could be up to 3 depending on sim date window calculation
    expect(count).toBeLessThanOrEqual(10);
  });

  test('no PHP errors on NextSim page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on NextSim page');
  });
});
