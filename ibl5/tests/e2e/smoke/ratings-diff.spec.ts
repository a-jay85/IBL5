import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// TrainingCampRatingsDiff admin page smoke tests.
//
// The CI seed does not include any `end-of-season` ibl_plr_snapshots rows, so
// the page renders the empty-state informational block. That is itself a
// legitimate rendered state — what we verify here is that the page loads,
// the admin auth guard works, and no PHP errors surface.

test.describe('TrainingCampRatingsDiff admin page', () => {
  test('loads without PHP errors for admin user', async ({ page }) => {
    await page.goto('modules.php?name=TrainingCampRatingsDiff');
    await assertNoPhpErrors(page, 'on TrainingCampRatingsDiff page');
    await expect(page.locator('body')).toBeVisible();
  });

  test('renders empty-state block when no end-of-season baseline exists', async ({ page }) => {
    await page.goto('modules.php?name=TrainingCampRatingsDiff');
    await expect(page.locator('.ibl-card')).toBeVisible();
    await expect(page.locator('.ibl-card')).toContainText(/No prior-season baseline found/i);
  });
});
