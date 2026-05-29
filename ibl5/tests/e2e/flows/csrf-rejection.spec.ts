import { randomBytes } from 'node:crypto';
import { test, expect } from '../fixtures/auth';

/**
 * Forged-CSRF-token rejection — the negative security path the rest of the
 * suite misses. Every other CSRF test submits a MISSING/empty token; here we
 * submit a syntactically valid but WRONG 64-hex token, exercising the
 * valid-format-but-incorrect-token branch of CsrfGuard::validateToken().
 *
 * Each protected endpoint validates the token BEFORE any DB mutation, so the
 * endpoint-specific rejection signal (error message / redirect away from the
 * success page) is itself proof that no mutation occurred — no read-back needed.
 *
 * This spec deliberately does NOT seed/reset ibl_fa_offers: the forged token is
 * rejected before the clear_offers handler runs, so the table state is
 * irrelevant. Touching the global table here would race against the FA offer
 * submission spec under fullyParallel sharding (see free-agency-submission.spec.ts).
 *
 * Admin (auth) fixture: all four endpoints are GM/admin actions.
 */

/** A random 64-hex token — correct format, wrong value. */
function forgedToken(): string {
  return randomBytes(32).toString('hex');
}

test.describe('Forged CSRF token is rejected', () => {
  test.describe.configure({ mode: 'serial' });

  test('block.php clear_offers with forged token → rejected, no mutation', async ({
    request,
  }) => {
    const response = await request.post('block.php', {
      form: { _csrf_token: forgedToken(), action: 'clear_offers' },
    });
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    // CsrfGuard rejects before the clear_offers handler runs. This rejection
    // signal is itself proof no offers were cleared.
    expect(html).toContain('Security validation failed');
  });

  test('maketradeoffer.php with forged token → redirected to Trading error, no offer sent', async ({
    request,
  }) => {
    const response = await request.post(
      '/ibl5/modules/Trading/maketradeoffer.php',
      {
        form: {
          _csrf_token: forgedToken(),
          offeringTeam: 'Metros',
          listeningTeam: 'Knights',
        },
        maxRedirects: 0,
      },
    );
    expect([301, 302, 303]).toContain(response.status());
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('name=Trading');
    // CSRF-specific: redirected to the "Invalid or expired form submission"
    // error, NOT the offer_sent success page.
    expect(location).toContain('Invalid');
    expect(location).not.toContain('offer_sent');
  });

  test('extension.php with forged token → redirected to index.php, contract untouched', async ({
    request,
  }) => {
    const response = await request.post(
      '/ibl5/modules/Player/extension.php',
      {
        form: {
          _csrf_token: forgedToken(),
          teamName: 'Metros',
          playerID: '30',
        },
        maxRedirects: 0,
      },
    );
    expect([301, 302, 303]).toContain(response.status());
    // extension.php redirects to /ibl5/index.php on CSRF failure, BEFORE any
    // ibl_plr write — distinct from the Team contracts result page a real
    // submission lands on.
    expect(response.headers()['location']).toBe('/ibl5/index.php');
  });

  test('ApiKeys generate with forged token → no key generated', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=ApiKeys&op=generate',
      { form: { _csrf_token: forgedToken() } },
    );
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    expect(html).not.toContain('API Key Generated');
    expect(html).toContain('Invalid or expired form submission');
  });
});
