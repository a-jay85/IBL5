import { randomBytes } from 'node:crypto';
import type { APIRequestContext } from '@playwright/test';
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

/**
 * Fetch a fresh, valid `_csrf_token` from a GET-rendered form on `path`.
 *
 * A page can render several CSRF tokens (each gate has its own per-action
 * token — e.g. the admin DebugMenu toggle in the page chrome). Pass `formName`
 * to scope extraction to a specific `<form name="...">`; the token for that
 * form is the first `_csrf_token` after the form's opening tag.
 *
 * Without `formName`, the page must contain exactly one token — if it has
 * more, extraction is ambiguous (the first match may be the chrome's token,
 * not the form under test) and this throws rather than silently picking one.
 */
async function fetchToken(
  request: APIRequestContext,
  path: string,
  formName?: string,
): Promise<string> {
  const resp = await request.get(path);
  if (!resp.ok()) {
    throw new Error(`token fetch GET ${path} failed: ${resp.status()}`);
  }
  let body = await resp.text();
  if (formName === undefined) {
    const tokenCount = (
      body.match(/name="_csrf_token" value="[0-9a-f]+"/g) ?? []
    ).length;
    if (tokenCount > 1) {
      throw new Error(
        `ambiguous _csrf_token on ${path}: ${tokenCount} tokens found — ` +
          `pass a formName to scope extraction to the form under test`,
      );
    }
  } else {
    const formIdx = body.indexOf(`name="${formName}"`);
    if (formIdx === -1) {
      throw new Error(`form name="${formName}" not found on ${path}`);
    }
    body = body.slice(formIdx);
  }
  const match = body.match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error(
      `no _csrf_token found on ${path}` +
        (formName !== undefined ? ` in form "${formName}"` : ''),
    );
  }
  return match[1];
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

  // ── leagueControlPanel.php (lcp_update_all token gate) ──────────────────

  test('leagueControlPanel.php with forged token → ?error= CSRF redirect, no dispatch', async ({
    request,
  }) => {
    const response = await request.post('leagueControlPanel.php', {
      form: {
        _csrf_token: forgedToken(),
        action: 'set_season_phase',
        SeasonPhase: 'Regular Season',
      },
      maxRedirects: 0,
    });
    expect([301, 302, 303]).toContain(response.status());
    const location = response.headers()['location'] ?? '';
    // CSRF-specific: redirected to ?error= carrying the CSRF message, BEFORE
    // dispatch — so the season-phase row is never written. NOT ?success=.
    expect(location).toContain('error=');
    expect(decodeURIComponent(location)).toContain(
      'Invalid or expired form submission',
    );
    expect(location).not.toContain('success=');
  });

  // V2: valid-token positive. DELIBERATELY uses an UNKNOWN action (non-mutating)
  // rather than the plan's literal set_season_phase — writing 'Current Season
  // Phase' here would make this file a NEW cross-file mutator of that global row
  // and reintroduce the #910 race that league-control-panel.spec.ts is engineered
  // to contain (this spec's header commits to no global mutation). A valid token
  // that reaches dispatch yields the dispatch 'Unknown action' message, NOT the
  // CSRF message — positive proof the gate accepted the token and dispatch ran.
  test('leagueControlPanel.php with valid token → passes gate, reaches dispatch', async ({
    request,
  }) => {
    const token = await fetchToken(request, 'leagueControlPanel.php');
    const response = await request.post('leagueControlPanel.php', {
      form: { _csrf_token: token, action: '__csrf_probe__' },
      maxRedirects: 0,
    });
    expect([301, 302, 303]).toContain(response.status());
    const location = decodeURIComponent(response.headers()['location'] ?? '');
    expect(location).toContain('Unknown action');
    expect(location).not.toContain('Invalid or expired form submission');
  });

  // ── import-demands.php (import_demands token gate) ──────────────────────

  test('import-demands.php with forged token + file → CSRF error, no truncate/insert', async ({
    request,
  }) => {
    const response = await request.post('import-demands.php', {
      multipart: {
        _csrf_token: forgedToken(),
        demands_csv: {
          name: 'demands.csv',
          mimeType: 'text/csv',
          buffer: Buffer.from('name,dem1,dem2,dem3,dem4,dem5,dem6\nTest Player,1,1,1,1,1,1\n'),
        },
      },
    });
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    // Rejection precedes the truncate+insert, so the message itself proves
    // ibl_demands was not touched.
    expect(html).toContain('Invalid or expired form submission');
    expect(html).not.toContain('Successfully imported');
  });

  // ── modules.php?name=OneOnOneGame (one_on_one token gate) ───────────────

  test('OneOnOneGame play with forged token → CSRF error, no game played', async ({
    request,
  }) => {
    const response = await request.post('modules.php?name=OneOnOneGame', {
      form: { _csrf_token: forgedToken(), pid1: '1', pid2: '2' },
    });
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    // Gate precedes playGame()'s INSERT into ibl_one_on_one, so no GAME ID:
    // result block renders and no row is inserted.
    expect(html).toContain('Invalid or expired form submission');
    expect(html).not.toContain('GAME ID:');
  });

  test('OneOnOneGame play with valid token → game played (GAME ID: block)', async ({
    request,
  }) => {
    const token = await fetchToken(
      request,
      'modules.php?name=OneOnOneGame',
      'OneOnOneGame',
    );
    const response = await request.post('modules.php?name=OneOnOneGame', {
      form: { _csrf_token: token, pid1: '1', pid2: '2' },
    });
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    expect(html).toContain('GAME ID:');
    expect(html).not.toContain('Invalid or expired form submission');
  });
});
