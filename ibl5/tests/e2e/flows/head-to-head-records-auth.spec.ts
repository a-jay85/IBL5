import { test, expect } from '../fixtures/auth';

// Logged-in user highlights — franchises dimension.
// CI setup (setup-docker-e2e/action.yml) wires IBL_TEST_USER as gm_username for
// teamid 1 (Metros), so the Metros row in the franchises matrix carries h2h-user-row.
// The gms dimension is not covered here: it requires ibl_gm_tenures CI seed rows,
// which are absent; that gap is tracked in a separate backlog issue.
test.describe('Head-to-Head Records — logged-in user', () => {
  test('own franchise row is highlighted with h2h-user-row', async ({ page }) => {
    await page.goto('modules.php?name=HeadToHeadRecords');

    // Apply all-time / all-phases filter so the matrix renders on any CI seed depth.
    const form = page.locator('form.h2h-filter');
    await expect(form).toBeVisible();
    await form.locator('select[name="phase"]').selectOption('all');
    await form.locator('select[name="scope"]').selectOption('all');
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      form.locator('button[type="submit"], input[type="submit"]').first().click(),
    ]);

    // Exactly one row carries h2h-user-row: the logged-in user's franchise.
    await expect(page.locator('th.h2h-user-row')).toHaveCount(1);
  });
});
