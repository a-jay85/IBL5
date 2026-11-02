import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Player Export page (modules.php?name=ApiKeys). Google Sheets Sync is the way GMs get a
// sheet; the API key card is hidden. The generate/revoke ops still exist server-side.
test.describe('Player Export page', () => {
  test('leads with Google Sheets Sync and shows no API key card', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Google Sheets Sync');
    await expect(page.getByRole('button', { name: /Generate API Key/i })).toHaveCount(0);
    await expect(page.getByRole('button', { name: /Revoke Key/i })).toHaveCount(0);
    await assertNoPhpErrors(page, 'Player Export page');
  });

  test('guide keeps the column reference and tips without key instructions', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    const guide = page.locator('.ibl-card', { has: page.getByRole('heading', { name: 'Player Export Guide' }) });
    await expect(guide.getByRole('heading', { name: 'Column Reference' })).toBeVisible();
    await expect(guide.getByRole('heading', { name: 'Tips' })).toBeVisible();
    await expect(guide).toContainText('Players tab');
    await expect(guide).not.toContainText('IMPORTDATA');
    await expect(guide).not.toContainText('API key');
    await expect(guide.locator('pre')).toHaveCount(0);
  });

  test('generate without CSRF token renders error banner', async ({ request }) => {
    const response = await request.post('modules.php?name=ApiKeys&op=generate', {
      form: {},
      maxRedirects: 0,
    });

    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain('Invalid or expired form submission');
    expect(body).not.toContain('API Key Generated');
  });
});
