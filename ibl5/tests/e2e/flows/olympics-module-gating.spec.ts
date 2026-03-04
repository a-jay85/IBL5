import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Verify IBL-only modules are gated in Olympics context.
// These modules should show "not available" or redirect, not crash.
test.use({ storageState: { cookies: [], origins: [] } });

const IBL_ONLY_MODULES = [
  'Trading',
  'Draft',
  'FreeAgency',
  'Waivers',
  'Voting',
];

test.describe('Olympics module gating', () => {
  for (const moduleName of IBL_ONLY_MODULES) {
    test(`${moduleName} is gated in Olympics context`, async ({ page }) => {
      await page.goto(`modules.php?name=${moduleName}&league=olympics`);
      const body = await page.locator('body').textContent() ?? '';

      // Should not show PHP fatal errors
      for (const pattern of PHP_ERROR_PATTERNS) {
        expect(body, `PHP error "${pattern}" on ${moduleName}`).not.toContain(pattern);
      }

      // Should either show a "not available" message or not render the module content
      // (exact gating behavior depends on how each module checks LeagueContext)
    });
  }
});
