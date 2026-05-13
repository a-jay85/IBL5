import { test, expect } from '../fixtures/auth';
import { resetExtension } from '../helpers/cleanup';

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
  if (!(await form.isVisible().catch(() => false))) {
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
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;

    // The rendered offer fields default to the player's demands, which is
    // exactly the input the processor expects for a successful submission.
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: buildFormBody(fields),
      maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(response.status());
    const location = response.headers()['location'] ?? '';
    expect(location).toMatch(/result=extension_(accepted|rejected)/);
    expect(location).toContain('display=contracts');

    // Follow the redirect manually and confirm the team page renders a banner.
    await page.goto(location.replace('/ibl5/', ''));
    const banner = page.locator('.ibl-alert--success, .ibl-alert--info');
    await expect(banner.first()).toBeVisible();
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
// Block 3 — bogus teamName: CommonMysqliRepository returns null →
// HtmxHelper::redirect('/ibl5/index.php')
// ---------------------------------------------------------------------------

test.describe('Contract Extension submission: bogus teamName', () => {
  test('POST with unknown teamName redirects to /ibl5/index.php', async ({
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
    test.skip(fields === null, 'IBL_TEST_USER does not own Metros — extension form not rendered');
    if (fields === null) return;
    const body = buildFormBody(fields, { teamName: 'NonExistentTeam' });
    const response = await request.post(EXTENSION_ENDPOINT, {
      form: body,
      maxRedirects: 0,
    });

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe('/ibl5/index.php');
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

