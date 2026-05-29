import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Module routing & error handling — verify graceful handling of invalid/unknown
// module requests and that known modules dispatch.
//
// Consolidated from the former error-pages.spec.ts + modules-dispatch.spec.ts.
// Exactly one unknown-module-redirect test is kept (the duplicates that lived in
// error-pages.spec.ts, modules-dispatch.spec.ts, and parameter-edge-cases.spec.ts
// were removed). Both distinct path-traversal encodings are preserved.
test.use({ storageState: publicStorageState() });

// Robust across both former files: ModuleRegistry::isValid() failures redirect to
// index.php (built-in server may land on `/` or `index.php`, with or without query).
const INDEX_REDIRECT = /index\.php($|\?)|\/$/;

test.describe('Module routing & error handling', () => {
  test('unknown module name redirects to index', async ({ page }) => {
    await page.goto('modules.php?name=NonExistentModule');
    await assertNoPhpErrors(page, 'on unknown module name redirect');
    expect(page.url()).toMatch(INDEX_REDIRECT);
  });

  test('missing name parameter redirects to index', async ({ page }) => {
    await page.goto('modules.php');
    await assertNoPhpErrors(page, 'on missing name parameter redirect');
    expect(page.url()).toMatch(INDEX_REDIRECT);
  });

  test('special-characters / XSS module name redirects to index', async ({ page }) => {
    await page.goto('modules.php?name=<script>alert(1)</script>');
    await assertNoPhpErrors(page, 'on special characters module name');
    expect(page.url()).toMatch(INDEX_REDIRECT);
  });

  test('path traversal in module name is handled safely', async ({ page }) => {
    // basename() strips path components, then the module doesn't exist.
    await page.goto('modules.php?name=../../../etc/passwd');
    // Either redirects or shows a safe error — the invariant is no PHP errors.
    await assertNoPhpErrors(page, 'on path traversal module name');
  });

  test('encoded path-traversal module name returns safe status', async ({ page }) => {
    const resp = await page.goto('modules.php?name=..%2F..%2Fconfig.php');
    await assertNoPhpErrors(page, 'on encoded path-traversal module name');
    expect(resp?.status()).toBeLessThan(500);
    expect(page.url()).toMatch(INDEX_REDIRECT);
  });

  test('nonexistent PHP file in valid module shows error message', async ({ page }) => {
    await page.goto('modules.php?name=Standings&file=nonexistent');
    const body = await page.locator('body').textContent();
    expect(body?.includes("doesn't exist")).toBe(true);
    await assertNoPhpErrors(page, 'on nonexistent file in valid module');
  });

  test('serves known module', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await expect(page.locator('h1, h2, .ibl-title').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on Standings module');
  });
});
