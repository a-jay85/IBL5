import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { assertColumnSorts } from '../helpers/sortable-table-page';
import { withPhases, CONTRACT_BOUNDARY_PHASES } from '../fixtures/phase';

// Cap Space — public page.

test.describe('Cap Space flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Contracts&tab=teams');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title')).toContainText(/Cap Info/i);
  });

  test('cap space table is visible', async ({ page }) => {
    const table = page.locator('.sticky-table').first();
    await expect(table).toBeVisible();
  });

  test('team rows have data-team-id attributes', async ({ page }) => {
    const teamRows = page.locator('tr[data-team-id]');
    const count = await teamRows.count();
    expect(count).toBeGreaterThanOrEqual(28);
  });

  test('sticky scroll wrapper exists for wide table', async ({ page }) => {
    // Cap space uses sticky table pattern
    const wrapper = page.locator('.sticky-scroll-wrapper');
    await expect(wrapper.first()).toBeVisible();
  });

  test('clicking a column header sorts the table', async ({ page }) => {
    await assertColumnSorts(page, {
      tableSelector: 'table.sortable',
      columnIndex: 1,
      minRows: 2,
    });
  });

  withPhases(CONTRACT_BOUNDARY_PHASES, () => {
    test('table has expected salary columns', async ({ page }) => {
      await page.goto('modules.php?name=Contracts&tab=teams');
      const table = page.locator('.sticky-table').first();
      await expect(table).toBeVisible();
      const headerText = await table.locator('thead').textContent();
      expect(headerText).toContain('Team');
    });

    test('MLE/LLE status indicators are present', async ({ page }) => {
      await page.goto('modules.php?name=Contracts&tab=teams');
      const table = page.locator('.sticky-table').first();
      await expect(table).toBeVisible();
      const headerText = await table.locator('thead').textContent();
      // Should contain MLE and LLE columns
      expect(headerText).toContain('MLE');
      expect(headerText).toContain('LLE');
    });

    test('no PHP errors', async ({ page }) => {
      await page.goto('modules.php?name=Contracts&tab=teams');
      await assertNoPhpErrors(page, 'on Cap Space page');
    });
  }, { test });

  test('legacy CapSpace URL redirects to Contracts teams tab', async ({ page }) => {
    await page.goto('modules.php?name=CapSpace');
    await expect(page).toHaveURL(/name=Contracts&tab=teams/);
    await expect(page.locator('.ibl-tab--active')).toHaveText('Teams');
  });

  test('bare Contracts URL defaults to teams tab', async ({ page }) => {
    await page.goto('modules.php?name=Contracts');
    await expect(page.locator('.ibl-tab--active')).toHaveText('Teams');
    await expect(page.locator('.ibl-title')).toContainText('Cap Info');
  });

  test('unknown tab value falls back to teams tab', async ({ page }) => {
    await page.goto('modules.php?name=Contracts&tab=bogus');
    await expect(page.locator('.ibl-tab--active')).toHaveText('Teams');
    await expect(page.locator('.ibl-tab--active')).toHaveCount(1);
    await assertNoPhpErrors(page, 'on Contracts with bogus tab');
  });

  test('players tab link switches content', async ({ page }) => {
    await page.goto('modules.php?name=Contracts&tab=teams');
    await page.locator('.ibl-tabs a.ibl-tab', { hasText: 'Players' }).click();
    await expect(page).toHaveURL(/tab=players/);
    await expect(page.locator('.ibl-title')).toContainText('Master Contract List');
  });
});
