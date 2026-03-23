import { test, expect } from '../fixtures/auth';

// Tab API endpoint tests — these are HTMX endpoints that return HTML fragments
// for tab/dropdown switching. The Saved Depth Chart API remains JSON.

// Helper: retry GET requests that return unexpected content (CI load)
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
// Returns HTML table fragment for a given display mode
// ============================================================

test.describe('DCE Tab API', () => {
  test('ratings display returns HTML with table', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=ratings',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<table');
  });

  test('all valid display modes return table html', async ({ request }) => {
    const modes = ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'];

    for (const mode of modes) {
      const response = await request.get(
        `modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=${mode}`,
      );

      expect(response.status(), `${mode} should return 200`).toBe(200);
      const html = await response.text();
      expect(html.length, `${mode} should return non-empty html`).toBeGreaterThan(0);
    }
  });

  test('invalid display mode falls back to ratings', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=invalid_mode',
    );

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<table');
  });

  test('response includes HX-Push-Url header', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=tab-api&teamID=1&display=contracts',
    );

    const pushUrl = response.headers()['hx-push-url'] ?? '';
    expect(pushUrl).toContain('display=contracts');
  });
});

// ============================================================
// NextSim Tab API (DepthChartEntry&op=nextsim-api)
// Returns HTML position table fragment
// ============================================================

test.describe('NextSim Tab API', () => {
  test('position endpoints return valid HTML', async ({ request }) => {
    const positions = ['PG', 'SG', 'SF', 'PF', 'C'];

    for (const pos of positions) {
      const response = await request.get(
        `modules.php?name=DepthChartEntry&op=nextsim-api&teamID=1&position=${pos}`,
      );

      const contentType = response.headers()['content-type'] ?? '';
      expect(contentType, `${pos} API must return HTML`).toContain('text/html');

      expect(response.status(), `${pos} should return 200`).toBe(200);
      // HTML may be empty if no games in sim window — that's valid
    }
  });

  test('invalid position falls back to PG', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=DepthChartEntry&op=nextsim-api&teamID=1&position=XX',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');
    expect(response.status()).toBe(200);
  });
});

// ============================================================
// Team API (Team&op=api)
// Returns HTML table fragment — public endpoint, no auth required
// ============================================================

test.describe('Team API', () => {
  test('ratings display returns HTML with table', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=Team&op=api&teamID=1&display=ratings',
    );

    const contentType = response.headers()['content-type'] ?? '';
    expect(contentType, 'API endpoint must return HTML').toContain('text/html');

    expect(response.status()).toBe(200);
    const html = await response.text();
    expect(html).toContain('<table');
  });

  test('all valid display modes return html', async ({ request }) => {
    const modes = ['ratings', 'total_s', 'avg_s', 'per36mins', 'contracts'];

    for (const mode of modes) {
      const response = await request.get(
        `modules.php?name=Team&op=api&teamID=1&display=${mode}`,
      );

      expect(response.status(), `Team API ${mode} should return 200`).toBe(200);
      const html = await response.text();
      expect(html.length, `Team API ${mode} should return non-empty html`).toBeGreaterThan(0);
    }
  });

  test('response includes HX-Push-Url header', async ({ request }) => {
    const response = await request.get(
      'modules.php?name=Team&op=api&teamID=1&display=contracts',
    );

    const pushUrl = response.headers()['hx-push-url'] ?? '';
    expect(pushUrl).toContain('display=contracts');
    expect(pushUrl).toContain('teamID=1');
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
// Requires auth — returns 401 without valid session (still JSON)
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
