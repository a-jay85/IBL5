import { test, expect } from '../fixtures/auth';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoPhpErrors } from '../helpers/php-errors';

// COVERAGE SCOPE — admin phase-gate ALLOW paths
//
// Modules whose admin-bypass is enforced ONLY at modules.php
// (ModuleAccessControl::isModuleAccessible + !is_admin() check):
//   - Draft         (covered: "shows admin-mode warning on gated module")
//   - FreeAgency    (covered: "admin sees FreeAgency tables outside Free Agency phase")
//
// Modules with a defense-in-depth INNER controller re-gate that blocks
// admin too — so there is NO admin bypass to test:
//   - Trading       (TradingController::handleTradeReview line 60 re-checks
//                    Season::areTradesAllowed(); see flows/trading.spec.ts
//                    "Trading: trades-closed state" for the admin-also-blocked
//                    characterization.)
//   - Waivers       (WaiversController::handleWaiverRequest line 64 re-checks
//                    Season::areWaiversAllowed(); see flows/waivers.spec.ts
//                    "Waivers flow: closed" for the admin-also-blocked
//                    characterization.)
//
// Voting (ASG/EOY): non-admin DENY paths are covered in phase-gating-public.spec.ts.
// Admin-ALLOW path for Voting is out of scope for this PR (Plan 8 candidate).

// Admin phase-gate notice: admin sees a warning banner on gated modules.
test.describe('Admin phase-gate notice', () => {
  test('shows admin-mode warning on gated module', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Show Draft Link': 'Off',
    });
    await gotoWithRetry(page, 'modules.php?name=Draft');

    const notice = page.locator('.ibl-alert--warning');
    await expect(notice).toBeVisible();
    await expect(notice).toContainText(
      'Admin mode: You can view this module, but it is currently closed to non-admin GMs.',
    );

    await assertNoPhpErrors(page, 'on Draft with admin phase-gate notice');
  });

  test('no admin-mode warning on accessible module', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await gotoWithRetry(page, 'modules.php?name=Standings');

    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await expect(
      page.getByText('Admin mode: You can view this module'),
    ).not.toBeVisible();

    await assertNoPhpErrors(page, 'on Standings without admin notice');
  });

  test('admin sees FreeAgency tables outside Free Agency phase', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });
    await gotoWithRetry(page, 'modules.php?name=FreeAgency');

    await expect(page.getByText('Players Under Contract').first()).toBeVisible();
    await expect(page.getByText('Unsigned Free Agents').first()).toBeVisible();
    await expect(page.getByText('All Other Free Agents').first()).toBeVisible();

    const notice = page.locator('.ibl-alert--warning');
    await expect(notice).toBeVisible();
    await expect(notice).toContainText(
      'Admin mode: You can view this module, but it is currently closed to non-admin GMs.',
    );

    await assertNoPhpErrors(page, 'on FreeAgency with admin phase-gate bypass');
  });
});
