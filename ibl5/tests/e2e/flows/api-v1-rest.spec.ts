import { test, expect } from '../fixtures/public';

const API_KEY = 'e2e-test-key-do-not-use-in-production'; // ci-seed.sql:1524
const AUTH_HEADERS = { 'X-API-Key': API_KEY };

const PLAYER_UUID = 'a0000000-0000-0000-0000-000000000001'; // pid=1, Test Player
const TEAM_UUID = 'b0000000-0000-0000-0000-000000000001'; // teamid=1, Metros
const UNPLAYED_GAME_UUID = 'c0000000-0000-0000-0000-000000000001';
const PLAYED_GAME_UUID = 'c1000000-0000-0000-0000-000000000001'; // seeded in ci-seed.sql: played game with router-compatible UUID
const ACCEPT_OFFER_ID = 7;
const DECLINE_OFFER_ID = 8;
const UNKNOWN_UUID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

async function getJson(
  request: import('@playwright/test').APIRequestContext,
  path: string,
  opts: { headers?: Record<string, string> } = {},
) {
  const headers = { ...AUTH_HEADERS, ...(opts.headers ?? {}) };
  return request.get(path, { headers });
}

function expectJsonEnvelope(
  response: import('@playwright/test').APIResponse,
  expectedStatus = 200,
) {
  expect(response.status()).toBe(expectedStatus);
  expect(response.headers()['content-type'] ?? '').toContain('application/json');
}

