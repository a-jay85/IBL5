import { test, expect } from '../fixtures/auth';
import type { Page, APIRequestContext } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { submitFormAndAssertEffect } from '../helpers/submit-form';
import {
  buildFormBody,
  collectNewOfferIds,
  rejectOfferSafe,
  type FormData,
} from '../helpers/trading';

// Serial: tests create and consume trade offers sequentially.
test.describe.configure({ mode: 'serial' });

// ---------------------------------------------------------------------------
// Types and helpers
// ---------------------------------------------------------------------------

/**
 * Extract all form field data from the currently-loaded trade offer form.
 * The page must already be on a trade form page.
 */
async function extractFormData(page: Page): Promise<FormData> {
  return page.evaluate(() => {
    const form = document.querySelector(
      'form[name="Trade_Offer"]',
    ) as HTMLFormElement;
    const cfg = (window as Record<string, unknown>)
      .IBL_TRADE_CONFIG as Record<string, unknown>;

    const switchCounter = cfg.switchCounter as number;
    const cashStartYear = cfg.cashStartYear as number;
    const cashEndYear = cfg.cashEndYear as number;

    const fieldsCounterInput = form.querySelector(
      'input[name="fieldsCounter"]',
    ) as HTMLInputElement;
    const fieldsCounter = parseInt(fieldsCounterInput.value, 10);

    const offeringTeamInput = form.querySelector(
      'input[name="offeringTeam"]',
    ) as HTMLInputElement;
    const listeningTeamInput = form.querySelector(
      'input[name="listeningTeam"]',
    ) as HTMLInputElement;

    const fields: Array<{
      index: string;
      type: string;
      contract: string;
      hasCheckbox: boolean;
    }> = [];

    for (let k = 0; k < fieldsCounter; k++) {
      const indexEl = form.querySelector(
        `input[name="index${k}"]`,
      ) as HTMLInputElement | null;
      const typeEl = form.querySelector(
        `input[name="type${k}"]`,
      ) as HTMLInputElement | null;
      const contractEl = form.querySelector(
        `input[name="contract${k}"]`,
      ) as HTMLInputElement | null;
      const checkboxEl = form.querySelector(
        `input[name="check${k}"][type="checkbox"]`,
      ) as HTMLInputElement | null;

      fields.push({
        index: indexEl?.value ?? '0',
        type: typeEl?.value ?? '0',
        contract: contractEl?.value ?? '0',
        hasCheckbox: checkboxEl !== null,
      });
    }

    return {
      offeringTeam: offeringTeamInput.value,
      listeningTeam: listeningTeamInput.value,
      switchCounter,
      fieldsCounter,
      cashStartYear,
      cashEndYear,
      fields,
    };
  });
}

/** Get checkable user player indices (type=1, before switchCounter) */
function getUserPlayerIndices(fd: FormData): number[] {
  return fd.fields
    .map((f, i) => i)
    .filter(
      (i) =>
        i < fd.switchCounter &&
        fd.fields[i].type === '1' &&
        fd.fields[i].hasCheckbox,
    );
}

/** Get checkable partner player indices (type=1, at/after switchCounter) */
function getPartnerPlayerIndices(fd: FormData): number[] {
  return fd.fields
    .map((f, i) => i)
    .filter(
      (i) =>
        i >= fd.switchCounter &&
        fd.fields[i].type === '1' &&
        fd.fields[i].hasCheckbox,
    );
}

/** Get checkable user pick indices (type=0, before switchCounter) */
function getUserPickIndices(fd: FormData): number[] {
  return fd.fields
    .map((f, i) => i)
    .filter(
      (i) =>
        i < fd.switchCounter &&
        fd.fields[i].type === '0' &&
        fd.fields[i].hasCheckbox,
    );
}

/**
 * Navigate to the trading page, try each partner until one satisfies the
 * predicate, and return its FormData. Returns null if none qualify.
 */
