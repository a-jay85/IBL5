import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// News — public, legacy PHP-Nuke module.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('News module flow', () => {
  test('news index page loads with article list', async ({ page }) => {
    await page.goto('modules.php?name=News');
    // News index should display articles or "no news" message
    const body = await page.locator('body').textContent();
    // Page should have rendered something meaningful
    expect(body!.length).toBeGreaterThan(100);
    await assertNoPhpErrors(page, 'on News index');
  });

  test('individual article view loads', async ({ page }) => {
    await page.goto('modules.php?name=News&file=article&sid=1');
    await assertNoPhpErrors(page, 'on News article view');
  });

  test('categories page loads', async ({ page }) => {
    await page.goto('modules.php?name=News&file=categories');
    await assertNoPhpErrors(page, 'on News categories');
  });

  test('article with nonexistent sid shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=News&file=article&sid=99999');
    await assertNoPhpErrors(page, 'on nonexistent article');
  });

  test('news index with topic filter loads', async ({ page }) => {
    await page.goto('modules.php?name=News&new_topic=1');
    await assertNoPhpErrors(page, 'on News with topic filter');
  });

  test('no PHP errors across news pages', async ({ page }) => {
    const urls = [
      'modules.php?name=News',
      'modules.php?name=News&file=categories',
      'modules.php?name=News&file=article&sid=1',
    ];
    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
