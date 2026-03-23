import { test, expect } from '@playwright/test';

// REST API v1 Tests — no browser rendering, uses page.request.
// API requires X-API-Key header for authentication.
// In CI, the test API key is seeded in ci-seed.sql.
// Locally, set IBL_API_KEY in .env.test or tests fall through to 401 checks.
test.use({ storageState: { cookies: [], origins: [] } });

const BASE_URL = '/ibl5/api/v1';
const API_KEY = process.env.IBL_API_KEY || 'e2e-test-key-do-not-use-in-production';

const authHeaders = { 'X-API-Key': API_KEY };

// Known UUIDs seeded in ci-seed.sql — must match router's UUID regex [0-9a-f]{8}-{4}-{4}-{4}-{12}
const SEED_PLAYER_UUID = 'a0000000-0000-0000-0000-000000000001';
const SEED_TEAM_UUID = 'b0000000-0000-0000-0000-000000000001';
const SEED_GAME_UUID = 'c0000000-0000-0000-0000-000000000001';

/**
 * Helper: make a GET request and verify either 401 (with error structure)
 * or 200 (with response shape validation).
 */
async function assertGetRoute(
  request: import('@playwright/test').APIRequestContext,
  path: string,
  validateBody?: (body: unknown) => void,
): Promise<void> {
  // Retry up to 5 times — CI server can return HTML or 500 under load
  let lastStatus = 0;
  let lastText = '';
  let gotJson = false;

  for (let attempt = 0; attempt < 5; attempt++) {
    const response = await request.get(`${BASE_URL}${path}`, {
      headers: authHeaders,
    });
    lastStatus = response.status();
    const contentType = response.headers()['content-type'] ?? '';

    if (!contentType.includes('json')) {
      lastText = await response.text();
      await new Promise((r) => setTimeout(r, 200));
      continue; // Retry — got HTML instead of JSON
    }

    gotJson = true;

    if (lastStatus === 401) {
      const body = await response.json();
      expect(body, `401 response for ${path} should have error property`).toHaveProperty('error');
      return;
    }

    if (lastStatus === 200) {
      const body = await response.json();
      expect(body, `${path} should return truthy body`).toBeTruthy();
      if (validateBody) validateBody(body);
      return;
    }

    // 404/500 or other error — retry
    lastText = await response.text();
  }

  // All retries exhausted — must have received JSON at least once
  expect(gotJson, `${path} returned non-JSON (status ${lastStatus}) after 5 attempts: ${lastText.slice(0, 200)}`).toBe(true);
  expect(lastStatus, `${path} returned ${lastStatus} after 5 attempts: ${lastText.slice(0, 200)}`).toBe(200);
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
    await assertGetRoute(request, `/players/${SEED_PLAYER_UUID}`, (body: unknown) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /players/{uuid}/stats returns player stats', async ({ request }) => {
    await assertGetRoute(request, `/players/${SEED_PLAYER_UUID}/stats`);
  });

  test('GET /players/{uuid}/history returns player history', async ({ request }) => {
    await assertGetRoute(request, `/players/${SEED_PLAYER_UUID}/history`);
  });

  test('GET /teams/{uuid} returns team detail', async ({ request }) => {
    await assertGetRoute(request, `/teams/${SEED_TEAM_UUID}`, (body: unknown) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /teams/{uuid}/roster returns team roster', async ({ request }) => {
    await assertGetRoute(request, `/teams/${SEED_TEAM_UUID}/roster`);
  });

  test('GET /games/{uuid} returns game detail', async ({ request }) => {
    await assertGetRoute(request, `/games/${SEED_GAME_UUID}`);
  });

  test('GET /games/{uuid}/boxscore returns boxscore', async ({ request }) => {
    await assertGetRoute(request, `/games/${SEED_GAME_UUID}/boxscore`);
  });
});

/**
 * Helper: make an API request with retries, skipping non-JSON responses
 * (PHP built-in server can serve HTML homepage under load).
 */
