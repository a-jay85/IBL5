import { test, expect } from '../fixtures/auth';
import { test as publicTest } from '../fixtures/public';
import type { APIRequestContext, Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { setState } from '../helpers/test-state';

// Serial: tests write ibl_team_info.trade_mode for franchise 1 and share the
// 'Allow Trades' setting, so they must not interleave.
test.describe.configure({ mode: 'serial' });

// ---------------------------------------------------------------------------
// Seed constants (ibl5/tests/e2e/fixtures/ci-seed.sql)
//
// The CI GM owns franchise 1 (Metros): ibl_team_info row at line 88.
// USER_PID    = 1 'Test Player'     teamid 1, cy 1, ordinal 1 (ibl_plr row at line 274)
// PARTNER_PID = 4 'Stars Guard'     teamid 2, cy 1, ordinal 1 (ibl_plr row at line 304)
//               PARTNER_NAME = 'Stars', ibl_team_info row at line 89, the row after Metros
// FOREIGN_PID = 6 'Phoenixes Guard' teamid 14, cy 1, ordinal 1 (ibl_plr row at line 324)
//               on neither side of a Metros/Stars trade
// 'Allow Trades' is seeded 'No' at line 48, so every panel test opens it first.
// Measured 2026-11-14: Metros in 'Free Agency' shows 0 cards. Stars in
// 'Regular Season' shows 2 cards with 8 profile lines and 2 eligible ideas.
// ---------------------------------------------------------------------------
const USER_PID = 1;
const PARTNER_PID = 4;
const PARTNER_NAME = 'Stars';
const FOREIGN_PID = 6;

const PANEL = '[data-component="trade-suggestions"]';
const MODE_POST_URL = 'modules.php?name=Trading&op=trade-mode';

async function getTradeMode(
  request: APIRequestContext,
  teamid: number,
): Promise<string> {
  const response = await request.get(
    `test-state.php?action=get-trade-mode&teamid=${teamid}`,
  );
  expect(response.ok(), 'get-trade-mode must answer 200').toBe(true);
  const body = (await response.json()) as { teamid: number; trade_mode: string };
  return body.trade_mode;
}

/**
 * Every franchise whose trade_mode is not 'auto', keyed by teamid. The CI GM
 * owns franchise 1 (see the seed notes above); scanning all 28 franchises keeps
 * the assertions correct on a worktree stack seeded with other data, where the
 * GM owns a different franchise.
 */
async function nonAutoModes(
  request: APIRequestContext,
): Promise<Record<number, string>> {
  const tids = Array.from({ length: 28 }, (_, i) => i + 1);
  const modes = await Promise.all(tids.map((tid) => getTradeMode(request, tid)));
  const found: Record<number, string> = {};
  tids.forEach((tid, i) => {
    const mode = modes[i];
    if (mode !== undefined && mode !== 'auto') found[tid] = mode;
  });
  return found;
}

/** Panel CSRF token for the mode form, read from the rendered homepage. */
async function panelToken(page: Page): Promise<string> {
  await gotoWithRetry(page, 'index.php');
  const token = await page
    .locator(PANEL)
    .locator('input[name="_csrf_token"]')
    .first()
    .inputValue();
  expect(token.length).toBeGreaterThan(0);
  return token;
}

/**
 * The franchise the logged-in GM owns, found by making one real mode change
 * and seeing which franchise it landed on. 1 on the CI seed. Resets the
 * fixture afterwards so the caller starts from all-'auto'.
 */
async function resolveGmTid(
  page: Page,
  request: APIRequestContext,
): Promise<number> {
  const token = await panelToken(page);
  const response = await page.request.post(MODE_POST_URL, {
    form: { trade_mode: 'win_now', _csrf_token: token },
    maxRedirects: 0,
  });
  expect(response.status()).toBeGreaterThanOrEqual(300);
  const changed = Object.keys(await nonAutoModes(request));
  await resetFixture(request);
  expect(changed, 'one legit mode POST must change exactly one franchise').toHaveLength(1);
  return Number(changed[0]);
}

async function resetFixture(request: APIRequestContext): Promise<void> {
  const response = await request.post(
    'test-state.php?action=trade-suggestions-fixture',
    { data: {} },
  );
  expect(response.ok(), 'trade-suggestions-fixture must answer 200').toBe(true);
}

const ACTION_POST_URL = 'modules.php?name=Trading&op=trade-suggestion-action';

interface FeedbackRow {
  tid: number;
  deal_key: string;
  core_key: string;
  reason_codes: string;
  free_text: string | null;
}

/**
 * CSRF token for the trade-idea action route, read from the first card's hide
 * form. The mode form's token belongs to another action and is refused there.
 */
async function actionToken(page: Page): Promise<string> {
  await gotoWithRetry(page, 'index.php');
  const token = await page
    .locator('[data-testid="trade-suggestion-hide"] input[name="_csrf_token"]')
    .first()
    .inputValue();
  expect(token.length).toBeGreaterThan(0);
  return token;
}

/** Deal key of the first card on the current page. */
async function firstDealKey(page: Page): Promise<string> {
  const key = await page
    .locator('[data-testid="trade-suggestion"] input[name="deal_key"]')
    .first()
    .inputValue();
  expect(key).toMatch(/^[0-9a-f]{40}$/);
  return key;
}

async function feedbackRows(
  request: APIRequestContext,
  tid: number,
): Promise<FeedbackRow[]> {
  const response = await request.get(
    `test-state.php?action=trade-suggestion-feedback&teamid=${tid}`,
  );
  expect(response.ok(), 'trade-suggestion-feedback must answer 200').toBe(true);
  const body = (await response.json()) as { rows: FeedbackRow[] };
  return body.rows;
}

async function resetFeedback(request: APIRequestContext): Promise<void> {
  const response = await request.delete(
    'test-state.php?action=trade-suggestion-feedback-reset',
  );
  expect(response.ok(), 'trade-suggestion-feedback-reset must answer 200').toBe(true);
}

/** POST to the action route without following the redirect; returns Location. */
async function postAction(
  page: Page,
  form: Record<string, string>,
): Promise<string> {
  const response = await page.request.post(ACTION_POST_URL, {
    form,
    maxRedirects: 0,
  });
  expect(response.status()).toBeGreaterThanOrEqual(300);
  expect(response.status()).toBeLessThan(400);
  return response.headers()['location'] ?? '';
}

async function refreshOffset(page: Page): Promise<{ eligible: number; offset: number }> {
  const form = page.locator('form.trade-suggestions__refresh-form');
  return {
    eligible: Number(await form.getAttribute('data-eligible')),
    offset: Number(await form.getAttribute('data-offset')),
  };
}

/**
 * Click refresh until the panel is back at offset 0. The offset lives in the
 * one shared PHP session, so a test that clicks refresh must undo it before
 * the next serial test renders the panel.
 */
async function rewindOffset(page: Page): Promise<void> {
  const { eligible } = await refreshOffset(page);
  const maxClicks = Math.ceil(eligible / 3) + 1;
  for (let i = 0; i < maxClicks && (await refreshOffset(page)).offset !== 0; i++) {
    await Promise.all([
      page.waitForEvent('load'),
      page.locator('[data-testid="trade-suggestions-refresh"]').click(),
    ]);
  }
  await expect(page.locator('form.trade-suggestions__refresh-form')).toHaveAttribute(
    'data-offset',
    '0',
  );
}

async function submitModeForm(
  page: Page,
  mode: 'auto' | 'win_now' | 'rebuild',
): Promise<void> {
  const form = page
    .locator(PANEL)
    .locator('form')
    .filter({ has: page.locator('[data-testid="trade-mode-select"]') });
  await form.locator('[data-testid="trade-mode-select"]').selectOption(mode);
  await Promise.all([
    page.waitForURL(/#trade-suggestions$/),
    form.getByRole('button').first().click(),
  ]);
}

// ---------------------------------------------------------------------------
// GM tests
// ---------------------------------------------------------------------------

test.describe('Trade suggestions panel (GM)', () => {
  test.beforeEach(async ({ appState, request }) => {
    await resetFixture(request);
    await resetFeedback(request);
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });
  });

  test.afterAll(async ({ request }) => {
    // Restore the seed default so later specs see 'Allow Trades' = 'No'.
    await setState(request, { 'Allow Trades': 'No' });
  });

  test('panel renders for GM when trades allowed', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await gotoWithRetry(page, 'index.php');

    const panel = page.locator(PANEL);
    await expect(panel).toBeVisible();
    await expect(page.locator('[data-testid="trade-mode-select"]')).toBeVisible();

    // On the CI seed, franchise 1 in 'Free Agency' gets zero cards (see the
    // feedback block below). The populated case is covered by the Stars tests.
    await expect(page.locator('[data-testid="trade-suggestions-empty"]')).toBeVisible();
    await expect(page.locator('[data-testid="trade-suggestion"]')).toHaveCount(0);

    // The only controls in the panel belong to the mode form.
    const modeFormControls = await panel
      .locator('form')
      .filter({ has: page.locator('[data-testid="trade-mode-select"]') })
      .getByRole('button')
      .count();
    await expect(panel.getByRole('button')).toHaveCount(modeFormControls);

    await assertNoPhpErrors(page, 'homepage trade suggestions panel');
  });

  test('panel hidden when trades closed', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'No' });
    await gotoWithRetry(page, 'index.php');
    await expect(page.locator(PANEL)).toHaveCount(0);
  });

  test('mode override persists and relabels', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    await gotoWithRetry(page, 'index.php');
    await submitModeForm(page, 'rebuild');

    expect(page.url().endsWith('#trade-suggestions')).toBe(true);
    const panel = page.locator(PANEL);
    await expect(panel).toContainText('Rebuild');
    await expect(panel).toContainText('your choice');
    expect(await nonAutoModes(request)).toEqual({ [gmTid]: 'rebuild' });

    await submitModeForm(page, 'auto');
    await expect(page.locator(PANEL)).toContainText('our guess');
    expect(await nonAutoModes(request)).toEqual({});
    await assertNoPhpErrors(page, 'after mode override');
  });

  test('mode POST without CSRF token is rejected', async ({ page, request }) => {
    const response = await page.request.post(MODE_POST_URL, {
      form: { trade_mode: 'rebuild' },
      maxRedirects: 0,
    });
    expect(response.status()).toBeGreaterThanOrEqual(300);
    expect(response.status()).toBeLessThan(400);
    expect(response.headers()['location'] ?? '').toContain(
      'trade_mode_error=csrf',
    );
    expect(await nonAutoModes(request)).toEqual({});
  });

  test('mode POST ignores posted teamid', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    // Any franchise other than the GM's: the endpoint must not honor it.
    const postedTid = (gmTid % 28) + 1;
    const token = await panelToken(page);

    const response = await page.request.post(MODE_POST_URL, {
      form: {
        trade_mode: 'win_now',
        teamid: String(postedTid),
        _csrf_token: token,
      },
      maxRedirects: 0,
    });
    expect(response.status()).toBeGreaterThanOrEqual(300);
    expect(response.status()).toBeLessThan(400);

    expect(await getTradeMode(request, postedTid)).toBe('auto');
    expect(await getTradeMode(request, gmTid)).toBe('win_now');
    expect(await nonAutoModes(request)).toEqual({ [gmTid]: 'win_now' });
  });

  test('prefill link ticks suggested boxes', async ({ page }) => {
    const offerRequests: string[] = [];
    page.on('request', (req) => {
      if (req.url().includes('maketradeoffer')) offerRequests.push(req.url());
    });

    await gotoWithRetry(
      page,
      `modules.php?name=Trading&op=offertrade&partner=${PARTNER_NAME}&prefill=1:${USER_PID},1:${PARTNER_PID}`,
    );
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();

    for (const pid of [USER_PID, PARTNER_PID]) {
      // Pick rows also carry index<k> (holding the pickid), so match type<k>=1 too.
      const cell = page
        .locator('td')
        .filter({
          has: page.locator(`input[type="hidden"][name^="index"][value="${pid}"]`),
        })
        .filter({
          has: page.locator('input[type="hidden"][name^="type"][value="1"]'),
        });
      await expect(
        cell.locator('input[type="checkbox"][name^="check"]'),
      ).toBeChecked();
    }
    await expect(
      page.locator('input[type="checkbox"][name^="check"]:checked'),
    ).toHaveCount(2);

    expect(page.url()).not.toContain('maketradeoffer');
    expect(offerRequests).toEqual([]);
    await assertNoPhpErrors(page, 'prefilled offer form');
  });

  test('tampered prefill ticks nothing', async ({ page }) => {
    const tampered = [
      `1:${USER_PID},1:${FOREIGN_PID}`,
      '1:5;DROP',
      `1:${'1'.repeat(199)}`, // 201 characters
    ];
    expect(tampered[2]?.length).toBe(201);

    for (const prefill of tampered) {
      await gotoWithRetry(
        page,
        `modules.php?name=Trading&op=offertrade&partner=${PARTNER_NAME}&prefill=${encodeURIComponent(prefill)}`,
      );
      await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
      await expect(
        page.locator('input[type="checkbox"][name^="check"]:checked'),
      ).toHaveCount(0);
      await assertNoPhpErrors(page, `tampered prefill ${prefill.slice(0, 20)}`);
    }
  });
});

