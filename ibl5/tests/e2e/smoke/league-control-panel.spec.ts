import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Archive buttons render only in Preseason and Playoffs phases.
// LCP is admin-only; test user is admin so no access check needed.
test.describe.configure({ mode: 'serial' });

const LCP_URL = 'leagueControlPanel.php';

test.describe('League Control Panel smoke tests', () => {
  test('LCP page loads without PHP errors', async ({ page }) => {
    await page.goto(LCP_URL);
    await assertNoPhpErrors(page, 'on LCP');
  });

  test('archive buttons visible in Playoffs', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Playoffs' });
    await page.goto(LCP_URL);

    await expect(page.locator('button[value="archive_season_hist"]')).toBeVisible();
    await expect(page.locator('button[value="validate_plr_accuracy"]')).toBeVisible();
  });

  test('archive buttons visible in Preseason', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Preseason' });
    await page.goto(LCP_URL);

    await expect(page.locator('button[value="archive_season_hist"]')).toBeVisible();
    await expect(page.locator('button[value="validate_plr_accuracy"]')).toBeVisible();
  });

  test('archive buttons NOT visible in Regular Season', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(LCP_URL);

    await expect(page.locator('button[value="archive_season_hist"]')).toHaveCount(0);
    await expect(page.locator('button[value="validate_plr_accuracy"]')).toHaveCount(0);
  });

  test('archive buttons NOT visible in HEAT', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'HEAT' });
    await page.goto(LCP_URL);

    await expect(page.locator('button[value="archive_season_hist"]')).toHaveCount(0);
    await expect(page.locator('button[value="validate_plr_accuracy"]')).toHaveCount(0);
  });
});
