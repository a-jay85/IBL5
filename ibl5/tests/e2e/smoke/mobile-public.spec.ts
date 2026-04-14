import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoHorizontalOverflow, assertScrollWrappersPresent, assertScrollContainerIsScrollable } from '../helpers/mobile';

// Mobile smoke tests for public pages — 375x812 viewport (iPhone SE).
test.use({ viewport: { width: 375, height: 812 } });

// hasWideTables: only true for pages whose tables are always wider than 375px regardless
// of seed data. responsive-tables.js only wraps tables that overflow, so data-dependent
// pages may not have scroll wrappers in CI. The overflow check still catches real issues.
const PAGES = [
  { name: 'homepage', url: 'index.php', selector: 'article', hasWideTables: false },
  { name: 'standings', url: 'modules.php?name=Standings', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'player page', url: 'modules.php?name=Player&pa=showpage&pid=1', selector: '.stats-grid', hasWideTables: false },
  { name: 'team page', url: 'modules.php?name=Team&op=team&teamid=1', selector: '.team-page-layout', hasWideTables: true },
  { name: 'season leaderboards', url: 'modules.php?name=SeasonLeaderboards', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'career leaderboards', url: 'modules.php?name=CareerLeaderboards', selector: 'form[name="CareerLeaderboards"]', hasWideTables: false },
  { name: 'draft history', url: 'modules.php?name=DraftHistory', selector: '.ibl-data-table', hasWideTables: false },
  { name: 'cap space', url: 'modules.php?name=CapSpace', selector: '.ibl-data-table, .sticky-table, table', hasWideTables: false },
  { name: 'schedule', url: 'modules.php?name=Schedule', selector: '.schedule-container, .ibl-data-table, table', hasWideTables: false },
  { name: 'injuries', url: 'modules.php?name=Injuries', selector: '.ibl-title, h2, h3', hasWideTables: false },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase', selector: 'form[name="Search"]', hasWideTables: false },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator', selector: '.ibl-title, table', hasWideTables: false },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: false },
  { name: 'contract list', url: 'modules.php?name=ContractList', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement', selector: '.ibl-title, .ibl-data-table, table, h2, h3', hasWideTables: false },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters', selector: '.ibl-data-table, table', hasWideTables: true },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers', selector: 'input[name="Player1"], input[name="player1"]', hasWideTables: false },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: false, skipOverflow: true },
  { name: 'head-to-head records', url: 'modules.php?name=HeadToHeadRecords', selector: '.ibl-data-table, table, .ibl-title, .h2h-empty-state', hasWideTables: false },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: false },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'record holders', url: 'modules.php?name=RecordHolders', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'all-star appearances', url: 'modules.php?name=AllStarAppearances', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'award history', url: 'modules.php?name=AwardHistory', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'franchise record book', url: 'modules.php?name=FranchiseRecordBook', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'team off/def stats', url: 'modules.php?name=TeamOffDefStats', selector: '.ibl-data-table, .ibl-title', hasWideTables: true },
  { name: 'transaction history', url: 'modules.php?name=TransactionHistory', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'search', url: 'modules.php?name=Search', selector: 'input[type="radio"]', hasWideTables: false, skipOverflow: true },
  { name: 'boxscore', url: 'modules.php?name=Boxscore&boxid=1', selector: '.ibl-data-table, table', hasWideTables: false },
  { name: 'season archive', url: 'modules.php?name=SeasonArchive', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: false },
  { name: 'one-on-one game', url: 'modules.php?name=OneOnOneGame', selector: '#pid1', hasWideTables: false, skipOverflow: true },
  { name: 'topics', url: 'modules.php?name=Topics', selector: '.ibl-title, table, a', hasWideTables: false, skipOverflow: true },
  { name: 'news', url: 'modules.php?name=News', selector: '.news-article, .news-article__title, article', hasWideTables: false },
] as const;

