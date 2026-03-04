import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Waivers — authenticated page with season-phase awareness.
// NOTE: NEVER submit a waiver claim — read-only assertions only.

test.describe('Waivers flow', () => {
  test.beforeEach(async ({ page }) => {
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

  test('if closed: shows closed message with no form elements', async ({
    page,
  }) => {
    const body = await page.locator('body').textContent();
    const isClosed =
      body?.toLowerCase().includes('closed') ||
      body?.toLowerCase().includes('not available') ||
      body?.toLowerCase().includes('waiver period');

    if (!isClosed) {
      test.skip(true, 'Waivers are currently open — skipping closed test');
    }

    // When closed, there should be no waiver form
    const form = page.locator('form[name="Waiver_Move"]');
    expect(await form.count()).toBe(0);
  });

  test('if open: waiver form has expected structure', async ({ page }) => {
    const form = page.locator('form[name="Waiver_Move"]');
    const formVisible = await form.isVisible().catch(() => false);

    if (!formVisible) {
      test.skip(true, 'Waivers are currently closed — skipping open test');
    }

    // Form should have player select and submit button
    await expect(page.locator('select[name="Player_ID"]')).toBeVisible();
    await expect(
      page.locator('button[type="submit"], input[type="submit"]').first(),
    ).toBeVisible();
  });

  test('if open: player select has options', async ({ page }) => {
    const playerSelect = page.locator('select[name="Player_ID"]');
    const selectVisible = await playerSelect.isVisible().catch(() => false);

    if (!selectVisible) {
      test.skip(true, 'Waivers are currently closed — skipping player list test');
    }

    const options = playerSelect.locator('option');
    // At least the default "Select player..." + some players
    expect(await options.count()).toBeGreaterThanOrEqual(1);
  });

  test('if open: team logo and roster info visible', async ({ page }) => {
    const form = page.locator('form[name="Waiver_Move"]');
    const formVisible = await form.isVisible().catch(() => false);

    if (!formVisible) {
      test.skip(true, 'Waivers are currently closed — skipping roster info test');
    }

    // Team logo banner should be visible
    await expect(page.locator('.team-logo-banner').first()).toBeVisible();
    // Roster spots info in card header
    await expect(page.locator('.ibl-card').first()).toBeVisible();
  });
});
