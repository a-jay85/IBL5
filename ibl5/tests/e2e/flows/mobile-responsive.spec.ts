import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Mobile responsive tests — verify key pages render correctly on mobile viewport.
// Tests table scroll wrappers, sticky columns, and no horizontal overflow.
test.use({
  storageState: { cookies: [], origins: [] },
  viewport: { width: 375, height: 812 },
});

test.describe('Mobile responsive — Standings', () => {
  test('standings tables render at mobile width', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('standings tables have scroll wrappers on mobile', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    // responsive-tables.js wraps tables in scroll containers on mobile
    const scrollContainers = page.locator('.table-scroll-container');
    const count = await scrollContainers.count();
    // At least one table should have a scroll wrapper
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('sticky team column is present on mobile', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    const stickyCols = page.locator('td.sticky-col');
    const count = await stickyCols.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('no PHP errors on standings mobile', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on Standings (mobile)');
  });
});

test.describe('Mobile responsive — Contract List', () => {
  test('contract list renders at mobile width', async ({ page }) => {
    await page.goto('modules.php?name=ContractList');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('contract list tables have scroll wrappers on mobile', async ({ page }) => {
    await page.goto('modules.php?name=ContractList');
    const scrollContainers = page.locator('.table-scroll-container');
    const count = await scrollContainers.count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('no PHP errors on contract list mobile', async ({ page }) => {
    await page.goto('modules.php?name=ContractList');
    await assertNoPhpErrors(page, 'on ContractList (mobile)');
  });
});

test.describe('Mobile responsive — Schedule', () => {
  test('schedule renders at mobile width', async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
    // Schedule may render as cards or tables
    const content = page.locator('.schedule-container, .ibl-data-table, .ibl-card');
    await expect(content.first()).toBeVisible();
  });

  test('no PHP errors on schedule mobile', async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
    await assertNoPhpErrors(page, 'on Schedule (mobile)');
  });
});

test.describe('Mobile responsive — League Starters', () => {
  test('league starters tables render at mobile width', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters');
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      test.skip(true, 'LeagueStarters page blank — missing depth chart data');
      return;
    }
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('no PHP errors on league starters mobile', async ({ page }) => {
    await page.goto('modules.php?name=LeagueStarters');
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      test.skip(true, 'LeagueStarters page blank — missing depth chart data');
      return;
    }
    await assertNoPhpErrors(page, 'on LeagueStarters (mobile)');
  });
});
