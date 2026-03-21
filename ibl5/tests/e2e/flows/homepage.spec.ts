import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.describe('Homepage flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('index.php');
  });

  test('homepage loads with IBL title', async ({ page }) => {
    await expect(page).toHaveTitle(/IBL/i);
  });

  test('navigation bar renders', async ({ page }) => {
    await expect(page.locator('nav.fixed').first()).toBeVisible();
  });

  test('news section renders article links', async ({ page }) => {
    // CI seed has news articles — look for article links with sid=
    const articleLinks = page.locator('a[href*="sid="]');
    await expect(articleLinks.first()).toBeVisible();
  });

  test('article links point to News module', async ({ page }) => {
    const articleLinks = page.locator('a[href*="sid="]');
    const count = await articleLinks.count();
    if (count > 0) {
      const href = await articleLinks.first().getAttribute('href');
      expect(href).toContain('News');
    }
  });

  test('main content area renders', async ({ page }) => {
    await expect(page.locator('#site-content').first()).toBeVisible();
  });

  test('no PHP errors on homepage', async ({ page }) => {
    await assertNoPhpErrors(page, 'on homepage');
  });
});

test.describe('Homepage with state override', () => {
  test('homepage renders correctly with trivia mode off', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on homepage with trivia off');
    await expect(page).toHaveTitle(/IBL/i);
  });
});
