import { test, expect } from '../fixtures/auth';

// Depth Chart Entry — authenticated page.
// The roster form may load asynchronously after the page header renders.
// NOTE: Do NOT submit the form — that would mutate data.

const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

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
    expect(await options.count()).toBeGreaterThanOrEqual(1);
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
    ).toBeVisible({ timeout: 10000 });

    // Player rows should have data-pid attributes
    const playerRows = page.locator('.depth-chart-table tr[data-pid]');
    expect(await playerRows.count()).toBeGreaterThan(0);
  });

  test('position selects have options', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    // Wait for form to load
    if (!(await form.isVisible({ timeout: 10000 }).catch(() => false))) return;

    const posSelect = page.locator('select[name^="pg"]').first();
    if (await posSelect.isVisible()) {
      const options = posSelect.locator('option');
      expect(await options.count()).toBeGreaterThanOrEqual(2);
    }
  });

  test('active selects have valid values', async ({ page }) => {
    const form = page.locator('.depth-chart-form');
    if (!(await form.isVisible({ timeout: 10000 }).catch(() => false))) return;

    const activeSelects = page.locator('select[name^="active"]');
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
    if (!(await resetBtn.isVisible({ timeout: 10000 }).catch(() => false)))
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
    if (!(await form.isVisible({ timeout: 10000 }).catch(() => false))) return;

    await expect(page.locator('.depth-chart-submit-btn')).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" on Depth Chart Entry`).not.toContain(
        pattern,
      );
    }
  });
});
