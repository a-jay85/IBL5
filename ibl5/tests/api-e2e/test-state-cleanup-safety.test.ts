/**
 * Safety-gate tests for the new test-state.php DELETE cleanup endpoints.
 * The delete-test-user endpoint must refuse to wipe accounts whose
 * username does not start with the `e2e_` prefix — a defense-in-depth
 * check in case E2E_TESTING leaks into a non-test environment.
 *
 * Verification row 13 of the Tier 2 E2E hardening plan.
 */

import { describe, test, expect } from 'vitest';

const BASE_URL = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
const stateUrl = `${BASE_URL.replace(/\/$/, '')}/test-state.php`;

describe('test-state.php cleanup safety gates', () => {
  test('delete-test-user refuses usernames without e2e_ prefix', async () => {
    for (const evil of ['admin', 'A-Jay', 'commish', 'root', '']) {
      const url = `${stateUrl}?action=delete-test-user&username=${encodeURIComponent(evil)}`;
      const res = await fetch(url, { method: 'DELETE' });
      expect(res.status, `username "${evil}" must be refused`).toBe(400);
      const body = await res.json();
      expect(body).toHaveProperty('error');
    }
  });

  test('delete-test-user accepts e2e_ prefix and returns deleted count', async () => {
    // No row with this username exists — endpoint should succeed with deleted=0.
    const url = `${stateUrl}?action=delete-test-user&username=e2e_nonexistent_user`;
    const res = await fetch(url, { method: 'DELETE' });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('deleted');
    expect(body.deleted).toBe(0);
  });

  test('reset-extension refuses pids other than 30', async () => {
    for (const pid of [0, 1, 29, 31, 100]) {
      const res = await fetch(`${stateUrl}?action=reset-extension&pid=${pid}`, { method: 'DELETE' });
      expect(res.status, `pid=${pid} must be refused`).toBe(400);
    }
  });

  test('reset-extension accepts pid=30 and reports reset status', async () => {
    const res = await fetch(`${stateUrl}?action=reset-extension&pid=30`, { method: 'DELETE' });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('reset');
    expect([0, 1]).toContain(body.reset);
  });

  test('reset-draft-order refuses out-of-range years', async () => {
    for (const year of [0, 1899, 2201, 9999]) {
      const res = await fetch(`${stateUrl}?action=reset-draft-order&year=${year}`, { method: 'DELETE' });
      expect(res.status, `year=${year} must be refused`).toBe(400);
    }
  });

  test('reset-draft-order accepts a plausible season year', async () => {
    const res = await fetch(`${stateUrl}?action=reset-draft-order&year=2026`, { method: 'DELETE' });
    expect(res.status).toBe(200);
    const body = await res.json();
    expect(body).toHaveProperty('cleared');
    expect(typeof body.cleared).toBe('number');
  });
});
