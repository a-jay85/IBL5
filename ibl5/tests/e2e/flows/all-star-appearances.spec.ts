import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { assertSortableTablePage } from '../helpers/sortable-table-page';

// All-Star Appearances is now a RecordHolders sub-view at ?op=allstar.
// The old module URL answers 302. See Module\ModuleRedirect::TARGETS.
test.use({ storageState: publicStorageState() });

const SUB_VIEW_URL = 'modules.php?name=RecordHolders&op=allstar';

test.describe('All-Star Appearances flow', () => {
  test('page loads with title, table, and player rows', async ({ page }) => {
    await assertSortableTablePage(page, {
      url: SUB_VIEW_URL,
      minRows: 2,
      expectedTitle: /All-Star Appearances/i,
    });
  });

  test('lists exactly the two seeded players', async ({ page }) => {
    await page.goto(SUB_VIEW_URL);
    await assertNoPhpErrors(page, 'on the all-star appearances sub-view');

    // ci-seed.sql seeds five Conference All-Star awards across two players.
    await expect(page.locator('tbody tr')).toHaveCount(2);
  });

  test('player links navigate to player page', async ({ page }) => {
    await page.goto(SUB_VIEW_URL);
    const playerLinks = page.locator('.ibl-data-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');

    // Navigate to the player page and verify it loads
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from All-Star Appearances');
    await expect(page.locator('h2, h3').first()).toBeVisible();
  });

  test('retired module URL answers 302 to the sub-view', async ({ page }) => {
    const response = await page.request.get('modules.php?name=AllStarAppearances', {
      maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/name=RecordHolders&op=allstar$/);
    expect((await response.body()).length).toBe(0);
  });

  test('an unknown op falls back to the record holders view', async ({ page }) => {
    await page.goto('modules.php?name=RecordHolders&op=bogus');
    await assertNoPhpErrors(page, 'on RecordHolders with an unknown op');

    // The allowlist is an exact string match; loosening it fails this.
    await expect(page.locator('body')).toContainText('Most All-Star Appearances');
  });

  test('the record holders page links to the full list', async ({ page }) => {
    // publicStorageState() already carries the _e2e=1 cookie that skips PageCache.
    await page.goto('modules.php?name=RecordHolders');
    await assertNoPhpErrors(page, 'on the RecordHolders landing page');

    await expect(
      page.locator(`#site-content a[href="${SUB_VIEW_URL}"]`).first(),
    ).toBeVisible();
  });
});
