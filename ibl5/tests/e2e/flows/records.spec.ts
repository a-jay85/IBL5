import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';
import { desktopNav } from '../helpers/navigation';

// Records — tabbed public page (All-Time, By Franchise, This Season) and legacy redirects.
test.use({ storageState: publicStorageState() });

test.describe('Records flow', () => {
  test('Records defaults to the All-Time tab', async ({ page }) => {
    await page.goto('modules.php?name=Records');
    await assertNoPhpErrors(page, 'on Records page');

    const tabs = page.locator('.ibl-tabs .ibl-tab');
    await expect(tabs).toHaveCount(3);

    const activeTab = page.locator('.ibl-tab--active');
    await expect(activeTab).toHaveCount(1);
    await expect(activeTab).toContainText('All-Time');

    await expect(page.locator('.ibl-tab-panel[data-tab="alltime"]')).toBeVisible();
    await expect(page.locator('.record-section').first()).toBeVisible();
  });

  test('By Franchise tab switches content', async ({ page }) => {
    await page.goto('modules.php?name=Records');

    const byFranchiseTab = page.locator('.ibl-tabs .ibl-tab', { hasText: 'By Franchise' });
    await expect(byFranchiseTab).toBeVisible();
    const href = await byFranchiseTab.getAttribute('href');
    await page.goto(href!);
    await page.waitForURL(/tab=byfranchise/);

    await expect(page.locator('.ibl-tab-panel[data-tab="byfranchise"]')).toBeVisible();

    const teamSelect = page.locator('#record-book-team');
    await expect(teamSelect).toBeVisible();
    const options = teamSelect.locator('option');
    // 28 real teams + at least 1 default option
    expect(await options.count()).toBeGreaterThanOrEqual(28);
  });

  test('This Season tab switches content', async ({ page }) => {
    await page.goto('modules.php?name=Records');

    const thisSeasonTab = page.locator('.ibl-tabs .ibl-tab', { hasText: 'This Season' });
    await expect(thisSeasonTab).toBeVisible();
    const href = await thisSeasonTab.getAttribute('href');
    await page.goto(href!);

    await expect(page.locator('.ibl-tab-panel[data-tab="thisseason"]')).toBeVisible();

    const headerText = await page.locator('.ibl-data-table thead').first().textContent();
    // [rendered] first table thead: 'POINTS'. PHP-emitted static label — env-independent.
    expect(headerText).toContain('POINTS');
  });

  test('unknown tab falls back to All-Time', async ({ page }) => {
    const urls = [
      'modules.php?name=Records&tab=bogus',
      'modules.php?name=Records&tab[]=byfranchise',
    ];
    for (const url of urls) {
      await page.goto(url);

      const activeTab = page.locator('.ibl-tab--active');
      await expect(activeTab).toHaveCount(1);
      await expect(activeTab).toContainText('All-Time');
      await expect(page.locator('.ibl-tab-panel[data-tab="alltime"]')).toBeVisible();
    }
  });

  test('legacy RecordHolders URL redirects to All-Time', async ({ page }) => {
    for (const url of [
      'modules.php?name=RecordHolders',
      'modules.php?name=RecordHolders&op=bogus',
    ]) {
      const response = await page.request.get(url, { maxRedirects: 0 });
      expect(response.status()).toBe(302);
      expect(response.headers()['location']).toMatch(/name=Records&tab=alltime$/);
      expect((await response.body()).length).toBe(0);
    }
  });

  test('legacy allstar op redirects to AllStarAppearances without a loop', async ({ page }) => {
    const response = await page.request.get('modules.php?name=RecordHolders&op=allstar', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/name=AllStarAppearances$/);

    // AllStarAppearances is a real standalone module — no redirect loop
    const direct = await page.request.get('modules.php?name=AllStarAppearances', {
      maxRedirects: 0,
    });
    expect(direct.status()).toBe(200);

    await page.goto('modules.php?name=AllStarAppearances');
    await expect(page.locator('h1.ibl-title')).toContainText('All-Star Appearances');
  });

  test('legacy FranchiseRecordBook URL keeps a numeric teamid', async ({ page }) => {
    // Numeric teamid is forwarded
    const r1 = await page.request.get('modules.php?name=FranchiseRecordBook&teamid=1', {
      maxRedirects: 0,
    });
    expect(r1.status()).toBe(302);
    expect(r1.headers()['location']).toMatch(/tab=byfranchise&teamid=1$/);

    // Non-numeric teamid is dropped
    const r2 = await page.request.get('modules.php?name=FranchiseRecordBook&teamid=5abc', {
      maxRedirects: 0,
    });
    expect(r2.status()).toBe(302);
    expect(r2.headers()['location']).toMatch(/tab=byfranchise$/);
  });

  test('legacy SeasonHighs URL keeps an encoded seasonPhase', async ({ page }) => {
    const r1 = await page.request.get('modules.php?name=SeasonHighs&seasonPhase=Playoffs', {
      maxRedirects: 0,
    });
    expect(r1.status()).toBe(302);
    expect(r1.headers()['location']).toMatch(/tab=thisseason&seasonPhase=Playoffs$/);

    // Header-injection attempt: CRLF chars must be percent-encoded in the location header
    const r2 = await page.request.get(
      'modules.php?name=SeasonHighs&seasonPhase=%0D%0ASet-Cookie:x',
      { maxRedirects: 0 },
    );
    expect(r2.status()).toBe(302);
    const loc = r2.headers()['location'] ?? '';
    expect(loc).toMatch(/seasonPhase=%0D%0ASet-Cookie%3Ax$/);
    expect(loc).not.toContain('\r');
    expect(loc).not.toContain('\n');
  });

  test('nav Records link opens the Records page', async ({ page }) => {
    await page.goto('modules.php?name=Records');
    await assertNoPhpErrors(page, 'on Records page for nav test');

    const nav = desktopNav(page);

    // Nav carries a direct Records link
    await expect(nav.locator('a[href="modules.php?name=Records"]')).toBeAttached();

    // No legacy module links in nav
    await expect(nav.locator('a[href="modules.php?name=RecordHolders"]')).toHaveCount(0);
    await expect(nav.locator('a[href="modules.php?name=FranchiseRecordBook"]')).toHaveCount(0);
    await expect(nav.locator('a[href="modules.php?name=SeasonHighs"]')).toHaveCount(0);

    // Open the History menu and click Records
    await nav.getByRole('button', { name: 'History' }).click();
    const recordsLink = nav.locator('a.nav-dropdown-item[href="modules.php?name=Records"]');
    await expect(recordsLink).toBeVisible();
    const href = await recordsLink.getAttribute('href');
    await page.goto(href!);

    await expect(page).toHaveURL(/name=Records/);
    const activeTab = page.locator('.ibl-tab--active');
    await expect(activeTab.first()).toContainText('All-Time');
  });
});
