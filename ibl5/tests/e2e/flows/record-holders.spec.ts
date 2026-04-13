import { test, expect } from '@playwright/test';
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

  test('page loads with title', async ({ page }) => {
    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    const titleText = await title.textContent();
    expect(titleText?.toLowerCase()).toContain('record');
  });

  test('multiple record card sections present', async ({ page }) => {
    // Record holders page has one .record-section wrapper with multiple .ibl-card subsections
    const cards = page.locator('.record-section .ibl-card');
    expect(await cards.count()).toBeGreaterThanOrEqual(3);
  });

  test('each card section contains data tables with rows', async ({ page }) => {
    const cards = page.locator('.record-section .ibl-card');
    const cardCount = await cards.count();

    // CI seed guarantees data: box scores for single-game records,
    // plus pid=3 year=2024 with games=55 for full-season averages.
    let cardsWithRows = 0;
    for (let i = 0; i < cardCount; i++) {
      const card = cards.nth(i);
      const tables = card.locator('.ibl-data-table');
      const tableCount = await tables.count();
      if (tableCount > 0) {
        const rows = tables.first().locator('tbody tr');
        if ((await rows.count()) > 0) {
          cardsWithRows++;
        }
      }
    }

    expect(cardsWithRows).toBeGreaterThan(0);
  });

  test('player record rows contain player links', async ({ page }) => {
    const playerLinks = page.locator('.record-section a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();
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
});
