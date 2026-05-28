import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

// Olympics public pages — verify league-context table resolution works.
// These pages append ?league=olympics to switch to Olympics context.
test.use({ storageState: publicStorageState() });

test.describe('Olympics page smoke tests', () => {
  test('standings page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Standings&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Standings&league=olympics');
    await expect(page.locator('.ibl-title')).toContainText(/Olympics Standings/i);
    await expect(page.locator('.ibl-data-table')).toBeVisible();
    const tableText = await page.locator('.ibl-data-table').textContent();
    expect(tableText ?? '').not.toContain('Eastern Conference');
    expect(tableText ?? '').not.toContain('Western Conference');
  });

  test('team page loads in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    await assertNoPhpErrors(page, 'on modules.php?name=Team&op=team&teamid=1&league=olympics');
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

test.describe('Olympics nav filtering', () => {
  test('Olympics nav: IBL-only Season links absent', async ({ page }) => {
    await page.goto('index.php?league=olympics');
    await assertNoPhpErrors(page, 'on index.php?league=olympics');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).click();

    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Standings' }).first()).toBeVisible();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Cap Space' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Draft Pick Locator' })).not.toBeAttached();
  });

  test('Olympics nav: IBL-only History links absent', async ({ page }) => {
    await page.goto('index.php?league=olympics');
    await assertNoPhpErrors(page, 'on index.php?league=olympics');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'History' }).click();

    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Transaction History' }).first()).toBeVisible();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Record Holders' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Award History' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Season Leaderboards' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Career Leaderboards' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise Record Book' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise History' })).not.toBeAttached();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'All-Star Appearances' })).not.toBeAttached();
  });

  test('IBL nav: all links present', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');
    const nav = desktopNav(page);

    await nav.getByRole('button', { name: 'Season' }).click();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Cap Space' }).first()).toBeVisible();

    await nav.getByRole('button', { name: 'History' }).click();
    await expect(nav.locator('.nav-dropdown-item', { hasText: 'Franchise History' }).first()).toBeVisible();
  });
});
