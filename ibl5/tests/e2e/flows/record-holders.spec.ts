import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Record Holders — public page, no authentication required.
test.use({ storageState: publicStorageState() });

test.describe('Record Holders flow', () => {
  // RecordHolders runs 17+ queries on cache miss — increase timeout
  test.use({ navigationTimeout: 60_000, actionTimeout: 30_000 });

  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=RecordHolders');
  });

  test('player links navigate to player page', async ({ page }) => {
    const playerLink = page.locator('.record-section a[href*="pid="]').first();
    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Record Holders link');
  });

  test('record tables have data rows', async ({ page }) => {
    // Multiple record tables should exist with actual tbody rows
    const tables = page.locator('.record-section .ibl-data-table');
    const tableCount = await tables.count();
    expect(tableCount).toBeGreaterThan(0);

    const firstTableRows = tables.first().locator('tbody tr');
    expect(await firstTableRows.count()).toBeGreaterThan(0);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Record Holders page');
  });

  // The five record sections render unconditionally (the card titles are emitted
  // before any data loop in RecordHoldersView), so these assertions are
  // independent of how sparse the CI seed's box-score data is. This confirms the
  // page still composes after the RecordStatDefinitions query-source refactor.
  test('all five record sections render', async ({ page }) => {
    const sectionTitles = [
      'Player, Regular Season (Single Game)',
      'Player, Regular Season (Full Season) [minimum 50 games]',
      'Player, Playoffs',
      'Player, H.E.A.T.',
      'Team Records',
    ];
    for (const title of sectionTitles) {
      await expect(
        page.locator('.ibl-card__title', { hasText: title }),
      ).toBeVisible();
    }
  });
});
