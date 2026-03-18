import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Sortable tables — public, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Sortable table functionality', () => {
  test.describe('Standings page sorting', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto('modules.php?name=Standings');
    });

    test('clicking a column header sorts the table', async ({ page }) => {
      const firstTable = page.locator('table.sortable').first();
      // Wait for sorttable to initialize
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      // Click Win% header to sort
      const winPctHeader = firstTable.locator('thead th:nth-child(3)');
      await winPctHeader.click();

      // Verify the header got the sorted class
      await expect(winPctHeader).toHaveClass(/sorttable_sorted/);

      // Verify ascending indicator exists
      const fwdIndicator = page.locator('#sorttable_sortfwdind');
      await expect(fwdIndicator).toBeAttached();
    });

    test('clicking same header twice reverses sort', async ({ page }) => {
      const firstTable = page.locator('table.sortable').first();
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      const winPctHeader = firstTable.locator('thead th:nth-child(3)');

      // First click — ascending
      await winPctHeader.click();
      await expect(winPctHeader).toHaveClass(/sorttable_sorted(?!_reverse)/);

      // Second click — descending
      await winPctHeader.click();
      await expect(winPctHeader).toHaveClass(/sorttable_sorted_reverse/);

      // Verify reverse indicator exists
      const revIndicator = page.locator('#sorttable_sortrevind');
      await expect(revIndicator).toBeAttached();
    });

    test('aria-sort attribute is set correctly', async ({ page }) => {
      const firstTable = page.locator('table.sortable').first();
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      const winPctHeader = firstTable.locator('thead th:nth-child(3)');

      // After init — should be none
      await expect(winPctHeader).toHaveAttribute('aria-sort', 'none');

      // First click — ascending
      await winPctHeader.click();
      await expect(winPctHeader).toHaveAttribute('aria-sort', 'ascending');

      // Second click — descending
      await winPctHeader.click();
      await expect(winPctHeader).toHaveAttribute('aria-sort', 'descending');

      // Third click — back to ascending
      await winPctHeader.click();
      await expect(winPctHeader).toHaveAttribute('aria-sort', 'ascending');
    });

    test('switching columns clears previous sort state', async ({ page }) => {
      const firstTable = page.locator('table.sortable').first();
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      const col3 = firstTable.locator('thead th:nth-child(3)');
      const col4 = firstTable.locator('thead th:nth-child(4)');

      // Sort by column 3
      await col3.click();
      await expect(col3).toHaveClass(/sorttable_sorted/);

      // Sort by column 4 — column 3 should lose sorted class
      await col4.click();
      await expect(col4).toHaveClass(/sorttable_sorted/);
      await expect(col3).not.toHaveClass(/sorttable_sorted/);
      await expect(col3).toHaveAttribute('aria-sort', 'none');
    });

    test('sorttable_customkey is used for sorting', async ({ page }) => {
      // Standings has sorttable_customkey on Streak column
      const firstTable = page.locator('table.sortable').first();
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      // Find the Streak header
      const headers = firstTable.locator('thead th');
      const headerTexts = await headers.allTextContents();
      const streakIdx = headerTexts.findIndex((t) => t.includes('Streak'));

      if (streakIdx >= 0) {
        const streakHeader = headers.nth(streakIdx);
        await streakHeader.click();
        await expect(streakHeader).toHaveClass(/sorttable_sorted/);

        // Verify customkey cells exist (they have the attribute)
        const customKeyCells = firstTable.locator(
          `tbody td:nth-child(${streakIdx + 1})[sorttable_customkey]`,
        );
        expect(await customKeyCells.count()).toBeGreaterThan(0);
      }
    });

    test('no PHP errors on standings page', async ({ page }) => {
      await assertNoPhpErrors(page, 'on Standings page');
    });
  });

  test.describe('Franchise History sorting', () => {
    test('franchise history table is sortable', async ({ page }) => {
      await page.goto('modules.php?name=FranchiseHistory');
      const sortableTable = page.locator('table.sortable').first();
      await expect(sortableTable).toHaveAttribute('data-sorttable', 'true');

      // Click the first sortable header
      const firstHeader = sortableTable.locator('thead th').first();
      await firstHeader.click();
      await expect(firstHeader).toHaveClass(/sorttable_sorted/);
      await expect(firstHeader).toHaveAttribute('aria-sort', 'ascending');
    });

    test('no PHP errors on franchise history page', async ({ page }) => {
      await page.goto('modules.php?name=FranchiseHistory');
      await assertNoPhpErrors(page, 'on Franchise History page');
    });
  });

  test.describe('No console errors', () => {
    test('no JS console errors on page with sortable tables', async ({ page }) => {
      const errors: string[] = [];
      page.on('console', (msg) => {
        if (msg.type() === 'error') {
          errors.push(msg.text());
        }
      });

      await page.goto('modules.php?name=Standings');
      const firstTable = page.locator('table.sortable').first();
      await expect(firstTable).toHaveAttribute('data-sorttable', 'true');

      // Click a sort header to exercise the code
      const header = firstTable.locator('thead th:nth-child(3)');
      await header.click();
      await header.click();

      expect(errors).toHaveLength(0);
    });
  });
});
