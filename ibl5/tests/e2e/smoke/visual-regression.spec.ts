import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { test as authTest, expect as authExpect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// ============================================================
// Public visual regression tests — no authentication required
// ============================================================

publicTest.describe('Visual regression — public pages', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  publicTest('homepage layout', async ({ page }) => {
    await page.goto('index.php');
    await publicExpect(page).toHaveTitle(/IBL/i);
    const article = page.locator('article').first();
    await publicExpect(article).toBeVisible();
    await publicExpect(article).toHaveScreenshot('homepage-content.png');
  });

  publicTest('standings table', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('standings-table.png');
  });

  publicTest('season leaderboards table', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('season-leaderboards-table.png');
  });

  publicTest('career leaderboards form', async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');
    const form = page.getByRole('button', { name: /display/i }).locator('..');
    await publicExpect(form).toBeVisible();
    await publicExpect(form).toHaveScreenshot('career-leaderboards-form.png');
  });

  publicTest('draft history table', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('draft-history-table.png');
  });

  publicTest('schedule page', async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
    const content = page.locator('.ibl-data-table, .ibl-title').first();
    await publicExpect(content).toBeVisible();
    await publicExpect(content).toHaveScreenshot('schedule-content.png');
  });

  publicTest('player page stats card', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    const heading = page.locator('h2, h3').first();
    await publicExpect(heading).toBeVisible();
    const card = page.locator('.player-stats-card, .ibl-card, .ibl-data-table').first();
    await publicExpect(card).toBeVisible();
    await publicExpect(card).toHaveScreenshot('player-page-card.png');
  });

  publicTest('team page roster table', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('team-roster-table.png');
  });

  publicTest('no PHP errors on visual regression pages', async ({ page }) => {
    const urls = [
      'index.php',
      'modules.php?name=Standings',
      'modules.php?name=SeasonLeaderboards',
      'modules.php?name=CareerLeaderboards',
      'modules.php?name=DraftHistory',
      'modules.php?name=Schedule',
      'modules.php?name=Player&pa=showpage&pid=1',
      'modules.php?name=Team&op=team&teamID=1',
    ];

    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});

// ============================================================
// Authenticated visual regression tests
// ============================================================

authTest.describe('Visual regression — authenticated pages', () => {
  authTest('trading page team select', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes' });
    await page.goto('modules.php?name=Trading');
    const teamSelect = page.locator('.trading-team-select');
    await authExpect(teamSelect).toBeVisible();
    await authExpect(teamSelect).toHaveScreenshot('trading-team-select.png');
  });

  authTest('depth chart entry page', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await authExpect(page.getByText('Sign In')).not.toBeVisible();
    const title = page.locator('.ibl-title').first();
    await authExpect(title).toBeVisible();
    await authExpect(title).toHaveScreenshot('depth-chart-entry.png');
  });

  authTest('free agency page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    const content = page.locator('.ibl-data-table, .ibl-card').first();
    await authExpect(content).toBeVisible();
    await authExpect(content).toHaveScreenshot('free-agency-content.png');
  });

  authTest('desktop navigation bar', async ({ page }) => {
    await page.goto('index.php');
    const nav = page.locator('nav, .ibl-nav, header').first();
    await authExpect(nav).toBeVisible();
    await authExpect(nav).toHaveScreenshot('nav-desktop.png');
  });

  authTest('no PHP errors on visual regression pages', async ({ appState, page }) => {
    await appState({ 'Allow Trades': 'Yes', 'Current Season Phase': 'Free Agency' });
    const urls = [
      'modules.php?name=Trading',
      'modules.php?name=DepthChartEntry',
      'modules.php?name=FreeAgency',
    ];

    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
