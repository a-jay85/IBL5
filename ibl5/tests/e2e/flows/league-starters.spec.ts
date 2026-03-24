import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// League Starters — public page showing starting lineups by position.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('League Starters flow', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/League Starters/i);
  });

  test('displays all five position tables', async ({ page }) => {
    const positionLabels = [
      'Point Guards',
      'Shooting Guards',
      'Small Forwards',
      'Power Forwards',
      'Centers',
    ];

    for (const label of positionLabels) {
      await expect(page.getByText(label, { exact: false }).first()).toBeVisible();
    }
  });

  test('each position has a data table', async ({ page }) => {
    const tables = page.locator('.ibl-data-table');
    const count = await tables.count();
    // At least 5 tables (one per position)
    expect(count).toBeGreaterThanOrEqual(5);
  });

  test('tables have expected stat columns for ratings view', async ({ page }) => {
    const firstTable = page.locator('.ibl-data-table').first();
    await expect(firstTable).toBeVisible();

    const headerText = await firstTable.locator('thead').textContent();
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
  });

  test('player links are present in tables', async ({ page }) => {
    // Player links use modules.php?name=Player&...
    const playerLinks = page.locator('.ibl-data-table a[href*="Player"]');
    const count = await playerLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('team rows have data-team-id attributes', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('view switcher tabs are present', async ({ page }) => {
    // LeagueStarters has tabs: Ratings, Season Totals, Season Averages, Per 36 Minutes
    const tabs = page.locator('.ibl-tab, [role="tab"]');
    const count = await tabs.count();
    expect(count).toBeGreaterThanOrEqual(4);
  });

  test('switching to Season Totals view works', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters&display=total_s');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on League Starters Season Totals view');
  });

  test('switching to Season Averages view works', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters&display=avg_s');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on League Starters Season Averages view');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on League Starters page');
  });

  test('no PHP errors on alternate display modes', async ({ page }) => {
    const displays = ['total_s', 'avg_s', 'per36mins'];
    for (const display of displays) {
      await page.goto(`modules.php?name=LeagueStarters&display=${display}`);
      await assertNoPhpErrors(page, `on League Starters ${display} view`);
    }
  });
});

test.describe('HTMX tab switching', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');
  });

  test('tab click swaps tables without full page reload', async ({ page }) => {
    // Mark nav to verify it persists (proves no full reload)
    await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      if (navEl) navEl.setAttribute('data-htmx-marker', '1');
    });

    const tab = page.locator('.ibl-tab:not(.ibl-tab--active)').first();
    await expect(tab).toBeVisible();

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('LeagueStarters') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      tab.click(),
    ]);

    await expect(
      page.locator('#league-starters-tables .ibl-data-table').first(),
    ).toBeVisible();

    // Nav marker survived — no full page reload occurred
    const marker = await page.evaluate(() =>
      document.querySelector('nav.fixed')?.getAttribute('data-htmx-marker'),
    );
    expect(marker).toBe('1');
  });

  test('tab click updates URL', async ({ page }) => {
    const tab = page.locator('.ibl-tab[data-display="avg_s"]');
    await expect(tab).toBeVisible();

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('LeagueStarters') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      tab.click(),
    ]);

    await page.waitForURL(/display=avg_s/);
    expect(page.url()).toContain('display=avg_s');
  });

  test('all display modes load via HTMX', async ({ page }) => {
    const modes = ['total_s', 'avg_s', 'per36mins'];

    for (const mode of modes) {
      const tab = page.locator(`.ibl-tab[data-display="${mode}"]`);
      await expect(tab).toBeVisible();

      await Promise.all([
        page.waitForResponse(
          (r) =>
            r.url().includes('LeagueStarters') &&
            r.url().includes('op=api') &&
            r.status() === 200,
        ),
        tab.click(),
      ]);

      await expect(
        page.locator('#league-starters-tables .ibl-data-table').first(),
      ).toBeVisible();
      await assertNoPhpErrors(page, `after HTMX switch to ${mode}`);
    }
  });
});

test.describe('browser back/forward after HTMX tab switch', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test('back/forward works after tab switch', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');

    const tab = page.locator('.ibl-tab[data-display="total_s"]');
    await expect(tab).toBeVisible();

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('LeagueStarters') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      tab.click(),
    ]);

    await page.waitForURL(/display=total_s/);

    await page.goBack();
    await page.waitForURL(/LeagueStarters/);
    expect(page.url()).not.toContain('display=total_s');

    await page.goForward();
    await page.waitForURL(/display=total_s/);
    expect(page.url()).toContain('display=total_s');
  });
});

test.describe('no-JS fallback', () => {
  test.use({ javaScriptEnabled: false });

  test('page renders correctly with JavaScript disabled', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters&display=total_s');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on LeagueStarters with JS disabled');

    // Tab links exist with href attributes (work as plain links without JS)
    const tabLink = page.locator('.ibl-tab[href]').first();
    await expect(tabLink).toBeAttached();
  });
});
