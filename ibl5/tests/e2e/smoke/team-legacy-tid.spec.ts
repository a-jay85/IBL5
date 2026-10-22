import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Legacy `tid=` Team URLs (old emitters removed in 791007cf6, still in the
// wild) must show the requested team. `teamid` wins whenever its key is
// present, so `teamid=0` keeps meaning the Free Agents page.
test.use({ storageState: publicStorageState() });

const TEAM_URL = 'modules.php?name=Team&op=team';

async function expectRealTeam(page: Page, id: number): Promise<string> {
  await expect(page.locator('.team-page-layout').first()).toBeVisible();
  const logo = page.locator('.team-banner-logo img').first();
  await expect(logo).toHaveAttribute('src', new RegExp(`logo/${id}\\.jpg`));
  await expect(page.locator('.team-logo-fallback')).toHaveCount(0);
  const alt = (await logo.getAttribute('alt')) ?? '';
  expect(alt.trim().length).toBeGreaterThan(0);
  return alt;
}

async function expectFreeAgents(page: Page): Promise<void> {
  await expect(page.locator('h1.ibl-title').first()).toHaveText(/Free Agents/);
  await expect(page.locator('.team-logo-fallback').first()).toBeAttached();
  await expect(page.locator('.team-banner-logo')).toHaveCount(0);
}

test.describe('Team page legacy tid parameter', () => {
  test('tid alone renders the requested team', async ({ page }) => {
    await page.goto(`${TEAM_URL}&tid=1`);
    await assertNoPhpErrors(page, 'on Team tid=1');
    await expectRealTeam(page, 1);

    await page.goto(`${TEAM_URL}&tid=2`);
    await assertNoPhpErrors(page, 'on Team tid=2');
    await expectRealTeam(page, 2);
  });

  test('tid renders the same team as teamid for the same id', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamid=2`);
    await assertNoPhpErrors(page, 'on Team teamid=2');
    const viaTeamid = await expectRealTeam(page, 2);

    await page.goto(`${TEAM_URL}&tid=2`);
    await assertNoPhpErrors(page, 'on Team tid=2');
    const viaTid = await expectRealTeam(page, 2);

    expect(viaTid).toBe(viaTeamid);
  });

  test('teamid wins when both teamid and tid are present', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamid=1&tid=2`);
    await assertNoPhpErrors(page, 'on Team teamid=1&tid=2');
    await expectRealTeam(page, 1);
  });

  test('teamid=0 with a tid still renders Free Agents', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamid=0&tid=1`);
    await assertNoPhpErrors(page, 'on Team teamid=0&tid=1');
    await expectFreeAgents(page);
  });

  test('non-numeric tid falls back to Free Agents without PHP errors', async ({ page }) => {
    await page.goto(`${TEAM_URL}&tid=abc`);
    await assertNoPhpErrors(page, 'on Team tid=abc');
    await expectFreeAgents(page);
  });

  test('tid with legacy display and yr params renders the team', async ({ page }) => {
    await page.goto(`${TEAM_URL}&tid=1&display=chunk&yr=2002`);
    await assertNoPhpErrors(page, 'on Team tid=1&display=chunk&yr=2002');
    await expectRealTeam(page, 1);
  });
});
