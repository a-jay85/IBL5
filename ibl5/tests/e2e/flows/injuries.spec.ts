import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Injuries — public page.
test.use({ storageState: publicStorageState() });

test.describe('Injuries flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Injuries');
  });

  test('at least 2 injured player rows', async ({ page }) => {
    // CI seed has pid=5 (5 days) and pid=7 (3 days)
    const teamRows = page.locator('.injuries-table tbody tr[data-team-id]');
    expect(await teamRows.count()).toBeGreaterThanOrEqual(2);
  });

  test('days cells have tooltip with return date', async ({ page }) => {
    // CI seed has ibl_sim_dates with End Date — tooltips should render
    const tooltips = page.locator('.injuries-table td .ibl-tooltip');
    await expect(tooltips.first()).toBeVisible();

    const title = await tooltips.first().getAttribute('title');
    expect(title).toBeTruthy();
    expect(title).toContain('Returns:');
  });

  test('player name links navigate to player page', async ({ page }) => {
    const playerLinks = page.locator('.injuries-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Injuries');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('team name cells link to team pages', async ({ page }) => {
    const teamLinks = page.locator('.injuries-table a[href*="teamid="]');
    const count = await teamLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);

    const href = await teamLinks.first().getAttribute('href');
    expect(href).toContain('name=Team');

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on team page from Injuries');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Injuries page');
  });

  // Characterization: the Injuries module reads ?teamid (modules/Injuries/index.php)
  // but never passes it to the service — getInjuredPlayersWithTeams() always returns
  // the whole league. So ?teamid=2 must STILL show injured players from other teams
  // (seed: pid=5 on teamid 2, pid=7 on teamid 14). This locks in the current no-op
  // behavior; if a real team filter is later added, update this test alongside it.
  // (See PR notes — the accepted-then-ignored param is a latent bug for the reviewer.)
  test('teamid param does not filter the injuries list (currently a no-op)', async ({
    page,
  }) => {
    await page.goto('modules.php?name=Injuries&teamid=2');

    await expect(
      page.locator('.injuries-table tbody tr[data-team-id="2"]').first(),
    ).toBeVisible();
    await expect(
      page.locator('.injuries-table tbody tr[data-team-id="14"]').first(),
    ).toBeVisible();
    await assertNoPhpErrors(page, 'on Injuries with teamid=2');
  });
});
