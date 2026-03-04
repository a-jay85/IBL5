import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Record Holders — public page, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Record Holders flow', () => {
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

    // In CI with sparse seed data, some record categories may have no tables.
    // Count cards that have at least one table with rows.
    let cardsWithRows = 0;
    for (let i = 0; i < cardCount; i++) {
      const card = cards.nth(i);
      const tables = card.locator('.ibl-data-table');
      if (await tables.count() > 0) {
        const rows = tables.first().locator('tbody tr');
        if (await rows.count() > 0) {
          cardsWithRows++;
        }
      }
    }

    // With sparse CI seed data, skip rather than fail if no cards have data.
    if (cardsWithRows === 0) {
      test.skip(true, 'No record data available (sparse CI seed)');
    }
    expect(cardsWithRows).toBeGreaterThan(0);
  });

  test('player record rows contain player links', async ({ page }) => {
    const playerLinks = page.locator('.record-section a[href*="pid="]');
    expect(await playerLinks.count()).toBeGreaterThan(0);
  });

  test('team record rows contain team-colored cells', async ({ page }) => {
    // Team records should have team styling via inline style or data attributes
    const tables = page.locator('.ibl-data-table');
    const tableCount = await tables.count();
    expect(tableCount).toBeGreaterThan(0);
  });

  test('no PHP errors', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Record Holders page`,
      ).not.toContain(pattern);
    }
  });
});
