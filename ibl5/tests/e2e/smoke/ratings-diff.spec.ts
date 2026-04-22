import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// RatingsDiff admin page smoke tests.
//
// The CI seed does not include any `end-of-season` ibl_plr_snapshots rows, so
// the page renders the empty-state informational block. That is itself a
// legitimate rendered state — what we verify here is that the page loads,
// the admin auth guard works, and no PHP errors surface.

test.describe('RatingsDiff admin page', () => {
  test('loads without PHP errors for admin user', async ({ page }) => {
    await page.goto('ratingsDiff.php');
    await assertNoPhpErrors(page, 'on RatingsDiff page');
    await expect(page.locator('body')).toBeVisible();
  });

  test('renders empty-state block when no end-of-season baseline exists', async ({ page }) => {
    await page.goto('ratingsDiff.php');
    await expect(page.locator('.ibl-card')).toBeVisible();
    await expect(page.locator('.ibl-card')).toContainText(/No prior-season baseline found/i);
  });
});
