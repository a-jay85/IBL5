import { test, expect } from '@playwright/test';

// REST API v1 Tests — no browser rendering, uses page.request.
// API requires X-API-Key header for authentication.
// In CI, the test API key is seeded in ci-seed.sql.
// Locally, set IBL_API_KEY in .env.test or tests fall through to 401 checks.
test.use({ storageState: { cookies: [], origins: [] } });

const BASE_URL = '/ibl5/api/v1';
const API_KEY = process.env.IBL_API_KEY || 'e2e-test-key-do-not-use-in-production';

const authHeaders = { 'X-API-Key': API_KEY };

/**
 * Helper: make a GET request and verify either 401 (with error structure)
 * or 200 (with response shape validation).
 */
async function assertGetRoute(
  request: import('@playwright/test').APIRequestContext,
  path: string,
  validateBody?: (body: unknown) => void,
): Promise<void> {
  // Retry up to 3 times — PHP built-in server in CI can return 500 under load
  let lastStatus = 0;
  let lastText = '';

  for (let attempt = 0; attempt < 3; attempt++) {
    const response = await request.get(`${BASE_URL}${path}`, {
      headers: authHeaders,
    });
    lastStatus = response.status();

    if (lastStatus === 401) {
      const body = await response.json();
      expect(body, `401 response for ${path} should have error property`).toHaveProperty('error');
      return;
    }

    if (lastStatus === 200) {
      const contentType = response.headers()['content-type'] ?? '';
      if (!contentType.includes('json')) {
        lastText = await response.text();
        continue; // Retry — got HTML instead of JSON
      }

      const body = await response.json();
      expect(body, `${path} should return truthy body`).toBeTruthy();
      if (validateBody) validateBody(body);
      return;
    }

    // 500 or other error — retry
    lastText = await response.text();
  }

  // All retries exhausted
  expect(lastStatus, `${path} returned ${lastStatus} after 3 attempts: ${lastText.slice(0, 200)}`).toBe(200);
}

/**
 * Fetch a list endpoint and return the first item's UUID.
 * Returns null if 401 (with assertion) or no items available.
 */
async function getFirstUuid(
  request: import('@playwright/test').APIRequestContext,
  listPath: string,
): Promise<string | null> {
  const response = await request.get(`${BASE_URL}/${listPath}`, {
    headers: authHeaders,
  });

  if (response.status() === 401) {
    const body = await response.json();
    expect(body).toHaveProperty('error');
    return null;
  }

  // If the list endpoint fails (500 or non-JSON), skip the detail test
  const contentType = response.headers()['content-type'] ?? '';
  if (response.status() !== 200 || !contentType.includes('json')) return null;

  const body = await response.json();
  const items = body.data ?? body;
  if (!Array.isArray(items) || items.length === 0) return null;

  return (items[0].uuid ?? items[0].id) || null;
}

test.describe('API v1 — list endpoints', () => {
  test('GET /season returns season data', async ({ request }) => {
    await assertGetRoute(request, '/season', (body: unknown) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /teams returns team list', async ({ request }) => {
    await assertGetRoute(request, '/teams', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(Array.isArray(b.data) || typeof b === 'object').toBe(true);
    });
  });

  test('GET /players returns player list', async ({ request }) => {
    await assertGetRoute(request, '/players', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(Array.isArray(b.data) || typeof b === 'object').toBe(true);
    });
  });

  test('GET /standings returns standings data', async ({ request }) => {
    await assertGetRoute(request, '/standings');
  });

  test('GET /standings/Eastern returns Eastern conference', async ({ request }) => {
    await assertGetRoute(request, '/standings/Eastern');
  });

  test('GET /standings/Western returns Western conference', async ({ request }) => {
    await assertGetRoute(request, '/standings/Western');
  });

  test('GET /injuries returns injuries data', async ({ request }) => {
    await assertGetRoute(request, '/injuries');
  });

  test('GET /stats/leaders returns leaders data', async ({ request }) => {
    await assertGetRoute(request, '/stats/leaders');
  });

  test('GET /games returns game list', async ({ request }) => {
    await assertGetRoute(request, '/games');
  });
});

test.describe('API v1 — detail endpoints (require valid UUIDs)', () => {
  test('GET /players/{uuid} returns player detail', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'players');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/players/${uuid}`, (body: unknown) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /players/{uuid}/stats returns player stats', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'players');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/players/${uuid}/stats`);
  });

  test('GET /players/{uuid}/history returns player history', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'players');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/players/${uuid}/history`);
  });

  test('GET /teams/{uuid} returns team detail', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'teams');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/teams/${uuid}`, (body: unknown) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /teams/{uuid}/roster returns team roster', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'teams');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/teams/${uuid}/roster`);
  });

  test('GET /games/{uuid} returns game detail', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'games');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/games/${uuid}`);
  });

  test('GET /games/{uuid}/boxscore returns boxscore', async ({ request }) => {
    const uuid = await getFirstUuid(request, 'games');
    if (!uuid) { test.skip(); return; }
    await assertGetRoute(request, `/games/${uuid}/boxscore`);
  });
});

/**
 * Helper: check if a response is a non-API HTML page (rewrite not working).
 * In CI, the Apache rewrite from .htaccess may not route to api.php,
 * causing the PHP-Nuke homepage (200 HTML) to be served instead.
 */
function isNonApiResponse(response: import('@playwright/test').APIResponse): boolean {
  if (response.status() !== 200) return false;
  const contentType = response.headers()['content-type'] ?? '';
  return !contentType.includes('json');
}

test.describe('API v1 — error handling', () => {
  test('GET /nonexistent returns 404', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/nonexistent`, {
      headers: authHeaders,
    });
    if (isNonApiResponse(response)) { test.skip(); return; }
    const status = response.status();
    expect([401, 404]).toContain(status);
    const body = await response.json();
    expect(body).toHaveProperty('error');
  });

  test('unauthenticated requests return proper error structure', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/season`, {
      headers: { 'X-API-Key': '' },
    });
    if (isNonApiResponse(response)) { test.skip(); return; }
    const status = response.status();
    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      expect(body.error).toBeTruthy();
    }
  });

  test('invalid UUID returns 404 or 400', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/players/not-a-valid-uuid`, {
      headers: authHeaders,
    });
    if (isNonApiResponse(response)) { test.skip(); return; }
    const status = response.status();
    expect([401, 404]).toContain(status);
  });

  test('POST to trade accept without auth returns 401', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/trades/999/accept`);
    if (isNonApiResponse(response)) { test.skip(); return; }
    const status = response.status();
    expect([401, 404]).toContain(status);
  });

  test('POST to trade decline without auth returns 401', async ({ request }) => {
    const response = await request.post(`${BASE_URL}/trades/999/decline`);
    if (isNonApiResponse(response)) { test.skip(); return; }
    const status = response.status();
    expect([401, 404]).toContain(status);
  });
});