// ---------------------------------------------------------------------------
// Feedback tests: the GM acts for Stars in the regular season
//
// On the CI seed, franchise 1 in 'Free Agency' gets zero cards: every idea
// fails the both-sides-gain check. Stars (teamid 2) in 'Regular Season'
// renders cards, so these tests switch the acting team with the `_test_team`
// cookie and the phase with `appState`. Both are scoped to this browser
// context. Nothing on the seed changes.
// ---------------------------------------------------------------------------

const FEEDBACK_TEAM = PARTNER_NAME;

test.describe('Trade suggestions feedback (GM as Stars)', () => {
  test.beforeEach(async ({ appState, context, page, request }) => {
    await resetFixture(request);
    await resetFeedback(request);
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
      'Current Season Phase': 'Regular Season',
    });
    await gotoWithRetry(page, 'index.php');
    await context.addCookies([
      { name: '_test_team', value: FEEDBACK_TEAM, domain: new URL(page.url()).hostname, path: '/' },
    ]);
  });

  test.afterAll(async ({ request }) => {
    await resetFeedback(request);
    // Restore the seed default so later specs see 'Allow Trades' = 'No'.
    await setState(request, { 'Allow Trades': 'No' });
  });

  test('acting team is Stars', async ({ page, request }) => {
    expect(await resolveGmTid(page, request)).toBe(2);
  });

  test('populated panel has only mode, hide and refresh controls', async ({ page }) => {
    await gotoWithRetry(page, 'index.php');
    const panel = page.locator(PANEL);
    const cards = panel.locator('[data-testid="trade-suggestion"]');
    await expect(cards.first()).toBeVisible();
    const cardCount = await cards.count();

    const modeButtons = await panel
      .locator('form')
      .filter({ has: page.locator('[data-testid="trade-mode-select"]') })
      .locator('button')
      .count();
    expect(modeButtons).toBeGreaterThanOrEqual(1);
    await expect(panel.locator('[data-testid="trade-suggestion-hide-submit"]')).toHaveCount(cardCount);
    await expect(panel.locator('[data-testid="trade-suggestions-refresh"]')).toHaveCount(1);
    // Hide buttons sit in a closed <details>, so count DOM buttons, not roles.
    await expect(panel.locator('button')).toHaveCount(modeButtons + cardCount + 1);
    await expect(panel.locator('input[type="submit"]')).toHaveCount(0);

    const links = panel.locator('[data-testid="trade-suggestion-open"]');
    await expect(links).toHaveCount(cardCount);
    const pattern =
      /^modules\.php\?name=Trading&op=offertrade&partner=[^&]+&prefill=[01]%3A\d+(%2C[01]%3A\d+)*$/;
    for (const href of await links.evaluateAll((els) => els.map((el) => el.getAttribute('href') ?? ''))) {
      expect(href).toMatch(pattern);
      expect(href).not.toContain('maketradeoffer');
    }
    await assertNoPhpErrors(page, 'populated trade suggestions panel');
  });

  test('hide with feedback stores the row and removes the card', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    await gotoWithRetry(page, 'index.php');
    const key = await firstDealKey(page);

    const card = page
      .locator('[data-testid="trade-suggestion"]')
      .filter({ has: page.locator(`input[name="deal_key"][value="${key}"]`) });
    await card.locator('summary').click();
    await card.locator('input[value="TOO_OLD"]').check();
    await card.locator('input[value="OTHER"]').check();
    await card.locator('textarea[name="free_text"]').fill('note <script>alert(1)</script>');
    await Promise.all([
      page.waitForURL(/trade_suggestion_hidden=1/),
      card.locator('[data-testid="trade-suggestion-hide-submit"]').click(),
    ]);

    await expect(page.locator('[data-testid="trade-suggestion-hidden-note"]')).toContainText(
      '<script>alert(1)</script>',
    );
    await expect(page.locator('script', { hasText: 'alert(1)' })).toHaveCount(0);

    const rows = await feedbackRows(request, gmTid);
    expect(rows).toHaveLength(1);
    expect(rows[0]?.deal_key).toBe(key);
    expect(rows[0]?.reason_codes).toBe('TOO_OLD,OTHER');
    expect(rows[0]?.free_text).toBe('note <script>alert(1)</script>');

    const hiddenCard = page.locator(`input[name="deal_key"][value="${key}"]`);
    await expect(hiddenCard).toHaveCount(0);

    // A refreshed run never brings the hidden idea back.
    await Promise.all([
      page.waitForEvent('load'),
      page.locator('[data-testid="trade-suggestions-refresh"]').click(),
    ]);
    await expect(page.locator(PANEL)).toBeVisible();
    await expect(hiddenCard).toHaveCount(0);
    await assertNoPhpErrors(page, 'after hiding a trade idea');
    await rewindOffset(page);
  });

  test('hide POST without CSRF token is rejected', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    await gotoWithRetry(page, 'index.php');
    const key = await firstDealKey(page);

    const location = await postAction(page, {
      action: 'hide',
      deal_key: key,
      'reasons[]': 'TOO_OLD',
    });
    expect(location).toContain('trade_suggestion_error=csrf');
    expect(await feedbackRows(request, gmTid)).toEqual([]);
  });

  test('hide POST with the mode token is rejected', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    const modeToken = await panelToken(page);
    const key = await firstDealKey(page);

    const location = await postAction(page, {
      action: 'hide',
      deal_key: key,
      'reasons[]': 'TOO_OLD',
      _csrf_token: modeToken,
    });
    expect(location).toContain('trade_suggestion_error=csrf');
    expect(await feedbackRows(request, gmTid)).toEqual([]);
  });

  test('hide POST ignores posted teamid', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    // Any franchise other than the GM's: the endpoint must not honor it.
    const postedTid = (gmTid % 28) + 1;
    const token = await actionToken(page);
    const key = await firstDealKey(page);

    const location = await postAction(page, {
      action: 'hide',
      deal_key: key,
      'reasons[]': 'TOO_OLD',
      tid: String(postedTid),
      teamid: String(postedTid),
      _csrf_token: token,
    });
    expect(location).toContain('trade_suggestion_hidden=1');

    const rows = await feedbackRows(request, gmTid);
    expect(rows).toHaveLength(1);
    expect(rows[0]?.tid).toBe(gmTid);
    expect(await feedbackRows(request, postedTid)).toEqual([]);
  });

  test('hide with an unknown reason is rejected', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    const token = await actionToken(page);
    const key = await firstDealKey(page);

    const location = await postAction(page, {
      action: 'hide',
      deal_key: key,
      'reasons[]': 'SMELLS',
      _csrf_token: token,
    });
    expect(location).toContain('trade_suggestion_error=reason');
    expect(await feedbackRows(request, gmTid)).toEqual([]);
  });

  test('hide with a 501-character note is rejected', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    const token = await actionToken(page);
    const key = await firstDealKey(page);

    // Posting directly skips the textarea's maxlength="500".
    const location = await postAction(page, {
      action: 'hide',
      deal_key: key,
      'reasons[]': 'OTHER',
      free_text: 'x'.repeat(501),
      _csrf_token: token,
    });
    expect(location).toContain('trade_suggestion_error=length');
    expect(await feedbackRows(request, gmTid)).toEqual([]);
  });

  test('hide of an idea no longer offered is rejected', async ({ page, request }) => {
    const gmTid = await resolveGmTid(page, request);
    const token = await actionToken(page);

    const location = await postAction(page, {
      action: 'hide',
      deal_key: 'f'.repeat(40),
      'reasons[]': 'TOO_OLD',
      _csrf_token: token,
    });
    expect(location).toContain('trade_suggestion_error=stale');
    expect(await feedbackRows(request, gmTid)).toEqual([]);
  });

  test('refresh steps the offset and wraps', async ({ page }) => {
    // Stars has 2 eligible ideas on the CI seed, so each click wraps to 0 here.
    // The step of three past offset 0 is covered by PanelSessionStateTest and
    // TradeSuggestionsServiceTest.
    await gotoWithRetry(page, 'index.php');
    let state = await refreshOffset(page);
    expect(state.eligible).toBeGreaterThanOrEqual(1);

    for (let i = 0; i < 5; i++) {
      const expected = state.offset + 3 >= state.eligible ? 0 : state.offset + 3;
      await Promise.all([
        page.waitForEvent('load'),
        page.locator('[data-testid="trade-suggestions-refresh"]').click(),
      ]);
      await expect(page.locator('form.trade-suggestions__refresh-form')).toHaveAttribute(
        'data-offset',
        String(expected),
      );
      state = await refreshOffset(page);
    }
    await assertNoPhpErrors(page, 'after refreshing trade ideas');
    await rewindOffset(page);
  });

  test('before/after profile line is visible on a card', async ({ page }) => {
    await gotoWithRetry(page, 'index.php');
    const lines = page.locator('[data-testid="trade-suggestion-profile"] li');
    await expect(lines.first()).toBeVisible();
    const texts = await lines.allTextContents();
    for (const text of texts) {
      expect(text.trim()).toMatch(/^.+ \d+(st|nd|rd|th) -> \d+(st|nd|rd|th)$/);
    }
  });
});

// ---------------------------------------------------------------------------
// Anonymous visitor
// ---------------------------------------------------------------------------

publicTest.describe('Trade suggestions panel (anonymous)', () => {
  publicTest('panel hidden for anonymous visitor', async ({ appState, page }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });
    await gotoWithRetry(page, 'index.php');
    await expect(page.locator(PANEL)).toHaveCount(0);
  });
});
