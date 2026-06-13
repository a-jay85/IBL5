import { test, expect } from '../fixtures/auth';
import { desktopNav } from '../helpers/navigation';
import { assertNoPhpErrors } from '../helpers/php-errors';

// These assertions are independent of mutable Metros board state (nav presence,
// page-renders smoke), so they are safe to run in parallel with big-board.spec.ts
// which owns the gm_draft_big_board[teamid=1] mutations. The stateful
// suggestion-vs-exhausted assertion lives in that serial file by design.

test.describe('Mock Draft: navigation + smoke', () => {
  test('My Team nav exposes Big Board and Mock Draft links that navigate', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');

    // Big Board / Mock Draft live inside the My Team dropdown — must open it first.
    // Same pattern as navigation.spec.ts lines 40-49 (Watchlist nav test).
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'My Team' }).click();

    const bigBoardLink = nav.locator('.nav-dropdown-item', { hasText: 'Big Board' }).first();
    await expect(bigBoardLink).toBeVisible();
    await expect(bigBoardLink).toHaveAttribute('href', /name=BigBoard/);

    const mockLink = nav.locator('.nav-dropdown-item', { hasText: 'Mock Draft' }).first();
    await expect(mockLink).toBeVisible();
    await expect(mockLink).toHaveAttribute('href', /name=BigBoard.*op=mock/);

    const href = await mockLink.getAttribute('href');
    await page.goto(href!);
    await expect(page.locator('h2.ibl-title')).toHaveText('Mock Draft');
  });

  test('Mock Draft page renders without PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard&op=mock');

    await expect(page.locator('h2.ibl-title')).toHaveText('Mock Draft');
    await assertNoPhpErrors(page, 'Mock Draft page');
  });
});
