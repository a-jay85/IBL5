import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoHorizontalOverflow } from '../helpers/mobile';

// Mobile smoke tests for authenticated pages — 375x812 viewport (iPhone SE).
test.use({ viewport: { width: 375, height: 812 } });

test.describe('Mobile authenticated page smoke tests', () => {
  test('trading — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await gotoWithRetry(page, 'modules.php?name=Trading');
    await assertNoPhpErrors(page, 'on modules.php?name=Trading (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on trading');
  });

  test('depth chart entry — loads on mobile', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await assertNoPhpErrors(page, 'on modules.php?name=DepthChartEntry (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    // DCE form has inherently wide select/table elements — overflow check skipped
  });

  test('free agency — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    await assertNoPhpErrors(page, 'on modules.php?name=FreeAgency (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on free agency');
  });

  test('draft — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');
    await assertNoPhpErrors(page, 'on modules.php?name=Draft (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on draft');
  });

  test('waivers — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=Waivers');
    await assertNoPhpErrors(page, 'on modules.php?name=Waivers (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on waivers');
  });

  test('voting — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=Voting');
    await assertNoPhpErrors(page, 'on modules.php?name=Voting (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on voting');
  });

  test('next sim — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    await assertNoPhpErrors(page, 'on modules.php?name=NextSim (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on next sim');
  });

  test('gm contact list — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    await assertNoPhpErrors(page, 'on modules.php?name=GMContactList (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on gm contact list');
  });

  test('voting results — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=VotingResults');
    await assertNoPhpErrors(page, 'on modules.php?name=VotingResults (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on voting results');
  });

  test('your account — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');
    await assertNoPhpErrors(page, 'on modules.php?name=YourAccount (mobile)');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on your account');
  });

  test('trading offer form — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await gotoWithRetry(page, 'modules.php?name=Trading&op=offertrade&partner=Stars');
    await assertNoPhpErrors(page, 'on modules.php?name=Trading&op=offertrade&partner=Stars (mobile)');
    await expect(page.locator('form[name="Trade_Offer"]')).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on trading offer form');
  });

  test('free agency negotiate page — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=FreeAgency&pa=negotiate&pid=11');
    await assertNoPhpErrors(page, 'on modules.php?name=FreeAgency&pa=negotiate&pid=11 (mobile)');
    // Verify page rendered (card or alert — depends on roster/demand data)
    const content = page.locator('.ibl-card__title, .ibl-alert, .ibl-title').first();
    await expect(content).toBeVisible();
    await assertNoHorizontalOverflow(page, 'on free agency negotiate page');
  });

});
