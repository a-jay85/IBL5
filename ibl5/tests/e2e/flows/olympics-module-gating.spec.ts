import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Verify IBL-only modules are gated in Olympics context.
// These modules should show "not available" message and not crash.
test.use({ storageState: publicStorageState() });

const IBL_ONLY_MODULES = [
  'Trading',
  'Draft',
  'FreeAgency',
  'Waivers',
  'Voting',
  'CapSpace',
  'FranchiseHistory',
  'AwardHistory',
  'FranchiseRecordBook',
  'CareerLeaderboards',
  'SeasonLeaderboards',
  'RecordHolders',
  'Records',
  'AllStarAppearances',
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

  test('SeasonHighs renders standalone in Olympics', async ({ page }) => {
    // SeasonHighs redirects to Records (IBL-only) in IBL context, but serves
    // directly in Olympics where Records is blocked.
    const response = await page.request.get('modules.php?name=SeasonHighs&league=olympics', {
      maxRedirects: 0,
    });
    expect(response.status()).toBe(200);

    await page.goto('modules.php?name=SeasonHighs&league=olympics');
    await assertNoPhpErrors(page, 'on SeasonHighs in Olympics');
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('Team page renders in Olympics context', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    await assertNoPhpErrors(page, 'on Team page in Olympics');

    const body = await page.locator('body').textContent();
    expect(body).not.toContain("Module isn't active");
  });

});
