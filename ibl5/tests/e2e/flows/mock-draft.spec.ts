import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// These assertions are independent of mutable Metros board state (nav presence,
// page-renders smoke), so they are safe to run in parallel with big-board.spec.ts
// which owns the gm_draft_big_board[teamid=1] mutations. The stateful
// suggestion-vs-exhausted assertion lives in that serial file by design.

test.describe('Mock Draft: navigation + smoke', () => {
  test('My Team nav exposes Big Board and Mock Draft links that navigate', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');

    const bigBoardLink = page.getByRole('link', { name: 'Big Board', exact: true }).first();
    await expect(bigBoardLink).toBeVisible();

    const mockLink = page.getByRole('link', { name: 'Mock Draft', exact: true }).first();
    await expect(mockLink).toBeVisible();

    await mockLink.click();
    await page.waitForURL(/name=BigBoard.*op=mock/);
    await expect(page.locator('h2.ibl-title')).toHaveText('Mock Draft');
  });

  test('Mock Draft page renders without PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard&op=mock');

    await expect(page.locator('h2.ibl-title')).toHaveText('Mock Draft');
    await assertNoPhpErrors(page, 'Mock Draft page');
  });
});
