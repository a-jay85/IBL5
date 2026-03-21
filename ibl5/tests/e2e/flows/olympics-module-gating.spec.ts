import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Verify IBL-only modules are gated in Olympics context.
// These modules should show "not available" message and not crash.
test.use({ storageState: { cookies: [], origins: [] } });

const IBL_ONLY_MODULES = [
  'Trading',
  'Draft',
  'FreeAgency',
  'Waivers',
  'Voting',
  'CapSpace',
  'FranchiseHistory',
];

test.describe('Olympics module gating', () => {
  for (const moduleName of IBL_ONLY_MODULES) {
    test(`${moduleName} is gated in Olympics context`, async ({ page }) => {
      await page.goto(`modules.php?name=${moduleName}&league=olympics`);

      // Should not show PHP fatal errors
      await assertNoPhpErrors(page, `on ${moduleName} in Olympics`);

      // Gated modules show "this Module isn't active" message
      const body = await page.locator('body').textContent();
      expect(body).toContain("Module isn't active");
    });
  }

  test('non-gated modules render normally in Olympics context', async ({ page }) => {
    // Standings should work in Olympics context
    await page.goto('modules.php?name=Standings&league=olympics');
    await assertNoPhpErrors(page, 'on Standings in Olympics');

    // Should render tables, not the gating message
    const body = await page.locator('body').textContent();
    expect(body).not.toContain("Module isn't active");
  });

  test('Team page renders in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1&league=olympics');
    await assertNoPhpErrors(page, 'on Team page in Olympics');

    const body = await page.locator('body').textContent();
    expect(body).not.toContain("Module isn't active");
  });

  test('SeasonLeaderboards renders in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards&league=olympics');
    await assertNoPhpErrors(page, 'on SeasonLeaderboards in Olympics');

    const body = await page.locator('body').textContent();
    expect(body).not.toContain("Module isn't active");
  });
});
