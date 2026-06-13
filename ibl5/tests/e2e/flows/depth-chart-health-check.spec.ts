// Lineup Health Check panel — server-rendered E2E spec.
//
// IMPORTANT: The panel (#dc-health-check) is server-rendered from the live DB
// dc_*_depth columns at page load. It does NOT recompute when the GM changes a
// <select> or loads a saved depth chart client-side. This spec must NOT mutate
// a select and expect the panel to change.

import { test as unhealthyTest, expect as unhealthyExpect } from '../fixtures/auth-unhealthy-dc';
import { test as clearTest, expect as clearExpect } from '../fixtures/auth-healthy-dc';
import { assertNoPhpErrors } from '../helpers/php-errors';

// ===========================================================================
// Warning-present: Huskies (tid=17) via auth-unhealthy-dc
// Roster is engineered so the only warning is injured_starter (PG slot).
// ===========================================================================

unhealthyTest.describe('Lineup Health Check — warning present (Huskies tid=17)', () => {
  unhealthyTest('shows injured_starter warning for IHL Injured PG', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await unhealthyExpect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Panel must be visible.
    await unhealthyExpect(page.locator('#dc-health-check')).toBeVisible();

    // Container carries the warn modifier class.
    await unhealthyExpect(page.locator('#dc-health-check')).toHaveClass(/dc-health-check--warn/);

    // The injured-starter warning item is attached and names the player.
    const warningItem = page.locator('#dc-health-check li[data-warning-type="injured_starter"]');
    await unhealthyExpect(warningItem).toBeAttached();
    await unhealthyExpect(warningItem).toContainText('IHL Injured PG');

    await assertNoPhpErrors(page, 'on Depth Chart Entry (Huskies, warning-present)');
  });
});

// ===========================================================================
// All-clear: Nuggets (tid=19) via auth-healthy-dc
// Uses a dedicated isolated team so submission-spec DB mutations on Monarchs
// (tid=8) cannot pollute this test's all-clear assertion.
// ===========================================================================

clearTest.describe('Lineup Health Check — all clear (Nuggets tid=19)', () => {
  clearTest('shows ok state with no warning items', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await clearExpect(page.locator('.depth-chart-form')).toBeVisible({ timeout: 15000 });

    // Panel must be visible and carry the ok modifier class.
    await clearExpect(page.locator('#dc-health-check')).toBeVisible();
    await clearExpect(page.locator('#dc-health-check')).toHaveClass(/dc-health-check--ok/);

    // No warning items present.
    await clearExpect(page.locator('#dc-health-check li[data-warning-type]')).toHaveCount(0);

    await assertNoPhpErrors(page, 'on Depth Chart Entry (Nuggets, all-clear)');
  });
});
