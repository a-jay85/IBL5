import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

// Olympics public pages — verify league-context table resolution works.
// These pages append ?league=olympics to switch to Olympics context.
test.use({ storageState: publicStorageState() });

test.describe('Olympics page smoke tests', () => {
  test('standings page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Standings&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings&league=olympics');
    await expect(page.locator('.ibl-title')).toContainText(/Olympics Standings/i);
    await expect(page.locator('.ibl-data-table')).toBeVisible();
    const tableText = await page.locator('.ibl-data-table').textContent();
    expect(tableText ?? '').not.toContain('Eastern Conference');
    expect(tableText ?? '').not.toContain('Western Conference');
  });

  test('team page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Team&op=team&teamid=1&league=olympics');
    // teamid=1 in Olympics context must resolve against ibl_olympics_team_info.
    // Seed (Eagles) vs wtdb (USA) disagree on team name — using structural invariant per §1.2 rule 3.
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await expect(page.locator('h2').filter({ hasText: 'Current Season' }).first()).toBeVisible();
  });

  test('player page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Player&pa=showpage&pid=1&league=olympics');
    // pid=1 rendered in Olympics context — player page must render without crashing.
    // Root cause (bug 6.24): ibl_olympics_plr had no seed row for pid=1, so
    // PlayerRepository::loadByID threw RuntimeException → HTTP 500. Fixed by seeding
    // ibl_olympics_plr. Player overview renders .player-stats-card (game log), not
    // .ibl-data-table (that class is used by standings/team pages, not player overview).
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await expect(page.locator('.player-stats-card').first()).toBeVisible();
  });
});

test.describe('Olympics nav filtering', () => {
  test('Olympics nav: IBL-only Season links absent', async ({ page }) => {
    await page.goto('index.php?league=olympics');
    await assertNoPhpErrors(page, 'on index.php?league=olympics');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).click();

    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Standings' }).first()).toBeVisible();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Cap Space' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Draft Pick Locator' })).not.toBeAttached();
  });

  test('Olympics nav: IBL-only History links absent', async ({ page }) => {
    await page.goto('index.php?league=olympics');
    await assertNoPhpErrors(page, 'on index.php?league=olympics');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'History' }).click();

    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Transaction History' }).first()).toBeVisible();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Record Holders' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Award History' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Season Leaderboards' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Career Leaderboards' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise Record Book' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise History' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'All-Star Appearances' })).not.toBeAttached();
  });

  test('IBL nav: all links present', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');
    const nav = desktopNav(page);

    await nav.getByRole('button', { name: 'Season' }).click();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Cap Space' }).first()).toBeVisible();

    await nav.getByRole('button', { name: 'History' }).click();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise History' }).first()).toBeVisible();
  });
});
