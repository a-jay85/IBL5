import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Error page smoke tests — verify graceful handling of invalid requests.
test.use({ storageState: publicStorageState() });

test.describe('Error page smoke tests', () => {
  test('invalid module name redirects to index', async ({ page }) => {
    await page.goto('modules.php?name=NonExistentModule');
    await assertNoPhpErrors(page, 'on invalid module name redirect');
    expect(page.url()).toContain('index.php');
  });

  test('module name with special characters does not error', async ({ page }) => {
    await page.goto('modules.php?name=<script>alert(1)</script>');
    await assertNoPhpErrors(page, 'on special characters module name');
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
    await assertNoPhpErrors(page, 'on missing module name redirect');
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
