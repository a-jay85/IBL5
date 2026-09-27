import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Leaderboards — tab-unified module (Season + Career tabs).
// Public, no authentication required.

test.describe('Leaderboards flow', () => {
  test('default tab is season', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards');
    await assertNoPhpErrors(page, 'on Leaderboards default tab');

    await expect(page.locator('h1.ibl-title')).toHaveText('Season Leaders');
    await expect(page.locator('.ibl-tabs .ibl-tab--active')).toHaveText('Season');
    await expect(page.locator('form[name="Leaderboards"]')).toBeVisible();
  });

  test('career tab renders career form', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards&tab=career');
    await assertNoPhpErrors(page, 'on Leaderboards career tab');

    await expect(page.locator('form[name="CareerLeaderboards"]')).toBeVisible();
    await expect(page.locator('form[name="Leaderboards"]')).toHaveCount(0);
  });

  test('tab bar marks active tab', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards');

    await page.locator('.ibl-tabs a:has-text("Career")').click();

    await expect(page).toHaveURL(/name=Leaderboards&tab=career/);
    const activeTab = page.locator('.ibl-tab--active');
    await expect(activeTab).toHaveCount(1);
    await expect(activeTab).toHaveText('Career');
    await expect(activeTab).toHaveAttribute('aria-current', 'page');
  });

  test('unknown tab falls back to season', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards&tab=bogus');
    await assertNoPhpErrors(page, 'on Leaderboards bogus tab');

    await expect(page.locator('h1.ibl-title')).toHaveText('Season Leaders');
  });

  test('old season URL redirects to season tab', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=SeasonLeaderboards');

    await expect(page).toHaveURL(/name=Leaderboards&tab=season/);
    await expect(page.locator('h1.ibl-title')).toHaveText('Season Leaders');
  });

  test('old career URL redirects to career tab', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=CareerLeaderboards');

    await expect(page).toHaveURL(/name=Leaderboards&tab=career/);
    await expect(page.locator('form[name="CareerLeaderboards"]')).toBeVisible();
  });

  test('season filter submit stays on season tab', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards&tab=season');

    const table = page.locator('.ibl-data-table').first();
    await expect(table).toBeVisible();

    await page.locator('select[name="sortby"]').selectOption('REB');
    await Promise.all([
      page.waitForResponse((r) => r.url().includes('tab=season') && r.request().method() === 'POST'),
      page.locator('.ibl-filter-form__submit').click(),
    ]);

    await expect(page).toHaveURL(/tab=season/);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });

  test('career filter submit stays on career tab', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Leaderboards&tab=career');

    await page.locator('.ibl-filter-form__submit').click();

    await expect(page).toHaveURL(/tab=career/);
    const rows = page.locator('.ibl-data-table').first().locator('tbody tr');
    await expect(rows.first()).toBeVisible();
  });

  test('nav menu links to leaderboards', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Homepage');

    await expect(page.locator('a[href="modules.php?name=Leaderboards"]')).toHaveCount(
      await page.locator('a[href="modules.php?name=Leaderboards"]').count(),
    );
    const leaderboardLinks = page.locator('a[href="modules.php?name=Leaderboards"]');
    const count = await leaderboardLinks.count();
    expect(count).toBeGreaterThanOrEqual(1);

    await expect(page.locator('a[href*="name=SeasonLeaderboards"]')).toHaveCount(0);
    await expect(page.locator('a[href*="name=CareerLeaderboards"]')).toHaveCount(0);

    const href = await leaderboardLinks.first().getAttribute('href');
    await page.goto(href!);
    await expect(page.locator('.ibl-tabs')).toBeVisible();
  });

  test('leaderboards hidden in trivia mode', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'On' });

    await page.goto('modules.php?name=Leaderboards&tab=season');
    await expect(page.getByText("Module isn't active")).toBeVisible();

    await page.goto('modules.php?name=Leaderboards&tab=career');
    await expect(page.getByText("Module isn't active")).toBeVisible();
  });
});
