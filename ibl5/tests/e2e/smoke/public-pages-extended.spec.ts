import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

// Public pages — no authentication required.
test.use({ storageState: publicStorageState() });

const PAGES = [
  { name: 'schedule', url: 'modules.php?name=Schedule', anchor: '.schedule-header' },
  { name: 'injuries', url: 'modules.php?name=Injuries', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'player database', url: 'modules.php?name=PlayerDatabase', anchor: 'form[action*="PlayerDatabase"]' },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator', anchor: '.draft-pick-locator-container' },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview', anchor: 'th.fa-preview-pos-col' },
  { name: 'contract list', url: 'modules.php?name=ContractList', anchor: '.totals-row' },
  { name: 'player movement', url: 'modules.php?name=PlayerMovement', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters', anchor: '#league-starters-tables' },
  { name: 'compare players', url: 'modules.php?name=ComparePlayers', anchor: 'form[action*="ComparePlayers"]' },
  { name: 'season highs', url: 'modules.php?name=SeasonHighs', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'series records', url: 'modules.php?name=SeriesRecords', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory', anchor: '.ibl-data-table',
    rowCount: { selector: 'tr[data-team-id]', minimum: 28 } },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker', anchor: '.ibl-data-table',
    rowCount: { selector: '.ibl-data-table tbody tr', minimum: 1 } },
];

test.describe('Extended public page smoke tests', () => {
  for (const { name, url, anchor, rowCount } of PAGES) {
    test(`${name} loads`, async ({ page }) => {
      test.setTimeout(60_000);
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on ${url}`);
      await expect(page.locator(anchor).first()).toBeVisible();
      if (rowCount) {
        const rows = page.locator(rowCount.selector);
        if (rowCount.minimum === 1) {
          await expect(rows.first()).toBeVisible();
        } else {
          const count = await rows.count();
          expect(count, `${name}: expected >= ${rowCount.minimum} rows`).toBeGreaterThanOrEqual(rowCount.minimum);
        }
      }
    });
  }
});