test.describe('api/v1 REST contract', () => {
  // --- Players ---

  test('GET api/v1/players returns 200 + list with seeded rows', async ({ request }) => {
    const response = await getJson(request, 'api/v1/players');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET api/v1/players returns 401 without API key', async ({ request }) => {
    const response = await request.get('api/v1/players');
    expectJsonEnvelope(response, 401);
    const body = await response.json();
    expect(body.error.code).toBe('unauthorized');
  });

  test('GET api/v1/players/{uuid} returns seeded player', async ({ request }) => {
    const response = await getJson(request, `api/v1/players/${PLAYER_UUID}`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(body.data.uuid).toBe(PLAYER_UUID);
  });

  test('GET api/v1/players/{unknown uuid} returns 404', async ({ request }) => {
    const response = await getJson(request, `api/v1/players/${UNKNOWN_UUID}`);
    expectJsonEnvelope(response, 404);
    const body = await response.json();
    expect(body.error.code).toBe('not_found');
  });

  test('GET api/v1/players/{uuid}/stats returns 200', async ({ request }) => {
    const response = await getJson(request, `api/v1/players/${PLAYER_UUID}/stats`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(typeof body.data).toBe('object');
    expect(body.data).not.toBeNull();
  });

  test('GET api/v1/players/{uuid}/history returns 200', async ({ request }) => {
    const response = await getJson(request, `api/v1/players/${PLAYER_UUID}/history`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET api/v1/players/export returns CSV with BOM', async ({ request }) => {
    const response = await getJson(request, 'api/v1/players/export');
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type'] ?? '').toContain('text/csv');
    const text = await response.text();
    expect(text.charCodeAt(0)).toBe(0xfeff);
    const lines = text.trim().split('\n');
    expect(lines.length).toBeGreaterThan(1);
  });

  // --- Teams ---

  test('GET api/v1/teams returns 200 with 28 franchises', async ({ request }) => {
    const response = await getJson(request, 'api/v1/teams?per_page=100');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThanOrEqual(28);
  });

  test('GET api/v1/teams/{uuid} returns Metros', async ({ request }) => {
    const response = await getJson(request, `api/v1/teams/${TEAM_UUID}`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(body.data.uuid).toBe(TEAM_UUID);
  });

  test('GET api/v1/teams/{uuid}/roster returns 200', async ({ request }) => {
    const response = await getJson(request, `api/v1/teams/${TEAM_UUID}/roster`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  // --- Standings ---

  test('GET api/v1/standings returns 200', async ({ request }) => {
    const response = await getJson(request, 'api/v1/standings');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET api/v1/standings/East normalizes conference', async ({ request }) => {
    const response = await getJson(request, 'api/v1/standings/East');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(body.meta.conference).toBe('Eastern');
  });

  test('GET api/v1/standings/Western returns 200', async ({ request }) => {
    const response = await getJson(request, 'api/v1/standings/Western');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(body.meta.conference).toBe('Western');
  });

  // --- Games ---

  test('GET api/v1/games returns 200', async ({ request }) => {
    const response = await getJson(request, 'api/v1/games');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('GET api/v1/games/{uuid} returns played game', async ({ request }) => {
    const response = await getJson(request, `api/v1/games/${PLAYED_GAME_UUID}`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(typeof body.data).toBe('object');
    expect(body.data).not.toBeNull();
  });

  test('GET api/v1/games/{uuid}/boxscore returns 200', async ({ request }) => {
    const response = await getJson(request, `api/v1/games/${PLAYED_GAME_UUID}/boxscore`);
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(typeof body.data).toBe('object');
    expect(body.data).not.toBeNull();
  });

  test('GET api/v1/games/{uuid}/boxscore returns 404 for unplayed game', async ({ request }) => {
    const response = await getJson(request, `api/v1/games/${UNPLAYED_GAME_UUID}/boxscore`);
    expectJsonEnvelope(response, 404);
    const body = await response.json();
    expect(body.error.code).toBe('no_boxscore');
  });

  // --- Stats ---

  test('GET api/v1/stats/leaders returns 200', async ({ request }) => {
    const response = await getJson(request, 'api/v1/stats/leaders');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  // --- Injuries ---

  test('GET api/v1/injuries returns 200 with seeded injuries', async ({ request }) => {
    const response = await getJson(request, 'api/v1/injuries');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(Array.isArray(body.data)).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  // --- Season ---

  test('GET api/v1/season returns 200', async ({ request }) => {
    const response = await getJson(request, 'api/v1/season');
    expectJsonEnvelope(response);
    const body = await response.json();
    expect(body).toMatchObject({ status: 'success' });
    expect(typeof body.data).toBe('object');
    expect(body.data).not.toBeNull();
  });

  // --- Routing ---

  test('GET api/v1/nonexistent returns 404 routing error', async ({ request }) => {
    const response = await getJson(request, 'api/v1/nonexistent-route');
    expectJsonEnvelope(response, 404);
    const body = await response.json();
    expect(body.error.code).toBe('not_found');
  });

  // --- Trades (POST) ---

  test('POST api/v1/trades/{offerId}/accept returns 401 without API key', async ({ request }) => {
    const response = await request.post(`api/v1/trades/${ACCEPT_OFFER_ID}/accept`, {
      data: { discord_user_id: '1' },
    });
    expectJsonEnvelope(response, 401);
    const body = await response.json();
    expect(body.error.code).toBe('unauthorized');
  });

  test('POST api/v1/trades/{unknown}/accept returns 404', async ({ request }) => {
    const response = await request.post('api/v1/trades/999999/accept', {
      headers: AUTH_HEADERS,
      data: { discord_user_id: 'test' },
    });
    expectJsonEnvelope(response, 404);
    const body = await response.json();
    expect(body.error.code).toBe('not_found');
  });

  test.describe('POST trades — offer lifecycle', () => {
    test.describe.configure({ mode: 'serial' });

    test('POST api/v1/trades/{offerId}/accept returns 400 when discord_user_id missing', async ({
      request,
    }) => {
      const response = await request.post(`api/v1/trades/${ACCEPT_OFFER_ID}/accept`, {
        headers: AUTH_HEADERS,
        data: {},
      });
      expectJsonEnvelope(response, 400);
      const body = await response.json();
      expect(body.error.code).toBe('bad_request');
    });

    test('POST api/v1/trades/{offerId}/accept happy path', async ({ request }) => {
      const response = await request.post(`api/v1/trades/${ACCEPT_OFFER_ID}/accept`, {
        headers: AUTH_HEADERS,
        data: { discord_user_id: 'irrelevant-bypassed-by-test-approval' },
      });
      expectJsonEnvelope(response);
      const body = await response.json();
      expect(body.data.accepted).toBe(true);

      const retry = await request.post(`api/v1/trades/${ACCEPT_OFFER_ID}/accept`, {
        headers: AUTH_HEADERS,
        data: { discord_user_id: 'irrelevant-bypassed-by-test-approval' },
      });
      expectJsonEnvelope(retry, 404);
      const retryBody = await retry.json();
      expect(retryBody.error.code).toBe('not_found');
    });

    test('POST api/v1/trades/{offerId}/decline happy path', async ({ request }) => {
      const response = await request.post(`api/v1/trades/${DECLINE_OFFER_ID}/decline`, {
        headers: AUTH_HEADERS,
        data: { discord_user_id: 'irrelevant-bypassed-by-test-approval' },
      });
      expectJsonEnvelope(response);
      const body = await response.json();
      expect(body.data.declined).toBe(true);
      expect(typeof body.data.offering_team).toBe('string');
      expect(body.data.offering_team.length).toBeGreaterThan(0);
    });
  });
});