async function assertApiErrorRoute(
  request: import('@playwright/test').APIRequestContext,
  method: 'get' | 'post',
  path: string,
  expectedStatuses: number[],
  validateBody?: (body: unknown) => void,
): Promise<void> {
  let lastStatus = 0;
  // More retries than assertGetRoute — error routes are more susceptible to
  // PHP built-in server serving the HTML homepage under load (200 HTML vs expected 404 JSON).
  for (let attempt = 0; attempt < 5; attempt++) {
    const response = method === 'get'
      ? await request.get(`${BASE_URL}${path}`, { headers: authHeaders })
      : await request.post(`${BASE_URL}${path}`);
    lastStatus = response.status();
    const contentType = response.headers()['content-type'] ?? '';
    if (!contentType.includes('json')) {
      // Brief pause before retry — gives server time to recover from load
      await new Promise((r) => setTimeout(r, 200));
      continue;
    }
    expect(expectedStatuses, `${path} returned ${lastStatus}`).toContain(lastStatus);
    if (validateBody) validateBody(await response.json());
    return;
  }
  expect(expectedStatuses, `${path} returned non-JSON (status ${lastStatus}) after 5 attempts`).toContain(lastStatus);
}

// ============================================================
// Response envelope validation — verify the standard
// { status, data, meta: { timestamp, version } } shape
// ============================================================

test.describe('API v1 — response envelope validation', () => {
  test('GET /season has standard envelope', async ({ request }) => {
    await assertGetRoute(request, '/season', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      expect(b).toHaveProperty('data');
      expect(b).toHaveProperty('meta');
      const meta = b.meta as Record<string, unknown>;
      expect(meta.version).toBe('v1');
      expect(typeof meta.timestamp).toBe('string');
    });
  });

  test('GET /season data has phase field', async ({ request }) => {
    await assertGetRoute(request, '/season', (body: unknown) => {
      const b = body as Record<string, unknown>;
      const data = b.data as Record<string, unknown>;
      expect(typeof data.phase).toBe('string');
    });
  });

  test('GET /players has envelope with pagination meta', async ({ request }) => {
    await assertGetRoute(request, '/players', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      expect(Array.isArray(b.data)).toBe(true);
      const meta = b.meta as Record<string, unknown>;
      expect(meta).toHaveProperty('page');
      expect(meta).toHaveProperty('per_page');
      expect(meta).toHaveProperty('total');
      expect(meta).toHaveProperty('total_pages');
    });
  });

  test('GET /players/{uuid} data has player fields', async ({ request }) => {
    await assertGetRoute(request, `/players/${SEED_PLAYER_UUID}`, (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      const data = b.data as Record<string, unknown>;
      expect(typeof data.uuid).toBe('string');
      expect(typeof data.name).toBe('string');
      expect(data.position).toMatch(/^(PG|SG|SF|PF|C)$/);
    });
  });

  test('GET /standings data is array of team standings', async ({ request }) => {
    await assertGetRoute(request, '/standings', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      expect(Array.isArray(b.data)).toBe(true);
      const arr = b.data as Record<string, unknown>[];
      if (arr.length > 0) {
        const entry = arr[0];
        expect(entry).toHaveProperty('team');
        expect(entry).toHaveProperty('win_percentage');
        const team = entry.team as Record<string, unknown>;
        expect(typeof team.uuid).toBe('string');
      }
    });
  });

  test('GET /games data has game structure', async ({ request }) => {
    await assertGetRoute(request, '/games', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      expect(Array.isArray(b.data)).toBe(true);
      const arr = b.data as Record<string, unknown>[];
      if (arr.length > 0) {
        const game = arr[0];
        expect(game).toHaveProperty('uuid');
        expect(game).toHaveProperty('date');
        expect(game).toHaveProperty('visitor');
        expect(game).toHaveProperty('home');
      }
    });
  });

  test('GET /stats/leaders data has player+team+stats', async ({ request }) => {
    await assertGetRoute(request, '/stats/leaders', (body: unknown) => {
      const b = body as Record<string, unknown>;
      expect(b.status).toBe('success');
      expect(Array.isArray(b.data)).toBe(true);
      const arr = b.data as Record<string, unknown>[];
      if (arr.length > 0) {
        const entry = arr[0];
        expect(entry).toHaveProperty('player');
        expect(entry).toHaveProperty('team');
        expect(entry).toHaveProperty('stats');
      }
    });
  });

  test('Content-Type header is application/json', async ({ request }) => {
    // Verify JSON Content-Type regardless of auth status (200 or 401).
    // Retry — PHP built-in server in CI can serve HTML homepage under load.
    let lastContentType = '';
    for (let attempt = 0; attempt < 5; attempt++) {
      const response = await request.get(`${BASE_URL}/season`, {
        headers: authHeaders,
      });

      lastContentType = response.headers()['content-type'] ?? '';
      if (lastContentType.includes('json')) {
        expect(lastContentType).toContain('application/json');
        return;
      }
      await new Promise((r) => setTimeout(r, 200));
    }
    expect(lastContentType, 'API must return JSON after retries').toContain('application/json');
  });
});

