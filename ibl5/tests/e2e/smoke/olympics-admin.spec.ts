import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Olympics admin page — verify pipeline loads in Olympics context.
test.describe('Olympics admin smoke tests', () => {
  test('updateAllTheThings loads in Olympics context', async ({ page }) => {
    await page.goto('scripts/updateAllTheThings.php?league=olympics');
    await expect(page.locator('body')).toContainText('Olympics');
    await assertNoPhpErrors(page, 'on Olympics pipeline');
  });

  test('Olympics LCP loads with sim length visible', async ({ page }) => {
    await page.goto('leagueControlPanel.php?league=olympics');
    await expect(page.locator('input[name="SimLengthInDays"]')).toBeVisible();
    await assertNoPhpErrors(page, 'on Olympics LCP');
  });

  test('Olympics LCP hides IBL-only buttons', async ({ page }) => {
    await page.goto('leagueControlPanel.php?league=olympics');
    await expect(page.locator('button[value="set_sim_length"]')).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).not.toContain('Trivia');
    expect(body).not.toContain('Quick Links');
  });
});
