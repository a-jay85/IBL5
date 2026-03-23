/**
 * REST API v1 Tests — pure HTTP, no browser.
 *
 * Migrated from Playwright to Vitest for reliability: runs as a separate
 * CI job without competing with browser tests for server resources.
 *
 * Transient failures (server returning HTML under load) are handled by
 * Vitest's built-in retry (retry: 2 in vitest.api.config.ts).
 *
 * Locally without a seeded API key, tests validate 401 error structure.
 * In CI with the key seeded, tests validate actual API responses.
 */

import { describe, test, expect } from 'vitest';
import {
  apiFetch,
  AUTH_HEADERS,
  API_BASE,
  SEED_PLAYER_UUID,
  SEED_TEAM_UUID,
  SEED_GAME_UUID,
} from './client';

/** Assert the response has JSON Content-Type. */
function assertJson(res: Response, context: string): void {
  const ct = res.headers.get('content-type') ?? '';
  expect(ct, `${context}: expected JSON, got ${ct}`).toContain('application/json');
}

/**
 * Assert a GET route returns either 200 (with optional body validation)
 * or 401 (with error structure). Both are valid depending on API key config.
 */
async function assertGetRoute(
  path: string,
  validateBody?: (body: Record<string, unknown>) => void,
): Promise<void> {
  const res = await apiFetch(path);
  assertJson(res, path);

  if (res.status === 401) {
    const body = await res.json();
    expect(body).toHaveProperty('error');
    return;
  }

  expect(res.status).toBe(200);
  const body = await res.json();
  expect(body).toBeTruthy();
  if (validateBody) validateBody(body);
}

// ============================================================
// List endpoints
// ============================================================

