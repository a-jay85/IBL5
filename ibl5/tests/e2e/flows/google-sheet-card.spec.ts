import { test, expect } from '../fixtures/auth-regular';
import type { APIRequestContext, Page, Route } from '@playwright/test';

/**
 * Google Sheets card on the ApiKeys page, driven by seeded connection rows for
 * the E2E regular user (test-state.php seed/get/delete-google-sheet-connection).
 * The seeded refresh token is a fixed fake; every Google API host is
 * route-intercepted and counted, and the count must stay 0.
 *
 * Runs in the `mutators` project: it rewrites the regular user's connection row,
 * which security/google-sheets-oauth.spec.ts also reads.
 */

const API_KEYS = 'modules.php?name=ApiKeys';
const SHEET_URL = 'https://docs.google.com/spreadsheets/d/e2e-sheet-id/edit';

async function seed(request: APIRequestContext, status: 'active' | 'broken', reason = ''): Promise<void> {
  const response = await request.post(
    `test-state.php?action=seed-google-sheet-connection&status=${status}&reason=${encodeURIComponent(reason)}`,
    { data: {} },
  );
  if (!response.ok()) {
    throw new Error(`seed-google-sheet-connection failed: ${response.status()} ${await response.text()}`);
  }
}

async function clear(request: APIRequestContext): Promise<void> {
  await request.delete('test-state.php?action=delete-google-sheet-connection');
}

async function connection(request: APIRequestContext): Promise<{ status: number; body: { status?: string } }> {
  const response = await request.get('test-state.php?action=get-google-sheet-connection');
  return { status: response.status(), body: response.ok() ? await response.json() : {} };
}

function countGoogleApiRequests(page: Page): { count: number } {
  const counter = { count: 0 };
  const handler = async (route: Route) => {
    counter.count++;
    await route.fulfill({ status: 500, body: 'unexpected Google API call' });
  };
  void page.route('https://oauth2.googleapis.com/**', handler);
  void page.route('https://sheets.googleapis.com/**', handler);
  return counter;
}

/** The `_csrf_token` inside the form posting to `op`, read from a fresh render. */
async function tokenFor(request: APIRequestContext, op: string): Promise<string> {
  const html = await (await request.get(API_KEYS)).text();
  const formIdx = html.indexOf(`op=${op}"`);
  if (formIdx === -1) {
    throw new Error(`no form for op=${op} on the ApiKeys page`);
  }
  const match = html.slice(formIdx).match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error(`no _csrf_token in the op=${op} form`);
  }
  return match[1];
}

async function post(request: APIRequestContext, op: string, withToken: boolean): Promise<void> {
  const form: Record<string, string> = withToken ? { _csrf_token: await tokenFor(request, op) } : {};
  const response = await request.post(`${API_KEYS}&op=${op}`, { form, maxRedirects: 0 });
  expect(response.status()).toBe(302);
}

test.describe('Google Sheets card', () => {
  test.describe.configure({ mode: 'serial' });

  test.skip(
    !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
    'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
  );

  let google: { count: number };

  test.beforeEach(async ({ page }) => {
    const html = await (await page.request.get(API_KEYS)).text();
    test.skip(
      html.includes('sync is not configured') && !process.env.CI,
      'Google Sheets sync not configured locally (GOOGLE_OAUTH_* / GOOGLE_TOKEN_KEY unset)',
    );
    await clear(page.request);
    google = countGoogleApiRequests(page);
  });

  test.afterEach(async ({ page }) => {
    expect(google.count).toBe(0);
    await clear(page.request);
  });

  test('no connection shows the sign-in button only', async ({ page }) => {
    await page.goto(API_KEYS);

    await expect(page.locator('#google-sheet-connect')).toBeVisible();
    await expect(page.locator('#google-sheet-open')).toHaveCount(0);
  });

  test('active connection shows open, refresh, and disconnect', async ({ page }) => {
    await seed(page.request, 'active');
    await page.goto(API_KEYS);

    await expect(page.locator(`#google-sheet-open[href="${SHEET_URL}"]`)).toBeVisible();
    await expect(page.locator('#google-sheet-refresh')).toBeVisible();
    await expect(page.locator('#google-sheet-disconnect')).toBeVisible();
    await expect(page.locator('#google-sheet-connect')).toHaveCount(0);
  });

  test('broken connection explains the revoke and offers reconnect', async ({ page }) => {
    await seed(page.request, 'broken', 'invalid_grant');
    await page.goto(API_KEYS);

    await expect(page.locator('#google-sheet-card .ibl-alert--warning')).toContainText('revoked or expired');
    await expect(page.locator('#google-sheet-reconnect')).toBeVisible();
    await expect(page.locator('#google-sheet-refresh')).toHaveCount(0);
  });

  test('refresh without a token is rejected and leaves the row active', async ({ page }) => {
    await seed(page.request, 'active');

    await post(page.request, 'google_refresh', false);
    await page.goto(API_KEYS);

    await expect(page.locator('#apikeys-flash.ibl-alert--error')).toContainText('Invalid or expired form submission');
    expect((await connection(page.request)).body.status).toBe('active');
  });

  test('refresh on a broken row asks to reconnect without calling Google', async ({ page }) => {
    // The broken card has no refresh form, so take the token while the row is
    // active, then break it.
    await seed(page.request, 'active');
    const token = await tokenFor(page.request, 'google_refresh');
    await seed(page.request, 'broken', 'invalid_grant');

    const response = await page.request.post(`${API_KEYS}&op=google_refresh`, {
      form: { _csrf_token: token },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    await page.goto(API_KEYS);

    await expect(page.locator('#apikeys-flash.ibl-alert--error')).toContainText('Reconnect Google');
  });

  test('disconnect on a broken row deletes it without calling Google', async ({ page }) => {
    await seed(page.request, 'broken', 'invalid_grant');

    await post(page.request, 'google_disconnect', true);
    await page.goto(API_KEYS);

    await expect(page.locator('#apikeys-flash.ibl-alert--success')).toContainText('stays in your Drive');
    expect((await connection(page.request)).status).toBe(404);
    await expect(page.locator('#google-sheet-connect')).toBeVisible();
  });

  test('disconnect without a token is rejected and keeps the row', async ({ page }) => {
    await seed(page.request, 'active');

    await post(page.request, 'google_disconnect', false);
    await page.goto(API_KEYS);

    await expect(page.locator('#apikeys-flash.ibl-alert--error')).toContainText('Invalid or expired form submission');
    expect((await connection(page.request)).status).toBe(200);
  });
});
