import { test, expect } from '../fixtures/auth';
import { resetDraftOrder } from '../helpers/cleanup';

/**
 * ProjectedDraftOrder save_order submission flow — exercises the POST that
 * accepts a reordered lottery and persists it to `ibl_draft`.
 *
 * Serial: every block writes to (or reads from) the same draft-pick rows.
 * Non-admin 403 path is covered separately in role-gating-non-admin.spec.ts
 * Block B (see Tier 2 plan, Phase 5 Block 2 cross-reference).
 *
 * Cleanup: beforeAll + afterAll both reset `ibl_draft` rows and the
 * `Draft Order Finalized` setting via test-state.php?action=reset-draft-order.
 * The beforeAll heals state left behind by a previous run that crashed
 * before its own afterAll; the afterAll keeps the slate clean for the next.
 */

test.describe.configure({ mode: 'serial' });

const SAVE_ORDER_URL = 'modules.php?name=ProjectedDraftOrder&file=save_order';
const TEST_SEASON_YEAR = 2026;

test.beforeAll(async ({ request }) => {
  await resetDraftOrder(request, TEST_SEASON_YEAR);
});

interface SaveOrderResponse {
  success: boolean;
  error?: string;
}

// ---------------------------------------------------------------------------
// Block 1 — admin happy path + persistence verification
// ---------------------------------------------------------------------------

test.describe('save_order: admin happy path', () => {
  test('POST with valid order returns success and persists the order', async ({
    appState,
    page,
    request,
  }) => {
    await appState({
      'Current Season Ending Year': String(TEST_SEASON_YEAR),
    });

    const order = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    const response = await request.post(SAVE_ORDER_URL, {
      data: { order },
      headers: { 'Content-Type': 'application/json' },
    });
    expect(response.status()).toBe(200);
    const body = (await response.json()) as SaveOrderResponse;
    expect(body.success).toBe(true);

    // Persistence verification: saveLotteryOrder also flips
    // `Draft Order Finalized` to 'Yes' in ibl_settings as part of its
    // transaction, so the page now reads saved picks. The lottery rows
    // (pick 1..12) must link to the teams we just wrote, in order. The
    // afterAll hook resets both `ibl_draft` rows and the finalized flag.
    await page.goto('modules.php?name=ProjectedDraftOrder');
    const table = page
      .locator('.projected-draft-order-table, .ibl-data-table')
      .first();
    await expect(table).toBeVisible();

    const lotteryRows = table
      .locator('tbody tr:not(.projected-draft-order-separator)')
      .filter({ has: page.locator('td a[href*="teamid="]') });

    for (let i = 0; i < order.length; i++) {
      const row = lotteryRows.nth(i);
      const teamLink = row.locator('a[href*="teamid="]').first();
      const href = await teamLink.getAttribute('href');
      expect(
        href,
        `pick ${i + 1} should link to teamid=${order[i]} (saved order)`,
      ).toMatch(new RegExp(`teamid=${order[i]}(\\D|$)`));
    }
  });
});

// ---------------------------------------------------------------------------
// Block 3 — method not allowed: GET → 405
// (Block 2 lives in role-gating-non-admin.spec.ts)
// ---------------------------------------------------------------------------

test.describe('save_order: method not allowed', () => {
  test('GET returns 405', async ({ request }) => {
    const response = await request.get(SAVE_ORDER_URL);
    expect(response.status()).toBe(405);
    const body = (await response.json()) as SaveOrderResponse;
    expect(body.success).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Block 4 — validation errors (one test per save_order.php branch)
// ---------------------------------------------------------------------------

test.describe('save_order: validation errors', () => {
  async function postJson(
    request: import('@playwright/test').APIRequestContext,
    data: unknown,
  ): Promise<{ status: number; body: SaveOrderResponse }> {
    const response = await request.post(SAVE_ORDER_URL, {
      data: data as Record<string, unknown>,
      headers: { 'Content-Type': 'application/json' },
    });
    return {
      status: response.status(),
      body: (await response.json()) as SaveOrderResponse,
    };
  }

  test('missing order key returns 400', async ({ request }) => {
    const { status, body } = await postJson(request, {});
    expect(status).toBe(400);
    expect(body.error).toMatch(/invalid request body/i);
  });

  test('order with 11 elements returns 400', async ({ request }) => {
    const { status, body } = await postJson(request, {
      order: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    });
    expect(status).toBe(400);
    expect(body.error).toMatch(/exactly 12 team IDs/i);
  });

  test('order with duplicates returns 400', async ({ request }) => {
    const { status, body } = await postJson(request, {
      order: [1, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    });
    expect(status).toBe(400);
    expect(body.error).toMatch(/duplicate/i);
  });

  test('order with out-of-range team ID returns 400', async ({ request }) => {
    const { status, body } = await postJson(request, {
      order: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 29],
    });
    expect(status).toBe(400);
    expect(body.error).toMatch(/invalid team ID: 29/i);
  });

  test('order with non-numeric string returns 400', async ({ request }) => {
    // save_order.php casts strings via (int), so "abc" → 0, which then
    // fails the >= 1 check in the range validator (line 64).
    const { status, body } = await postJson(request, {
      order: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 'abc'],
    });
    expect(status).toBe(400);
    expect(body.error).toMatch(/invalid team ID: 0/i);
  });
});

test.afterAll(async ({ request }) => {
  await resetDraftOrder(request, TEST_SEASON_YEAR);
});
