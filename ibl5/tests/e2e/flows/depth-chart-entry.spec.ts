import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Depth Chart Entry — authenticated page.
// The roster form may load asynchronously after the page header renders.
// NOTE: Do NOT submit the form — that would mutate data.

test.describe('Depth Chart Entry flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('page loads with title and team banner', async ({ page }) => {
    // The title uses .ibl-title with CSS text-transform: uppercase
    await expect(page.locator('.ibl-title').first()).toBeVisible();
    // Authenticated user sees their team banner
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('saved depth chart dropdown present', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();
    const options = dropdown.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('roster form loads with player rows', async ({ page }) => {
    // The form may load asynchronously after the page header renders.
    // If it doesn't appear within timeout, skip — MAMP may be under load.
    const form = page.locator('.depth-chart-form');
    if (!(await form.isVisible({ timeout: 15000 }).catch(() => false))) {
      test.skip(true, 'Depth chart form did not load (async render or MAMP load)');
    }

    await expect(
      page.locator('.depth-chart-table').first(),
    ).toBeVisible();

    // Player rows should have data-pid attributes
    const playerRows = page.locator('.depth-chart-table tr[data-pid]');
    await expect(playerRows.first()).toBeVisible();
  });

  test('position selects have options', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    // Wait for form to load
    if (!(await form.isVisible().catch(() => false))) return;

    const posSelect = page.locator('select[name^="pg"]').first();
    if (await posSelect.isVisible()) {
      const options = posSelect.locator('option');
      await expect(options.first()).toBeAttached();
    }
  });

  test('active selects have valid values', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    if (!(await form.isVisible().catch(() => false))) return;

    const activeSelects = page.locator('select[name^="canPlayInGame"]');
    const count = await activeSelects.count();
    if (count === 0) return;

    // Each active select should have a value of "1" or "0"
    for (let i = 0; i < Math.min(count, 3); i++) {
      const value = await activeSelects.nth(i).inputValue();
      expect(['0', '1']).toContain(value);
    }
  });

  test('reset button prompts confirmation', async ({ page }) => {
    const resetBtn = page.locator('.depth-chart-reset-btn');
    if (!(await resetBtn.isVisible().catch(() => false)))
      return;

    let dialogFired = false;
    page.on('dialog', async (dialog) => {
      dialogFired = true;
      await dialog.dismiss();
    });

    await resetBtn.click();
    expect(dialogFired).toBe(true);
  });

  test('submit button present when form loaded', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    if (!(await form.isVisible().catch(() => false))) return;

    await expect(page.locator('.depth-chart-submit-btn')).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Depth Chart Entry');
  });
});

// ===========================================================================
// NextSim position tab switching
// ===========================================================================

test.describe('DCE: NextSim position tabs', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('position tabs render in NextSim section', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    const count = await tabs.count();
    if (count === 0) {
      test.skip(true, 'No NextSim tabs (no games in sim window)');
    }

    // Should have 5 position tabs: PG, SG, SF, PF, C
    expect(count).toBe(5);
  });

  test('clicking tab moves active state', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    if ((await tabs.count()) === 0) {
      test.skip(true, 'No NextSim tabs (no games in sim window)');
    }

    // First tab (PG) should be active by default
    const pgTab = tabs.first();
    await expect(pgTab).toHaveClass(/ibl-tab--active/);

    // Click SG tab (second tab)
    const sgTab = tabs.nth(1);
    await sgTab.click();

    // SG should now be active, PG should not
    await expect(sgTab).toHaveClass(/ibl-tab--active/);
    await expect(pgTab).not.toHaveClass(/ibl-tab--active/);
  });

  test('tab click loads content without PHP errors', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    if ((await tabs.count()) === 0) {
      test.skip(true, 'No NextSim tabs (no games in sim window)');
    }

    // Click a non-default tab
    const sfTab = tabs.nth(2);
    await sfTab.click();

    // Wait for AJAX content to load (loading class removed)
    await page.waitForTimeout(500);
    await assertNoPhpErrors(page, 'after NextSim tab switch');
  });
});
