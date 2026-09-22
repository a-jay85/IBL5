import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';

// Logged-in user highlights.
// CI setup (setup-docker-e2e/action.yml) wires IBL_TEST_USER as gm_username for
// teamid 1 (Metros), so the Metros row in the franchises matrix carries h2h-user-row.
// The gms matrix keys the highlight on ibl_team_info.owner_name instead, which the
// CI seed pins to 'GM TestUser' for teamid 1 and matches with an ibl_gm_tenures row.

/** Select a dimension with the all-time / all-phases filter, waiting for each auto-submit reload. */
async function applyDimension(page: Page, dimension: string): Promise<void> {
  const form = page.locator('form.h2h-filter');
  await expect(form).toBeVisible();
  // The page script removes the submit button and auto-submits on each select
  // change, so every changed select triggers its own reload.
  for (const [name, value] of [
    ['dimension', dimension],
    ['phase', 'all'],
    ['scope', 'all'],
  ] as const) {
    const select = page.locator(`form.h2h-filter select[name="${name}"]`);
    if ((await select.inputValue()) === value) {
      continue;
    }
    await Promise.all([page.waitForEvent('load'), select.selectOption(value)]);
    await expect(select).toHaveValue(value);
  }
}

test.describe('Head-to-Head Records — logged-in user', () => {
  test('own franchise row is highlighted with h2h-user-row', async ({ page }) => {
    await page.goto('modules.php?name=HeadToHeadRecords');
    await applyDimension(page, 'franchises');

    // Exactly one row carries h2h-user-row: the logged-in user's franchise.
    await expect(page.locator('tr.h2h-user-row')).toHaveCount(1);
    // The same franchise's column header carries h2h-user-col.
    await expect(page.locator('.h2h-matrix thead th.h2h-user-col')).toHaveCount(1);
  });

  test('own GM row is highlighted with h2h-user-row', async ({ page }) => {
    await page.goto('modules.php?name=HeadToHeadRecords');
    await applyDimension(page, 'gms');

    const userRow = page.locator('tr.h2h-user-row');
    await expect(userRow).toHaveCount(1);
    await expect(userRow).toHaveText(/GM TestUser/);
  });
});
