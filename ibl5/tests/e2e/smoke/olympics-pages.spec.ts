import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Olympics public pages — verify league-context table resolution works.
// These pages append ?league=olympics to switch to Olympics context.
test.use({ storageState: publicStorageState() });

const OLYMPICS_URLS = [
  'modules.php?name=Team&op=team&teamid=1&league=olympics',
  'modules.php?name=Standings&league=olympics',
  'modules.php?name=SeasonLeaderboards&league=olympics',
  'modules.php?name=Player&pa=showpage&pid=1&league=olympics',
];

test.describe('Olympics page smoke tests', () => {
  test('standings page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Standings&league=olympics');
    // Should not crash — verify page rendered some HTML content
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('team page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('season leaderboards loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('no PHP errors on Olympics pages', async ({ page }) => {
    for (const url of OLYMPICS_URLS) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
