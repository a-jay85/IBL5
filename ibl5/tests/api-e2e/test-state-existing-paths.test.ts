/**
 * Characterization tests for ibl5/test-state.php — verifies the existing
 * GET, POST, and `DELETE action=clear-throttle` paths keep responding with
 * the same shapes after PR-A adds new DELETE actions
 * (reset-extension, reset-draft-order, delete-test-user).
 *
 * Verification row 12 of the Tier 2 E2E hardening plan.
 *
 * Path note: the plan literally said `tests/api/test-state-existing-paths.spec.ts`,
 * but `vitest.api.config.ts` only includes `tests/api-e2e/**!/!*.test.ts`. Placed
 * under api-e2e/ with the .test.ts suffix so the existing vitest job picks it up.
 */

import { describe, test, expect } from 'vitest';

const BASE_URL = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
const stateUrl = `${BASE_URL.replace(/\/$/, '')}/test-state.php`;

describe('test-state.php existing endpoints', () => {
  test('GET returns the ibl_settings dictionary', async () => {
    const res = await fetch(stateUrl);
    expect(res.status).toBe(200);
    expect(res.headers.get('content-type')).toContain('application/json');
    const body = await res.json();
    expect(body).toBeTypeOf('object');
    expect(body).not.toBeNull();
  });

  test('POST with an allow-listed setting returns previous + applied', async () => {
    const res = await fetch(stateUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 'Allow Trades': 'Yes' }),
    });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('previous');
    expect(body).toHaveProperty('applied');
    expect(body.applied).toEqual({ 'Allow Trades': 'Yes' });
  });

  test('POST with an unknown setting returns 400', async () => {
    const res = await fetch(stateUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 'Not A Real Setting': 'x' }),
    });
    expect(res.status).toBe(400);
    const body = await res.json();
    expect(body).toHaveProperty('error');
  });

  test('DELETE action=clear-throttle returns cleared count', async () => {
    const res = await fetch(`${stateUrl}?action=clear-throttle`, { method: 'DELETE' });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('cleared');
    expect(typeof body.cleared).toBe('number');
  });

  test('unsupported method returns 405', async () => {
    const res = await fetch(stateUrl, { method: 'PUT' });
    expect(res.status).toBe(405);
  });
});