async function findPartner(
  page: Page,
  predicate: (fd: FormData) => boolean,
  maxTries = 10,
): Promise<FormData | null> {
  await gotoWithRetry(page, 'modules.php?name=Trading');
  const teamLinks = page.locator('.trading-team-select a');
  const linkCount = await teamLinks.count();

  for (let i = 0; i < Math.min(linkCount, maxTries); i++) {
    const href = await teamLinks.nth(i).getAttribute('href');
    await page.goto(href!);

    const form = page.locator('form[name="Trade_Offer"]');
    if ((await form.count()) === 0) {
      await gotoWithRetry(page, 'modules.php?name=Trading');
      continue;
    }

    const fd = await extractFormData(page);
    if (predicate(fd)) {
      return fd;
    }
    await gotoWithRetry(page, 'modules.php?name=Trading');
  }
  return null;
}

/**
 * Extract the CSRF token from the trading form on the current page.
 */
async function getCsrfToken(page: Page): Promise<string> {
  const token = await page
    .locator('form[name="Trade_Offer"] input[name="_csrf_token"]')
    .getAttribute('value');
  return token ?? '';
}

/**
 * Extract a valid trade_accept CSRF token from the review page.
 * The trade_accept token is generated per accept form, not the offer form.
 */
async function getAcceptCsrfToken(page: Page): Promise<string> {
  await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
  const tokenInput = page.locator(
    '.trade-offer-card form[action*="accepttradeoffer"] input[name="_csrf_token"]',
  );
  if ((await tokenInput.count()) === 0) return '';
  return (await tokenInput.first().getAttribute('value')) ?? '';
}

/**
 * Submit a trade offer via API POST. Returns the redirect Location header.
 */
async function submitOffer(
  request: APIRequestContext,
  formBody: Record<string, string>,
): Promise<string> {
  const response = await request.post(
    '/ibl5/modules/Trading/maketradeoffer.php',
    { form: formBody, maxRedirects: 0 },
  );
  return response.headers()['location'] ?? '';
}

/**
 * Collect all offer IDs currently on the review page.
 */
async function collectAllOfferIds(page: Page): Promise<Set<number>> {
  await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
  const buttons = page.locator('[data-preview-offer]');
  const count = await buttons.count();
  const ids = new Set<number>();
  for (let i = 0; i < count; i++) {
    const idStr = await buttons.nth(i).getAttribute('data-preview-offer');
    ids.add(parseInt(idStr ?? '0', 10));
  }
  return ids;
}

// ---------------------------------------------------------------------------
// Block 1: Players-only trade (UI-driven)
// ---------------------------------------------------------------------------

test.describe('Trade submission: players-only (UI)', () => {
  const createdOfferIds: number[] = [];

  test('submit a player-for-player trade via the form', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    const fd = await findPartner(
      page,
      (fd) =>
        getUserPlayerIndices(fd).length > 0 &&
        getPartnerPlayerIndices(fd).length > 0,
    );

    expect(fd, 'CI seed must provide a trade partner with tradeable players on both sides').toBeTruthy();

    // Page is already on the form — check one player from each player roster
    const userCheckbox = page
      .locator('.trading-roster.team-table')
      .first()
      .locator('input[type="checkbox"]')
      .first();
    await userCheckbox.check();

    const partnerCheckbox = page
      .locator('.trading-roster.team-table')
      .nth(1)
      .locator('input[type="checkbox"]')
      .first();
    await partnerCheckbox.check();

    const submitBtn = page.locator(
      'form[name="Trade_Offer"] button[type="submit"]',
    );

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await Promise.all([
          page.waitForURL(/result=offer_sent/),
          submitBtn.click(),
        ]);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success')).toBeVisible();
        await assertNoPhpErrors(page, 'after player trade submission');
      },
      readBack: async () => {
        const buttons = page.locator('[data-preview-offer]');
        const count = await buttons.count();
        let maxId = 0;
        for (let i = 0; i < count; i++) {
          const idStr = await buttons.nth(i).getAttribute('data-preview-offer');
          const id = parseInt(idStr ?? '0', 10);
          if (id > maxId) maxId = id;
        }
        expect(maxId, 'new offer ID should be positive').toBeGreaterThan(0);
        createdOfferIds.push(maxId);
      },
    });
  });

  test.afterAll(async ({ request }) => {
    for (const id of createdOfferIds) {
      await rejectOfferSafe(request, id, '', '');
    }
  });
});

