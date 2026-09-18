import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// TrainingCampRatingsDiff admin page smoke tests.
//
// The CI seed carries `finals` ibl_plr_snapshots rows for 2025 — the baseline year
// when 'Current Season Ending Year' is 2026 — so the default view renders the diff
// table. The empty-state branch is reached explicitly via ?year=, pointing at a
// season the seed has no snapshots for.

test.describe('TrainingCampRatingsDiff admin page', () => {
  test('loads without PHP errors for admin user', async ({ page }) => {
    await page.goto('modules.php?name=TrainingCampRatingsDiff');
    await assertNoPhpErrors(page, 'on TrainingCampRatingsDiff page');
  });

  test('renders the diff table against the playoffs baseline', async ({ appState, page }) => {
    await appState({ 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=TrainingCampRatingsDiff');
    await assertNoPhpErrors(page, 'on modules.php?name=TrainingCampRatingsDiff');
    await expect(page.locator('.ratings-diff-table')).toBeVisible();
    await expect(page.locator('.ratings-diff-page')).toContainText(/last playoffs ratings/i);
  });

  test('renders empty-state block when the baseline year has no snapshots', async ({ page }) => {
    await page.goto('modules.php?name=TrainingCampRatingsDiff&year=1900');
    await assertNoPhpErrors(page, 'on modules.php?name=TrainingCampRatingsDiff&year=1900');
    await expect(page.locator('.ibl-card')).toBeVisible();
    await expect(page.locator('.ibl-card')).toContainText(/No prior-season baseline found/i);
  });
});
