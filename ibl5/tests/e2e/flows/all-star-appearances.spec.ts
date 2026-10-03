import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { assertSortableTablePage } from '../helpers/sortable-table-page';

// All-Star Appearances is a standalone module at name=AllStarAppearances.
// RecordHolders&op=allstar redirects here via ModuleRedirect::sendWithPassthrough().
test.use({ storageState: publicStorageState() });

const SUB_VIEW_URL = 'modules.php?name=AllStarAppearances';

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

  test('AllStarAppearances renders without redirect', async ({ page }) => {
    const response = await page.request.get('modules.php?name=AllStarAppearances', {
      maxRedirects: 0,
    });

    expect(response.status()).toBe(200);
    expect((await response.body()).length).toBeGreaterThan(0);
  });
});
