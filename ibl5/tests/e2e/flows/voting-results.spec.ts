import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// VotingResults — authenticated (needs appState for phase control).

test.describe('Voting Results — Regular Season (ASG)', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await gotoWithRetry(page, 'modules.php?name=VotingResults');
  });

  test('shows ASG category tables', async ({ page }) => {
    await expect(page.locator('.voting-results-table').first()).toBeVisible();
  });

  test('has at least 4 ASG category titles', async ({ page }) => {
    const titles = page.locator('.ibl-title');
    expect(await titles.count()).toBeGreaterThanOrEqual(4);

    // Should contain Eastern/Western conference category names
    const allText = await titles.allTextContents();
    const joined = allText.join(' ');
    expect(joined).toMatch(/Eastern|Western/i);
  });

  test('tables have Player and Votes columns', async ({ page }) => {
    const firstTable = page.locator('.voting-results-table').first();
    const headers = firstTable.locator('thead th');
    await expect(headers).toHaveCount(2);
    await expect(headers.nth(0)).toContainText('Player');
    await expect(headers.nth(1)).toContainText('Votes');
  });

  test('player cells have pid= links', async ({ page }) => {
    await expect(
      page.locator('.voting-results-table a[href*="pid="]').first()
    ).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on VotingResults Regular Season');
  });
});

test.describe('Voting Results — Off-Season (EOY)', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Off-Season' });
    await gotoWithRetry(page, 'modules.php?name=VotingResults');
  });

  test('shows EOY award tables', async ({ page }) => {
    const tables = page.locator('.voting-results-table');
    expect(await tables.count()).toBeGreaterThanOrEqual(4);
  });

  test('EOY category titles include MVP and ROY', async ({ page }) => {
    const titles = page.locator('.ibl-title');
    const allText = await titles.allTextContents();
    const joined = allText.join(' ');
    expect(joined).toMatch(/Most Valuable Player/i);
    expect(joined).toMatch(/Rookie of the Year/i);
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on VotingResults Off-Season');
  });
});
