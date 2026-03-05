import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Waivers — authenticated page with explicit state control.
// NOTE: NEVER submit a waiver claim — read-only assertions only.
// Serial: open and closed blocks set the same setting (Allow Waiver Moves).
test.describe.configure({ mode: 'serial' });

test.describe('Waivers flow: closed', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'No' });
    await page.goto('modules.php?name=Waivers');
  });

  test('page loads without PHP errors', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Waivers page`,
      ).not.toContain(pattern);
    }
  });

  test('shows closed message with no form elements', async ({ page }) => {
    const form = page.locator('form[name="Waiver_Move"]');
    expect(await form.count()).toBe(0);
  });
});

test.describe('Waivers flow: open', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers');
  });

  test('waiver form has expected structure', async ({ page }) => {
    await expect(page.locator('form[name="Waiver_Move"]')).toBeVisible();
    await expect(page.locator('select[name="Player_ID"]')).toBeVisible();
    await expect(
      page.locator('button[type="submit"], input[type="submit"]').first(),
    ).toBeVisible();
  });

  test('player select has options', async ({ page }) => {
    const playerSelect = page.locator('select[name="Player_ID"]');
    await expect(playerSelect).toBeVisible();
    const options = playerSelect.locator('option');
    expect(await options.count()).toBeGreaterThanOrEqual(1);
  });

  test('team logo and roster info visible', async ({ page }) => {
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
    await expect(page.locator('.ibl-card').first()).toBeVisible();
  });

  test('page loads without PHP errors', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Waivers page (open)`,
      ).not.toContain(pattern);
    }
  });
});