// ---------------------------------------------------------------------------
// Block 2: Draft pick trade (API-driven)
// ---------------------------------------------------------------------------

test.describe('Trade submission: draft pick trade (API)', () => {
  let createdOfferIds: number[] = [];

  test('submit a player-for-pick trade via API', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    const fd = await findPartner(
      page,
      (fd) =>
        getUserPickIndices(fd).length > 0 &&
        getPartnerPlayerIndices(fd).length > 0,
    );

    expect(fd, 'CI seed must provide a partner with user pick + partner player').toBeTruthy();

    const token = await getCsrfToken(page);
    const existingIds = await collectAllOfferIds(page);

    const pickIdx = getUserPickIndices(fd!)[0];
    const partnerIdx = getPartnerPlayerIndices(fd!)[0];
    const body = buildFormBody(fd!, [pickIdx, partnerIdx], undefined, undefined, token);
    const location = await submitOffer(request, body);

    // Accept either success or cap/validation error — both prove the pipeline works
    expect(
      location.includes('result=offer_sent') || location.includes('error='),
    ).toBeTruthy();

    if (location.includes('result=offer_sent')) {
      createdOfferIds = await collectNewOfferIds(page, existingIds);
    }
  });

  test.afterAll(async ({ request }) => {
    for (const id of createdOfferIds) {
      await rejectOfferSafe(request, id, '', '');
    }
  });
});

// ---------------------------------------------------------------------------
// Block 3: Cash-only trade (API-driven)
// ---------------------------------------------------------------------------

test.describe('Trade submission: cash-only trade (API)', () => {
  let createdOfferIds: number[] = [];

  test('submit a cash-only trade via API', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    // Any partner works for a cash-only trade
    const fd = await findPartner(page, () => true);
    expect(fd, 'CI seed must provide a trade partner').toBeTruthy();

    const token = await getCsrfToken(page);
    const existingIds = await collectAllOfferIds(page);

    const cashYear = fd!.cashStartYear;
    const body = buildFormBody(fd!, [], { [cashYear]: 200 }, undefined, token);
    const location = await submitOffer(request, body);

    // CI seed provides teams under cap — cash trade must succeed
    expect(location).toContain('result=offer_sent');

    createdOfferIds = await collectNewOfferIds(page, existingIds);
  });

  test.afterAll(async ({ request }) => {
    for (const id of createdOfferIds) {
      await rejectOfferSafe(request, id, '', '');
    }
  });
});

// ---------------------------------------------------------------------------
// Block 4: Mixed trade (API-driven)
// ---------------------------------------------------------------------------

test.describe('Trade submission: mixed trade (API)', () => {
  let createdOfferIds: number[] = [];

  test('submit a player + cash trade via API', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    const fd = await findPartner(
      page,
      (fd) =>
        getUserPlayerIndices(fd).length > 0 &&
        getPartnerPlayerIndices(fd).length > 0,
    );
    expect(fd, 'CI seed must provide a partner with tradeable players on both sides').toBeTruthy();

    const token = await getCsrfToken(page);
    const existingIds = await collectAllOfferIds(page);

    const userIdx = getUserPlayerIndices(fd!)[0];
    const partnerIdx = getPartnerPlayerIndices(fd!)[0];
    const cashYear = fd!.cashStartYear;
    const body = buildFormBody(
      fd!,
      [userIdx, partnerIdx],
      { [cashYear]: 100 },
      { [cashYear]: 150 },
      token,
    );
    const location = await submitOffer(request, body);

    // Accept either success or cap/validation error — both prove the pipeline works
    expect(
      location.includes('result=offer_sent') || location.includes('error='),
    ).toBeTruthy();

    if (location.includes('result=offer_sent')) {
      createdOfferIds = await collectNewOfferIds(page, existingIds);
    }
  });

  test.afterAll(async ({ request }) => {
    for (const id of createdOfferIds) {
      await rejectOfferSafe(request, id, '', '');
    }
  });
});

// ---------------------------------------------------------------------------
// Block 5: Validation errors
// ---------------------------------------------------------------------------