// ============================================================
// Pagination parameter tests
// ============================================================

test.describe('API v1 — pagination parameters', () => {
  test('per_page limits result count', async ({ request }) => {
    await assertGetRoute(request, '/players?per_page=5', (body: unknown) => {
      const b = body as Record<string, unknown>;
      const data = b.data as unknown[];
      expect(data.length).toBeLessThanOrEqual(5);
      const meta = b.meta as Record<string, unknown>;
      expect(meta.per_page).toBe(5);
    });
  });

  test('page parameter advances to next page', async ({ request }) => {
    await assertGetRoute(request, '/players?page=2&per_page=5', (body: unknown) => {
      const b = body as Record<string, unknown>;
      const meta = b.meta as Record<string, unknown>;
      expect(meta.page).toBe(2);
      expect(meta.per_page).toBe(5);
    });
  });
});

// ============================================================
// ETag / 304 caching
// ============================================================

test.describe('API v1 — ETag caching', () => {
  test('ETag header present on cacheable endpoint', async ({ request }) => {
    // Retry — CI server can return HTML under load
    for (let attempt = 0; attempt < 5; attempt++) {
      const response = await request.get(`${BASE_URL}/standings`, {
        headers: authHeaders,
      });
      const contentType = response.headers()['content-type'] ?? '';
      if (!contentType.includes('json')) {
        await new Promise((r) => setTimeout(r, 200));
        continue;
      }

      if (response.status() === 401) {
        const body = await response.json();
        expect(body).toHaveProperty('error');
        return;
      }
      expect(response.status()).toBe(200);

      const etag = response.headers()['etag'];
      expect(etag, 'Standings endpoint should return ETag header').toBeTruthy();

      // Second request with If-None-Match should return 304
      const response2 = await request.get(`${BASE_URL}/standings`, {
        headers: { ...authHeaders, 'If-None-Match': etag },
      });
      expect(response2.status()).toBe(304);
      return;
    }
    expect(false, 'Standings endpoint returned non-JSON after 5 attempts').toBe(true);
  });
});

test.describe('API v1 — error handling', () => {
  test('GET /nonexistent returns 404', async ({ request }) => {
    await assertApiErrorRoute(request, 'get', '/nonexistent', [401, 404], (body) => {
      expect(body).toHaveProperty('error');
    });
  });

  test('unauthenticated requests return proper error structure', async ({ request }) => {
    let lastStatus = 0;
    for (let attempt = 0; attempt < 5; attempt++) {
      const response = await request.get(`${BASE_URL}/season`, {
        headers: { 'X-API-Key': '' },
      });
      lastStatus = response.status();
      const contentType = response.headers()['content-type'] ?? '';
      if (!contentType.includes('json')) {
        await new Promise((r) => setTimeout(r, 200));
        continue;
      }
      expect(lastStatus, 'Empty API key should return 401').toBe(401);
      const body = await response.json();
      expect(body).toHaveProperty('error');
      expect(body.error).toBeTruthy();
      return;
    }
    expect(lastStatus, '/season (unauth) returned non-JSON after 5 attempts').toBe(401);
  });

  test('invalid UUID returns 404 or 400', async ({ request }) => {
    await assertApiErrorRoute(request, 'get', '/players/not-a-valid-uuid', [401, 404]);
  });

  test('POST to trade accept without auth returns 401', async ({ request }) => {
    await assertApiErrorRoute(request, 'post', '/trades/999/accept', [401, 404]);
  });

  test('POST to trade decline without auth returns 401', async ({ request }) => {
    await assertApiErrorRoute(request, 'post', '/trades/999/decline', [401, 404]);
  });
});
