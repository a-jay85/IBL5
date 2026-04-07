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
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    await expect(
      page.locator('.depth-chart-table').first(),
    ).toBeVisible();

    // Player rows should have data-pid attributes
    const playerRows = page.locator('.depth-chart-table tr[data-pid]');
    await expect(playerRows.first()).toBeVisible();
  });

  test('role slot selects have options', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Position selects (pg/sg/sf/pf/c) are now hidden inputs; role slot
    // selects use field names BH, DI, OI, DF, OF for PG/SG/SF/PF/C columns.
    const roleSelect = page.locator('select[name^="BH"]').first();
    await expect(roleSelect).toBeVisible();
    const options = roleSelect.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('active checkboxes render as form controls', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Active field is a checkbox (not a select) as of the depth-chart-redesign
    // refactor. Use the desktop `.dc-active-cb` class to disambiguate from the
    // mobile `.dc-card__active-cb` which shares the name prefix.
    const activeCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    await expect(activeCheckboxes.first()).toBeAttached();
    // At least one player in the seed should have `checked` pre-set.
    const count = await activeCheckboxes.count();
    expect(count).toBeGreaterThan(0);
  });

  test('minutes renders as number input 0-40', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    const minInputs = page.locator('input[type="number"][name^="min"]');
    await expect(minInputs.first()).toBeAttached();
    // The server constrains the input to 0-40 via min/max attributes.
    await expect(minInputs.first()).toHaveAttribute('min', '0');
    await expect(minInputs.first()).toHaveAttribute('max', '40');
    await expect(minInputs.first()).toHaveAttribute('step', '1');
  });

  test('reset button prompts confirmation', async ({ page }) => {
    const resetBtn = page.locator('.depth-chart-buttons .depth-chart-reset-btn');
    await expect(resetBtn).toBeVisible({ timeout: 15000 });

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
    await expect(form).toBeVisible({ timeout: 15000 });

    await expect(page.locator('.depth-chart-buttons .depth-chart-submit-btn')).toBeVisible();
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
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('position tabs render in NextSim section', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    // Should have 5 position tabs: PG, SG, SF, PF, C
    await expect(tabs).toHaveCount(5);
    await expect(tabs.first()).toBeVisible();
  });

  test('tab click loads content without PHP errors', async ({ page }) => {
    const tabs = page.locator('.nextsim-tab-container .ibl-tab');
    await expect(tabs).toHaveCount(5);

    // Click a non-default tab and wait for HTMX response
    const sfTab = tabs.nth(2);
    await Promise.all([
      page.waitForResponse(resp => resp.url().includes('nextsim-api') && resp.status() === 200),
      sfTab.click(),
    ]);
    await assertNoPhpErrors(page, 'after NextSim tab switch');
  });
});
