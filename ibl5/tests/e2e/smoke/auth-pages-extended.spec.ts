import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Authenticated page smoke tests — extended coverage.

const AUTH_URLS = [
  'modules.php?name=FreeAgency',
  'modules.php?name=Draft',
  'modules.php?name=Waivers',
  'modules.php?name=Voting',
  'modules.php?name=NextSim',
  'modules.php?name=GMContactList',
];

/** Phase-closed patterns keyed by module name. */
const CLOSED_PATTERNS: Record<string, string[]> = {
  FreeAgency: ['free agency is closed', 'free agency is not open'],
  Draft: ['draft is closed', 'draft is not open'],
  Waivers: ['waivers are closed', 'waivers are not open'],
  Voting: ['voting is closed', 'voting is not open'],
};

function isFeatureClosed(body: string | null, moduleName: string): boolean {
  const patterns = CLOSED_PATTERNS[moduleName];
  if (!patterns || !body) return false;
  const lower = body.toLowerCase();
  return patterns.some((p) => lower.includes(p));
}

test.describe('Extended authenticated page smoke tests', () => {
  test('free agency page loads', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency');
    const body = await page.locator('body').textContent();
    if (isFeatureClosed(body, 'FreeAgency')) {
      test.skip(true, 'Free agency is currently closed');
    }
    // FA page may have tables, or may be blank between seasons
    if (!body || body.trim().length < 100) {
      test.skip(true, 'Free agency page has no content in current phase');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('draft page loads', async ({ page }) => {
    await page.goto('modules.php?name=Draft');
    const body = await page.locator('body').textContent();
    if (isFeatureClosed(body, 'Draft')) {
      test.skip(true, 'Draft is currently closed');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('waivers page loads', async ({ page }) => {
    await page.goto('modules.php?name=Waivers');
    const body = await page.locator('body').textContent();
    if (isFeatureClosed(body, 'Waivers')) {
      test.skip(true, 'Waivers are currently closed');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('voting page loads', async ({ page }) => {
    await page.goto('modules.php?name=Voting');
    const body = await page.locator('body').textContent();
    if (isFeatureClosed(body, 'Voting')) {
      test.skip(true, 'Voting is currently closed');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('next sim page loads', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    const body = await page.locator('body').textContent();
    // NextSim may have no content if no games are scheduled
    const hasContent = await page
      .locator('.ibl-title, .ibl-data-table, table, h2, h3')
      .first()
      .isVisible()
      .catch(() => false);
    if (!hasContent) {
      test.skip(true, 'NextSim page has no content in current season phase');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('gm contact list loads', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    await expect(
      page.locator('.ibl-data-table, table').first(),
    ).toBeVisible();
  });

  test('no PHP errors on auth pages', async ({ page }) => {
    for (const url of AUTH_URLS) {
      await page.goto(url);
      const body = await page.locator('body').textContent();
      for (const pattern of PHP_ERROR_PATTERNS) {
        expect(body, `PHP error "${pattern}" found on ${url}`).not.toContain(
          pattern,
        );
      }
    }
  });
});
