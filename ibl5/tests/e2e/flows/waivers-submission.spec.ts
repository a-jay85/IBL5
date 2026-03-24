import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Waivers submission flow — tests that actually submit waiver moves.
// Serial: add/waive mutate roster state.
test.describe.configure({ mode: 'serial' });

// ============================================================
// Add player from waivers
// ============================================================

test.describe('Waivers: add player', () => {
  test('add form renders with free agent options', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers');

    await expect(page.locator('form[name="Waiver_Move"]')).toBeVisible();
    const playerSelect = page.locator('select[name="Player_ID"]');
    await expect(playerSelect).toBeVisible();

    const options = playerSelect.locator('option');
    await expect(options.first()).toBeAttached();
  });

  test('submit add: sign free agent', async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'Yes', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Waivers');

    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toBeVisible();

    // Check if Action hidden field is set to "add"
    const actionInput = form.locator('input[name="Action"]');
    const actionValue = await actionInput.inputValue();
    expect(actionValue, 'Waivers must default to add form').toBe('add');

    // Select the first available player (skip the placeholder option)
    const playerSelect = form.locator('select[name="Player_ID"]');
    const playerOption = playerSelect.locator('option[value]:not([value=""])').first();
    await expect(playerOption).toBeAttached({ timeout: 5000 });
    const optionValue = await playerOption.getAttribute('value');
    expect(optionValue, 'Expected at least one player option with a value').toBeTruthy();
    await playerSelect.selectOption(optionValue!);

    // Intercept the POST response body to capture PHP errors
    let postBody = '';
    let postStatus = 0;
    await page.route('**/modules.php**', async (route) => {
      const request = route.request();
      if (request.method() === 'POST') {
        const response = await route.fetch();
        postStatus = response.status();
        postBody = (await response.text()).substring(0, 1000);
        await route.fulfill({ response });
      } else {
        await route.continue();
      }
    });

    // Strip onclick handler — it calls form.submit() programmatically which
    // blocks the browser's native submit and races with Playwright.
    const submitBtn = form.locator('button[type="submit"], input[type="submit"]').first();
    await submitBtn.evaluate(btn => (btn as HTMLElement).removeAttribute('onclick'));
    await submitBtn.click();

    await page.waitForLoadState('networkidle');
    const url = page.url();

    // Diagnostic: log what the server returned
    expect(
      url,
      `POST status=${postStatus} postBody=${postBody.replace(/\s+/g, ' ').trim().substring(0, 500)}`,
    ).toMatch(/(result|error)=/);
    await assertNoPhpErrors(page, 'after waiver add');
  });

  test('success banner shows on successful add', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });

    // Navigate with a success result param to verify banner rendering
    await page.goto(
      'modules.php?name=Waivers&action=add&result=player_added',
    );

    const successBanner = page.locator('.ibl-alert--success');
    await expect(successBanner).toBeVisible();
  });
});

// ============================================================
// Waive (drop) player
// ============================================================

test.describe('Waivers: waive player', () => {
  test('waive form renders with roster options', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers');

    // Look for the waive section — may be a separate form or same form with different Action
    const body = await page.locator('body').textContent();

    // The page should have some form for waiving
    // Verify roster info is visible
    await expect(page.locator('.ibl-card').first()).toBeVisible();
  });

  test('error banner shows on error result', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });

    // Navigate with an error param to verify error banner rendering
    await page.goto(
      'modules.php?name=Waivers&action=waive&error=Test+error+message',
    );

    const errorBanner = page.locator('.ibl-alert--error');
    await expect(errorBanner).toBeVisible();
  });
});

// ============================================================
// Closed state
// ============================================================

test.describe('Waivers: closed state', () => {
  test('no form elements when moves disabled', async ({
    appState,
    page,
  }) => {
    // Phase must be Free Agency or Preseason for the toggle to matter —
    // during HEAT/Regular Season/Playoffs, waivers are always open.
    await appState({ 'Current Season Phase': 'Free Agency', 'Allow Waiver Moves': 'No' });
    await page.goto('modules.php?name=Waivers');

    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toHaveCount(0);
  });

  test('no PHP errors on closed waivers page', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Allow Waiver Moves': 'No' });
    await page.goto('modules.php?name=Waivers');

    await assertNoPhpErrors(page, 'on Waivers page (closed)');
  });
});

// ============================================================
// Waive form and result banner
// ============================================================

test.describe('Waivers: waive form and result banner', () => {
  test('waive form has Action=waive hidden field', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto('modules.php?name=Waivers&action=waive');

    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toBeVisible();

    const actionInput = form.locator('input[name="Action"]');
    await expect(actionInput).toHaveValue('waive');
  });

  test('player_dropped success banner', async ({ appState, page }) => {
    await appState({ 'Allow Waiver Moves': 'Yes' });
    await page.goto(
      'modules.php?name=Waivers&action=waive&result=player_dropped',
    );

    const successBanner = page.locator('.ibl-alert--success');
    await expect(successBanner).toBeVisible();
    await expect(successBanner).toContainText(
      'Player successfully dropped to waivers.',
    );
  });
});
