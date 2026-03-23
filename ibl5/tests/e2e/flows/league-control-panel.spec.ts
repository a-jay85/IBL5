import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Finals MVP flow — sets Finals MVP for the current season year.
// Uses auth fixture (admin access required for leagueControlPanel.php).
// set_finals_mvp has no phase gate — works in any season phase.
// CI uses a fresh DB per run; local re-runs may need manual cleanup:
//   DELETE FROM ibl_awards WHERE Award='IBL Finals MVP' AND year = <seed year>;

test.describe('LeagueControlPanel — Finals MVP flow', () => {
  test.describe.configure({ mode: 'serial' });

  test('page loads without PHP errors', async ({ page }) => {
    await page.goto('leagueControlPanel.php');
    await assertNoPhpErrors(page, 'on LeagueControlPanel page');

    await expect(page.locator('form')).toBeVisible();
  });

  test('submits Finals MVP and hides input on reload', async ({ page }) => {
    await page.goto('leagueControlPanel.php');
    await assertNoPhpErrors(page, 'before Finals MVP submission');

    // Assert Finals MVP input is visible
    const mvpInput = page.locator('input[name="finals_mvp_name"]');
    await expect(mvpInput).toBeVisible();

    const mvpButton = page.locator('button[value="set_finals_mvp"]');
    await expect(mvpButton).toBeVisible();

    // Fill and submit
    await mvpInput.fill('E2E Test MVP');
    await Promise.all([
      page.waitForURL(/success=/),
      mvpButton.click(),
    ]);

    // Assert success flash message
    await assertNoPhpErrors(page, 'after Finals MVP submission');
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).toContain('E2E Test MVP');

    // Reload and verify input is hidden (hasFinalsMvp is now true)
    await page.reload();
    await assertNoPhpErrors(page, 'after reload');
    await expect(page.locator('input[name="finals_mvp_name"]')).toHaveCount(0);
  });
});
