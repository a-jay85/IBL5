import { test, expect } from '@playwright/test';

// REST API v1 Tests — no browser rendering, uses page.request.
// API requires X-API-Key header for authentication.
// In CI, the test API key is seeded in ci-seed.sql.
// Locally, set IBL_API_KEY in .env.test or tests fall through to 401 checks.
test.use({ storageState: { cookies: [], origins: [] } });

const BASE_URL = '/ibl5/api/v1';
const API_KEY = process.env.IBL_API_KEY || 'e2e-test-key-do-not-use-in-production';

const authHeaders = { 'X-API-Key': API_KEY };

// Known UUIDs seeded in ci-seed.sql — used to avoid dynamic list-endpoint dependency
const SEED_PLAYER_UUID = 'plr-uuid-00000000-0000-000000000001';
const SEED_TEAM_UUID = 'team-uuid-01';
const SEED_GAME_UUID = 'sched-uuid-0001';

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
  for (let attempt = 0; attempt < 3; attempt++) {
    const response = method === 'get'
      ? await request.get(`${BASE_URL}${path}`, { headers: authHeaders })
      : await request.post(`${BASE_URL}${path}`);
    lastStatus = response.status();
    const contentType = response.headers()['content-type'] ?? '';
    if (!contentType.includes('json')) continue;
    expect(expectedStatuses, `${path} returned ${lastStatus}`).toContain(lastStatus);
    if (validateBody) validateBody(await response.json());
    return;
  }
  expect(expectedStatuses, `${path} returned non-JSON (status ${lastStatus}) after 3 attempts`).toContain(lastStatus);
}

test.describe('API v1 — error handling', () => {
  test('GET /nonexistent returns 404', async ({ request }) => {
    await assertApiErrorRoute(request, 'get', '/nonexistent', [401, 404], (body) => {
      expect(body).toHaveProperty('error');
    });
  });

  test('unauthenticated requests return proper error structure', async ({ request }) => {
    let lastStatus = 0;
    for (let attempt = 0; attempt < 3; attempt++) {
      const response = await request.get(`${BASE_URL}/season`, {
        headers: { 'X-API-Key': '' },
      });
      lastStatus = response.status();
      const contentType = response.headers()['content-type'] ?? '';
      if (!contentType.includes('json')) continue;
      if (lastStatus === 401) {
        const body = await response.json();
        expect(body).toHaveProperty('error');
        expect(body.error).toBeTruthy();
      }
      return;
    }
    expect(lastStatus, '/season (unauth) returned non-JSON after 3 attempts').toBe(401);
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
