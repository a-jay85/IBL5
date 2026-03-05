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

test.describe('Extended authenticated page smoke tests', () => {
  test('free agency page loads', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('draft page loads', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('waivers page loads', async ({ page }) => {
    // No state override needed — page loads and shows auth regardless of open/closed
    await page.goto('modules.php?name=Waivers');
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('voting page loads', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=Voting');
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('next sim page loads', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    // NextSim may have no content if no games are scheduled — data-dependent skip
    const hasContent = await page
      .locator('.ibl-title, .ibl-data-table, table, h2, h3')
      .first()
      .isVisible()
      .catch(() => false);
    if (!hasContent) {
      test.skip(true, 'NextSim page has no content (no scheduled games)');
    }
    await expect(page.getByText('Sign In')).not.toBeVisible();
  });

  test('gm contact list loads', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    await expect(
      page.locator('.ibl-data-table, table').first(),
    ).toBeVisible();
  });

  test('no PHP errors on auth pages', async ({ appState, page }) => {
    // Set state so all modules render content
    await appState({
      'Current Season Phase': 'Free Agency',
      'ASG Voting': 'Yes',
    });
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
