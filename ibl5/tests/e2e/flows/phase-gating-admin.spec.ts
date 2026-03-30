import { test, expect } from '../fixtures/auth';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Admin phase-gate notice: admin sees a warning banner on gated modules.
test.describe('Admin phase-gate notice', () => {
  test('shows admin-mode warning on gated module', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Show Draft Link': 'Off',
    });
    await gotoWithRetry(page, 'modules.php?name=Draft');

    const notice = page.locator('.ibl-alert--warning');
    await expect(notice).toBeVisible();
    await expect(notice).toContainText(
      'Admin mode: You can view this module, but it is currently closed to non-admin GMs.',
    );

    await assertNoPhpErrors(page, 'on Draft with admin phase-gate notice');
  });

  test('no admin-mode warning on accessible module', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await gotoWithRetry(page, 'modules.php?name=Standings');

    await expect(page.locator('.ibl-title').first()).toBeVisible();
    await expect(
      page.getByText('Admin mode: You can view this module'),
    ).not.toBeVisible();

    await assertNoPhpErrors(page, 'on Standings without admin notice');
  });
});
