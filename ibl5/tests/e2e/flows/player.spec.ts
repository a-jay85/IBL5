import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Player page — public, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Player page flow — active player', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
  });

  test('player page loads with trading card', async ({ page }) => {
    // Trading card flip container should be present
    const card = page.locator('.card-flip-container').first();
    await expect(card).toBeVisible();
  });

  test('player bio info is visible (position, team)', async ({ page }) => {
    // All player pages show position badge and team info
    const body = await page.locator('body').textContent();
    // Should contain position abbreviations somewhere
    const hasPosition = /\b(PG|SG|SF|PF|C)\b/.test(body ?? '');
    expect(hasPosition).toBe(true);
  });

  test('player navigation menu has stat view links', async ({ page }) => {
    // The player menu should have links to different stat views
    const navLinks = page.locator('.plr-nav__pill, .plr-nav a, a[href*="pageView="]');
    await expect(navLinks.first()).toBeVisible();
  });

  test('overview shows stats content', async ({ page }) => {
    // Overview page should display stats card with data
    const statsContent = page.locator('.player-stats-card, .stats-card, .stats-grid, table').first();
    await expect(statsContent).toBeVisible();
  });

  test('no PHP errors on player overview', async ({ page }) => {
    await assertNoPhpErrors(page, 'on player overview page');
  });
});

test.describe('Player page flow — stat views', () => {
  test('regular season totals view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=3');
    await expect(page.locator('.player-stats-card, .stats-card, .stats-grid, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on regular season totals');
  });

  test('regular season averages view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=4');
    await expect(page.locator('.player-stats-card, .stats-card, .stats-grid, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on regular season averages');
  });

  test('ratings and salary view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=9');
    await expect(page.locator('.player-stats-card, .stats-card, .stats-grid, table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on ratings and salary');
  });

  test('awards and news view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=1');
    // May have no awards data — just verify no errors
    await assertNoPhpErrors(page, 'on awards and news');
  });

  test('sim stats view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=10');
    await assertNoPhpErrors(page, 'on sim stats');
  });

  test('playoff stats view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=5');
    await assertNoPhpErrors(page, 'on playoff totals');
  });

  test('HEAT stats view loads', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&pageView=7');
    await assertNoPhpErrors(page, 'on HEAT totals');
  });
});

test.describe('Player page flow — edge cases', () => {
  test('player page with invalid PID shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=99999');
    await assertNoPhpErrors(page, 'on invalid PID');
  });

  test('player links from team page resolve correctly', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    const playerLink = page.locator('a[href*="name=Player"][href*="pid="]').first();
    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from team link');
    // Should display a trading card
    const card = page.locator('.card-flip-container').first();
    await expect(card).toBeVisible();
  });

  test('no PHP errors across multiple player stat views', async ({ page }) => {
    const views = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    for (const view of views) {
      await page.goto(`modules.php?name=Player&pa=showpage&pid=1&pageView=${view}`);
      await assertNoPhpErrors(page, `on player page view=${view}`);
    }
  });
});
