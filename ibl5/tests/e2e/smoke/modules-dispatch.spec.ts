import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });

test.describe('Module dispatch allowlist', () => {
  test('rejects unknown module name with redirect to index', async ({ page }) => {
    await page.goto('modules.php?name=NotAModule');
    expect(page.url()).toMatch(/index\.php$|\/$/);
  });

  test('rejects path-traversal module name', async ({ page }) => {
    const resp = await page.goto('modules.php?name=..%2F..%2Fconfig.php');
    expect(resp?.status()).toBeLessThan(500);
    expect(page.url()).toMatch(/index\.php$|\/$/);
  });

  test('serves known module', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await expect(page.locator('h1, h2, .ibl-title').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on Standings module');
  });

  test('missing name parameter redirects to index', async ({ page }) => {
    await page.goto('modules.php');
    expect(page.url()).toMatch(/index\.php$|\/$/);
  });
});
