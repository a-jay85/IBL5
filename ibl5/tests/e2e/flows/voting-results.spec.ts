import { test, expect } from '../fixtures/auth';
import { test as nonAdminTest } from '../fixtures/auth-regular';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// Voting Results is no longer its own module. It renders as a collapsed,
// admin-only expander on the Voting ballot page, and the old module URL
// answers 302. See Module\ModuleRedirect::TARGETS.

test.describe('Voting Results expander — admin', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await gotoWithRetry(page, 'modules.php?name=Voting');
  });

  test('expander header is visible and results start collapsed', async ({ page }) => {
    await expect(
      page.locator('.voting-category-title', { hasText: 'Voting Results' }),
    ).toBeVisible();
    await expect(page.locator('#Results')).toBeHidden();
  });

  test('clicking the header reveals then hides the results', async ({ page }) => {
    const header = page.locator('[onclick="ShowAndHideResults()"]');
    const results = page.locator('#Results');

    await header.click();
    await expect(results).toBeVisible();

    await header.click();
    await expect(results).toBeHidden();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Voting ballot with admin results expander');
  });
});

nonAdminTest.describe('Voting Results expander — regular GM', () => {
  // e2e-hygiene-allow: CI-config env gating — auth-regular.setup.ts also skips when IBL_TEST_USER_REGULAR is unset, so regular.json is absent or stale and these assertions would run against an unauthenticated session
  nonAdminTest.skip(
    !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
    'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
  );

  nonAdminTest('ballot renders without any results expander', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=Voting');
    await assertNoPhpErrors(page, 'on Voting ballot as a regular GM');

    // The ballot itself must still render, so an empty page cannot pass this.
    await expect(page.locator('h1').first()).toBeVisible();

    // The gate lives in VotingController::showBallot(). Dropping it fails here.
    await expect(page.locator('text=Voting Results')).toHaveCount(0);
    expect(await page.content()).not.toContain('ShowAndHideResults');
  });
});

test.describe('Retired VotingResults module URL', () => {
  test('answers 302 to the Voting page with an empty body', async ({ page }) => {
    const response = await page.request.get('modules.php?name=VotingResults', {
      maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    expect(response.headers()['location']).toMatch(/name=Voting$/);
    expect((await response.body()).length).toBe(0);
  });
});
