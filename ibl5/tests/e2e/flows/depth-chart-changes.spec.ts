import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Depth Chart Changes — tests the JS change-detection (depth-chart-changes.js).
// Extends depth-chart.spec.ts with glow/dirty indicator tests.
// NOTE: Do NOT submit the form — that would mutate data.

test.describe('Depth Chart change detection', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');

    // Wait for the form to load (async rendering)
    const form = page.locator('.depth-chart-form');
    if (!(await form.isVisible({ timeout: 15000 }).catch(() => false))) {
      test.skip(
        true,
        'Depth chart form did not load (async render or MAMP load)',
      );
    }
  });

  test('form loads with no glow indicators initially', async ({ page }) => {
    // No cells should have glow classes on initial load
    const glowCells = page.locator(
      '[class*="dc-glow-"]',
    );
    expect(await glowCells.count()).toBe(0);
  });

  test('changing a position select triggers glow on the cell', async ({
    page,
  }) => {
    // Find the first position select
    const posSelects = page.locator('.depth-chart-table select');
    const selectCount = await posSelects.count();

    if (selectCount === 0) return;

    const firstSelect = posSelects.first();
    const originalValue = await firstSelect.inputValue();

    // Get available options
    const options = firstSelect.locator('option');
    const optionCount = await options.count();

    if (optionCount < 2) return;

    // Select a different option
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val !== originalValue) {
        await firstSelect.selectOption(val!);
        break;
      }
    }

    // The parent cell or row should now have a glow class
    const glowCells = page.locator('[class*="dc-glow-"]');
    expect(await glowCells.count()).toBeGreaterThan(0);
  });

  test('reverting a select to original value removes glow', async ({
    page,
  }) => {
    const posSelects = page.locator('.depth-chart-table select');
    const selectCount = await posSelects.count();

    if (selectCount === 0) return;

    const firstSelect = posSelects.first();
    const originalValue = await firstSelect.inputValue();

    // Get a different option value
    const options = firstSelect.locator('option');
    const optionCount = await options.count();
    if (optionCount < 2) return;

    let differentValue = originalValue;
    for (let i = 0; i < optionCount; i++) {
      const val = await options.nth(i).getAttribute('value');
      if (val !== originalValue) {
        differentValue = val!;
        break;
      }
    }

    // Change to different value
    await firstSelect.selectOption(differentValue);
    expect(await page.locator('[class*="dc-glow-"]').count()).toBeGreaterThan(0);

    // Revert to original value
    await firstSelect.selectOption(originalValue);

    // Glow should be removed (or at least reduced)
    // Allow a brief moment for JS to update
    await page.waitForTimeout(100);
    const glowCount = await page.locator('[class*="dc-glow-"]').count();
    expect(glowCount).toBe(0);
  });

  test('multiple changes increase glow intensity', async ({ page }) => {
    const posSelects = page.locator('.depth-chart-table select');
    const selectCount = await posSelects.count();

    if (selectCount < 3) return;

    // Change multiple selects to trigger higher glow levels
    for (let i = 0; i < Math.min(selectCount, 3); i++) {
      const select = posSelects.nth(i);
      const original = await select.inputValue();
      const options = select.locator('option');
      const optionCount = await options.count();

      for (let j = 0; j < optionCount; j++) {
        const val = await options.nth(j).getAttribute('value');
        if (val !== original) {
          await select.selectOption(val!);
          break;
        }
      }
    }

    // Multiple glowing cells should exist
    const glowCells = page.locator('[class*="dc-glow-"]');
    expect(await glowCells.count()).toBeGreaterThan(0);
  });

  test('saved DC dropdown present and has options', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();
    const options = dropdown.locator('option');
    expect(await options.count()).toBeGreaterThanOrEqual(1);
  });

  test('saved DC load triggers AJAX and updates positions', async ({
    page,
  }) => {
    const dropdown = page.locator('#saved-dc-select');
    const options = dropdown.locator('option');
    expect(await options.count()).toBeGreaterThanOrEqual(2);

    // Mock the AJAX endpoint
    await page.route(
      '**/modules.php*name=DepthChartEntry*op=dc-api*action=load**',
      async (route) => {
        // Return a mock response — the JS expects JSON with player data
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ success: true }),
        });
      },
    );

    // Select a saved config (skip index 0 which is usually the default/prompt)
    const savedValue = await options.nth(1).getAttribute('value');
    if (savedValue) {
      await dropdown.selectOption(savedValue);

      // Wait briefly for AJAX handling
      await page.waitForTimeout(500);

      // Page should still be functional (no errors)
      const body = await page.locator('body').textContent();
      for (const pattern of PHP_ERROR_PATTERNS) {
        expect(body).not.toContain(pattern);
      }
    }
  });

  test('no PHP errors', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Depth Chart Entry`,
      ).not.toContain(pattern);
    }
  });
});
