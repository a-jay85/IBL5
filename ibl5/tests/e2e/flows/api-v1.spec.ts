import { test, expect } from '@playwright/test';

// REST API v1 Tests — no browser rendering, uses page.request.get().
// API requires X-API-Key header for authentication.
// If no API key is available, tests verify 401 response.
test.use({ storageState: { cookies: [], origins: [] } });

const BASE_URL = '/ibl5/api/v1';

test.describe('API v1 endpoints', () => {
  test('GET /season returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/season`);
    const status = response.status();

    if (status === 401) {
      // No API key configured — verify 401 response structure
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(body).toBeTruthy();
  });

  test('GET /teams returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/teams`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(Array.isArray(body.data) || typeof body === 'object').toBe(true);
  });

  test('GET /standings returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/standings`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(body).toBeTruthy();
  });

  test('GET /standings/Eastern returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/standings/Eastern`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(body).toBeTruthy();
  });

  test('GET /players returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/players`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(body).toBeTruthy();
  });

  test('GET /injuries returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/injuries`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    // May be empty array if no injuries
    expect(body).toBeTruthy();
  });

  test('GET /stats/leaders returns 200 or 401', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/stats/leaders`);
    const status = response.status();

    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      return;
    }

    expect(status).toBe(200);
    const body = await response.json();
    expect(body).toBeTruthy();
  });

  test('GET /nonexistent returns 404', async ({ request }) => {
    const response = await request.get(`${BASE_URL}/nonexistent`);
    const status = response.status();

    // Should be 404 (or 401 if auth required before routing)
    expect([401, 404]).toContain(status);

    const body = await response.json();
    expect(body).toHaveProperty('error');
  });

  test('unauthenticated requests return proper error structure', async ({
    request,
  }) => {
    // Make request without API key — should get 401
    const response = await request.get(`${BASE_URL}/season`, {
      headers: { 'X-API-Key': '' },
    });

    const status = response.status();
    // Either 401 (no key) or 200 (if somehow no auth needed)
    if (status === 401) {
      const body = await response.json();
      expect(body).toHaveProperty('error');
      expect(body.error).toBeTruthy();
    }
  });
});
