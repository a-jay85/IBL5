import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoHorizontalOverflow } from '../helpers/mobile';

// Mobile smoke tests for authenticated pages — 375x812 viewport (iPhone SE).
test.use({ viewport: { width: 375, height: 812 } });

const AUTH_PAGES = [
  { name: 'trading', url: 'modules.php?name=Trading' },
  { name: 'depth chart entry', url: 'modules.php?name=DepthChartEntry' },
  { name: 'free agency', url: 'modules.php?name=FreeAgency' },
  { name: 'draft', url: 'modules.php?name=Draft' },
  { name: 'waivers', url: 'modules.php?name=Waivers' },
  { name: 'voting', url: 'modules.php?name=Voting' },
  { name: 'next sim', url: 'modules.php?name=NextSim' },
  { name: 'gm contact list', url: 'modules.php?name=GMContactList' },
] as const;

test.describe('Mobile authenticated page smoke tests', () => {
  test('trading — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await gotoWithRetry(page, 'modules.php?name=Trading');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on trading');
  });

  test('depth chart entry — loads on mobile', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    // DCE form has inherently wide select/table elements — overflow check skipped
  });

  test('free agency — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on free agency');
  });

  test('draft — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on draft');
  });

  test('waivers — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=Waivers');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on waivers');
  });

  test('voting — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=Voting');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on voting');
  });

  test('next sim — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on next sim');
  });

  test('gm contact list — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=GMContactList');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on gm contact list');
  });

  test('voting results — no horizontal overflow on mobile', async ({ appState, page }) => {
    await appState({ 'ASG Voting': 'Yes' });
    await page.goto('modules.php?name=VotingResults');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on voting results');
  });

  test('your account — no horizontal overflow on mobile', async ({ page }) => {
    await page.goto('modules.php?name=YourAccount');
    await expect(page.getByText('Sign In')).not.toBeVisible();
    await assertNoHorizontalOverflow(page, 'on your account');
  });

  test('no PHP errors on mobile auth pages', async ({ appState, page }) => {
    test.setTimeout(120_000);
    await appState({
      'Allow Trades': 'Yes',
      'Current Season Phase': 'Free Agency',
      'ASG Voting': 'Yes',
    });
    const urls = [
      ...AUTH_PAGES.map(p => p.url),
      'modules.php?name=VotingResults',
      'modules.php?name=YourAccount',
    ];
    for (const url of urls) {
      await gotoWithRetry(page, url);
      await assertNoPhpErrors(page, `on ${url} (mobile)`);
    }
  });
});
