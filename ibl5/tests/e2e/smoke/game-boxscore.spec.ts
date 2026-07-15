import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Public page smoke test for the GameBoxscore module — a single game's boxscore,
// no auth required. Seed facts (2026-02-20, game_of_that_day = 1) are pinned
// against tests/e2e/fixtures/ci-seed.sql:
//   - Header score: visitor Metros 105 (28+26+27+24), home Stars 98 (24+25+24+25).
//   - Away panel = Metros (tid 1), 8 player rows; team pts total 114.
//   - Home panel = Stars (tid 2), 3 player rows; team pts total 55.
//   - Default selected team = home (Stars) per the CSS radio-tab default checked.
//
// NOTE: the seed's inline comment near its 2026-02-20 INSERT says "4 Metros + 3
// Stars = 7 rows" — that comment is stale. The actual INSERT has 8 Metros rows
// (pids 1, 2, 20, 21, 22, 25, 26, 27) + 3 Stars rows (pids 4, 5, 12) = 11 rows.
// Assertions below are pinned to the real row counts, not the stale comment.

test.describe('GameBoxscore smoke tests', () => {
  test('route renders, header + no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=1');
    await expect(page.locator('.game-boxscore__scoreboard')).toContainText('Metros');
    await expect(page.locator('.game-boxscore__scoreboard')).toContainText('Stars');
    await expect(page.locator('.game-boxscore__scoreboard')).toContainText('105');
    await expect(page.locator('.game-boxscore__scoreboard')).toContainText('98');
    await expect(page.locator('.game-boxscore__scoreboard')).toContainText('Game 1');
    await assertNoPhpErrors(page, 'boxscore 2026-02-20 game 1');
  });

  test('default view = home (Stars)', async ({ page }) => {
    await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=1');
    await expect(page.locator('[data-team-panel="home"]')).toBeVisible();
    await expect(page.locator('[data-team-panel="away"]')).not.toBeVisible();
    await expect(page.locator('[data-team-panel="home"] tbody tr')).toHaveCount(3);
    await expect(page.locator('[data-team-panel="home"]')).toContainText('Stars Guard');
  });

  test('team selector flips to away (Metros)', async ({ page }) => {
    await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=1');
    await page.locator('label[for="boxscore-team-away"]').click();
    await expect(page.locator('[data-team-panel="away"]')).toBeVisible();
    await expect(page.locator('[data-team-panel="home"]')).not.toBeVisible();
    await expect(page.locator('[data-team-panel="away"] tbody tr')).toHaveCount(8);
    await expect(page.locator('[data-team-panel="away"]')).toContainText('Metros PG');
  });

  test('column sort is descending-first (sorttable.js)', async ({ page }) => {
    await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=1');
    await page.locator('label[for="boxscore-team-away"]').click();
    await expect(page.locator('[data-team-panel="away"]')).toBeVisible();
    await page.locator('[data-team-panel="away"] thead th[data-col="pts"]').click();
    await expect(page.locator('[data-team-panel="away"] tbody tr').first()).toContainText('Metros PG');
  });

  test('totals row (tfoot)', async ({ page }) => {
    await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=1');
    await page.locator('label[for="boxscore-team-away"]').click();
    await expect(page.locator('[data-team-panel="away"]')).toBeVisible();
    await expect(page.locator('[data-team-panel="away"] tfoot tr')).toContainText('114');
    await page.locator('label[for="boxscore-team-home"]').click();
    await expect(page.locator('[data-team-panel="home"]')).toBeVisible();
    await expect(page.locator('[data-team-panel="home"] tfoot tr')).toContainText('55');
  });

  test('empty state — valid date/game with no rows', async ({ page }) => {
    const response = await page.goto('modules.php?name=GameBoxscore&date=2020-01-01&game=1');
    expect(response?.status()).toBe(404);
    await expect(page.locator('.game-boxscore-not-found')).toBeVisible();
    await assertNoPhpErrors(page, 'boxscore 2020-01-01 game 1 (empty state)');
  });

  test('[negative] malformed input never emits a PHP error', async ({ page }) => {
    const response = await page.goto('modules.php?name=GameBoxscore&date=not-a-date&game=1');
    expect(response?.status()).toBe(404);
    await expect(page.locator('.game-boxscore-not-found')).toBeVisible();
    await assertNoPhpErrors(page, 'boxscore malformed date');
    await expect(page.locator('body')).not.toContainText('not-a-date');

    const response2 = await page.goto('modules.php?name=GameBoxscore&date=2026-02-20&game=abc');
    expect(response2?.status()).toBe(404);
    await expect(page.locator('.game-boxscore-not-found')).toBeVisible();
    await assertNoPhpErrors(page, 'boxscore malformed game');
    await expect(page.locator('body')).not.toContainText('abc');
  });
});
