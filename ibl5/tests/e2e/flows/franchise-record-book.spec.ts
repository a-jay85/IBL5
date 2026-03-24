import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Franchise Record Book — public page, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Franchise Record Book flow', () => {
  test('league-wide view loads with correct title', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const title = page.locator('.ibl-title').first();
    await expect(title).toBeVisible();
    const titleText = await title.textContent();
    expect(titleText?.toLowerCase()).toContain('league');
  });

  test('team selector has 28+ team options', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const teamSelect = page.locator('#record-book-team');
    await expect(teamSelect).toBeVisible();

    const options = teamSelect.locator('option');
    // 28 real teams + at least 1 default option
    expect(await options.count()).toBeGreaterThanOrEqual(28);
  });

  test('league-wide view renders grid with stat tables', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const grid = page.locator('.ibl-grid.ibl-grid--3col');
    await expect(grid.first()).toBeVisible();

    const tables = page.locator('.stat-table');
    await expect(tables.first()).toBeVisible();
  });

  test('selecting a team switches to team view via HTMX', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const teamSelect = page.locator('#record-book-team');
    await expect(teamSelect).toBeVisible();

    // Select the first real team option (skip "League-Wide" at index 0)
    const options = teamSelect.locator('option');
    expect(await options.count()).toBeGreaterThan(1);

    const teamValue = await options.nth(1).getAttribute('value');
    const teamName = (await options.nth(1).textContent())?.trim() ?? '';

    // HTMX swaps #record-book-content — wait for the content update
    await teamSelect.selectOption(teamValue!);

    // The title inside #record-book-content updates to show the team name
    const title = page.locator('#record-book-content .ibl-title').first();
    await expect(title).toContainText(teamName, { timeout: 10_000 });
    await expect(title).toContainText(/Record Book/i);
  });

  test('team view uses narrower grid layout', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');

    // Team view uses 4-col grid (narrower tables without Team column)
    const grid4col = page.locator('.ibl-grid--4col');
    await expect(grid4col.first()).toBeVisible();
  });

  test('career records section absent in team view', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');

    // In team view, the Career Records section should not be rendered
    const sectionTitles = page.locator('.record-book-section-title');
    const titles = await sectionTitles.allTextContents();
    const hasCareer = titles.some((t) =>
      t.toLowerCase().includes('career'),
    );
    expect(hasCareer).toBe(false);
  });

  test('direct URL loads team records', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');

    // Should load without errors and show stat tables
    const tables = page.locator('.stat-table');
    await expect(tables.first()).toBeVisible();
  });

  test('no PHP errors on league-wide view', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    await assertNoPhpErrors(page, 'on league-wide Record Book');
  });

  test('team selection does not trigger full page reload', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    // Mark nav to verify it persists (proves no full reload)
    await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      if (navEl) navEl.setAttribute('data-htmx-marker', '1');
    });

    const teamSelect = page.locator('#record-book-team');
    const options = teamSelect.locator('option');
    const teamValue = await options.nth(1).getAttribute('value');

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('FranchiseRecordBook') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      teamSelect.selectOption(teamValue!),
    ]);

    // Nav marker survived — no full page reload occurred
    const marker = await page.evaluate(() =>
      document.querySelector('nav.fixed')?.getAttribute('data-htmx-marker'),
    );
    expect(marker).toBe('1');
  });

  test('team selection updates URL', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const teamSelect = page.locator('#record-book-team');
    const options = teamSelect.locator('option');
    const teamValue = await options.nth(1).getAttribute('value');

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('FranchiseRecordBook') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      teamSelect.selectOption(teamValue!),
    ]);

    await page.waitForURL(/teamid=/);
    expect(page.url()).toContain('teamid=');
  });

  test('no PHP errors on team view', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');

    await assertNoPhpErrors(page, 'on team Record Book');
  });
});

test.describe('browser back/forward after HTMX team switch', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });

  test('back/forward works after team switch', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook');

    const teamSelect = page.locator('#record-book-team');
    const options = teamSelect.locator('option');
    const teamValue = await options.nth(1).getAttribute('value');
    const teamName = (await options.nth(1).textContent())?.trim() ?? '';

    await Promise.all([
      page.waitForResponse(
        (r) =>
          r.url().includes('FranchiseRecordBook') &&
          r.url().includes('op=api') &&
          r.status() === 200,
      ),
      teamSelect.selectOption(teamValue!),
    ]);

    await page.waitForURL(/teamid=/);

    // Verify team view loaded
    const title = page.locator('#record-book-content .ibl-title').first();
    await expect(title).toContainText(teamName, { timeout: 10_000 });

    // Go back to league-wide view
    await page.goBack();
    await page.waitForURL(/FranchiseRecordBook/);
    expect(page.url()).not.toContain('teamid=');

    // Go forward to team view
    await page.goForward();
    await page.waitForURL(/teamid=/);
    expect(page.url()).toContain('teamid=');
  });
});

test.describe('no-JS fallback', () => {
  test.use({ javaScriptEnabled: false });

  test('pages render correctly with JavaScript disabled', async ({ page }) => {
    // League-wide view
    await page.goto('modules.php?name=FranchiseRecordBook');
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on league-wide Record Book with JS disabled');

    // Team-specific view via direct URL
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');
    await expect(page.locator('.stat-table').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on team Record Book with JS disabled');
  });
});
