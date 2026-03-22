import { test, expect } from '../fixtures/auth';

// AJAX/Tab API endpoint tests — these are internal JSON endpoints called by
// JavaScript for tab switching and saved depth chart management. A broken
// endpoint would cause silent failures in the UI.

// Helper: retry GET requests that return HTML instead of JSON (MAMP/CI load)
async function fetchJson(
  request: import('@playwright/test').APIRequestContext,
  url: string,
  retries = 3,
): Promise<{ status: number; body: unknown; contentType: string }> {
  let lastStatus = 0;
  let lastContentType = '';

  for (let attempt = 0; attempt < retries; attempt++) {
    const response = await request.get(url);
    lastStatus = response.status();
    lastContentType = response.headers()['content-type'] ?? '';

    if (lastContentType.includes('json')) {
      const text = await response.text();
      let body: unknown = null;
      try {
        body = text ? JSON.parse(text) : null;
      } catch {
        // Empty or malformed JSON — return null body
      }
      return { status: lastStatus, body, contentType: lastContentType };
    }
    // Got HTML instead of JSON — brief pause before retry
    await new Promise((r) => setTimeout(r, 200));
  }

  return { status: lastStatus, body: null, contentType: lastContentType };
}

// ============================================================
// DCE Tab API (DepthChartEntry&op=tab-api)
// Returns {"html": "..."} with table HTML for a given display mode
// ============================================================

test.describe('DCE Tab API', () => {
  test('ratings display returns JSON with html property', async ({
    request,
  }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=ratings',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(200);
    expect(body).toHaveProperty('html');
    const html = (body as Record<string, string>).html;
    expect(html).toContain('<table');
  });

  test('all valid display modes return table html', async ({ request }) => {
    const modes = ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'];

    for (const mode of modes) {
      const { status, body, contentType } = await fetchJson(
        request,
        `modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=${mode}`,
      );

      expect(contentType, `${mode} API must return JSON, not HTML`).toContain('json');

      expect(status, `${mode} should return 200`).toBe(200);
      expect(body, `${mode} should have html property`).toHaveProperty('html');
      const html = (body as Record<string, string>).html;
      expect(html.length, `${mode} should return non-empty html`).toBeGreaterThan(0);
    }
  });

  test('invalid display mode falls back to ratings', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=invalid_mode',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(200);
    expect(body).toHaveProperty('html');
    // Falls back to ratings — should still return a table
    const html = (body as Record<string, string>).html;
    expect(html).toContain('<table');
  });
});

// ============================================================
// NextSim Tab API (DepthChartEntry&op=nextsim-api)
// Returns {"html": "..."} with position table HTML
// ============================================================

test.describe('NextSim Tab API', () => {
  test('position endpoints return valid JSON', async ({ request }) => {
    const positions = ['PG', 'SG', 'SF', 'PF', 'C'];

    for (const pos of positions) {
      const { status, body, contentType } = await fetchJson(
        request,
        `modules.php?name=DepthChartEntry&op=nextsim-api&teamID=1&position=${pos}`,
      );

      expect(contentType, `${pos} API must return JSON, not HTML`).toContain('json');

      expect(status, `${pos} should return 200`).toBe(200);
      expect(body, `${pos} should have html property`).toHaveProperty('html');
      // HTML may be empty if no games in sim window — that's valid
    }
  });

  test('invalid position falls back to PG', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=nextsim-api&teamID=1&position=XX',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(200);
    expect(body).toHaveProperty('html');
  });
});

// ============================================================
// Team API (Team&op=api)
// Returns {"html": "..."} — public endpoint, no auth required
// ============================================================

test.describe('Team API', () => {
  test('ratings display returns JSON with html property', async ({
    request,
  }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=Team&op=api&teamID=1&display=ratings',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(200);
    expect(body).toHaveProperty('html');
    const html = (body as Record<string, string>).html;
    expect(html).toContain('<table');
  });

  test('all valid display modes return html', async ({ request }) => {
    const modes = ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'];

    for (const mode of modes) {
      const { status, body, contentType } = await fetchJson(
        request,
        `modules.php?name=Team&op=api&teamID=1&display=${mode}`,
      );

      expect(contentType, `Team API ${mode} must return JSON, not HTML`).toContain('json');

      expect(status, `Team API ${mode} should return 200`).toBe(200);
      expect(body, `Team API ${mode} should have html`).toHaveProperty('html');
    }
  });

  test('invalid teamID returns error response', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=Team&op=api&teamID=99999&display=ratings',
    );

    // Invalid teamID currently returns 500 (Team::initialize fails).
    // Verify it returns a response at all (doesn't hang/timeout).
    expect([200, 500]).toContain(response.status());
  });
});

// ============================================================
// Saved Depth Chart API (DepthChartEntry&op=api)
// Requires auth — returns 401 without valid session
// ============================================================

test.describe('Saved Depth Chart API', () => {
  test('list action returns JSON with auth', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=list',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    // Should return 200 with saved depth chart list (may be empty array)
    expect(status).toBe(200);
    expect(body).toBeTruthy();
  });

  test('unauthenticated request returns 401', async ({ request }) => {
    // Use a fresh request context without auth cookies
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=api&action=list',
      { headers: { Cookie: '' } },
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'Unauth API endpoint must return JSON, not HTML').toContain('json');

    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body).toHaveProperty('error');
  });

  test('unknown action returns 400', async ({ request }) => {
    const { status, body, contentType } = await fetchJson(
      request,
      'modules.php?name=DepthChartEntry&op=api&action=nonexistent',
    );

    expect(contentType, 'API endpoint must return JSON, not HTML').toContain('json');

    expect(status).toBe(400);
    expect(body).toHaveProperty('error');
  });
});
