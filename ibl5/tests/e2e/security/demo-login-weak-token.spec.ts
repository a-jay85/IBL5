import { test, expect } from '../fixtures/public';

/**
 * Negative security path for the demo-login magic link (matrix #4).
 *
 * demo-login.php now fails closed via Auth\DemoLoginGate: when the configured
 * DEMO_LOGIN_TOKEN is unset, empty, or the known-weak literal 'demo', the
 * endpoint rejects every request with HTTP 403 and establishes NO session.
 *
 * The CI/e2e environment intentionally configures no strong token, so the
 * weak/disabled state is what we exercise here. The positive path (a valid
 * configured token establishing a session) cannot share the same container
 * config — a 403 requires a weak/empty server token while a successful login
 * requires a strong one — so matrix #5 is covered by DemoLoginGateTest
 * (unit-level), per the plan's footnote.
 *
 * Uses the public (unauthenticated) fixture: the token check runs before any
 * session logic, so a 403 here proves no demo session was created.
 */
test.describe('demo-login fails closed on weak/empty token', () => {
  test('?token=demo is rejected with 403, no session, no redirect', async ({ request }) => {
    const response = await request.get('demo-login.php', {
      params: { token: 'demo' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(403);
    // No redirect to the authenticated landing page.
    expect(response.headers()['location'] ?? '').toBe('');
    // No auth/demo session cookie was established.
    const setCookie = response.headers()['set-cookie'] ?? '';
    expect(setCookie).not.toContain('auth_username');
  });

  test('missing token is rejected with 403', async ({ request }) => {
    const response = await request.get('demo-login.php', { maxRedirects: 0 });
    expect(response.status()).toBe(403);
  });
});
