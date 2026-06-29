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

    // page.request shares the PHPSESSID with the page context, so the CSRF token obtained
    // above is valid for this POST.
    const response = await page.request.post('/ibl5/modules.php?name=Waivers', {
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
// IDOR (D-08): acting team bound to session, not POST Team_Name
// ============================================================
//
// The waiver add path assigns the picked free agent to getTeamByName(Team_Name),
// so a tampered hidden Team_Name is the observable IDOR: unfixed, a logged-in user
// can sign a free agent onto ANY team. (The waive/drop path drops purely by pid —
// its team value is only used for the news-story/Discord text — so it is NOT a valid
// fixed-vs-unfixed discriminator; the add path is.)
//
// This lives in this serial waiver-mutation owner file (not a standalone security/
// spec) to avoid a fullyParallel cross-worker race on shared roster state. The forged
// POST replicates a legitimate add (real rosterslots/healthyrosterslots read from the
// rendered form, so validateAdd passes) and tampers ONLY Team_Name.
test.describe('Waivers: IDOR — add binds to session team', () => {
  test('add with tampered Team_Name signs to the session team (Metros), not the victim (Stars)', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'Yes', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Waivers');

    const form = page.locator('form[name="Waiver_Move"]');
    await expect(form).toBeVisible();

    // Pick the first available free agent from the rendered select.
    const playerOption = form.locator('select[name="Player_ID"] option[value]:not([value=""])').first();
    await expect(playerOption).toBeAttached({ timeout: 5000 });
    const playerID = await playerOption.getAttribute('value');
    const optionLabel = (await playerOption.textContent())?.trim() ?? '';
    const playerNameOnly = optionLabel.replace(/\s+\d.*$/, '').trim();
    expect(playerID, 'expected at least one free-agent option').toBeTruthy();
    expect(playerNameOnly, 'expected a player display label').toBeTruthy();

    // Replicate the legitimate add payload (so roster validation passes), then tamper Team_Name.
    const csrfToken = await form.locator('input[name="_csrf_token"]').inputValue();
    const rosterslots = await form.locator('input[name="rosterslots"]').inputValue();
    const healthyrosterslots = await form.locator('input[name="healthyrosterslots"]').inputValue();
    expect(csrfToken).toBeTruthy();

    const response = await page.request.post('modules.php?name=Waivers', {
      form: {
        Action: 'add',
        Player_ID: playerID!,
        Team_Name: 'Stars', // tampered — server must ignore and use the session team
        rosterslots,
        healthyrosterslots,
        _csrf_token: csrfToken,
      },
      maxRedirects: 0,
    });
    const location = response.headers()['location'] ?? '';
    expect(location, `add should succeed on the session team; got ${location}`).toContain('result=player_added');

    // The free agent lands on Metros (session team, teamid=1), NOT Stars (teamid=2).
    // (Unfixed: getTeamByName('Stars') signs the player to Stars and the Metros assertion fails.)
    await page.goto('modules.php?name=Team&op=team&teamid=1');
    await expect(page.locator('body'), 'player must join the session team (Metros)').toContainText(
      playerNameOnly,
      { timeout: 5000 },
    );
    await page.goto('modules.php?name=Team&op=team&teamid=2');
    await expect(page.locator('body'), 'victim team (Stars) must be untouched').not.toContainText(
      playerNameOnly,
    );

    // Cleanup: waive the player back off the Metros active roster (legitimate, as Metros).
    await page.goto('modules.php?name=Waivers&action=waive');
    const waiveForm = page.locator('form[name="Waiver_Move"]');
    const waiveToken = await waiveForm.locator('input[name="_csrf_token"]').inputValue();
    await page.request.post('modules.php?name=Waivers', {
      form: { Action: 'waive', Player_ID: playerID!, Team_Name: 'Metros', _csrf_token: waiveToken },
      maxRedirects: 0,
    });
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
});
