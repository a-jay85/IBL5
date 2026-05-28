import { test, expect } from '../fixtures/base';
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
  // Trading and FreeAgency call loginbox() for unauthenticated users before the
  // phase gate, so they redirect to YourAccount regardless of phase.
  { name: 'Trading', phase: null },
  { name: 'FreeAgency', phase: null },
  // NOTE: Draft and Waivers are intentionally NOT here — they render a public,
  // read-only view for unauthenticated users (no loginbox redirect), so a
  // redirect assertion would not hold. Their public render is covered by the
  // phase-gating-public spec instead.
];

test.describe('Unauthenticated redirect tests', () => {
  for (const { name } of AUTH_MODULES) {
    test(`${name} redirects unauthenticated users to login`, async ({ page }) => {
      await page.goto(`modules.php?name=${name}`);
      // loginbox() emits a JS redirect: window.location.href="modules.php?name=YourAccount"
      await page.waitForURL(/name=YourAccount/, { timeout: 10_000 });
      await expect(page.locator('#login-username'), 'YourAccount login form must render after redirect').toBeVisible();
    });
  }
});
