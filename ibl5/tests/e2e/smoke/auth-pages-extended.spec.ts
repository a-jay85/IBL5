import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Authenticated page smoke tests — extended coverage.

test.describe('Extended authenticated page smoke tests', () => {
  test('free agency page loads', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    await assertNoPhpErrors(page, 'on modules.php?name=FreeAgency');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('table.fa-table').first()).toBeVisible();
  });

  test('draft page loads', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');
    await assertNoPhpErrors(page, 'on modules.php?name=Draft');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('div.draft-container')).toBeVisible();
  });

  test('waivers page loads', async ({ page }) => {
    await page.goto('modules.php?name=Waivers');
    await assertNoPhpErrors(page, 'on modules.php?name=Waivers');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('div.waivers-page')).toBeVisible();
  });

  test('voting page loads', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=Voting');
    await assertNoPhpErrors(page, 'on modules.php?name=Voting');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('div.voting-form-container')).toBeVisible();
  });

  test('next sim page loads', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    await assertNoPhpErrors(page, 'on modules.php?name=NextSim');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(
      page.locator('.ibl-title, .ibl-data-table, table, h2, h3').first(),
    ).toBeVisible();
  });

  test('gm contact list loads', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    await assertNoPhpErrors(page, 'on modules.php?name=GMContactList');
    await expect(
      page.locator('.ibl-data-table, table').first(),
    ).toBeVisible();
  });

  // Folded in from the deleted auth-pages.spec.ts — the only assertion there not
  // already covered by VR + this file: an authenticated user sees the entry form,
  // not a Sign In prompt.
  test('depth chart entry page loads', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await assertNoPhpErrors(page, 'on modules.php?name=DepthChartEntry');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await expect(page.locator('form[name="DepthChartEntry"]').first()).toBeVisible();
  });
});
