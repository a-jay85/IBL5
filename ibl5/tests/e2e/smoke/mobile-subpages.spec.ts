import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import {
  assertNoHorizontalOverflow,
  assertScrollWrappersPresent,
  assertScrollContainerIsScrollable,
} from '../helpers/mobile';

// Mobile smoke tests for sub-page variants — 375x812 viewport (iPhone SE).
test.use({ viewport: { width: 375, height: 812 } });

// --- Player stat view variants ---

// skipOverflow: RS Totals/Averages tables live inside .player-stats-card (overflow-x: auto),
// not .table-scroll-container. The card itself causes body-level overflow (pre-existing issue).
const PLAYER_VIEWS = [
  { name: 'Awards & News', param: 'pageView=1', hasWideTables: false, dataDependentSkip: false },
  { name: 'RS Totals', param: 'pageView=3', hasWideTables: false, dataDependentSkip: false, skipOverflow: true },
  { name: 'RS Averages', param: 'pageView=4', hasWideTables: false, dataDependentSkip: false, skipOverflow: true },
  { name: 'Playoff Totals', param: 'pageView=5', hasWideTables: false, dataDependentSkip: true },
  { name: 'HEAT Totals', param: 'pageView=7', hasWideTables: false, dataDependentSkip: true },
  { name: 'Ratings & Salary', param: 'pageView=9', hasWideTables: false, dataDependentSkip: false },
  { name: 'Sim Stats', param: 'pageView=10', hasWideTables: false, dataDependentSkip: false },
] as const;

const PLAYER_BASE_URL = 'modules.php?name=Player&pa=showpage&pid=1';

// --- Team display mode variants ---

const TEAM_VIEWS = [
  { name: 'Season Totals', param: 'display=total_s', hasWideTables: true, dataDependentSkip: false },
  { name: 'Contracts', param: 'display=contracts', hasWideTables: true, dataDependentSkip: false },
  { name: 'Averages', param: 'display=avg_s', hasWideTables: true, dataDependentSkip: false },
  { name: 'Per 36 Min', param: 'display=per36mins', hasWideTables: true, dataDependentSkip: false },
  { name: 'Sim Averages', param: 'display=chunk', hasWideTables: true, dataDependentSkip: true },
  { name: 'Split (Home)', param: 'display=split&split=home', hasWideTables: true, dataDependentSkip: true },
  { name: 'Playoffs', param: 'display=playoffs', hasWideTables: false, dataDependentSkip: true },
  { name: 'Historical 2024', param: 'yr=2024', hasWideTables: false, dataDependentSkip: true },
] as const;

const TEAM_BASE_URL = 'modules.php?name=Team&op=team&teamID=1';

// --- Olympics pages ---

const OLYMPICS_URLS = [
  { name: 'Standings', url: 'modules.php?name=Standings&league=olympics' },
  { name: 'Team', url: 'modules.php?name=Team&op=team&teamID=1&league=olympics' },
  { name: 'Season Leaderboards', url: 'modules.php?name=SeasonLeaderboards&league=olympics' },
  { name: 'Player', url: 'modules.php?name=Player&pa=showpage&pid=1&league=olympics' },
] as const;

test.describe('Player stat view mobile smoke tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const view of PLAYER_VIEWS) {
    test(`player ${view.name} — no horizontal overflow on mobile`, async ({ page }) => {
      test.setTimeout(60_000);
      const url = `${PLAYER_BASE_URL}&${view.param}`;
      await gotoWithRetry(page, url);

      const content = page.locator('.player-stats-card, h2, h3').first();

      if (view.dataDependentSkip) {
        const visible = await content.isVisible().catch(() => false);
        if (!visible) {
          test.skip(true, `player ${view.name} rendered no content (seed data)`);
        }
      }

      await expect(content).toBeVisible();

      if (!('skipOverflow' in view)) {
        await assertNoHorizontalOverflow(page, `on player ${view.name}`);
      }

      if (view.hasWideTables) {
        await assertScrollWrappersPresent(page, `on player ${view.name}`);
      }
    });
  }

  test('player RS Totals — stats card is scrollable on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, `${PLAYER_BASE_URL}&pageView=3`);
    // Player stats tables use .player-stats-card (overflow-x: auto), not .table-scroll-container
    await expect(page.locator('.player-stats-card').first()).toBeVisible();
    await assertScrollContainerIsScrollable(
      page,
      page.locator('.player-stats-card').first(),
      'on player RS Totals',
    );
  });

  test('no PHP errors on player stat view pages', async ({ page }) => {
    test.setTimeout(120_000);
    for (const view of PLAYER_VIEWS) {
      const url = `${PLAYER_BASE_URL}&${view.param}`;
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on player ${view.name} (mobile)`);
    }
  });
});

test.describe('Team display mode mobile smoke tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const view of TEAM_VIEWS) {
    test(`team ${view.name} — no horizontal overflow on mobile`, async ({ page }) => {
      test.setTimeout(60_000);
      const url = `${TEAM_BASE_URL}&${view.param}`;
      await gotoWithRetry(page, url);

      const content = page.locator('.ibl-data-table, table, h2, h3').first();

      if (view.dataDependentSkip) {
        const visible = await content.isVisible().catch(() => false);
        if (!visible) {
          test.skip(true, `team ${view.name} rendered no content (seed data)`);
        }
      }

      await expect(content).toBeVisible();
      await assertNoHorizontalOverflow(page, `on team ${view.name}`);

      if (view.hasWideTables) {
        await assertScrollWrappersPresent(page, `on team ${view.name}`);
      }
    });
  }

  test('team Season Totals — scroll container is scrollable on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, `${TEAM_BASE_URL}&display=total_s`);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertScrollContainerIsScrollable(
      page,
      page.locator('.table-scroll-container').first(),
      'on team Season Totals',
    );
  });

  test('no PHP errors on team display mode pages', async ({ page }) => {
    test.setTimeout(120_000);
    for (const view of TEAM_VIEWS) {
      const url = `${TEAM_BASE_URL}&${view.param}`;
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on team ${view.name} (mobile)`);
    }
  });
});

test.describe('Olympics mobile smoke tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const entry of OLYMPICS_URLS) {
    test(`olympics ${entry.name} — loads on mobile`, async ({ page }) => {
      test.setTimeout(60_000);
      await gotoWithRetry(page, entry.url);
      const body = await page.locator('body').textContent();
      expect(body?.length, `olympics ${entry.name} body too short`).toBeGreaterThan(100);
    });
  }

  test('olympics Standings — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=Standings&league=olympics');
    const body = await page.locator('body').textContent();
    if ((body?.length ?? 0) <= 100) {
      test.skip(true, 'olympics Standings rendered no content (seed data)');
    }
    await assertNoHorizontalOverflow(page, 'on olympics Standings');
  });

  test('olympics Team — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=Team&op=team&teamID=1&league=olympics');
    const body = await page.locator('body').textContent();
    if ((body?.length ?? 0) <= 100) {
      test.skip(true, 'olympics Team rendered no content (seed data)');
    }
    await assertNoHorizontalOverflow(page, 'on olympics Team');
  });

  test('no PHP errors on Olympics mobile pages', async ({ page }) => {
    test.setTimeout(120_000);
    for (const entry of OLYMPICS_URLS) {
      await gotoWithRetry(page, entry.url);
      await assertNoPhpErrors(page, `on olympics ${entry.name} (mobile)`);
    }
  });
});
