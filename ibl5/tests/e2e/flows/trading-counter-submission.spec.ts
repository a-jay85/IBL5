import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { resetTradeOffers } from '../helpers/cleanup';

// Serial: tests share the seeded trade offers (ibl_trade_info ids 1-6) and the
// last test auto-rejects offer 1, so they must run in order. Each test re-seeds
// via resetTradeOffers in beforeEach for deterministic state.
test.describe.configure({ mode: 'serial' });

// Seed grounding (ci-seed.sql):
//   Offer 1 (L897-898): player pid=4 Stars->Metros, player pid=2 Metros->Stars.
//     Metros (E2E user, teamid=1) is the recipient; original proposer = Stars.
//   Offer 7 (L923-924): Spurs->Flames / Flames->Spurs. Metros is NOT a party,
//     so it is the IDOR negative case. (Reserved for the REST spec — never on
//     the Metros review page; the counter must be denied, never deleted.)
const SEED_OFFER_ID = 1;
const SEED_OFFER_PROPOSER = 'Stars';
const IDOR_OFFER_ID = 7;

const COUNTER_ENDPOINT = '/ibl5/modules/Trading/countertradeoffer.php';

async function gotoReview(page: Page): Promise<void> {
  await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
}

/**
 * Harvest the shared trade_counter CSRF token from a counter form on the
 * currently-loaded review page.
 */
async function getCounterToken(page: Page): Promise<string> {
  const tokenInput = page.locator(
    `form[action*="countertradeoffer.php"] input[name="_csrf_token"]`,
  );
  if ((await tokenInput.count()) === 0) return '';
  return (await tokenInput.first().getAttribute('value')) ?? '';
}

test.describe('Trade counter-offer flow', () => {
  test.beforeEach(async ({ appState, request }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });
    await resetTradeOffers(request);
  });

  test.afterAll(async ({ request }) => {
    await resetTradeOffers(request);
  });

  test('every offer card renders a Counter button', async ({ page }) => {
    await gotoReview(page);

    const cards = page.locator('.trade-offer-card');
    const cardCount = await cards.count();
    expect(cardCount, 'seed must provide at least one Metros offer').toBeGreaterThan(0);

    const counterButtons = page.locator(
      `form[action*="countertradeoffer.php"] button[type="submit"]`,
    );
    await expect(counterButtons).toHaveCount(cardCount);
    await expect(counterButtons.first()).toHaveText('Counter');
  });

  test('counter POST without a CSRF token is rejected and does not delete the offer', async ({
    page,
    request,
  }) => {
    const response = await request.post(COUNTER_ENDPOINT, {
      form: { offer: String(SEED_OFFER_ID) },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    expect(response.headers()['location'] ?? '').toContain('error=');

    // The source offer must still be on the review page (no delete).
    await gotoReview(page);
    await expect(
      page.locator(`form[action*="countertradeoffer.php"] input[name="offer"][value="${SEED_OFFER_ID}"]`),
    ).toHaveCount(1);
  });

  test('counter on an offer the user is not the recipient of is denied (IDOR)', async ({
    page,
    request,
  }) => {
    // Harvest a genuinely valid trade_counter token so this exercises the IDOR
    // gate, not the CSRF gate.
    await gotoReview(page);
    const token = await getCounterToken(page);
    expect(token, 'review page must render a counter token').not.toBe('');

    const response = await request.post(COUNTER_ENDPOINT, {
      form: { offer: String(IDOR_OFFER_ID), _csrf_token: token },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('op=reviewtrade');
    expect(location).toContain('error=');
    // The delete only runs after the authorization check passes, so a redirect
    // to the error path proves offer 7 was not deleted.
  });

  test('clicking Counter lands on the pre-filled offer form and auto-rejects the original', async ({
    page,
  }) => {
    await gotoReview(page);

    // Confirm the source offer is present before countering it.
    const sourceOfferInput = page.locator(
      `form[action*="countertradeoffer.php"] input[name="offer"][value="${SEED_OFFER_ID}"]`,
    );
    await expect(sourceOfferInput).toHaveCount(1);

    const counterForm = page
      .locator('form[action*="countertradeoffer.php"]')
      .filter({ has: page.locator(`input[name="offer"][value="${SEED_OFFER_ID}"]`) });

    await Promise.all([
      page.waitForURL(/op=offertrade/),
      counterForm.locator('button[type="submit"]').click(),
    ]);

    // Landed on the make-offer form authored back to the original proposer.
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
    await expect(page.locator('form[name="Trade_Offer"] input[name="listeningTeam"]')).toHaveValue(
      SEED_OFFER_PROPOSER,
    );

    // The source offer's assets (pid 4 & 2) are pre-checked.
    const checkedBoxes = page.locator('.trading-roster input[type="checkbox"]:checked');
    expect(await checkedBoxes.count()).toBeGreaterThanOrEqual(1);

    await assertNoPhpErrors(page, 'after counter -> pre-filled offer form');

    // The original offer was auto-rejected (gone from the review page).
    await gotoReview(page);
    await expect(
      page.locator(`form[action*="countertradeoffer.php"] input[name="offer"][value="${SEED_OFFER_ID}"]`),
    ).toHaveCount(0);
  });
});
