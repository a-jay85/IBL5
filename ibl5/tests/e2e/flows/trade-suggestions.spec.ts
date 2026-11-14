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
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });
  });

  test.afterAll(async ({ request }) => {
    // Restore the seed default so later specs see 'Allow Trades' = 'No'.
    await setState(request, { 'Allow Trades': 'No' });
  });

  test('panel renders for GM when trades allowed', async ({ page }) => {
    await gotoWithRetry(page, 'index.php');

    const panel = page.locator(PANEL);
    await expect(panel).toBeVisible();
    await expect(page.locator('[data-testid="trade-mode-select"]')).toBeVisible();

    // The CI seed may or may not yield a suggestion: assert the exclusive-or,
    // never a count.
    const items = await page.locator('[data-testid="trade-suggestion"]').count();
    const emptyVisible = await page
      .locator('[data-testid="trade-suggestions-empty"]')
      .isVisible();
    expect(items >= 1 !== emptyVisible).toBe(true);

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

  test('suggestion links target offer screen only', async ({ page }) => {
    await gotoWithRetry(page, 'index.php');
    await expect(page.locator(PANEL)).toBeVisible();

    // The CI seed may yield zero links; the invariant holds for whatever renders.
    // The worktree-stack pre-prod row exercises a populated panel.
    const links = page.locator('[data-testid="trade-suggestion-open"]');
    const count = await links.count();
    const pattern =
      /^modules\.php\?name=Trading&op=offertrade&partner=[^&]+&prefill=[01]%3A\d+(%2C[01]%3A\d+)*$/;
    for (let i = 0; i < count; i++) {
      const href = (await links.nth(i).getAttribute('href')) ?? '';
      expect(href).toMatch(pattern);
      expect(href).not.toContain('maketradeoffer');
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
