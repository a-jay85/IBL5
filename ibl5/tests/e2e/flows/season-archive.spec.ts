import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Season Archive — public page.
test.use({ storageState: publicStorageState() });

test.describe('Season Archive flow', () => {
  test('index page loads with title', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await expect(page.locator('.ibl-title')).toContainText(/Season Archive/i);
  });

  test('index table has .season-archive-index-table class', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await expect(page.locator('.season-archive-index-table')).toBeVisible();
  });

  test('index has 4 column headers: Season, HEAT Champion, IBL Champion, MVP', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    const headers = page.locator('.season-archive-index-table thead th');
    await expect(headers).toHaveCount(4);

    await expect(headers.nth(0)).toContainText('Season');
    await expect(headers.nth(1)).toContainText('HEAT');
    await expect(headers.nth(2)).toContainText('IBL');
    await expect(headers.nth(3)).toContainText('MVP');
  });

  test('index has at least 3 season rows', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    // CI seed has ibl_awards for years 2024, 2025, 2026
    const rows = page.locator('.season-archive-index-table tbody tr');
    expect(await rows.count()).toBeGreaterThanOrEqual(3);
  });

  test('season links navigate to detail page', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    const link = page.locator('.season-archive-index-table tbody a[href*="year="]').first();
    const href = await link.getAttribute('href');
    expect(href).toBeTruthy();
    await page.goto(href!);
    await assertNoPhpErrors(page, 'on Season Archive detail via link');
  });

  test('detail page renders without errors for year 2026', async ({ page }) => {
    // Year 2026 (season 38) exceeds the archive season cap — no detail sections render,
    // but the page must load without PHP errors.
    await page.goto('modules.php?name=SeasonArchive&year=2026');
    await assertNoPhpErrors(page, 'on Season Archive detail year=2026');
  });

  test('nonexistent year shows no sections', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive&year=1800');
    await assertNoPhpErrors(page, 'on Season Archive year=1800');
    await expect(page.locator('.season-archive-section')).toHaveCount(0);
  });

  test('index table is sortable', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    const sortable = page.locator('.sortable');
    await expect(sortable.first()).toBeVisible();
  });

  test('no PHP errors on index page', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await assertNoPhpErrors(page, 'on Season Archive index');
  });

  test('no PHP errors on nonexistent year', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive&year=1900');
    await assertNoPhpErrors(page, 'on Season Archive invalid year');
  });
});
