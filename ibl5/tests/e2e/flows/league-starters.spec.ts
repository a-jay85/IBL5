import { test, expect } from '../fixtures/base';
import type { Page } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { assertHtmxSwap } from '../helpers/htmx-swap';
import { gotoWithRetry } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

/** Normalised stat-column header text of the first rendered table. */
async function readHeaderText(page: Page): Promise<string> {
  const text = await page
    .locator('#league-starters-tables .ibl-data-table thead')
    .first()
    .textContent();
  return (text ?? '').replace(/\s+/g, ' ').trim();
}

// League Starters — public page showing starting lineups by position.
test.use({ storageState: publicStorageState() });

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
    await assertHtmxSwap(page, {
      trigger: () =>
        page.locator('.ibl-tab[data-display="total_s"]').click(),
      apiUrlPattern: (url) => url.includes('LeagueStarters'),
      expectedUrl: /display=total_s/,
      contentSelector: '#league-starters-tables .ibl-data-table',
    });
  });

  test('tab click updates URL', async ({ page }) => {
    await assertHtmxSwap(page, {
      trigger: () => page.locator('.ibl-tab[data-display="avg_s"]').click(),
      apiUrlPattern: (url) => url.includes('LeagueStarters'),
      expectedUrl: /display=avg_s/,
      contentSelector: '#league-starters-tables .ibl-data-table',
    });

    expect(page.url()).toContain('display=avg_s');
  });

  test('display mode tabs change the stat-column header set', async ({
    page,
  }) => {
    // Default load is the ratings view.
    const headersByMode: Record<string, string> = {
      ratings: await readHeaderText(page),
    };

    for (const mode of ['total_s', 'avg_s', 'per36mins']) {
      await assertHtmxSwap(page, {
        trigger: () =>
          page.locator(`.ibl-tab[data-display="${mode}"]`).click(),
        apiUrlPattern: (url) => url.includes('LeagueStarters'),
        expectedUrl: new RegExp(`display=${mode}`),
        contentSelector: '#league-starters-tables .ibl-data-table',
      });
      headersByMode[mode] = await readHeaderText(page);
      await assertNoPhpErrors(page, `after HTMX switch to ${mode}`);
    }

    // Each display mode emits a distinct stat-column set. If the tab swap were
    // a no-op the four headers would be identical, so this fails on no-op.
    const headers = Object.values(headersByMode);
    expect(new Set(headers).size).toBe(4);
    // Mode-specific differentiators confirm the right table rendered.
    expect(headersByMode.per36mins.toLowerCase()).toContain('36min');
    expect(headersByMode.ratings).not.toBe(headersByMode.total_s);
  });
});

test.describe('browser back/forward after HTMX tab switch', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test('back/forward works after tab switch', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=LeagueStarters');

    await assertHtmxSwap(page, {
      trigger: () =>
        page.locator('.ibl-tab[data-display="total_s"]').click(),
      apiUrlPattern: (url) => url.includes('LeagueStarters'),
      expectedUrl: /display=total_s/,
      contentSelector: '#league-starters-tables .ibl-data-table',
    });

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
