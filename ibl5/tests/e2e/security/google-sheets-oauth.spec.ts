import { test as regularTest, expect } from '../fixtures/auth-regular';
import { test as publicTest } from '../fixtures/public';
import type { APIRequestContext } from '@playwright/test';

/**
 * Google sign-in hardening for the ApiKeys page: CSRF on `op=google_start`, the
 * session-bound `state` check on `op=google_callback`, the non-POST guard, and
 * the anonymous gate. PHP makes every Google call server-side, so the browser
 * cannot observe them. These tests prove rejection through the redirect, the flash
 * alert, and the absence of a stored connection row.
 *
 * CI passes placeholder GOOGLE_OAUTH_* / GOOGLE_TOKEN_KEY values so the feature
 * renders as configured. Local runs need the same three variables.
 */

const API_KEYS = 'modules.php?name=ApiKeys';
// The PHP container has no IBL_TEST_USER_REGULAR, so name the user per request.
const USER = `username=${encodeURIComponent(process.env.IBL_TEST_USER_REGULAR ?? '')}`;

async function isConfigured(request: APIRequestContext): Promise<boolean> {
  const html = await (await request.get(API_KEYS)).text();
  return html.includes('id="google-sheet-card"') && !html.includes('sync is not configured');
}

regularTest.describe('Google sign-in (regular user)', () => {
  regularTest.describe.configure({ mode: 'serial' });

  // e2e-hygiene-allow: CI-config env gating — auth-regular.setup.ts also skips when IBL_TEST_USER_REGULAR is unset, so regular.json is absent or stale and these assertions would run against an unauthenticated session
  regularTest.skip(
    !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
    'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
  );

  regularTest.beforeEach(async ({ page }) => {
    expect(await isConfigured(page.request), 'set GOOGLE_OAUTH_CLIENT_ID / GOOGLE_OAUTH_CLIENT_SECRET / GOOGLE_TOKEN_KEY for the PHP server').toBe(true);
    // The connect button only renders with no row for this user.
    await page.request.delete(`test-state.php?action=delete-google-sheet-connection&${USER}`);
  });

  regularTest('google_start with a valid token redirects to Google with state, drive.file scope, consent', async ({ page }) => {
    await page.route('https://accounts.google.com/**', (route) =>
      route.fulfill({ status: 200, contentType: 'text/plain', body: 'stub' }),
    );
    await page.goto(API_KEYS);

    const [googleRequest] = await Promise.all([
      page.waitForRequest((req) => req.url().startsWith('https://accounts.google.com/')),
      page.locator('#google-sheet-connect').click(),
    ]);

    const url = googleRequest.url();
    expect(url).toContain('state=');
    expect(url).toContain('scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fdrive.file');
    expect(url).toContain('prompt=consent');
  });

  regularTest('google_start without a token shows the CSRF error and redirects back to the page', async ({ page }) => {
    const response = await page.request.post(`${API_KEYS}&op=google_start`, {
      form: {},
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toContain('name=ApiKeys');

    await page.goto(API_KEYS);
    await expect(page.locator('#apikeys-flash.ibl-alert--error')).toContainText('Invalid or expired form submission');
  });

  regularTest('google_callback with a bogus state is rejected before any token exchange', async ({ page }) => {
    await page.goto(`${API_KEYS}&op=google_callback&state=bogus&code=x`);

    await expect(page).toHaveURL(/modules\.php\?name=ApiKeys$/);
    await expect(page.locator('#apikeys-flash.ibl-alert--error')).toContainText('expired or invalid');
    const conn = await page.request.get(`test-state.php?action=get-google-sheet-connection&${USER}`);
    expect(conn.status()).toBe(404);
  });

  regularTest('GET op=google_start is redirected back without an alert', async ({ page }) => {
    const response = await page.request.get(`${API_KEYS}&op=google_start`, { maxRedirects: 0 });
    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toContain('name=ApiKeys');
    expect(response.headers()['location']).not.toContain('google');

    await page.goto(API_KEYS);
    await expect(page.locator('#apikeys-flash')).toHaveCount(0);
  });
});

publicTest.describe('Google sign-in (anonymous)', () => {
  publicTest('anonymous google_callback renders the login box and no alert', async ({ page }) => {
    await page.goto(`${API_KEYS}&op=google_callback&state=x&code=y`);

    // loginBox() sends anonymous users to the YourAccount login form.
    await expect(page.locator('#login-username')).toBeVisible();
    await expect(page.locator('#apikeys-flash')).toHaveCount(0);
    await expect(page.locator('#google-sheet-card')).toHaveCount(0);
  });
});
