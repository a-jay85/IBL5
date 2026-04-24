import { test, expect } from '../fixtures/auth-isolated';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Depth Chart submission flow — these tests mutate shared DB state via form
// POSTs, so they run serially. They collectively exercise the Post-Redirect-
// Get (PRG) implementation behind the fix for the "Invalid or expired form
// submission" regression that users hit after submit → back → resubmit.
//
// Isolation: the auth-dc fixture sets a `_test_dc_team` cookie that overrides
// the logged-in user's team to Monarchs (tid=8). The Monarchs roster has
// exactly 12 active players with proper depth coverage (seeded in ci-seed.sql)
// and is not referenced by any other spec, so parallel workers cannot pollute it.
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

  test('submit redirects back to module URL with success flash and fresh form', async ({ page }) => {
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Distinctive but non-starter value (3 = "3rd backup") so we don't
    // create a "starter at multiple positions" validation failure for a
    // player who may already be a 1st at another position in the seed.
    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('3');

    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    // PRG: URL lands back at the module base — no `&op=submit` artifact.
    await expect(page).toHaveURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/);

    // Flash success visible with expected copy.
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await expect(page.locator('.ibl-alert--success')).toContainText(/saved/i);

    // Form is still visible (fresh GET re-renders it).
    await expect(page.locator('.depth-chart-form')).toBeVisible();

    // Submitted value persisted — the fresh GET reads from DB.
    await expect(
      page.locator('.depth-chart-table select[name^="pg"]').first(),
    ).toHaveValue('3');

    await assertNoPhpErrors(page, 'after depth chart submission');
  });

  test('CSRF token rotates across submit', async ({ page }) => {
    // Tokens are single-use + regenerated on every page render, so a fresh
    // GET after PRG must produce a different `_csrf_token` value.
    const tokenBefore = await page
      .locator('input[name="_csrf_token"]')
      .first()
      .getAttribute('value');
    expect(tokenBefore).toMatch(/^[a-f0-9]{64}$/);

    // Benign non-starter change: toggle between 3rd and 4th. Both are
    // non-"1st" so they never trigger the multi-starter validation rule.
    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    const before = await firstPg.inputValue();
    await firstPg.selectOption(before === '3' ? '4' : '3');

    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();
    await expect(page.locator('.ibl-alert--success')).toBeVisible();

    const tokenAfter = await page
      .locator('input[name="_csrf_token"]')
      .first()
      .getAttribute('value');
    expect(tokenAfter).toMatch(/^[a-f0-9]{64}$/);
    expect(tokenAfter).not.toBe(tokenBefore);
  });

  test('back-to-depth-chart after submit still allows resubmit (regression)', async ({ page }) => {
    // Exact repro from production: submit succeeds, user navigates away,
    // comes back to the depth chart, edits, and resubmits. Without the PRG
    // fix this second submit produced "Invalid or expired form submission"
    // because the browser restored a stale form whose single-use token had
    // already been consumed server-side.
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // First submit — non-starter value so we pass validation.
    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('3');
    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();
    await expect(page.locator('.ibl-alert--success')).toBeVisible();

    // Leave the depth chart page and come back — minimal reliable repro of
    // the "user returns to the form later" pattern.
    await page.goto('modules.php?name=Standings');
    await page.goto('modules.php?name=DepthChartEntry');
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Resubmit with a different non-starter value — must succeed.
    await page
      .locator('.depth-chart-table select[name^="pg"]')
      .first()
      .selectOption('4');
    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await expect(page.getByText(/Invalid or expired/i)).not.toBeVisible();
  });

  test('validation failure preserves submitted form values and renders fresh token', async ({
    page,
    appState,
  }) => {
    // Regular season requires exactly 12 active players. Deactivating one
    // drops to 11 → validator emits "at least 12 active players" error.
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=DepthChartEntry');
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Uncheck the first currently-active checkbox and remember its row.
    const activeCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    const cbCount = await activeCheckboxes.count();
    let uncheckedIndex = -1;
    for (let i = 0; i < cbCount; i++) {
      if (await activeCheckboxes.nth(i).isChecked()) {
        await activeCheckboxes.nth(i).uncheck();
        uncheckedIndex = i;
        break;
      }
    }
    expect(uncheckedIndex).toBeGreaterThanOrEqual(0);

    // Distinctive non-starter value so we can verify the form re-renders
    // with POST, not DB. The first form row is already a starter at some
    // position in the seed, so avoid '1' to prevent a secondary multi-
    // starter validation error from overwriting the active-count message.
    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('4');

    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    // PRG: lands back at module base.
    await expect(page).toHaveURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/);

    // Validator error banner.
    await expect(page.locator('.ibl-alert--error')).toBeVisible();
    await expect(page.locator('.ibl-alert--error')).toContainText(
      /at least 12 active players/i,
    );

    // In-flight edit preserved: PG value from POST, not DB.
    await expect(
      page.locator('.depth-chart-table select[name^="pg"]').first(),
    ).toHaveValue('4');

    // The unchecked box stays unchecked (POST value overrides DB value).
    await expect(
      page
        .locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]')
        .nth(uncheckedIndex),
    ).not.toBeChecked();

    // Token is fresh (64-char hex — see CsrfGuard::generateToken).
    const token = await page
      .locator('input[name="_csrf_token"]')
      .first()
      .getAttribute('value');
    expect(token).toMatch(/^[a-f0-9]{64}$/);
  });

  test('validation failure → fix → resubmit succeeds on the same visit', async ({
    page,
    appState,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=DepthChartEntry');
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Uncheck → submit → error (setup).
    const activeCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    const cbCount = await activeCheckboxes.count();
    for (let i = 0; i < cbCount; i++) {
      if (await activeCheckboxes.nth(i).isChecked()) {
        await activeCheckboxes.nth(i).uncheck();
        break;
      }
    }
    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();
    await expect(page.locator('.ibl-alert--error')).toBeVisible();

    // Recheck the first unchecked active box to restore the valid count.
    const reloadedCheckboxes = page.locator('input[type="checkbox"].dc-active-cb[name^="canPlayInGame"]');
    const reloadedCount = await reloadedCheckboxes.count();
    for (let i = 0; i < reloadedCount; i++) {
      if (!(await reloadedCheckboxes.nth(i).isChecked())) {
        await reloadedCheckboxes.nth(i).check();
        break;
      }
    }

    // Resubmit — must now pass validation with the fresh token.
    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await expect(page.locator('.ibl-alert--error')).not.toBeVisible();
  });

  test('CSRF failure shows inline error without redirecting', async ({ page }) => {
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Blank the CSRF token field so server-side validation fails.
    await page.evaluate(() => {
      const el = document.querySelector('input[name="_csrf_token"]');
      if (el instanceof HTMLInputElement) {
        el.value = '';
      }
    });

    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    // CSRF-fail path deliberately does NOT redirect — the user needs to
    // reload manually, and the inline message tells them so.
    await expect(page.locator('.ibl-form-error')).toBeVisible();
    await expect(page.locator('.ibl-form-error')).toContainText(/Invalid or expired/i);

    // URL still carries &op=submit (no redirect).
    await expect(page).toHaveURL(/op=submit/);
  });

  test('saved depth chart dropdown has options', async ({ page }) => {
    const dropdown = page.locator('#saved-dc-select');
    await expect(dropdown).toBeVisible();

    const options = dropdown.locator('option');
    // "Current (Live)" + at least one saved config from seed. The exact
    // count fluctuates within this serial describe because prior submit
    // tests may update the active saved DC to match live settings, which
    // SavedDepthChartService then hides from the dropdown.
    expect(await options.count()).toBeGreaterThanOrEqual(2);
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
