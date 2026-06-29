import { test as authTest, expect } from '../fixtures/auth';
import { test as publicTest } from '../fixtures/public';
import type { Page } from '@playwright/test';
import { gotoWithRetry } from '../helpers/navigation';
import { resetTradeOffers } from '../helpers/cleanup';
import { collectNewOfferIds } from '../helpers/trading';

/**
 * IDOR / missing-auth coverage for the trade submit endpoint (security finding
 * D-03). These rows cannot live in the PHPUnit suite: the controller is
 * static-walled — every post-auth path ends in HtmxHelper::redirect() (which
 * exit()s) — so the happy path and the IDOR-different-team path are E2E-only.
 *
 * (Accept/reject IDOR — D-04/D-05 — is owned by the trade-refactor PR that binds
 * those endpoints to the session; its coverage lives with that change.)
 *
 * Two distinct fixtures:
 *   - authTest (Metros, teamid=1): D-03 submit-override IDOR + the unresolvable-team
 *     guard (session team forced to Free Agents via the `_test_team` override).
 *   - publicTest (unauthenticated): refusal of the submit endpoint.
 */

const MAKE_ENDPOINT = '/ibl5/modules/Trading/maketradeoffer.php';

// Success-result marker — its ABSENCE proves the action did not complete.
const SUCCESS_MARKERS = ['result=offer_sent'];

// ---------------------------------------------------------------------------
// D-03: submit IDOR — offeringTeam is bound to the session, never the POST body
// ---------------------------------------------------------------------------

authTest.describe('Trade submit IDOR: offeringTeam bound to session (D-03)', () => {
  authTest.afterAll(async ({ request }) => {
    await resetTradeOffers(request);
  });

  authTest(
    'tampered offeringTeam=Stars is ignored — created offer is bound to Metros',
    async ({ appState, page }) => {
      await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

      // Baseline the existing offers before creating one (this navigates to the
      // review page).
      const existingIds = await collectAllOfferIds(page);

      // Find a partner whose form offers a checkable player on BOTH rosters so
      // the submitted offer is non-empty; the page is left on that form.
      const onForm = await findTradeFormWithBothRosters(page);
      expect(onForm, 'CI seed must provide a partner with checkable players on both sides').toBe(true);

      // Tamper the hidden offeringTeam field to spoof another team. The session
      // override must win: the created offer's user-side trade_from stays Metros.
      await page
        .locator('form[name="Trade_Offer"] input[name="offeringTeam"]')
        .evaluate((el) => {
          (el as HTMLInputElement).value = 'Stars';
        });

      // Check one player on each roster.
      await page
        .locator('.trading-roster.team-table')
        .first()
        .locator('input[type="checkbox"]')
        .first()
        .check();
      await page
        .locator('.trading-roster.team-table')
        .nth(1)
        .locator('input[type="checkbox"]')
        .first()
        .check();

      const submitBtn = page.locator('form[name="Trade_Offer"] button[type="submit"]');
      await Promise.all([page.waitForURL(/result=offer_sent/), submitBtn.click()]);

      // The created offer appears on the MetROS review page only if at least one
      // row has trade_from=Metros or trade_to=Metros (TradingService groupTradeOffers
      // filter). createTradeOffer writes the offering-team items with
      // trade_from = offeringTeam. Had the spoofed 'Stars' been trusted, every row
      // would be Stars/partner and the offer would be invisible to Metros. Its
      // presence here proves the session override (offeringTeam=Metros) won.
      const newIds = await collectNewOfferIds(page, existingIds);
      expect(
        newIds.length,
        'created offer must be visible on the Metros review page — proving it was bound to Metros, not Stars',
      ).toBeGreaterThan(0);
    },
  );
});

// ---------------------------------------------------------------------------
// D-03 guard: a session whose team resolves to Free Agents / is unresolvable is
// refused BEFORE any offer is created (submitTradeOffer's resolve-team bail)
// ---------------------------------------------------------------------------

