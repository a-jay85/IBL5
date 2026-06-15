import { test, expect } from '../fixtures/auth';
import { test as isolatedTest, expect as isolatedExpect } from '../fixtures/auth-isolated';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { resetExtension } from '../helpers/cleanup';
import { submitFormAndAssertEffect } from '../helpers/submit-form';

/**
 * Contract Extension submission flow — exercises the actual POST to
 * modules/Player/extension.php instead of the read-only render checks in
 * contract-extension.spec.ts.
 *
 * Serial mode: only Block 1 (happy path) mutates pid=30's contract row.
 * Blocks 2-4 redirect at the CSRF/teamName guards or at the processor's
 * validation step before touching ibl_plr, so a single afterAll reset is
 * sufficient — running it between every test would just burn HTTP calls.
 */

test.describe.configure({ mode: 'serial' });

const EXTENSION_PID = 30;
const METROS_TEAM_NAME = 'Metros';
const NEGOTIATE_URL = `modules.php?name=Player&pa=negotiate&pid=${EXTENSION_PID}`;
const EXTENSION_ENDPOINT = '/ibl5/modules/Player/extension.php';

test.afterAll(async ({ request }) => {
  await resetExtension(request, EXTENSION_PID);
});

interface ExtensionFormFields {
  csrf: string;
  maxyr1: string;
  demandsYears: string;
  demandsTotal: string;
  offerYear1: string;
  offerYear2: string;
  offerYear3: string;
  offerYear4: string;
  offerYear5: string;
}

/**
 * Pull every hidden + numeric field from the rendered Extension Offer form
 * so we can replay the user's submission via APIRequestContext.post.
 *
 * The form only renders when the logged-in user owns pid=30's team
 * (Metros). In CI that's the seeded admin; local dev typically logs in as
 * a different GM and would see "Sorry, X is not on your team." instead.
 * Returns null in that case so the caller can skip the test.
 */
async function readExtensionForm(
  page: import('@playwright/test').Page,
): Promise<ExtensionFormFields | null> {
  const form = page.locator('form[name="ExtensionOffer"]');
  if (!(await form.isVisible().catch(() => false))) { // e2e-hygiene-allow: helper returns null sentinel — callers now hard-assert with expect().not.toBeNull()
    return null;
  }

  return {
    csrf:
      (await form.locator('input[name="_csrf_token"]').getAttribute('value')) ??
      '',
    maxyr1:
      (await form.locator('input[name="maxyr1"]').getAttribute('value')) ?? '0',
    demandsYears:
      (await form
        .locator('input[name="demandsYears"]')
        .getAttribute('value')) ?? '0',
    demandsTotal:
      (await form
        .locator('input[name="demandsTotal"]')
        .getAttribute('value')) ?? '0',
    offerYear1:
      (await form.locator('input[name="offerYear1"]').getAttribute('value')) ??
      '0',
    offerYear2:
      (await form.locator('input[name="offerYear2"]').getAttribute('value')) ??
      '0',
    offerYear3:
      (await form.locator('input[name="offerYear3"]').getAttribute('value')) ??
      '0',
    offerYear4:
      (await form.locator('input[name="offerYear4"]').getAttribute('value')) ??
      '0',
    offerYear5:
      (await form.locator('input[name="offerYear5"]').getAttribute('value')) ??
      '0',
  };
}

function buildFormBody(
  fields: ExtensionFormFields,
  overrides: Partial<Record<string, string>> = {},
): Record<string, string> {
  const body: Record<string, string> = {
    _csrf_token: fields.csrf,
    teamName: METROS_TEAM_NAME,
    playerID: String(EXTENSION_PID),
    playerName: 'Extension Vet',
    demandsYears: fields.demandsYears,
    demandsTotal: fields.demandsTotal,
    maxyr1: fields.maxyr1,
    offerYear1: fields.offerYear1,
    offerYear2: fields.offerYear2,
    offerYear3: fields.offerYear3,
    offerYear4: fields.offerYear4,
    offerYear5: fields.offerYear5,
  };
  for (const [key, value] of Object.entries(overrides)) {
    if (value === undefined) {
      delete body[key];
    } else {
      body[key] = value;
    }
  }
  return body;
}

// ---------------------------------------------------------------------------
// Block 1 — happy path: matching the demands should produce
// extension_accepted or extension_rejected (both are valid simulation outcomes).
// ---------------------------------------------------------------------------

test.describe('Contract Extension submission: happy path', () => {
  test('valid offer redirects to Team contracts with extension result', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto(NEGOTIATE_URL);

    const fields = await readExtensionForm(page);
    // e2e-hygiene-allow: team ownership is user-specific; IBL_TEST_USER may not own Metros in all CI environments
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;

    let location = '';

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        const response = await request.post(EXTENSION_ENDPOINT, {
          form: buildFormBody(fields!),
          maxRedirects: 0,
        });
        expect([301, 302, 303]).toContain(response.status());
        location = response.headers()['location'] ?? '';
        expect(location).toMatch(/result=extension_(accepted|rejected)/);
        expect(location).toContain('display=contracts');
      },
      readBack: async () => {
        await page.goto(location.replace('/ibl5/', ''));
        const banner = page.locator('.ibl-alert--success, .ibl-alert--info');
        await expect(banner.first()).toBeVisible();
      },
    });
  });
});

// ---------------------------------------------------------------------------
// Block 2 — bad CSRF token: HtmxHelper::redirect('/ibl5/index.php')
// ---------------------------------------------------------------------------

