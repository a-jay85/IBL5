import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// SiteStatistics — legacy PHP-Nuke visitor stats module.
// This module requires the lang-SiteStatistics.php language file and
// nuke_counter/nuke_stats_* tables with data. In CI, seed data provides this.
// Locally it may return 500 if the language file is missing.
test.use({ storageState: publicStorageState() });

const SITE_STATS_PAGES = [
  { name: 'main stats', url: 'modules.php?name=SiteStatistics' },
  { name: 'detailed stats', url: 'modules.php?name=SiteStatistics&op=Stats' },
  { name: 'yearly stats', url: 'modules.php?name=SiteStatistics&op=YearlyStats&year=2025' },
  { name: 'monthly stats', url: 'modules.php?name=SiteStatistics&op=MonthlyStats&year=2025&month=1' },
];

test.describe('SiteStatistics smoke tests', () => {
  for (const { name, url } of SITE_STATS_PAGES) {
    test(`${name} page does not produce PHP errors`, async ({ page }) => {
      const response = await page.goto(url);
      const status = response?.status() ?? 0;

      expect(status, `SiteStatistics ${name} returned ${status}`).toBe(200);
      await assertNoPhpErrors(page, `on SiteStatistics ${name}`);
    });
  }
});