test.describe('Mobile public page smoke tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const pageInfo of PAGES) {
    test(`${pageInfo.name} — no horizontal overflow on mobile`, async ({ page }) => {
      test.setTimeout(60_000);
      await gotoWithRetry(page, pageInfo.url);
      await assertNoPhpErrors(page, `on ${pageInfo.url} (mobile)`);

      await expect(page.locator(pageInfo.selector).first()).toBeVisible();

      await assertNoHorizontalOverflow(page, `on ${pageInfo.name}`);

      if (pageInfo.hasWideTables) {
        await assertScrollWrappersPresent(page, `on ${pageInfo.name}`);
      }
    });
  }

  test('team schedule — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=Schedule&teamid=1');
    await assertNoPhpErrors(page, 'on modules.php?name=Schedule&teamid=1 (mobile)');
    await expect(page.locator('.schedule-container, .schedule-game, table').first()).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on team schedule');
  });

  test('draft history year detail — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=DraftHistory&year=2026');
    await assertNoPhpErrors(page, 'on modules.php?name=DraftHistory&year=2026 (mobile)');
    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on draft history year detail');
    // CI seed has minimal draft data — table may not overflow at 375px,
    // so scroll wrappers are not guaranteed.
  });

  test('draft history team view — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=DraftHistory&teamid=1');
    await assertNoPhpErrors(page, 'on modules.php?name=DraftHistory&teamid=1 (mobile)');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on draft history team view');
  });

  test('franchise record book team view — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=FranchiseRecordBook&teamid=1');
    await assertNoPhpErrors(page, 'on modules.php?name=FranchiseRecordBook&teamid=1 (mobile)');
    await expect(page.locator('.ibl-title, .ibl-data-table, table').first()).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on franchise record book team view');
  });

  test('season archive year detail — no horizontal overflow on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=SeasonArchive&year=2026');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonArchive&year=2026 (mobile)');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on season archive year detail');
  });

  test('registration form — no horizontal overflow on mobile', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=YourAccount&op=new_user');
    await assertNoPhpErrors(page, 'on modules.php?name=YourAccount&op=new_user (mobile)');
    await expect(page.locator('#register-username')).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on registration form');
  });

  test('forgot password form — no horizontal overflow on mobile', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=YourAccount&op=pass_lost');
    await assertNoPhpErrors(page, 'on modules.php?name=YourAccount&op=pass_lost (mobile)');
    await expect(page.locator('#reset-email')).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on forgot password form');
  });

});

test.describe('Responsive scroll container tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  test('standings — scroll container is scrollable on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings (mobile)');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertScrollContainerIsScrollable(page, page.locator('.table-scroll-container').first(), 'on standings');
  });

  test('season leaderboards — scroll container is scrollable on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=SeasonLeaderboards');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonLeaderboards (mobile)');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertScrollContainerIsScrollable(page, page.locator('.table-scroll-container').first(), 'on season leaderboards');
  });

  test('contract list — scroll container is scrollable on mobile', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=ContractList');
    await assertNoPhpErrors(page, 'on modules.php?name=ContractList (mobile)');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertScrollContainerIsScrollable(page, page.locator('.table-scroll-container').first(), 'on contract list');
  });

  test('standings — sticky column stays visible after scroll', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings (mobile)');
    // Wait for tbody rows to render (query JOINs ibl_team_info) — toBeAttached
    // retries for up to 10s and works even if the element is below the fold
    await expect(page.locator('.table-scroll-container tbody td.sticky-col').first())
      .toBeAttached({ timeout: 10_000 });
    const result = await page.locator('.table-scroll-container').first().evaluate((el: Element) => {
      const c = el as HTMLElement;
      c.scrollLeft = c.scrollWidth;
      const cell = c.querySelector('tbody td.sticky-col');
      const nextCell = cell?.nextElementSibling;
      if (!cell || !nextCell) return null;
      return {
        stickyLeft: cell.getBoundingClientRect().left,
        nextLeft: nextCell.getBoundingClientRect().left,
      };
    });
    expect(result).not.toBeNull();
    // e2e-hygiene-allow: viewport coordinate has 0 as a meaningful lower bound
    expect(result!.stickyLeft, 'sticky column scrolled out of viewport').toBeGreaterThanOrEqual(0);
    expect(result!.stickyLeft, 'sticky column must be within viewport').toBeLessThan(375);
  });

  test('season leaderboards — scroll shadow indicator present on load', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=SeasonLeaderboards');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonLeaderboards (mobile)');
    await expect(page.locator('.table-scroll-wrapper').first()).toBeAttached();
    await expect(page.locator('.table-scroll-wrapper').first()).not.toHaveClass(/scrolled-end/);
  });

  test('season leaderboards — scroll shadow disappears after scrolling to end', async ({ page }) => {
    test.setTimeout(60_000);
    await gotoWithRetry(page, 'modules.php?name=SeasonLeaderboards');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonLeaderboards (mobile)');
    await expect(page.locator('.table-scroll-container').first()).toBeAttached();
    await page.locator('.table-scroll-container').first().evaluate((el: Element) => {
      const container = el as HTMLElement;
      container.scrollLeft = container.scrollWidth;
      el.dispatchEvent(new Event('scroll'));
    });
    await expect(page.locator('.table-scroll-wrapper').first()).toHaveClass(/scrolled-end/);
  });
});