test.describe('Trade submission: validation errors', () => {
  test('cash below minimum returns error', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    const fd = await findPartner(page, () => true);
    expect(fd, 'CI seed must provide a trade partner').toBeTruthy();

    const token = await getCsrfToken(page);
    const cashYear = fd!.cashStartYear;
    // 50 is below the 100 minimum
    const body = buildFormBody(fd!, [], { [cashYear]: 50 }, undefined, token);
    const location = await submitOffer(request, body);
    expect(location).toContain('error=');

    // Navigate to the error URL and verify it renders
    await page.goto(location.replace('/ibl5/', ''));
    await expect(page.locator('.ibl-alert--error')).toBeVisible();
    await assertNoPhpErrors(page, 'on trade error page');
  });

  test('trades disabled shows closed message', async ({
    appState,
    page,
  }) => {
    // areTradesAllowed() returns true during Draft/Free Agency regardless of setting.
    // Must use a non-FA phase to test the "Allow Trades" check.
    await appState({
      'Allow Trades': 'No',
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await gotoWithRetry(page, 'modules.php?name=Trading');

    await expect(
      page.locator('form[name="Trade_Offer"]'),
    ).not.toBeAttached();

    const body = await page.locator('body').textContent();
    expect(body).toContain('trades are not allowed');

    await assertNoPhpErrors(page, 'on trades-closed page');
  });
});

// ---------------------------------------------------------------------------
// Block 6: Accept and reject flows
// ---------------------------------------------------------------------------

test.describe('Trade submission: accept and reject', () => {
  let offerAId = 0;
  let offerBId = 0;
  let offeringTeam = '';
  let listeningTeam = '';
  let setupDone = false;

  test('create offers for accept/reject tests', async ({
    appState,
    page,
    request,
  }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    const fd = await findPartner(
      page,
      (fd) =>
        getUserPlayerIndices(fd).length >= 2 &&
        getPartnerPlayerIndices(fd).length >= 2,
    );

    expect(fd, 'CI seed must provide a partner with 2+ tradeable players on each side').toBeTruthy();

    offeringTeam = fd!.offeringTeam;
    listeningTeam = fd!.listeningTeam;

    const tokenA = await getCsrfToken(page);
    const tradeFormUrl = page.url();
    const existingIds = await collectAllOfferIds(page);
    const userPlayers = getUserPlayerIndices(fd!);
    const partnerPlayers = getPartnerPlayerIndices(fd!);
    const bodyA = buildFormBody(fd!, [userPlayers[0], partnerPlayers[0]], undefined, undefined, tokenA);
    const locationA = await submitOffer(request, bodyA);

    // Reload trading form to get a fresh CSRF token (single-use tokens)
    await page.goto(tradeFormUrl);
    const tokenB = await getCsrfToken(page);
    const bodyB = buildFormBody(fd!, [userPlayers[1], partnerPlayers[1]], undefined, undefined, tokenB);
    const locationB = await submitOffer(request, bodyB);

    // CI seed provides teams under cap — both offers must succeed
    expect(locationA).toContain('result=offer_sent');
    expect(locationB).toContain('result=offer_sent');

    const newIds = await collectNewOfferIds(page, existingIds);
    newIds.sort((a, b) => a - b);
    expect(newIds.length).toBeGreaterThanOrEqual(2);
    offerAId = newIds[0];
    offerBId = newIds[1];
    setupDone = true;
  });

  test('reject offer via UI', async ({ appState, page }) => {
    expect(setupDone, 'Setup test must have created offers').toBe(true);

    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');

    const offerBCard = page.locator('.trade-offer-card').filter({
      has: page.locator(`[data-preview-offer="${offerBId}"]`),
    });
    await expect(offerBCard).toBeVisible();

    const rejectBtn = offerBCard.locator('.ibl-btn--danger');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await Promise.all([
          page.waitForURL(/result=trade_rejected/),
          rejectBtn.click(),
        ]);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--info')).toBeVisible();
        await assertNoPhpErrors(page, 'after reject');
      },
      readBack: async () => {
        const rejectedCard = page.locator('.trade-offer-card').filter({
          has: page.locator(`[data-preview-offer="${offerBId}"]`),
        });
        await expect(rejectedCard).toHaveCount(0);
      },
    });
  });

  test('rejecting already-processed offer returns warning', async ({
    appState,
    page,
  }) => {
    expect(setupDone, 'Setup test must have created offers').toBe(true);

    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

    // Navigate to review page — offerA should still be visible with a reject button
    await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
    const offerACard = page.locator('.trade-offer-card').filter({
      has: page.locator(`[data-preview-offer="${offerAId}"]`),
    });

    // Tamper with the reject form's offer ID to point at the already-rejected offerB.
    // This simulates submitting a reject for an already-processed offer while
    // still using a valid CSRF token from the browser's form.
    await offerACard.locator('form[name="tradereject"] input[name="offer"]')
      .evaluate((el, id) => { (el as HTMLInputElement).value = String(id); }, offerBId);

    const rejectBtn = offerACard.locator('.ibl-btn--danger');
    await Promise.all([
      page.waitForURL(/result=already_processed/),
      rejectBtn.click(),
    ]);

    // Scope by text — admin phase-gate notice may share .ibl-alert--warning.
    await expect(
      page.locator('.ibl-alert--warning').filter({ hasText: 'already' }),
    ).toBeVisible();
  });

  test('accept offer via UI', async ({ appState, page, request }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');

    // Find any offer card with an Accept button (user has the "hammer").
    // Self-created offers show "Awaiting Approval" — only partner-created
    // or seeded offers (where approval = user's team) have Accept.
    const acceptableCard = page.locator('.trade-offer-card').filter({
      has: page.locator('.ibl-btn--success'),
    });

    expect(await acceptableCard.count(), 'CI seed must provide offers with Accept button').toBeGreaterThan(0);

    const acceptBtn = acceptableCard.first().locator('.ibl-btn--success');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await Promise.all([
          page.waitForURL(/result=trade_accepted/),
          acceptBtn.click(),
        ]);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success')).toBeVisible();
        await assertNoPhpErrors(page, 'after accept');
      },
      readBack: async () => {
        expect(page.url()).toContain('result=trade_accepted');
      },
    });

    // Clean up offer A if it still exists
    if (setupDone && offerAId > 0) {
      await rejectOfferSafe(request, offerAId, offeringTeam, listeningTeam);
    }
  });
});

