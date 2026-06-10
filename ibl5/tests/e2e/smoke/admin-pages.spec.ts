import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors, PHP_ERROR_PATTERNS } from '../helpers/php-errors';
import { triggerUpdater } from '../helpers/updater';

// Admin-only page smoke tests — require roles_mask = 1 (ADMIN) on the test user.

test.describe('Admin page smoke tests', () => {
  // The updater is now POST-only + CSRF-validated. These tests only need the
  // pipeline to fire (not a phase-dependent outcome), so they trigger it via a
  // CSRF-token POST (token renders in the LCP form at any phase) rather than
  // clicking the phase-gated button — that avoids mutating the shared season-phase
  // row, leaving updater-awards.spec.ts the sole writer (no cross-worker race).
  // The genuine button click→POST→pipeline path is covered by olympics-admin.spec.ts.
  test('updateAllTheThings pipeline runs for admin (CSRF POST)', async ({ request }) => {
    const body = await triggerUpdater(request);

    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" in updater output`).not.toContain(pattern);
    }
    // The page renders an Initialization section and a completion summary.
    expect(body).toContain('Initialization');
    expect(body).toMatch(/\d+\s+(steps?\s+completed|succeeded)/i);
  });

  test('admin GET to updateAllTheThings redirects to LCP without running pipeline', async ({
    page,
  }) => {
    // Security proof: the CSRF fix makes a GET inert — it 302s back to the LCP
    // (where the real tokened POST button lives) instead of mutating the league.
    await page.goto('scripts/updateAllTheThings.php', { timeout: 60_000 });

    await expect(page).toHaveURL(/leagueControlPanel\.php/);
    await expect(page.getByText('Initialization')).not.toBeVisible();
  });

  test('block.php loads for admin', async ({ page }) => {
    const response = await page.goto('block.php');
    const status = response?.status() ?? 0;

    expect(status, 'Test user must have admin privileges — ensure roles_mask=1').not.toBe(403);
    await assertNoPhpErrors(page, 'on block.php');

    // block.php is the Free Agency admin page — it should render without crashing
    // Content depends on season phase, so just verify no access denial
    expect(status).toBeLessThan(500);
  });
});
