import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.describe('import-demands.php — admin upload flow', () => {
  test.describe.configure({ mode: 'serial' });

  test('page loads, no PHP errors, form renders', async ({ page }) => {
    const response = await page.goto('import-demands.php');
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on import-demands.php');
    await expect(
      page.getByRole('heading', { name: 'Import Free Agent Demands' }),
    ).toBeVisible();
    await expect(
      page.locator('input[type="file"][name="demands_csv"]'),
    ).toBeAttached();
    await expect(
      page.getByRole('button', { name: /import demands/i }),
    ).toBeVisible();
  });

  test('uploading a 2-row CSV truncates and reloads ibl_demands', async ({
    page,
    request,
  }) => {
    // Upload a 2-row CSV (only FA Guard + FA Center)
    await page.goto('import-demands.php');
    const csv = Buffer.from(
      'name,dem1,dem2,dem3,dem4,dem5,dem6\nFA Guard,900,990,1080,0,0,0\nFA Center,600,660,720,0,0,0\n',
    );

    await page
      .locator('input[type="file"][name="demands_csv"]')
      .setInputFiles({
        name: 'demands.csv',
        mimeType: 'text/csv',
        buffer: csv,
      });
    await page.getByRole('button', { name: /import demands/i }).click();
    await assertNoPhpErrors(page, 'after import-demands submission');

    await expect(page.locator('.alert-success')).toContainText(
      'Successfully imported 2 player demand',
    );

    // Read-back: verify DB row count is exactly 2 (table was truncated from 3 seed rows)
    const countResponse = await request.get(
      'test-state.php?action=count-demands',
    );
    const countBody =
      (await countResponse.json()) as Record<string, unknown>;
    expect(countBody.count).toBe(2);
  });

  test.afterAll(async ({ request }) => {
    await request.delete('test-state.php?action=reset-demands');
  });
});
