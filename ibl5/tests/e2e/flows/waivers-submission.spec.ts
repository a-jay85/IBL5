import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { submitFormAndAssertEffect } from '../helpers/submit-form';
import { resetWaiverPlayer } from '../helpers/cleanup';

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

    const actionInput = form.locator('input[name="Action"]');
    expect(await actionInput.inputValue(), 'Waivers must default to add form').toBe('add');

    // Select the first available player (skip the placeholder option)
    const playerSelect = form.locator('select[name="Player_ID"]');
    const playerOption = playerSelect.locator('option[value]:not([value=""])').first();
    await expect(playerOption).toBeAttached({ timeout: 5000 });
    const optionValue = await playerOption.getAttribute('value');
    const optionLabel = (await playerOption.textContent())?.trim() ?? '';
    expect(optionValue, 'Expected at least one player option with a value').toBeTruthy();
    expect(optionLabel, 'Expected player option to carry a display label').toBeTruthy();
    await playerSelect.selectOption(optionValue!);

    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('modules.php') && resp.request().method() === 'POST',
    );

    // Strip onclick handler — it disables button + calls form.submit() which
    // races with Playwright's navigation tracking
    const submitBtn = form.locator('button[type="submit"], input[type="submit"]').first();
    await submitBtn.evaluate(btn => (btn as HTMLElement).removeAttribute('onclick'));

    const playerNameOnly = optionLabel.replace(/\s+\d.*$/, '').trim();

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await submitBtn.click();
        const response = await responsePromise;
        const postStatus = response.status();
        const postBody = await response.text().catch(() => '');
        await page.waitForLoadState('networkidle');
        const url = page.url();
        const bodySnippet = postBody.substring(0, 500).replace(/\s+/g, ' ').trim();
        expect(url, `POST status=${postStatus} body=${bodySnippet}`).toContain('result=player_added');
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success')).toBeVisible();
        await assertNoPhpErrors(page, 'after waiver add');
      },
      readBack: async () => {
        await page.goto('modules.php?name=Team&op=team&teamid=1');
        await expect(page.locator('body')).toContainText(playerNameOnly, { timeout: 5000 });
      },
    });
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
  test('submit waive: drop player to waivers', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'Allow Waiver Moves': 'Yes',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=Waivers&action=waive');

    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toBeVisible();

    const playerSelect = form.locator('select[name="Player_ID"]');
    await playerSelect.selectOption('200000031');

    const responsePromise = page.waitForResponse(
      resp => resp.url().includes('modules.php') && resp.request().method() === 'POST',
    );

    // Strip onclick handler — it disables button + calls form.submit() which
    // races with Playwright's navigation tracking
    const submitBtn = form.locator('button[type="submit"], input[type="submit"]').first();
    await submitBtn.evaluate(btn => (btn as HTMLElement).removeAttribute('onclick'));

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await submitBtn.click();
        const response = await responsePromise;
        const postStatus = response.status();
        const postBody = await response.text().catch(() => '');
        await page.waitForLoadState('networkidle');
        const url = page.url();
        const bodySnippet = postBody.substring(0, 500).replace(/\s+/g, ' ').trim();
        expect(url, `POST status=${postStatus} body=${bodySnippet}`).toContain('result=player_dropped');
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success')).toBeVisible();
        await expect(page.locator('.ibl-alert--success')).toContainText(
          'Player successfully dropped to waivers.',
        );
        await assertNoPhpErrors(page, 'after waiver drop');
      },
      readBack: async () => {
        // dropPlayerToWaivers sets ordinal=1000; getHealthyAndInjuredPlayersOrderedByName
        // filters to ordinal <= WAIVERS_ORDINAL (960), so a waived player disappears
        // from the waive-form select. This proves the drop persisted.
        await page.goto('modules.php?name=Waivers&action=waive');
        await expect(
          page.locator('select[name="Player_ID"] option[value="200000031"]'),
        ).toHaveCount(0);
      },
    });
  });

  test('submit waive rejection: invalid player returns error', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'Allow Waiver Moves': 'Yes',
      'Current Season Ending Year': '2026',
    });

    // Read the CSRF token from the rendered waive form
    await page.goto('modules.php?name=Waivers&action=waive');
    const csrfToken = await page
      .locator('form[name="Waiver_Move"] input[name="_csrf_token"]')
      .inputValue();

    // POST with an invalid Player_ID that is not on the user's roster
    const response = await request.post('/ibl5/modules.php?name=Waivers', {
      form: {
        Action: 'waive',
        Player_ID: '999999',
        _csrf_token: csrfToken,
      },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] ?? '';
    expect(location, 'Expected error redirect').toContain('error=');
    expect(location, 'Expected action=waive in redirect').toContain('action=waive');

    await page.goto(location);
    await expect(page.locator('.ibl-alert--error')).toBeVisible();
  });

  test.afterAll(async ({ request }) => {
    await resetWaiverPlayer(request, 200000031);
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
