import { test, expect } from '../fixtures/auth';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// My Team Transactions E2E — read-only ledger scoped to the logged-in GM's own team.
// The auth fixture is the test user, who owns Metros (teamid=1).
//
// Deterministic grounding (ci-seed.sql):
//   nuke_stories (read-only, never mutated by other specs):
//     "Metros waive Test Bench Player"                       (catid 1)  -> in ledger
//     "Metros trade Test Player Two to Stars for draft pick" (catid 2)  -> in ledger
//     "Test Player extends with Metros for 3 years"          (catid 3)  -> in ledger
//     "Metros sign Free Agent Guard"                         (catid 8)  -> in ledger
//     "Stars trade Draft Pick to Cougars for Cash"           (catid 2)  -> ABSENT (no "Metros")
//
// Pending trade offers (ids 1-6, all involving Metros) and FA bids (teamid=1, pids 10/11/12)
// are CONSUMED/MUTATED by trading-submission and free-agency-submission specs running in
// parallel, so this spec asserts only their section presence, never row counts.

test.describe('My Team Transactions', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=MyTransactions');
  });

  test('page renders the owner team ledger with no PHP errors', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'My Team Transactions' })).toBeVisible();
    await assertNoPhpErrors(page, 'on My Team Transactions page');
  });

  test('ledger lists the owner team transactions across categories', async ({ page }) => {
    await expect(page.getByText('Metros waive Test Bench Player')).toBeVisible();
    await expect(page.getByText('Test Player extends with Metros for 3 years')).toBeVisible();
    await expect(page.getByText('Metros sign Free Agent Guard')).toBeVisible();
  });

  test('ledger is scoped to the owner team only', async ({ page }) => {
    // A transaction mentioning only other teams must NOT appear — proves name scoping.
    await expect(page.getByText('Stars trade Draft Pick to Cougars for Cash')).toHaveCount(0);
  });

  test('outstanding-offer sections are present', async ({ page }) => {
    await expect(page.getByRole('heading', { name: 'Outstanding Trade Offers' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Outstanding Free-Agent Bids' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Transaction History' })).toBeVisible();
  });

  test('My Transactions nav link is present and navigable for the owner', async ({ page }) => {
    const navLink = page.locator('a[href="modules.php?name=MyTransactions"]').first();
    await expect(navLink).toBeAttached();
  });
});

publicTest.describe('My Team Transactions -- unauthenticated access', () => {
  publicTest('redirects logged-out users to the login page', async ({ page }) => {
    await page.goto('modules.php?name=MyTransactions');
    // Unauthenticated users are redirected to YourAccount (login) module.
    await page.waitForURL(/name=YourAccount/);
    await publicExpect(
      page.locator('#login-username'),
      'YourAccount login form must render after redirect',
    ).toBeVisible();
  });
});
