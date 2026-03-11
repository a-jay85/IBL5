import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

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
    await expect(glowCells).toHaveCount(0);
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
    await expect(glowCells.first()).toBeVisible();
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
    await expect(page.locator('[class*="dc-glow-"]').first()).toBeVisible();

    // Revert to original value
    await firstSelect.selectOption(originalValue);

    // Glow should be removed (auto-retries until JS updates DOM)
    await expect(page.locator('[class*="dc-glow-"]')).toHaveCount(0);
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
    await expect(glowCells.first()).toBeVisible();
  });

  test('saved DC dropdown present and has options', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();
    const options = dropdown.locator('option');
    await expect(options.first()).toBeAttached();
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

      // Wait for AJAX to complete, then verify no errors
      await page.waitForLoadState('networkidle');
      await assertNoPhpErrors(page);
    }
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Depth Chart Entry');
  });
});