authTest.describe('Trade submit refuses an unresolvable session team (D-03 guard)', () => {
  authTest.afterAll(async ({ request }) => {
    // Defensive: the bail should create nothing, but reset in case a regression
    // run did write an offer before failing.
    await resetTradeOffers(request);
  });

  authTest(
    'session team = Free Agents is refused at the resolve-team guard — no offer created',
    async ({ appState, page, context }) => {
      await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

      // Land on a real trade form AS the normal session team (Metros) so we hold a
      // server-issued CSRF token + a live submit button — an otherwise-valid request.
      const onForm = await findTradeFormWithBothRosters(page);
      expect(onForm, 'CI seed must provide a partner with checkable players on both sides').toBe(true);

      // Check one player on each roster — a complete, otherwise-valid offer. (The
      // submit button is client-side disabled until both sides have a selection;
      // the server-side guard runs ahead of offer construction regardless.)
      await page.locator('.trading-roster.team-table').first().locator('input[type="checkbox"]').first().check();
      await page.locator('.trading-roster.team-table').nth(1).locator('input[type="checkbox"]').first().check();

      // Flip ONLY the resolved team to Free Agents via the `_test_team` override
      // (TestCookieOverrides::getTeamOverride). The CSRF token is session-bound, not
      // team-bound, so it stays valid — the request reaches submitTradeOffer's
      // resolve-team check, where sessionTeam === FREE_AGENTS_TEAM_NAME forces the
      // bail before any DB write.
      await context.addCookies([
        { name: '_test_team', value: 'Free Agents', domain: new URL(page.url()).hostname, path: '/' },
      ]);

      const submitBtn = page.locator('form[name="Trade_Offer"] button[type="submit"]');
      await Promise.all([page.waitForURL(/op=reviewtrade/), submitBtn.click()]);

      // The bail redirects to reviewtrade with this exact message (rawurlencode of
      // 'Could not resolve your team.'). The string is unique to the resolve-team
      // branch — a CSRF failure redirects with 'Invalid...' and no op=reviewtrade,
      // and a successful submit carries result=offer_sent. Asserting the message
      // (not merely the absence of offer_sent) means deleting the guard fails this.
      const errorParam = new URL(page.url()).searchParams.get('error') ?? '';
      expect(
        errorParam,
        'Free-Agents/unresolvable session team must be refused at the resolve-team guard',
      ).toContain('Could not resolve your team');
      for (const marker of SUCCESS_MARKERS) {
        expect(page.url(), `unresolvable-team submit must not yield ${marker}`).not.toContain(marker);
      }
    },
  );
});

// ---------------------------------------------------------------------------
// Unauthenticated refusal of the submit endpoint (D-03)
// ---------------------------------------------------------------------------

publicTest.describe('Trade submit endpoint refuses unauthenticated requests', () => {
  // NOTE on what is proven: CSRF validation is the FIRST check in each controller
  // method and exit()s on failure, so an unauthenticated request (which holds no
  // valid token) is refused at the CSRF gate, BEFORE the isUser() auth gate is
  // reached. Either way the action does not complete — we assert the absence of a
  // success result, which both gates guarantee. The auth gate itself is covered
  // directly by the PHPUnit characterization tests (unauthenticated, CSRF primed).
  for (const [name, endpoint, form] of [
    ['maketradeoffer.php (submit)', MAKE_ENDPOINT, { offeringTeam: 'Stars', listeningTeam: 'Metros' }],
  ] as const) {
    publicTest(`${name} does not complete for an unauthenticated user`, async ({ request }) => {
      const response = await request.post(endpoint, { form, maxRedirects: 0 });
      const location = response.headers()['location'] ?? '';
      for (const marker of SUCCESS_MARKERS) {
        expect(location, `unauthenticated ${name} must not yield ${marker}`).not.toContain(marker);
      }
    });
  }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Collect all offer IDs currently on the Metros review page. */
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

/**
 * Walk the available trade partners and land on the first form whose two
 * rosters each expose a checkable item. Returns true if such a form was found
 * (page is left on it), false otherwise.
 */
async function findTradeFormWithBothRosters(page: Page, maxTries = 12): Promise<boolean> {
  await gotoWithRetry(page, 'modules.php?name=Trading');
  const teamLinks = page.locator('.trading-team-select a');
  const linkCount = await teamLinks.count();

  for (let i = 0; i < Math.min(linkCount, maxTries); i++) {
    const href = await teamLinks.nth(i).getAttribute('href');
    if (!href) continue;
    await page.goto(href);

    const form = page.locator('form[name="Trade_Offer"]');
    if ((await form.count()) === 0) {
      await gotoWithRetry(page, 'modules.php?name=Trading');
      continue;
    }

    const userBoxes = page
      .locator('.trading-roster.team-table')
      .first()
      .locator('input[type="checkbox"]');
    const partnerBoxes = page
      .locator('.trading-roster.team-table')
      .nth(1)
      .locator('input[type="checkbox"]');

    const [userCount, partnerCount] = await Promise.all([userBoxes.count(), partnerBoxes.count()]);
    if (userCount > 0 && partnerCount > 0) {
      return true;
    }
    await gotoWithRetry(page, 'modules.php?name=Trading');
  }
  return false;
}
