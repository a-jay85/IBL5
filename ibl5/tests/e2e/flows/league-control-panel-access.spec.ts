import { test as publicTest, expect } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Access routing for the LeagueControlPanel module. The control panel moved from
// the standalone leagueControlPanel.php to modules.php?name=LeagueControlPanel.
// Path-traversal on the download endpoint is covered in league-control-panel.spec.ts.
// Non-admin 403 coverage lives in role-gating-non-admin.spec.ts.

const LCP_MODULE_URL = 'modules.php?name=LeagueControlPanel';

publicTest.describe('LeagueControlPanel module — unauthenticated', () => {
  publicTest('anonymous visit to the LeagueControlPanel module redirects to login', async ({ page }) => {
    await page.goto(LCP_MODULE_URL);

    await expect(page).toHaveURL(/name=YourAccount/);
  });

  publicTest('legacy leagueControlPanel.php URL is gone', async ({ page }) => {
    const response = await page.goto('leagueControlPanel.php');

    expect(response?.status()).toBe(404);
  });
});

authTest.describe('LeagueControlPanel module — admin', () => {
  authTest('admin sees the control panel at the module URL', async ({ page }) => {
    await page.goto(LCP_MODULE_URL);
    await assertNoPhpErrors(page, 'on LeagueControlPanel module page');

    await expect(page.locator('main.updater')).toBeVisible();
    await expect(page.locator('form[action="modules.php?name=LeagueControlPanel"]')).toHaveCount(1);
  });
});
