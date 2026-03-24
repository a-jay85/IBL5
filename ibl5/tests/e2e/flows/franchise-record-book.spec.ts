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

  test('no PHP errors on team view', async ({ page }) => {
    await page.goto('modules.php?name=FranchiseRecordBook&teamid=1');

    await assertNoPhpErrors(page, 'on team Record Book');
  });
});