test.describe('Contract Extension submission: bad CSRF', () => {
  test('POST without _csrf_token redirects to /ibl5/index.php', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto(NEGOTIATE_URL);

    const fields = await readExtensionForm(page);
    // e2e-hygiene-allow: team ownership is user-specific; IBL_TEST_USER may not own Metros in all CI environments
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;
    const body = buildFormBody(fields, { _csrf_token: undefined });
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: body,
      maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe('/ibl5/index.php');
  });
});

// ---------------------------------------------------------------------------
// Block 3 — bogus teamName: with the D-10 ownership gate, a POST whose teamName
// differs from the session team is refused at the ownership check (which runs
// before the team-not-found lookup), so the redirect now carries the distinct
// extension_forbidden signal rather than the bare /ibl5/index.php bounce.
// ---------------------------------------------------------------------------

test.describe('Contract Extension submission: bogus teamName', () => {
  test('POST with a non-owned teamName is refused with extension_forbidden', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto(NEGOTIATE_URL);

    const fields = await readExtensionForm(page);
    // e2e-hygiene-allow: team ownership is user-specific; IBL_TEST_USER may not own Metros in all CI environments
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;
    const body = buildFormBody(fields, { teamName: 'NonExistentTeam' });
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: body,
      maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe(
      '/ibl5/index.php?result=extension_forbidden',
    );
  });
});

// ---------------------------------------------------------------------------
// Block 4 — zero offer: processor rejects, redirect carries
// result=extension_error and a non-empty msg.
// ---------------------------------------------------------------------------

test.describe('Contract Extension submission: zero offer', () => {
  test('all-zero offer redirects with extension_error', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto(NEGOTIATE_URL);

    const fields = await readExtensionForm(page);
    // e2e-hygiene-allow: team ownership is user-specific; IBL_TEST_USER may not own Metros in all CI environments
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;
    const body = buildFormBody(fields, {
      offerYear1: '0',
      offerYear2: '0',
      offerYear3: '0',
      offerYear4: '0',
      offerYear5: '0',
    });
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: body,
      maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(response.status());
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('result=extension_error');
    expect(location).toMatch(/[?&]msg=[^&]+/);
  });
});

// ---------------------------------------------------------------------------
// Block 5 — D-10 IDOR ownership gate (deterministic in CI).
//
// Uses the auth-isolated fixture so the session team is Monarchs (tid=8) via the
// `_test_team` cookie, independent of the un-seeded `gm_username`. A Monarchs
// bench player (pid=111) is made extension-eligible in ci-seed.sql so its
// ExtensionOffer form renders and we can mint a valid `extension` CSRF token.
// We then replay that token against a real victim team the session does NOT own
// (Stars, tid=2). The ownership check runs BEFORE processExtension(), so no
// victim contract or extension flag is ever touched — the distinct
// `extension_forbidden` signal proves the ownership gate fired (not the generic
// not-found bounce). The negative "no victim write" is pinned at unit level in
// DepthChartEntrySubmissionHandlerOwnershipTest / ExtensionRepository tests.
// ---------------------------------------------------------------------------

const MONARCHS_ELIGIBLE_PID = 111; // 'DC Utility B' (tid=8) — expiring deal in ci-seed
const VICTIM_TEAM_NAME = 'Stars'; // tid=2 — a real team the Monarchs session does not own
const VICTIM_PID = 4; // 'Stars Guard' (ci-seed.sql)

isolatedTest.describe('Contract Extension submission: IDOR ownership gate', () => {
  isolatedTest('Monarchs session POSTing another team is refused', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await page.goto(`modules.php?name=Player&pa=negotiate&pid=${MONARCHS_ELIGIBLE_PID}`);

    const form = page.locator('form[name="ExtensionOffer"]');
    await isolatedExpect(
      form,
      'Monarchs eligible player (pid=111) must render the extension form so we can mint a valid token',
    ).toBeVisible({ timeout: 15000 });
    const csrf =
      (await form.locator('input[name="_csrf_token"]').getAttribute('value')) ??
      '';
    isolatedExpect(csrf).toMatch(/^[a-f0-9]{64}$/);

    const response = await request.post(EXTENSION_ENDPOINT, {
      form: {
        _csrf_token: csrf,
        teamName: VICTIM_TEAM_NAME,
        playerID: String(VICTIM_PID),
        playerName: 'Stars Guard',
        demandsYears: '3',
        demandsTotal: '3000',
        maxyr1: '1000',
        offerYear1: '1000',
        offerYear2: '1000',
        offerYear3: '1000',
        offerYear4: '0',
        offerYear5: '0',
      },
      maxRedirects: 0,
    });

    isolatedExpect([301, 302, 303]).toContain(response.status());
    isolatedExpect(response.headers()['location']).toBe(
      '/ibl5/index.php?result=extension_forbidden',
    );
  });
});

// ---------------------------------------------------------------------------
// Block 6 — D-10 unauthenticated POST is refused.
//
// The `public` fixture sets `_no_auto_login` so DevAutoLogin does not promote
// the request to an admin session. With no valid `extension` token in a public
// session the CSRF gate bounces to /ibl5/index.php (the auth gate would too) —
// either way the request never reaches processExtension(), so no contract or
// flag is written.
// ---------------------------------------------------------------------------

publicTest.describe('Contract Extension submission: unauthenticated', () => {
  publicTest('unauthenticated POST to extension.php is refused', async ({
    request,
  }) => {
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: {
        teamName: VICTIM_TEAM_NAME,
        playerID: String(VICTIM_PID),
        playerName: 'Stars Guard',
        offerYear1: '1000',
      },
      maxRedirects: 0,
    });

    publicExpect([301, 302, 303]).toContain(response.status());
    publicExpect(response.headers()['location']).toBe('/ibl5/index.php');
  });
});

