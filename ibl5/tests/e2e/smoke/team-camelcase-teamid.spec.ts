import type { Page } from '@playwright/test';
import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// camelCase `teamID=` links (posted by IBLbot before this change, permanent in
// Discord history) must open the requested team in the Team, Schedule and
// DraftHistory modules. Lowercase `teamid` wins whenever both keys are present.
test.use({ storageState: publicStorageState() });

const TEAM_URL = 'modules.php?name=Team&op=team';
const SCHEDULE_URL = 'modules.php?name=Schedule';
const DRAFT_URL = 'modules.php?name=DraftHistory';

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
  await expect(page.locator('.team-logo-fallback img')).toHaveCount(0);
  await expect(page.locator('.team-banner-logo')).toHaveCount(0);
}

test.describe('Team page camelCase teamID parameter', () => {
  test('teamID renders the same team as teamid', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamid=2`);
    await assertNoPhpErrors(page, 'on Team teamid=2');
    const viaLower = await expectRealTeam(page, 2);

    await page.goto(`${TEAM_URL}&teamID=2`);
    await assertNoPhpErrors(page, 'on Team teamID=2');
    const viaCamel = await expectRealTeam(page, 2);

    expect(viaCamel).toBe(viaLower);
  });

  test('teamid wins when both teamid and teamID are present', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamid=0&teamID=1`);
    await assertNoPhpErrors(page, 'on Team teamid=0&teamID=1');
    await expectFreeAgents(page);
  });

  test('non-numeric teamID falls back to Free Agents', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamID=abc`);
    await assertNoPhpErrors(page, 'on Team teamID=abc');
    await expectFreeAgents(page);
  });

  test('teamID with yr renders the team', async ({ page }) => {
    await page.goto(`${TEAM_URL}&teamID=1&yr=2002`);
    await assertNoPhpErrors(page, 'on Team teamID=1&yr=2002');
    await expectRealTeam(page, 1);
  });
});

test.describe('Schedule camelCase teamID parameter', () => {
  test('teamID renders the team schedule', async ({ page }) => {
    await page.goto(`${SCHEDULE_URL}&teamid=1`);
    await assertNoPhpErrors(page, 'on Schedule teamid=1');
    const lower = await page.locator('.schedule-team-banner').first().innerText();

    await page.goto(`${SCHEDULE_URL}&teamID=1`);
    await assertNoPhpErrors(page, 'on Schedule teamID=1');
    await expect(page.locator('.schedule-team-banner').first()).toBeVisible();
    expect(await page.locator('.schedule-team-banner').first().innerText()).toBe(lower);
  });

  test('non-numeric teamID shows the league schedule', async ({ page }) => {
    await page.goto(`${SCHEDULE_URL}&teamID=abc`);
    await assertNoPhpErrors(page, 'on Schedule teamID=abc');
    await expect(page.locator('.schedule-team-banner')).toHaveCount(0);
  });
});

test.describe('DraftHistory camelCase teamID parameter', () => {
  test('teamID renders the team draft history', async ({ page }) => {
    await page.goto(`${DRAFT_URL}&teamid=1`);
    await assertNoPhpErrors(page, 'on DraftHistory teamid=1');
    const lower = await page.locator('h1.ibl-title').first().innerText();

    await page.goto(`${DRAFT_URL}&teamID=1`);
    await assertNoPhpErrors(page, 'on DraftHistory teamID=1');
    await expect(page.locator('h1.ibl-title').first()).toHaveText(/Draft History/);
    expect(await page.locator('h1.ibl-title').first().innerText()).toBe(lower);
    await expect(page.locator('#draft-year-select')).toHaveCount(0);
  });

  test('teamID=0 shows the draft year view', async ({ page }) => {
    await page.goto(`${DRAFT_URL}&teamID=0`);
    await assertNoPhpErrors(page, 'on DraftHistory teamID=0');
    await expect(page.locator('#draft-year-select')).toBeAttached();
  });
});
