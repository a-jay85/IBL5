import { test, expect } from '../fixtures/auth';

/**
 * Regression guard for the Olympics league-switch session leak (PR #878).
 *
 * All authenticated E2E workers share ONE server-side PHP session (the pinned
 * PHPSESSID in playwright/.auth/user.json). The league switch must therefore
 * persist via the per-browser-context cookie + an in-memory request value —
 * never `$_SESSION`. If it writes the shared session, one test switching to
 * Olympics flips every other concurrent authenticated test into Olympics
 * context, which silently rewrites their queries to ibl_olympics_* tables.
 *
 * Assertions inspect the RAW server response (via `request`) rather than the
 * rendered DOM: the league `<select>` lives inside an Alpine dropdown template
 * that is absent from the live DOM until opened, and the raw HTML is the
 * truest signal of which league the server resolved for the request.
 */
const STORAGE_STATE = 'playwright/.auth/user.json';
// The resolved league's <option> carries `selected` in the nav switcher markup.
const OLYMPICS_SELECTED = /league=olympics"\s+selected/;
const IBL_SELECTED = /league=ibl"\s+selected/;

test('switching league in one context does not leak via the shared session', async ({
  page,
  browser,
}) => {
  // Context A (default authenticated context) switches to Olympics.
  const switchResp = await page.request.get('index.php?league=olympics');
  expect(await switchResp.text()).toMatch(OLYMPICS_SELECTED);

  // Context B shares the same auth storageState — the same server-side session
  // (PHPSESSID) — but has its own cookie jar with no ibl_league cookie.
  const contextB = await browser.newContext({ storageState: STORAGE_STATE });
  try {
    const respB = await contextB.request.get('index.php');
    const bodyB = await respB.text();
    // Must still resolve to IBL: A's switch lives in A's cookie + request,
    // not in the session both contexts share.
    expect(bodyB).toMatch(IBL_SELECTED);
    expect(bodyB).not.toMatch(OLYMPICS_SELECTED);
  } finally {
    await contextB.close();
  }
});

test('league switch persists within the same context via cookie', async ({ page }) => {
  // Positive half of the invariant: persistence still works for the switcher.
  const switchResp = await page.request.get('index.php?league=olympics');
  expect(await switchResp.text()).toMatch(OLYMPICS_SELECTED);

  // A later request in the SAME context (no league param) stays Olympics,
  // carried by the per-context ibl_league cookie.
  const followUp = await page.request.get('index.php');
  expect(await followUp.text()).toMatch(OLYMPICS_SELECTED);
});
