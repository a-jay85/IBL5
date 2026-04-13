import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

// Public pages — no authentication required.
test.use({ storageState: publicStorageState() });

const PAGES = [
  { name: 'schedule', url: 'modules.php?name=Schedule', selector: '.schedule-container, .ibl-data-table, table' },
  { name: 'injuries', url: 'modules.php?name=Injuries', selector: '.ibl-title, h2, h3' },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase', selector: 'form[name="Search"]' },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder', selector: '.ibl-title, .ibl-data-table, table' },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator', selector: '.ibl-title, table' },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview', selector: '.ibl-data-table, table, .ibl-title' },
  { name: 'contract list', url: 'modules.php?name=ContractList', selector: '.ibl-data-table, table, .ibl-title' },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement', selector: '.ibl-title, .ibl-data-table, table, h2, h3' },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters', selector: '.ibl-data-table, table' },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers', selector: 'input[name="Player1"], input[name="player1"]' },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs', selector: '.ibl-data-table, table, .ibl-title' },
  { name: 'series records', url: 'modules.php?name=SeriesRecords', selector: '.ibl-data-table, table, .ibl-title' },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory', selector: '.ibl-data-table, table, .ibl-title' },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker', selector: '.ibl-data-table, table, .ibl-title' },
];

test.describe('Extended public page smoke tests', () => {
  for (const { name, url, selector } of PAGES) {
    test(`${name} loads`, async ({ page }) => {
      // Individual page loads may need extra time under parallel worker load
      test.setTimeout(60_000);
      await gotoWithRetry(page, url);
      await expect(page.locator(selector).first()).toBeVisible();
    });
  }

  test('no PHP errors on extended public pages', async ({ page }) => {
    // This test visits all pages sequentially — give it plenty of time
    test.setTimeout(120_000);
    for (const { url } of PAGES) {
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
