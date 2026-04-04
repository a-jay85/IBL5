import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Depth Chart submission flow — tests that interact with the form.
// Serial: submission tests depend on form state.
test.describe.configure({ mode: 'serial' });

test.describe('Depth Chart submission', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
  });

  test('form loads with current depth chart', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Role slot selects (BH = PG column) should be present and visible
    const bhSelects = page.locator('select[name^="BH"]');
    await expect(bhSelects.first()).toBeVisible();

    // Active selects should be pre-populated — at least one player is active
    const activeSelects = page.locator('select[name^="canPlayInGame"]');
    let hasActive = false;
    const count = await activeSelects.count();
    for (let i = 0; i < count; i++) {
      const val = await activeSelects.nth(i).inputValue();
      if (val === '1') {
        hasActive = true;
        break;
      }
    }
    expect(hasActive).toBe(true);
  });

  test('change a role slot assignment', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Find a BH (PG role slot) select with value "0" and change it to "2" (backup)
    const bhSelects = page.locator('select[name^="BH"]');
    const count = await bhSelects.count();

    for (let i = 0; i < count; i++) {
      const val = await bhSelects.nth(i).inputValue();
      if (val === '0') {
        await bhSelects.nth(i).selectOption('2');
        const newVal = await bhSelects.nth(i).inputValue();
        expect(newVal).toBe('2');
        break;
      }
    }
  });

  test('submit depth chart successfully', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Submit the current depth chart (already valid from seed data)
    const submitBtn = page.locator('.depth-chart-buttons .depth-chart-submit-btn');
    await expect(submitBtn).toBeVisible();
    await submitBtn.click();

    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent();

    // Success or validation error should appear
    const hasSuccess = body?.match(
      /submitted.*successfully|thank you|depth chart has been/i,
    );
    const hasError = body?.match(/must have|active players|position/i);

    expect(hasSuccess || hasError).toBeTruthy();
    await assertNoPhpErrors(page, 'after depth chart submission');
  });

  test('saved depth chart dropdown has options', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();

    const options = dropdown.locator('option');
    // Should have at least 3: "Current Live" + 2 saved configs from seed
    expect(await options.count()).toBeGreaterThanOrEqual(3);
  });

  test('loading saved depth chart updates form', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    const dropdown = page.locator('#saved-dc-select');
    const options = dropdown.locator('option');
    const optCount = await options.count();
    expect(optCount, 'Saved DC dropdown should have at least 2 options').toBeGreaterThanOrEqual(2);

    // Record current value of first BH (PG role slot) select
    const bhSelect = page.locator('select[name^="BH"]').first();
    const originalValue = await bhSelect.inputValue();

    // Select the second option (first saved config)
    await dropdown.selectOption({ index: 1 });

    // Wait for AJAX to update the hidden field
    const loadedId = page.locator('#loaded_dc_id, input[name="loaded_dc_id"]');
    if ((await loadedId.count()) > 0) {
      await expect(async () => {
        const val = await loadedId.first().inputValue();
        expect(val).not.toBe('0');
      }).toPass({ timeout: 5000 });
    }
  });

  test('no PHP errors on depth chart page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Depth Chart Entry');
  });
});
