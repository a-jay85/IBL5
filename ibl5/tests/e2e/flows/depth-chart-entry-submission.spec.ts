import { test, expect } from '../fixtures/auth-isolated';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { submitFormAndAssertEffect } from '../helpers/submit-form';

// Depth Chart submission flow — these tests mutate shared DB state via form
// POSTs, so they run serially. They collectively exercise the Post-Redirect-
// Get (PRG) implementation behind the fix for the "Invalid or expired form
// submission" regression that users hit after submit → back → resubmit.
//
// Isolation: the auth-dc fixture sets a `_test_dc_team` cookie that overrides
// the logged-in user's team to Monarchs (tid=8). The Monarchs roster has
// exactly 12 active players with proper depth coverage (seeded in ci-seed.sql)
// and is not referenced by any other spec, so parallel workers cannot pollute it.
//
// Why we do NOT assert the ".ibl-alert--success / depth chart saved" flash here:
// the success flash lives in $_SESSION['flash_success'] and is single-use — the
// FIRST PageLayout::header() render for that session consumes (unset)s it. All
// authenticated E2E workers share ONE pinned server-side PHPSESSID (see
// playwright-tests.md "Shared server session"), so any parallel worker that
// renders any module in the ~ms window between this spec's submit-POST and its
// redirect-GET can render the flash into ITS page and clear it, leaving this
// spec's GET flash-less. That race made these tests intermittently red. The
// flash render + single-use consumption is already unit-covered by
// tests/Unit/PageLayout/PageLayoutTest.php, so here we assert the DURABLE proof
// of success instead — the submitted depth value persisted to the DB (readable
// back on the redirected form). (The ".ibl-alert--error" validation-failure test
// below is NOT affected: its flash key `_ibl_depth_chart_flash` is module-scoped
// and only this serial spec ever renders DepthChartEntry, so no parallel worker
// can steal it.)
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

  test('submit redirects back to module URL with a fresh form and persisted change', async ({ page }) => {
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('3');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();
      },
      expectSameSpot: async () => {
        // PRG landed us back on the module URL (not op=submit) with a fresh form.
        // The success flash is deliberately NOT asserted — see the shared-session
        // note at the top of this file; the durable success proof is the readBack.
        await expect(page).toHaveURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/);
        await expect(page.locator('.depth-chart-form')).toBeVisible();
        await assertNoPhpErrors(page, 'after depth chart submission');
      },
      readBack: async () => {
        await expect(
          page.locator('.depth-chart-table select[name^="pg"]').first(),
        ).toHaveValue('3');
      },
    });
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
    // Do NOT rely on page.waitForURL here: beforeEach already navigated to a URL
    // that matches this same regex, so waitForURL can resolve immediately against
    // the pre-submit document — before the PRG redirect's new document has actually
    // loaded — and a one-shot getAttribute() right after would then read the STALE
    // pre-submit token (this is exactly what broke when the old success-flash
    // .toBeVisible() wait — which only exists in the NEW document — was removed for
    // the shared-session flash-steal race; see the note at the top of this file).
    // Poll for the token to actually change instead, which only becomes true once
    // the new document has loaded, without depending on the racy shared-session flash.
    let tokenAfter: string | null = null;
    await expect(async () => {
      tokenAfter = await page
        .locator('input[name="_csrf_token"]')
        .first()
        .getAttribute('value');
      expect(tokenAfter).not.toBe(tokenBefore);
    }).toPass({ timeout: 15_000 });
    expect(tokenAfter).toMatch(/^[a-f0-9]{64}$/);
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
    // Set up the URL wait BEFORE clicking (Promise.all), not after: beforeEach
    // already navigated to a URL matching this same pattern, so a wait started
    // AFTER the click can observe the still-current pre-submit URL as an
    // immediate "match" and resolve before the PRG redirect actually lands —
    // which then let the very next page.goto() below abort that still-in-flight
    // navigation (net::ERR_ABORTED). Starting the wait first ties it to the
    // navigation the click triggers, not to whatever URL happened to be current.
    await Promise.all([
      page.waitForURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/, { timeout: 15_000 }),
      page.locator('.depth-chart-buttons .depth-chart-submit-btn').click(),
    ]);
    await page.waitForLoadState('networkidle');

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
    await Promise.all([
      page.waitForURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/, { timeout: 15_000 }),
      page.locator('.depth-chart-buttons .depth-chart-submit-btn').click(),
    ]);

    // The pre-PRG regression symptom: a stale-token resubmit rendered this error.
    // Redirected back to the module URL (asserted above) with that error absent is
    // the exact regression proof. Success flash intentionally not asserted, and no
    // read-back reload added here — value-persistence is already covered by the
    // first submit test and the IDOR test, and a bare goto under parallel load is a
    // blank-page flake vector we deliberately avoid (see the shared-session note).
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
    expect(uncheckedIndex, 'findIndex must locate an unchecked slot').not.toBe(-1);

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

    // Both success and validation-failure take the PRG redirect, so the durable
    // discriminator for "resubmit succeeded" is: we redirected back to the module
    // URL AND no validation-error alert rendered. The success flash is not asserted
    // (shared-session steal — see the note at the top of this file); the error flash
    // key is module-scoped, so its absence here is not racy.
    await page.waitForURL(/modules\.php\?name=DepthChartEntry(?!.*op=submit)/, { timeout: 15_000 });
    await expect(page.locator('.ibl-alert--error')).not.toBeVisible();
  });

  test('CSRF failure shows inline error without redirecting', async ({ page }) => {
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Blank the depth chart form's CSRF token so server-side validation fails.
    await page.evaluate(() => {
      const el = document.querySelector('.depth-chart-form input[name="_csrf_token"]');
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
    await expect(loadedId.first(), 'loaded_dc_id hidden field must exist in form').toBeAttached();
    await expect(async () => {
      const val = await loadedId.first().inputValue();
      expect(val).not.toBe('0');
    }).toPass({ timeout: 5000 });
  });

  test('no PHP errors on depth chart page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Depth Chart Entry');
  });

  test('IDOR: spoofed Team_Name is ignored — write targets the session team, not the victim', async ({
    page,
  }) => {
    // The hidden Team_Name field renders as the session team (Monarchs). A
    // logged-in GM could tamper it to a real victim team (Metros, tid=1) and
    // replay the valid CSRF token. The D-09 fix derives the write target from
    // the session, so the tampered Team_Name is ignored: the submission applies
    // to Monarchs (the legitimate owner), never Metros. The "no victim write"
    // negative is pinned at unit level in
    // DepthChartEntrySubmissionHandlerOwnershipTest.
    await expect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    const firstPg = page
      .locator('.depth-chart-table select[name^="pg"]')
      .first();
    await firstPg.selectOption('3');

    // Tamper the hidden Team_Name to a real victim team the session doesn't own.
    await page.evaluate(() => {
      const el = document.querySelector(
        '.depth-chart-form input[name="Team_Name"]',
      );
      if (el instanceof HTMLInputElement) {
        el.value = 'Metros';
      }
    });

    await page.locator('.depth-chart-buttons .depth-chart-submit-btn').click();

    // Treated as a normal Monarchs submission (Team_Name ignored): PRG back to
    // the module, and the change persisted on Monarchs. The success flash is not
    // asserted (shared-session steal — see the note at the top of this file); the
    // durable proof the write landed on Monarchs is the read-back below.
    await expect(page).toHaveURL(
      /modules\.php\?name=DepthChartEntry(?!.*op=submit)/,
    );
    await expect(
      page.locator('.depth-chart-table select[name^="pg"]').first(),
    ).toHaveValue('3');
    await assertNoPhpErrors(page, 'after IDOR-tampered depth chart submission');
  });
});

// ---------------------------------------------------------------------------
// D-09 unauthenticated submit — refused before the handler runs.
//
// submit() gates is_user() before the CSRF check and before the controller, so
// an unauthenticated POST (DevAutoLogin suppressed via the public fixture)
// renders the inline "Invalid or expired form submission" error and writes
// nothing.
// ---------------------------------------------------------------------------

publicTest.describe('Depth Chart submission — unauthenticated', () => {
  publicTest('unauthenticated submit is refused with the inline error', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=DepthChartEntry&op=submit',
      {
        form: { Team_Name: 'Metros', pg1: '1' },
        maxRedirects: 0,
      },
    );
    const body = await response.text();
    publicExpect(body).toContain('Invalid or expired form submission');
    publicExpect(body).not.toContain('depth chart saved');
  });
});
