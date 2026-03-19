import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoHorizontalOverflow, assertScrollWrappersPresent } from '../helpers/mobile';

// Mobile smoke tests for public pages — 375x812 viewport (iPhone SE).
test.use({ viewport: { width: 375, height: 812 } });

const PAGES = [
  { name: 'homepage', url: 'index.php', selector: 'body', hasWideTables: false },
  { name: 'standings', url: 'modules.php?name=Standings', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'player page', url: 'modules.php?name=Player&pa=showpage&pid=1', selector: 'h2, h3', hasWideTables: true },
  { name: 'team page', url: 'modules.php?name=Team&op=team&teamID=1', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'season leaderboards', url: 'modules.php?name=SeasonLeaderboards', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'career leaderboards', url: 'modules.php?name=CareerLeaderboards', selector: 'button, .ibl-data-table', hasWideTables: true },
  { name: 'draft history', url: 'modules.php?name=DraftHistory', selector: '.ibl-data-table', hasWideTables: true },
  { name: 'cap space', url: 'modules.php?name=CapSpace', selector: '.ibl-data-table, .sticky-table, table', hasWideTables: true, dataDependentSkip: true },
  { name: 'schedule', url: 'modules.php?name=Schedule', selector: '.schedule-container, .ibl-data-table, table', hasWideTables: false },
  { name: 'injuries', url: 'modules.php?name=Injuries', selector: '.ibl-title, h2, h3', hasWideTables: true },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase', selector: 'form[name="Search"]', hasWideTables: false },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder', selector: '.ibl-title, .ibl-data-table, table', hasWideTables: true },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator', selector: '.ibl-title, table', hasWideTables: true },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'contract list', url: 'modules.php?name=ContractList', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement', selector: '.ibl-title, .ibl-data-table, table, h2, h3', hasWideTables: true },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters', selector: '.ibl-data-table, table', hasWideTables: true },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers', selector: 'input[name="Player1"], input[name="player1"]', hasWideTables: false },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'series records', url: 'modules.php?name=SeriesRecords', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker', selector: '.ibl-data-table, table, .ibl-title', hasWideTables: true },
] as const;

test.describe('Mobile public page smoke tests', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const pageInfo of PAGES) {
    test(`${pageInfo.name} — no horizontal overflow on mobile`, async ({ page }) => {
      test.setTimeout(60_000);
      await gotoWithRetry(page, pageInfo.url);

      // Data-dependent skip for Cap Space
      if (pageInfo.dataDependentSkip) {
        const table = page.locator(pageInfo.selector).first();
        const visible = await table.isVisible().catch(() => false);
        if (!visible) {
          test.skip(true, 'Cap Space rendered no table content (local DB state)');
        }
      }

      await expect(page.locator(pageInfo.selector).first()).toBeVisible();
      await assertNoHorizontalOverflow(page, `on ${pageInfo.name}`);

      if (pageInfo.hasWideTables) {
        await assertScrollWrappersPresent(page, `on ${pageInfo.name}`);
      }
    });
  }

  test('no PHP errors on mobile public pages', async ({ page }) => {
    test.setTimeout(120_000);
    for (const { url } of PAGES) {
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on ${url} (mobile)`);
    }
  });
});
