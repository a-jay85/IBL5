import { test, expect } from '../fixtures/base';
import type { Page } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { assertHtmxSwap } from '../helpers/htmx-swap';
import { publicStorageState } from '../helpers/public-storage-state';

// Seeded draftyear discriminators (ci-seed.sql): 2026 has exactly one pick
// ("Draft Rookie 2026"); 2021 has "Test Player" (and others). Switching to
// 2026 must surface the 2026 pick and exclude the 2021 pick.
const YEAR_WITH_ONE_PICK = '2026';
const PICK_IN_2026 = 'Draft Rookie 2026';
const PICK_IN_2021 = 'Test Player';

// Draft History — public page.
test.use({ storageState: publicStorageState() });

/** Extract the first numeric year value from the draft year dropdown. */
async function getFirstDraftYear(page: Page): Promise<string> {
  const options = page.locator('#draft-year-select option');
  const optionCount = await options.count();
  for (let i = 0; i < optionCount; i++) {
    const val = await options.nth(i).getAttribute('value');
    if (val && /^\d{4}$/.test(val)) {
      return val;
    }
  }
  return '';
}

/**
 * Navigate to a draft year that has data (player picks).
 * Tries each year from the dropdown until a draft table appears.
 */
async function navigateToDraftYearWithData(page: Page): Promise<boolean> {
  const options = page.locator('#draft-year-select option');
  const optionCount = await options.count();
  for (let i = 0; i < optionCount; i++) {
    const val = await options.nth(i).getAttribute('value');
    if (val && /^\d{4}$/.test(val)) {
      await page.goto(`modules.php?name=DraftHistory&year=${val}`);
      const table = page.locator('.draft-history-table');
      if ((await table.count()) > 0) { // e2e-hygiene-allow: helper probes years until data found, returns boolean to caller
        return true;
      }
    }
  }
  return false;
}

test.describe('Draft History flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
  });

  test('page loads with title containing Draft', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Draft/i);
  });

  test('year selector dropdown is present', async ({ page }) => {
    await expect(page.locator('#draft-year-select')).toBeVisible();
  });

  test('year dropdown has multiple options', async ({ page }) => {
    const options = page.locator('#draft-year-select option');
    expect(await options.count()).toBeGreaterThanOrEqual(2);
  });

  test('default page shows no-data message or draft table', async ({ page }) => {
    const noData = page.locator('.draft-no-data');
    const table = page.locator('.draft-history-table');
    const noDataCount = await noData.count();
    const tableCount = await table.count();
    expect(noDataCount + tableCount).toBeGreaterThan(0);
  });

  test('selecting a year with data shows draft picks table', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);
    await assertNoPhpErrors(page, 'on DraftHistory with data');

    const table = page.locator('.draft-history-table');
    await expect(table.first()).toBeVisible();
    const rows = table.first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('draft table has responsive-table class', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const table = page.locator('.draft-history-table.responsive-table');
    await expect(table.first()).toBeVisible();
  });

  test('draft picks table has expected column headers', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const table = page.locator('.draft-history-table').first();
    const headerText = await table.locator('thead').textContent();
    expect(headerText).toContain('Rd');
    expect(headerText).toContain('Pick');
    expect(headerText).toContain('Player');
    expect(headerText).toContain('Team');
  });

  test('team history page loads without errors', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory&op=team&teamid=1');
    await assertNoPhpErrors(page, 'on DraftHistory team view');
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
  });

  test('player links exist in draft table', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const playerLinks = page.locator('.draft-history-table a[href*="pid="]');
    await expect(playerLinks.first()).toBeVisible();

    const href = await playerLinks.first().getAttribute('href');
    expect(href).toContain('name=Player');
  });

  test('player link navigates to player page', async ({ page }) => {
    const found = await navigateToDraftYearWithData(page);
    expect(found).toBe(true);

    const playerLink = page.locator('.draft-history-table a[href*="pid="]').first();
    const href = await playerLink.getAttribute('href');
    expect(href).toBeTruthy();

    await page.goto(href!);
    await assertNoPhpErrors(page, 'on player page from Draft History link');
  });

  test('year dropdown navigates correctly', async ({ page }) => {
    const yearValue = await getFirstDraftYear(page);
    expect(yearValue).toBeTruthy();

    await page.goto(`modules.php?name=DraftHistory&year=${yearValue}`);
    await assertNoPhpErrors(page, `on DraftHistory year=${yearValue}`);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft History page');
  });
});

test.describe('HTMX year switching', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test('year dropdown swaps to the selected year-specific picks', async ({
    page,
  }) => {
    await page.goto('modules.php?name=DraftHistory');

    await assertHtmxSwap(page, {
      trigger: () =>
        page.locator('#draft-year-select').selectOption(YEAR_WITH_ONE_PICK),
      apiUrlPattern: (url) => url.includes('DraftHistory'),
      expectedUrl: new RegExp('year=' + YEAR_WITH_ONE_PICK),
      contentSelector: '#draft-history-content',
    });

    // Content delta: the swapped table shows only the 2026 pick, never the
    // 2021 pick — proving the year param actually filtered the result set.
    const table = page.locator('.draft-history-table');
    await expect(table.first()).toBeVisible();
    await expect(table).toContainText(PICK_IN_2026);
    await expect(table).not.toContainText(PICK_IN_2021);
  });

  test('year change updates URL', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');

    await assertHtmxSwap(page, {
      trigger: () =>
        page.locator('#draft-year-select').selectOption(YEAR_WITH_ONE_PICK),
      apiUrlPattern: (url) => url.includes('DraftHistory'),
      expectedUrl: new RegExp('year=' + YEAR_WITH_ONE_PICK),
      contentSelector: '#draft-history-content',
    });

    expect(page.url()).toContain('year=' + YEAR_WITH_ONE_PICK);
  });
});

test.describe('browser back/forward after HTMX year switch', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test('back/forward works after year switch', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');

    const yearValue = YEAR_WITH_ONE_PICK;

    await assertHtmxSwap(page, {
      trigger: () =>
        page.locator('#draft-year-select').selectOption(yearValue),
      apiUrlPattern: (url) => url.includes('DraftHistory'),
      expectedUrl: new RegExp('year=' + yearValue),
      contentSelector: '#draft-history-content',
    });

    await page.goBack();
    await page.waitForURL(/DraftHistory/, { timeout: 10000 });
    expect(page.url()).not.toContain('year=' + yearValue);

    await page.goForward();
    await page.waitForURL(new RegExp('year=' + yearValue), { timeout: 10000 });
    expect(page.url()).toContain('year=' + yearValue);
  });
});

test.describe('no-JS fallback', () => {
  test.use({ javaScriptEnabled: false });

  test('page renders correctly with JavaScript disabled', async ({ page }) => {
    // With JS disabled, onchange can't fire — verify server-side rendering path
    await page.goto('modules.php?name=DraftHistory');
    const noData = page.locator('.draft-no-data');
    const table = page.locator('.draft-history-table');
    const noDataCount = await noData.count();
    const tableCount = await table.count();
    expect(noDataCount + tableCount).toBeGreaterThan(0);
    await assertNoPhpErrors(page, 'on DraftHistory with JS disabled');

    // Direct URL with year param also works
    await page.goto('modules.php?name=DraftHistory&year=9999');
    await assertNoPhpErrors(page, 'on DraftHistory year=9999 with JS disabled');
  });
});
