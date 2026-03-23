import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Cross-module navigation — verify links between modules resolve correctly.
// All read-only — no data mutation.

test.describe('Cross-module navigation', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Ending Year': '2026' });
  });

  test('standings → click team → team page loads', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on Standings');

    const teamLink = page.locator('.ibl-data-table a[href*="name=Team"]').first();
    await expect(teamLink).toBeVisible();

    const href = await teamLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Team page from Standings');
    // Team page should render content (table or heading)
    await expect(page.locator('.ibl-data-table, .ibl-title, h2').first()).toBeVisible();
  });

  test('team page roster → click player → player page loads', async ({ page }) => {
    // Navigate to standings first, then click first team
    await page.goto('modules.php?name=Standings');
    const teamLink = page.locator('.ibl-data-table a[href*="name=Team"]').first();
    const teamHref = await teamLink.getAttribute('href');
    await page.goto(teamHref!);
    await assertNoPhpErrors(page, 'on team page');

    const playerLink = page.locator('a[href*="name=Player"][href*="pid="]').first();
    const count = await playerLink.count();
    expect(count, 'CI seed must provide player links on team page').toBeGreaterThan(0);

    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Player page from Team roster');
    // Player page should show player content
    await expect(page.locator('h2, h3, .ibl-title').first()).toBeVisible();
  });

  test('season leaderboard → click leader → player page loads', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    await assertNoPhpErrors(page, 'on Season Leaderboards');

    const playerLink = page.locator('a[href*="name=Player"][href*="pid="]').first();
    await expect(playerLink).toBeVisible();

    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Player page from Season Leaderboards');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('draft history → click player → player page loads', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    await assertNoPhpErrors(page, 'on Draft History');

    const playerLink = page.locator('a[href*="name=Player"][href*="pid="]').first();
    const count = await playerLink.count();
    expect(count, 'CI seed must provide player links on Draft History page').toBeGreaterThan(0);

    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Player page from Draft History');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('injuries → click player → player page loads', async ({ page }) => {
    await page.goto('modules.php?name=Injuries');
    await assertNoPhpErrors(page, 'on Injuries');

    const playerLink = page.locator('.injuries-table a[href*="pid="]');
    const count = await playerLink.count();
    expect(count, 'CI seed must provide injured players').toBeGreaterThan(0);

    const href = await playerLink.first().getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Player page from Injuries');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('all-star appearances → click player → player page loads', async ({ page }) => {
    await page.goto('modules.php?name=AllStarAppearances');
    await assertNoPhpErrors(page, 'on All-Star Appearances');

    const playerLink = page.locator('.ibl-data-table a[href*="pid="]');
    const count = await playerLink.count();
    expect(count, 'CI seed must provide All-Star data').toBeGreaterThan(0);

    const href = await playerLink.first().getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Player page from All-Star Appearances');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('no PHP errors across standings → team → player chain', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on Standings (chain)');

    const teamLink = page.locator('.ibl-data-table a[href*="name=Team"]').first();
    const teamHref = await teamLink.getAttribute('href');
    await page.goto(teamHref!);
    await assertNoPhpErrors(page, 'on Team page (chain)');

    const playerLink = page.locator('a[href*="name=Player"][href*="pid="]').first();
    const playerCount = await playerLink.count();
    if (playerCount > 0) {
      const playerHref = await playerLink.getAttribute('href');
      await page.goto(playerHref!);
      await assertNoPhpErrors(page, 'on Player page (chain)');
    }
  });
});
