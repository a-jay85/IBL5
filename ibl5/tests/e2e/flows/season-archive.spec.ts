import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Season Archive — public page.
test.use({ storageState: publicStorageState() });

test.describe('Season Archive flow', () => {
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

  test('no PHP errors on index page', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive');
    await assertNoPhpErrors(page, 'on Season Archive index');
  });

  test('no PHP errors on nonexistent year', async ({ page }) => {
    await page.goto('modules.php?name=SeasonArchive&year=1900');
    await assertNoPhpErrors(page, 'on Season Archive invalid year');
  });
});