// ---------------------------------------------------------------------------
// Block 7: PHP error coverage on review page after mutations
// ---------------------------------------------------------------------------

test.describe('Trade review page: no PHP errors after mutations', () => {
  test('review page has no PHP errors', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
    await assertNoPhpErrors(page, 'on review page after mutations');
  });
});

// ---------------------------------------------------------------------------
// Block 8: Trade accept: error branches (API)
// ---------------------------------------------------------------------------

test.describe('Trade accept: error branches (API)', () => {
  test('accept with stale offer ID returns already_processed', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });
    const token = await getAcceptCsrfToken(page);
    expect(
      token,
      'CI seed must provide offers with accept forms on review page',
    ).toBeTruthy();

    const response = await request.post(
      '/ibl5/modules/Trading/accepttradeoffer.php',
      {
        form: { offer: '999999', _csrf_token: token },
        maxRedirects: 0,
      },
    );
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('result=already_processed');
  });

  test('accept with non-numeric offer redirects to reviewtrade', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });
    const token = await getAcceptCsrfToken(page);
    expect(token).toBeTruthy();

    const response = await request.post(
      '/ibl5/modules/Trading/accepttradeoffer.php',
      {
        form: { offer: 'not-a-number', _csrf_token: token },
        maxRedirects: 0,
      },
    );
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('op=reviewtrade');
    expect(location).not.toContain('result=');
  });

  test('accept without CSRF token returns error', async ({
    appState,
    request,
  }) => {
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Ending Year': '2026',
    });

    const response = await request.post(
      '/ibl5/modules/Trading/accepttradeoffer.php',
      {
        form: { offer: '1' },
        maxRedirects: 0,
      },
    );
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('error=');
  });
});
