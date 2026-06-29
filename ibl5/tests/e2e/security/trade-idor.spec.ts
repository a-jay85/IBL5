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
 * Reject IDOR (D-05) lives here too: this is the trade-refactor PR that binds the
 * reject endpoint to the session, so its coverage ships with the change. The
 * reject gate is INLINE in the controller and every branch ends in
 * HtmxHelper::redirect() (exit()), so "a non-party cannot delete the offer" can
 * only be proven end-to-end — there is no exit-free unit seam. (Accept IDOR —
 * D-04 — gates inside TradeExecutionService and IS unit-tested exit-free in
 * TradeExecutionServiceTest::testValidateAndExecuteRejectsNonPartyWithoutExecuting.)
 *
 * Two distinct fixtures:
 *   - authTest (Metros, teamid=1): D-03 submit-override IDOR + the unresolvable-team
 *     guard (session team forced to Free Agents via the `_test_team` override).
 *   - publicTest (unauthenticated): refusal of the submit endpoint.
 */

const MAKE_ENDPOINT = '/ibl5/modules/Trading/maketradeoffer.php';
const REJECT_ENDPOINT = '/ibl5/modules/Trading/rejecttradeoffer.php';

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
// D-05: reject IDOR — a non-party GM cannot DELETE an offer they are not part of
// ---------------------------------------------------------------------------

authTest.describe('Trade reject IDOR: a non-party cannot delete an offer (D-05)', () => {
  authTest.afterAll(async ({ request }) => {
    await resetTradeOffers(request);
  });

  authTest(
    'a non-party acting team is refused at the gate and the offer is NOT deleted',
    async ({ appState, page, context }) => {
      await appState({ 'Allow Trades': 'Yes', 'Current Season Ending Year': '2026' });

      // --- Seed exactly one real offer authored by the session team (Metros). ---
      const existingIds = await collectAllOfferIds(page);

      // Real partner team names (from the ?partner= link param) so the non-party
      // override below is a genuine team, not a synthetic string the gate might
      // one day reject for a different reason.
      const partnerNames = await collectPartnerNames(page);
      expect(
        partnerNames.length,
        'CI seed must expose >=2 trade partners so a non-party team exists',
      ).toBeGreaterThanOrEqual(2);

      const onForm = await findTradeFormWithBothRosters(page);
      expect(onForm, 'CI seed must provide a partner with checkable players on both sides').toBe(true);

      // The team this offer is actually with — read from the form, authoritative.
      const offerPartner = await page
        .locator('form[name="Trade_Offer"] input[name="listeningTeam"]')
        .inputValue();

      await page.locator('.trading-roster.team-table').first().locator('input[type="checkbox"]').first().check();
      await page.locator('.trading-roster.team-table').nth(1).locator('input[type="checkbox"]').first().check();
      const submitBtn = page.locator('form[name="Trade_Offer"] button[type="submit"]');
      await Promise.all([page.waitForURL(/result=offer_sent/), submitBtn.click()]);

      const newIds = await collectNewOfferIds(page, existingIds);
      expect(newIds.length, 'a Metros-authored offer must exist to target').toBeGreaterThan(0);
      const targetOfferId = newIds[0];

      // The offer's party set is {Metros, offerPartner}. A faithful non-party is
      // any real team that is neither.
      const nonParty = partnerNames.find((t) => t !== offerPartner && t !== 'Metros');
      expect(nonParty, 'CI seed must expose a real team that is not a party to the offer').toBeTruthy();

      // --- Harvest the session-bound trade_reject CSRF token from the review page.
      // It is rendered inside the offer card and is session-bound, NOT offer- or
      // team-bound (TradingView mints it once via generateRawToken('trade_reject')),
      // so it stays valid after the team override below. ---
      await gotoWithRetry(page, 'modules.php?name=Trading&op=reviewtrade');
      const rejectToken = await page
        .locator('form[name="tradereject"] input[name="_csrf_token"]')
        .first()
        .inputValue();
      expect(rejectToken, 'review page must mint a trade_reject token').not.toBe('');

      // --- Flip the ACTING team to the non-party via _test_team. The CSRF token is
      // session-bound so it stays valid (same technique as the D-03 guard test);
      // resolveActingTeam() now returns the non-party, so assertActingTeamIsParty()
      // must fail and short-circuit BEFORE deleteTradeOffer() runs. teamRejecting is
      // attacker-controlled and used only for the Discord DM — the gate ignores it. ---
      const host = new URL(page.url()).hostname;
      await context.addCookies([{ name: '_test_team', value: nonParty!, domain: host, path: '/' }]);

      const resp = await page.request.post(REJECT_ENDPOINT, {
        form: {
          _csrf_token: rejectToken,
          offer: String(targetOfferId),
          teamRejecting: nonParty!,
          teamReceiving: offerPartner,
        },
        maxRedirects: 0,
      });
      const location = resp.headers()['location'] ?? '';
      expect(location, 'non-party reject must hit the gate deny branch').toContain('result=reject_error');
      expect(
        location,
        'non-party reject must NOT report a successful rejection',
      ).not.toContain('result=trade_rejected');

      // --- Load-bearing assertion: the offer ROW still exists. A delete-then-redirect
      // bug (gate moved below the delete, or redirect() stops exiting) would pass the
      // marker check above but fail HERE. Re-scrape as a real party (override → Metros,
      // the offering team, which sees the offer on its review page). ---
      await context.addCookies([{ name: '_test_team', value: 'Metros', domain: host, path: '/' }]);
      const survivingIds = await collectAllOfferIds(page);
      expect(
        [...survivingIds],
        'the offer a non-party tried to reject must still exist',
      ).toContain(targetOfferId);
    },
  );
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Real partner team names from the trade team-select links. Each link is
 * `...&partner=<teamName>` (TradingView line ~323), so the value is the clean
 * team name — used to pick a genuine non-party team for the IDOR override.
 */
async function collectPartnerNames(page: Page): Promise<string[]> {
  await gotoWithRetry(page, 'modules.php?name=Trading');
  const links = page.locator('.trading-team-select a');
  const count = await links.count();
  const names: string[] = [];
  for (let i = 0; i < count; i++) {
    const href = await links.nth(i).getAttribute('href');
    const match = href?.match(/[?&]partner=([^&]+)/);
    if (match) {
      names.push(decodeURIComponent(match[1]));
    }
  }
  return names;
}

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
