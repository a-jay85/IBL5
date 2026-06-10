import type { Page, APIRequestContext } from '@playwright/test';

/**
 * Trigger the full-season updater the way an admin does: by clicking the League
 * Control Panel's CSRF-tokened POST button (formaction → updateAllTheThings.php).
 *
 * The script is now POST-only + CSRF-validated, so a bare `page.goto()` GET no
 * longer runs the pipeline. The button is also phase-gated — it only renders in
 * Preseason / HEAT / Regular Season / Playoffs. Callers MUST set a button-rendering
 * phase (Regular Season is safe for both leagues) via setState BEFORE calling,
 * because the CI seed default phase (IBL = Free Agency) renders no button. The LCP
 * reads the phase from the DB directly, so the cookie-based appState fixture does
 * NOT affect button rendering — use DB-level setState.
 *
 * The script streams progressive HTML (flush() after each step), so we wait for the
 * full `load` event before returning.
 */
export async function runUpdater(
  page: Page,
  options: { league?: 'ibl' | 'olympics' } = {},
): Promise<void> {
  const path =
    options.league === 'olympics'
      ? 'leagueControlPanel.php?league=olympics'
      : 'leagueControlPanel.php';
  await page.goto(path);
  await Promise.all([
    page.waitForLoadState('load'),
    page.getByRole('button', { name: 'Update All The Things' }).click(),
  ]);
}

/**
 * Trigger the updater via a CSRF-validated POST WITHOUT mutating the season phase.
 *
 * Use this when a test only needs the pipeline to fire (not a phase-dependent
 * outcome). The LCP renders the `_csrf_token` in its form unconditionally — at
 * ANY phase — so this fetches the token from the LCP and POSTs it directly to the
 * script. That avoids the DB-level `setState('Current Season Phase')` that the
 * click path requires (the button is phase-gated), keeping `updater-awards` the
 * sole writer of the shared phase row and avoiding a cross-worker race under
 * Playwright's parallel execution (cf. PR #884/#886 shared-table flakes).
 *
 * Returns the streamed response body text for assertion.
 */
export async function triggerUpdater(
  request: APIRequestContext,
  options: { league?: 'ibl' | 'olympics' } = {},
): Promise<string> {
  const lcpPath =
    options.league === 'olympics'
      ? 'leagueControlPanel.php?league=olympics'
      : 'leagueControlPanel.php';

  const lcpResp = await request.get(lcpPath);
  if (!lcpResp.ok()) {
    throw new Error(`LCP GET failed: ${lcpResp.status()} ${await lcpResp.text()}`);
  }
  const html = await lcpResp.text();
  const match = html.match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error('No _csrf_token found in the League Control Panel form');
  }

  const form: Record<string, string> = { _csrf_token: match[1] };
  if (options.league === 'olympics') {
    form.league = 'olympics';
  }

  const resp = await request.post('scripts/updateAllTheThings.php', { form });
  if (!resp.ok()) {
    throw new Error(`updateAllTheThings POST failed: ${resp.status()} ${await resp.text()}`);
  }
  return resp.text();
}
