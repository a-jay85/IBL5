import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Error page smoke tests — verify graceful handling of invalid requests.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Error page smoke tests', () => {
  test('invalid module name shows error message without PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=NonExistentModule');
    // Should show "doesn't exist" message or module-not-active message
    const body = await page.locator('body').textContent();
    const hasErrorMessage =
      body?.includes("doesn't exist") ||
      body?.includes('not active') ||
      body?.includes('not found');
    expect(hasErrorMessage).toBe(true);
    await assertNoPhpErrors(page, 'on invalid module page');
  });

  test('module name with special characters does not error', async ({ page }) => {
    // modules.php validates module names — special chars trigger redirect or safe error
    await page.goto('modules.php?name=<script>alert(1)</script>');
    // Should redirect to index.php (regex rejects non-alphanumeric)
    expect(page.url()).toContain('index.php');
  });

  test('path traversal in module name is handled safely', async ({ page }) => {
    // basename() strips path components, then the module doesn't exist
    await page.goto('modules.php?name=../../../etc/passwd');
    // Either redirects or shows safe error — no PHP errors
    await assertNoPhpErrors(page, 'on path traversal module name');
  });

  test('missing module name parameter redirects to index', async ({ page }) => {
    await page.goto('modules.php');
    // Should redirect to index.php
    expect(page.url()).toContain('index.php');
  });

  test('nonexistent PHP file in valid module shows error', async ({ page }) => {
    await page.goto('modules.php?name=Standings&file=nonexistent');
    const body = await page.locator('body').textContent();
    const hasErrorMessage = body?.includes("doesn't exist");
    expect(hasErrorMessage).toBe(true);
    await assertNoPhpErrors(page, 'on nonexistent file in valid module');
  });
});
