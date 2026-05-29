import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Waivers — authenticated page with explicit state control.
// NOTE: NEVER submit a waiver claim — read-only assertions only.
test.describe('Waivers flow: closed', () => {
  test.beforeEach(async ({ appState, page }) => {
    // Phase must be Free Agency or Preseason for the toggle to matter —
    // during HEAT/Regular Season/Playoffs, waivers are always open.
    await appState({ 'Current Season Phase': 'Free Agency', 'Allow Waiver Moves': 'No' });
    await page.goto('modules.php?name=Waivers');
  });

  test('page loads without PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Waivers page');
  });

  test('shows closed message with no form elements', async ({ page }) => {
    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toHaveCount(0);
  });
});

test.describe('Waivers flow: open', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers');
  });

  test('waiver form has expected structure', async ({ page }) => {
    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toBeVisible();
    await expect(page.locator('select[name="Player_ID"]')).toBeVisible();
    await expect(
      form.locator('button[type="submit"], input[type="submit"]').first(),
    ).toBeVisible();
  });

  test('player select has options', async ({ page }) => {
    const playerSelect = page.locator('select[name="Player_ID"]');
    await expect(playerSelect).toBeVisible();
    const options = playerSelect.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('team logo and roster info visible', async ({ page }) => {
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
    await expect(page.locator('.ibl-card').first()).toBeVisible();
  });

  test('page loads without PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Waivers page (open)');
  });
});

test.describe('Waivers flow: closed — message text', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'Allow Waiver Moves': 'No',
    });
    await page.goto('modules.php?name=Waivers');
  });

  test('shows exact closed message text', async ({ page }) => {
    await expect(
      page.getByText(
        'Sorry, but players may not be added from or dropped to waivers at the present time.',
      ),
    ).toBeVisible();
  });
});

test.describe('Waivers: view switcher tabs', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers');
  });

  test('view switcher tabs are present', async ({ page }) => {
    const tabs = page.locator('.ibl-tab');
    expect(await tabs.count()).toBeGreaterThanOrEqual(4);
  });

  test('clicking tab switches displayed table', async ({ page }) => {
    const totalTab = page.locator('.ibl-tab').filter({ hasText: /total/i });
    await expect(totalTab.first(), 'Waivers must render the "Total" tab').toBeVisible();
    await totalTab.first().click();
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('no PHP errors after tab switch', async ({ page }) => {
    const tab = page.locator('.ibl-tab').nth(1);
    await expect(tab, 'Waivers tab index 1 must render').toBeVisible();
    await tab.click();
    await assertNoPhpErrors(page, 'after tab switch');
  });

  test('stat-column header set differs between ratings and total_s views', async ({ page }) => {
    // ratings view renders percentage/attribute columns (2g%, fta, oo, do, ...)
    await page.goto('modules.php?name=Waivers&display=ratings');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const ratingsHeader = (
      await page.locator('.ibl-data-table thead').first().textContent()
    )?.replace(/\s+/g, ' ').trim() ?? '';

    // total_s view renders season-totals columns (g, gs, min, fgm, fga, ...)
    await page.goto('modules.php?name=Waivers&display=total_s');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    const totalsHeader = (
      await page.locator('.ibl-data-table thead').first().textContent()
    )?.replace(/\s+/g, ' ').trim() ?? '';

    expect(ratingsHeader).not.toEqual(totalsHeader);
    expect(ratingsHeader).toContain('2g%');
    expect(totalsHeader).toContain('fgm');
  });
});
