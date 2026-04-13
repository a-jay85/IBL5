import { test, expect } from '@playwright/test';
import { publicStorageState } from '../helpers/public-storage-state';

// Auth redirect smoke tests — verify all auth-required modules redirect
// unauthenticated users to the login page (YourAccount module).
//
// These modules call loginbox() which does a JS redirect to YourAccount.
// Some modules also require specific season phase settings to be accessible.
test.use({ storageState: publicStorageState() });

// Modules that require authentication and call loginbox()
// Note: Some require specific phase to avoid "module not active" before auth check
const AUTH_MODULES = [
  { name: 'DepthChartEntry', phase: null },
  { name: 'NextSim', phase: null },
  { name: 'Voting', phase: null },
  // Trading, FreeAgency, and Draft require specific phase settings
  // but the auth check happens first inside the module
];

test.describe('Unauthenticated redirect tests', () => {
  for (const { name } of AUTH_MODULES) {
    test(`${name} redirects unauthenticated users to login`, async ({ page }) => {
      await page.goto(`modules.php?name=${name}`);
      // loginbox() emits a JS redirect: window.location.href="modules.php?name=YourAccount"
      await page.waitForURL(/name=YourAccount/, { timeout: 10_000 });
      await expect(page.locator('body')).toBeVisible();
    });
  }
});
