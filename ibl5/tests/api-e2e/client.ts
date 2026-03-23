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

/** Fetch a REST API endpoint with the auth header. */
export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const headers = {
    ...AUTH_HEADERS,
    ...((init.headers as Record<string, string> | undefined) ?? {}),
  };
  return fetch(`${API_BASE}${path}`, { ...init, headers });
}
