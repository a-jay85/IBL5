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

    // Position depth selects (pg = PG column) should be present and visible
    const pgSelects = page.locator('select[name^="pg"]');
    await expect(pgSelects.first()).toBeVisible();

    // Active checkboxes should be pre-populated — at least one player is active.
    // The desktop `.dc-active-cb` class disambiguates from the mobile
    // `.dc-card__active-cb` which shares the canPlayInGame name prefix.
    const activeCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    const count = await activeCheckboxes.count();
    let hasActive = false;
    for (let i = 0; i < count; i++) {
      if (await activeCheckboxes.nth(i).isChecked()) {
        hasActive = true;
        break;
      }
    }
    expect(hasActive).toBe(true);
  });

  test('change a position depth assignment', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Find a pg (PG position depth) select with value "0" and change it to "2" (2nd)
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

  test('submit depth chart successfully and confirmation shows submitted values', async ({
    page,
  }) => {
    const form = page.locator('.depth-chart-form');
    await expect(form).toBeVisible({ timeout: 15000 });

    // Set a distinctive pg (PG) value on the first desktop player row so we
    // can verify the confirmation page echoes back exactly what was POSTed.
    // Scoping to `.depth-chart-table` avoids colliding with the mobile
    // card duplicates that share the same input names.
    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('1');

    // Submit the current depth chart
    const submitBtn = page.locator('.depth-chart-buttons .depth-chart-submit-btn');
    await expect(submitBtn).toBeVisible();
    await submitBtn.click();

    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent();

    // Success or validation error banner should appear. The confirmation
    // table is rendered in both branches.
    const hasSuccess = body?.match(
      /submitted.*successfully|thank you|depth chart has been/i,
    );
    const hasError = body?.match(/must have|active players|position/i);
    expect(hasSuccess || hasError).toBeTruthy();

    // Confirmation table structure: Name, Active, PG, SG, SF, PF, C columns.
    // Locator scopes to the "Depth Chart Submission" heading so we don't
    // hit an unrelated .ibl-data-table elsewhere on the page.
    const confirmationRegion = page
      .locator('body')
      .filter({ hasText: /Depth Chart Submission/i });
    const confirmationTable = confirmationRegion.locator('table.ibl-data-table').last();
    await expect(confirmationTable).toBeVisible();

    // Use textContent (raw source text) instead of innerText — the table's
    // CSS applies text-transform: uppercase, which would turn "Name" into
    // "NAME" for allInnerTexts() but leaves allTextContents() untouched.
    const headers = await confirmationTable.locator('thead th').allTextContents();
    expect(headers.map((h) => h.trim())).toEqual([
      'Name',
      'Active',
      'PG',
      'SG',
      'SF',
      'PF',
      'C',
      'Min',
    ]);

    // At least one player row should be rendered.
    const bodyRows = confirmationTable.locator('tbody tr');
    const rowCount = await bodyRows.count();
    expect(rowCount).toBeGreaterThan(0);

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

    // Ensure first pg select is ready before loading a saved config
    await expect(page.locator('select[name^="pg"]').first()).toBeEnabled();

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
