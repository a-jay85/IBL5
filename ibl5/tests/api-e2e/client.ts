/**
 * Shared API client for Vitest REST API e2e tests.
 *
 * No retry logic here — Vitest `retry: 2` in vitest.api.config.ts
 * handles transient failures at the test level.
 */

const BASE_URL = process.env.BASE_URL || 'http://main.localhost/ibl5/';

export const API_BASE = `${BASE_URL.replace(/\/$/, '')}/api/v1`;

export const API_KEY =
  process.env.IBL_API_KEY || 'e2e-test-key-do-not-use-in-production';

export const AUTH_HEADERS: Record<string, string> = {
  'X-API-Key': API_KEY,
};

// Seed UUIDs — must match ci-seed.sql and the router's UUID regex
export const SEED_PLAYER_UUID = 'a0000000-0000-0000-0000-000000000001';
export const SEED_TEAM_UUID = 'b0000000-0000-0000-0000-000000000001';
export const SEED_GAME_UUID = 'c0000000-0000-0000-0000-000000000001';

/**
 * Fetch a REST API endpoint with auth header and HTML retry.
 *
 * CI's Apache container sometimes serves HTML instead of JSON under load.
 * Retries up to 3 times with 250ms delays when a non-JSON response is received.
 * Vitest's built-in retry (config level) handles other transient failures.
 */
export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const headers = {
    ...AUTH_HEADERS,
    ...((init.headers as Record<string, string> | undefined) ?? {}),
  };
  const url = `${API_BASE}${path}`;

  for (let attempt = 0; attempt < 3; attempt++) {
    const res = await fetch(url, { ...init, headers });
    const ct = res.headers.get('content-type') ?? '';
    if (ct.includes('json') || ct.includes('application/json')) {
      return res;
    }
    // Got HTML — pause and retry
    await new Promise((r) => setTimeout(r, 250));
  }
  // Return last response even if non-JSON — let the test assertion fail clearly
  return fetch(url, { ...init, headers });
}
