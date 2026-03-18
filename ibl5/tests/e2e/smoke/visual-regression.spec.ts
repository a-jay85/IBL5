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
    await page.waitForLoadState('networkidle');
    const article = page.locator('article').first();
    await publicExpect(article).toBeVisible();
    await publicExpect(article).toHaveScreenshot('homepage-content.png', {
      animations: 'disabled',
    });
  });

  publicTest('standings table', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    await page.waitForLoadState('networkidle');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('standings-table.png', {
      animations: 'disabled',
    });
  });

  publicTest('season leaderboards table', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    await page.waitForLoadState('networkidle');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('season-leaderboards-table.png', {
      animations: 'disabled',
    });
  });

  publicTest('career leaderboards form', async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');
    await page.waitForLoadState('networkidle');
    const form = page.getByRole('button', { name: /display/i }).locator('..');
    await publicExpect(form).toBeVisible();
    await publicExpect(form).toHaveScreenshot('career-leaderboards-form.png', {
      animations: 'disabled',
    });
  });

  publicTest('draft history table', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    await page.waitForLoadState('networkidle');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('draft-history-table.png', {
      animations: 'disabled',
    });
  });

  publicTest('schedule page', async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
    await page.waitForLoadState('networkidle');
    const content = page.locator('.ibl-data-table, .ibl-title').first();
    await publicExpect(content).toBeVisible();
    await publicExpect(content).toHaveScreenshot('schedule-content.png', {
      animations: 'disabled',
    });
  });

  publicTest('player page ratings grid', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    await page.waitForLoadState('networkidle');
    // Target the ratings grid instead of the full card to avoid headshot image instability
    const grid = page.locator('.stats-grid').first();
    await publicExpect(grid).toBeVisible();
    await publicExpect(grid).toHaveScreenshot('player-page-ratings.png', {
      animations: 'disabled',
    });
  });

  publicTest('team page roster table', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    await page.waitForLoadState('networkidle');
    const table = page.locator('.ibl-data-table').first();
    await publicExpect(table).toBeVisible();
    await publicExpect(table).toHaveScreenshot('team-roster-table.png', {
      animations: 'disabled',
    });
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
    await page.waitForLoadState('networkidle');
    const teamSelect = page.locator('.trading-team-select');
    await authExpect(teamSelect).toBeVisible();
    await authExpect(teamSelect).toHaveScreenshot('trading-team-select.png', {
      animations: 'disabled',
    });
  });

  authTest('depth chart entry page', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await page.waitForLoadState('networkidle');
    await authExpect(page.getByText('Sign In')).not.toBeVisible();
    const title = page.locator('.ibl-title').first();
    await authExpect(title).toBeVisible();
    await authExpect(title).toHaveScreenshot('depth-chart-entry.png', {
      animations: 'disabled',
    });
  });

  authTest('free agency page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    await page.waitForLoadState('networkidle');
    const content = page.locator('.ibl-data-table, .ibl-card').first();
    await authExpect(content).toBeVisible();
    await authExpect(content).toHaveScreenshot('free-agency-content.png', {
      animations: 'disabled',
    });
  });

  authTest('desktop navigation bar', async ({ page }) => {
    await page.goto('index.php');
    await page.waitForLoadState('networkidle');
    const nav = page.locator('nav, .ibl-nav, header').first();
    await authExpect(nav).toBeVisible();
    await authExpect(nav).toHaveScreenshot('nav-desktop.png', {
      animations: 'disabled',
    });
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