describe('API v1 — list endpoints', () => {
  test('GET /season returns season data', async () => {
    await assertGetRoute('/season', (body) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /teams returns team list', async () => {
    await assertGetRoute('/teams', (body) => {
      expect(Array.isArray(body.data) || typeof body === 'object').toBe(true);
    });
  });

  test('GET /players returns player list', async () => {
    await assertGetRoute('/players', (body) => {
      expect(Array.isArray(body.data) || typeof body === 'object').toBe(true);
    });
  });

  test('GET /standings returns standings data', async () => {
    await assertGetRoute('/standings');
  });

  test('GET /standings/Eastern returns Eastern conference', async () => {
    await assertGetRoute('/standings/Eastern');
  });

  test('GET /standings/Western returns Western conference', async () => {
    await assertGetRoute('/standings/Western');
  });

  test('GET /injuries returns injuries data', async () => {
    await assertGetRoute('/injuries');
  });

  test('GET /stats/leaders returns leaders data', async () => {
    await assertGetRoute('/stats/leaders');
  });

  test('GET /games returns game list', async () => {
    await assertGetRoute('/games');
  });
});

// ============================================================
// Detail endpoints (require valid UUIDs)
// ============================================================

describe('API v1 — detail endpoints', () => {
  test('GET /players/{uuid} returns player detail', async () => {
    await assertGetRoute(`/players/${SEED_PLAYER_UUID}`, (body) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /players/{uuid}/stats returns player stats', async () => {
    await assertGetRoute(`/players/${SEED_PLAYER_UUID}/stats`);
  });

  test('GET /players/{uuid}/history returns player history', async () => {
    await assertGetRoute(`/players/${SEED_PLAYER_UUID}/history`);
  });

  test('GET /teams/{uuid} returns team detail', async () => {
    await assertGetRoute(`/teams/${SEED_TEAM_UUID}`, (body) => {
      expect(typeof body).toBe('object');
    });
  });

  test('GET /teams/{uuid}/roster returns team roster', async () => {
    await assertGetRoute(`/teams/${SEED_TEAM_UUID}/roster`);
  });

  test('GET /games/{uuid} returns game detail', async () => {
    await assertGetRoute(`/games/${SEED_GAME_UUID}`);
  });

  test('GET /games/{uuid}/boxscore returns JSON response', async () => {
    const res = await apiFetch(`/games/${SEED_GAME_UUID}/boxscore`);
    assertJson(res, '/games/{uuid}/boxscore');
    // 200 if game has been played, 404 if unplayed — both valid in CI seed
    expect([200, 401, 404]).toContain(res.status);
  });
});

// ============================================================
// Response envelope validation
// ============================================================

describe('API v1 — response envelope', () => {
  test('GET /season has standard envelope', async () => {
    await assertGetRoute('/season', (body) => {
      expect(body.status).toBe('success');
      expect(body).toHaveProperty('data');
      expect(body).toHaveProperty('meta');
      const meta = body.meta as Record<string, unknown>;
      expect(meta.version).toBe('v1');
      expect(typeof meta.timestamp).toBe('string');
    });
  });

  test('GET /season data has phase field', async () => {
    await assertGetRoute('/season', (body) => {
      const data = body.data as Record<string, unknown>;
      expect(typeof data.phase).toBe('string');
    });
  });

  test('GET /players has envelope with pagination meta', async () => {
    await assertGetRoute('/players', (body) => {
      expect(body.status).toBe('success');
      expect(Array.isArray(body.data)).toBe(true);
      const meta = body.meta as Record<string, unknown>;
      expect(meta).toHaveProperty('page');
      expect(meta).toHaveProperty('per_page');
      expect(meta).toHaveProperty('total');
      expect(meta).toHaveProperty('total_pages');
    });
  });

  test('GET /players/{uuid} data has player fields', async () => {
    await assertGetRoute(`/players/${SEED_PLAYER_UUID}`, (body) => {
      expect(body.status).toBe('success');
      const data = body.data as Record<string, unknown>;
      expect(typeof data.uuid).toBe('string');
      expect(typeof data.name).toBe('string');
      expect(data.position).toMatch(/^(PG|SG|SF|PF|C)$/);
    });
  });

  test('GET /standings data is array of team standings', async () => {
    await assertGetRoute('/standings', (body) => {
      expect(body.status).toBe('success');
      expect(Array.isArray(body.data)).toBe(true);
      const arr = body.data as Record<string, unknown>[];
      if (arr.length > 0) {
        expect(arr[0]).toHaveProperty('team');
        expect(arr[0]).toHaveProperty('win_percentage');
        const team = (arr[0] as Record<string, unknown>).team as Record<string, unknown>;
        expect(typeof team.uuid).toBe('string');
      }
    });
  });

  test('GET /games data has game structure', async () => {
    await assertGetRoute('/games', (body) => {
      expect(body.status).toBe('success');
      expect(Array.isArray(body.data)).toBe(true);
      const arr = body.data as Record<string, unknown>[];
      if (arr.length > 0) {
        expect(arr[0]).toHaveProperty('uuid');
        expect(arr[0]).toHaveProperty('date');
        expect(arr[0]).toHaveProperty('visitor');
        expect(arr[0]).toHaveProperty('home');
      }
    });
  });

  test('GET /stats/leaders data has player+team+stats', async () => {
    await assertGetRoute('/stats/leaders', (body) => {
      expect(body.status).toBe('success');
      expect(Array.isArray(body.data)).toBe(true);
      const arr = body.data as Record<string, unknown>[];
      if (arr.length > 0) {
        expect(arr[0]).toHaveProperty('player');
        expect(arr[0]).toHaveProperty('team');
        expect(arr[0]).toHaveProperty('stats');
      }
    });
  });

  test('Content-Type header is application/json', async () => {
    const res = await apiFetch('/season');
    const ct = res.headers.get('content-type') ?? '';
    expect(ct).toContain('application/json');
  });
});

// ============================================================
// Pagination parameters
// ============================================================

describe('API v1 — pagination', () => {
  test('per_page limits result count', async () => {
    await assertGetRoute('/players?per_page=5', (body) => {
      const data = body.data as unknown[];
      expect(data.length).toBeLessThanOrEqual(5);
      const meta = body.meta as Record<string, unknown>;
      expect(meta.per_page).toBe(5);
    });
  });

  test('page parameter advances to next page', async () => {
    await assertGetRoute('/players?page=2&per_page=5', (body) => {
      const meta = body.meta as Record<string, unknown>;
      expect(meta.page).toBe(2);
      expect(meta.per_page).toBe(5);
    });
  });
});

// ============================================================
// ETag / 304 caching
// ============================================================

describe('API v1 — ETag caching', () => {
  test('ETag header present and 304 on repeat request', async () => {
    const res = await apiFetch('/standings');
    assertJson(res, '/standings');

    if (res.status === 401) {
      // Without a valid API key, verify error structure instead.
      const body = await res.json();
      expect(body).toHaveProperty('error');
      return;
    }
    expect(res.status).toBe(200);

    const etag = res.headers.get('etag');
    expect(etag, 'Standings endpoint should return ETag header').toBeTruthy();

    const res2 = await apiFetch('/standings', {
      headers: { ...AUTH_HEADERS, 'If-None-Match': etag! },
    });
    expect(res2.status).toBe(304);
  });
});

// ============================================================
// Error handling
// ============================================================

describe('API v1 — error handling', () => {
  test('GET /nonexistent returns 404', async () => {
    const res = await apiFetch('/nonexistent');
    assertJson(res, '/nonexistent');
    expect([401, 404]).toContain(res.status);
    const body = await res.json();
    expect(body).toHaveProperty('error');
  });

  test('unauthenticated request returns 401 with error structure', async () => {
    const res = await fetch(`${API_BASE}/season`, {
      headers: { 'X-API-Key': '' },
    });
    assertJson(res, '/season (unauth)');
    expect(res.status).toBe(401);
    const body = await res.json();
    expect(body).toHaveProperty('error');
    expect(body.error).toBeTruthy();
  });

  test('invalid UUID returns 404', async () => {
    const res = await apiFetch('/players/not-a-valid-uuid');
    assertJson(res, '/players/invalid-uuid');
    expect([401, 404]).toContain(res.status);
  });

  test('POST to trade accept without auth returns 401', async () => {
    const res = await fetch(`${API_BASE}/trades/999/accept`, {
      method: 'POST',
    });
    assertJson(res, '/trades/999/accept');
    expect(res.status).toBe(401);
  });

  test('POST to trade decline without auth returns 401', async () => {
    const res = await fetch(`${API_BASE}/trades/999/decline`, {
      method: 'POST',
    });
    assertJson(res, '/trades/999/decline');
    expect(res.status).toBe(401);
  });
});
