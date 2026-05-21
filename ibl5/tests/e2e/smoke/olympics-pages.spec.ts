import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Olympics public pages — verify league-context table resolution works.
// These pages append ?league=olympics to switch to Olympics context.
test.use({ storageState: publicStorageState() });

test.describe('Olympics page smoke tests', () => {
  test('standings page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Standings&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('team page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Team&op=team&teamid=1&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('season leaderboards loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=SeasonLeaderboards&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });

  test('player page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Player&pa=showpage&pid=1&league=olympics');
    const body = await page.locator('body').textContent();
    expect(body?.length).toBeGreaterThan(100);
  });
});
