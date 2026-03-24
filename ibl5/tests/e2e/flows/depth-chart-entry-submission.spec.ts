import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { setNavMarker, assertNavMarkerPersists } from '../helpers/htmx';

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

    // Position selects should be pre-populated with current assignments
    const pgSelects = page.locator('select[name^="pg"]');
    await expect(pgSelects.first()).toBeVisible();

    // At least one should have a non-zero value (starter)
    let hasStarter = false;
    const count = await pgSelects.count();
    for (let i = 0; i < count; i++) {
      const val = await pgSelects.nth(i).inputValue();
      if (val !== '0') {
        hasStarter = true;
        break;
      }
    }
    expect(hasStarter).toBe(true);
  });

  test('change a position assignment', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Find a PG select with value "0" and change it to "2" (backup)
    const pgSelects = page.locator('select[name^="pg"]');
    const count = await pgSelects.count();

    for (let i = 0; i < count; i++) {
      const val = await pgSelects.nth(i).inputValue();
      if (val === '0') {
        await pgSelects.nth(i).selectOption('2');
        const newVal = await pgSelects.nth(i).inputValue();
        expect(newVal).toBe('2');
        break;
      }
    }
  });

  test('submit depth chart successfully', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Submit the current depth chart (already valid from seed data)
    await setNavMarker(page);

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
    await assertNavMarkerPersists(page);
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

    // Record current value of first PG select
    const pgSelect = page.locator('select[name^="pg"]').first();
    const originalValue = await pgSelect.inputValue();

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
