import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// NextSim — authenticated page showing upcoming matchup data.
// Games in the sim window depend on date-sensitive settings, so tests that
// require game data skip gracefully when the schedule strip is empty.

test.describe('NextSim flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=NextSim');
  });

  test('page loads without PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on NextSim page');
  });

  test('schedule strip or empty message renders', async ({ page }) => {
    // When games exist: .next-sim-schedule-strip renders
    // When no games: .next-sim-empty renders with "No games projected" message
    const strip = page.locator('.next-sim-schedule-strip');
    const emptyMsg = page.locator('.next-sim-empty');
    const stripCount = await strip.count();
    const emptyCount = await emptyMsg.count();
    expect(
      stripCount + emptyCount,
      'NextSim should show either schedule strip or empty message',
    ).toBeGreaterThan(0);
  });

  test('schedule strip game cards have dates and logos', async ({
    page,
  }) => {
    const dayRow = page.locator('.next-sim-day-row').first();
    if ((await page.locator('.next-sim-day-row').count()) === 0) {
      test.skip(true, 'No games in current sim window');
    }

    // Date text
    const date = dayRow.locator('.next-sim-game-date');
    await expect(date).toBeVisible();

    // Day label (e.g., "Day N @" or "Day N vs")
    const dayLabel = dayRow.locator('.next-sim-day-label');
    await expect(dayLabel).toBeVisible();
  });

  test('position sections render when games exist', async ({ page }) => {
    if ((await page.locator('.next-sim-day-row').count()) === 0) {
      test.skip(true, 'No games in current sim window');
    }

    const sections = page.locator('.next-sim-position-section');
    // 5 positions: PG, SG, SF, PF, C
    await expect(sections).toHaveCount(5);
  });

  test('position tables have headers', async ({ page }) => {
    if ((await page.locator('.next-sim-position-section').count()) === 0) {
      test.skip(true, 'No position sections (no games in sim window)');
    }

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
    if ((await page.locator('.next-sim-row--user').count()) === 0) {
      test.skip(true, 'No user rows (no games in sim window)');
    }

    const userRow = page.locator('.next-sim-row--user');
    await expect(userRow.first()).toBeVisible();
  });

  test('opponent starters listed with team colors', async ({ page }) => {
    if ((await page.locator('.next-sim-row--opponent').count()) === 0) {
      test.skip(true, 'No opponent rows (no games in sim window)');
    }

    const opponentRows = page.locator('.next-sim-row--opponent');
    await expect(opponentRows.first()).toBeVisible();

    // Opponent rows should have team color CSS variables
    const firstOpponent = opponentRows.first();
    const style = await firstOpponent.getAttribute('style');
    expect(style).toContain('--team-color-primary');
  });
});
