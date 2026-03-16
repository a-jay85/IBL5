import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// League Starters — public page.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('League Starters flow', () => {
  test('page loads without PHP errors', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
    await assertNoPhpErrors(page, 'on League Starters page');
  });

  test('page has title or content', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
    // Page should render meaningful content
    const body = await page.locator('body').textContent();
    expect(body!.length).toBeGreaterThan(100);
  });

  test('starters tables are visible when data exists', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
    const tables = page.locator('.ibl-data-table, table');
    const count = await tables.count();
    if (count > 0) {
      await expect(tables.first()).toBeVisible();
    }
  });

  test('display mode total_s loads without errors', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters&display=total_s');
    await assertNoPhpErrors(page, 'on League Starters total_s view');
  });

  test('display mode avg_s loads without errors', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters&display=avg_s');
    await assertNoPhpErrors(page, 'on League Starters avg_s view');
  });

  test('display mode per36mins loads without errors', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters&display=per36mins');
    await assertNoPhpErrors(page, 'on League Starters per36mins view');
  });

  test('no PHP errors across all display modes', async ({ page }) => {
    const modes = ['ratings', 'total_s', 'avg_s', 'per36mins'];
    for (const mode of modes) {
      await gotoWithRetry(page, `modules.php?name=LeagueStarters&display=${mode}`);
      await assertNoPhpErrors(page, `on League Starters ${mode} view`);
    }
  });
});
