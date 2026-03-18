import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { test as authTest, expect as authExpect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import type { Page, Locator } from '@playwright/test';

/**
 * Take a stable element screenshot by waiting for network idle and fonts,
 * then clipping to a fixed height to avoid dimension variance between runs.
 */
async function stableScreenshot(
  page: Page,
  locator: Locator,
  name: string,
  expect: typeof publicExpect,
  clipHeight?: number,
): Promise<void> {
  await page.waitForLoadState('networkidle');
  await expect(locator).toBeVisible();
  const options: { name: string; clip?: { x: number; y: number; width: number; height: number } } = { name };
  if (clipHeight) {
    const box = await locator.boundingBox();
    if (box) {
      options.clip = { x: 0, y: 0, width: Math.round(box.width), height: clipHeight };
    }
  }
  if (options.clip) {
    await expect(locator).toHaveScreenshot(name, { clip: options.clip });
  } else {
    await expect(locator).toHaveScreenshot(name);
  }
}

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
    await stableScreenshot(page, article, 'homepage-content.png', publicExpect);
  });

  publicTest('standings table', async ({ page }) => {
    await page.goto('modules.php?name=Standings');
    const table = page.locator('.ibl-data-table').first();
    await stableScreenshot(page, table, 'standings-table.png', publicExpect, 400);
  });

  publicTest('season leaderboards table', async ({ page }) => {
    await page.goto('modules.php?name=SeasonLeaderboards');
    const table = page.locator('.ibl-data-table').first();
    await stableScreenshot(page, table, 'season-leaderboards-table.png', publicExpect, 400);
  });

  publicTest('career leaderboards form', async ({ page }) => {
    await page.goto('modules.php?name=CareerLeaderboards');
    const form = page.getByRole('button', { name: /display/i }).locator('..');
    await stableScreenshot(page, form, 'career-leaderboards-form.png', publicExpect);
  });

  publicTest('draft history table', async ({ page }) => {
    await page.goto('modules.php?name=DraftHistory');
    const table = page.locator('.ibl-data-table').first();
    await stableScreenshot(page, table, 'draft-history-table.png', publicExpect, 400);
  });

  publicTest('schedule page', async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
    const content = page.locator('.ibl-data-table, .ibl-title').first();
    await stableScreenshot(page, content, 'schedule-content.png', publicExpect);
  });

  publicTest('player page stats card', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=showpage&pid=1');
    const heading = page.locator('h2, h3').first();
    await publicExpect(heading).toBeVisible();
    const card = page.locator('.player-stats-card, .ibl-card, .ibl-data-table').first();
    await stableScreenshot(page, card, 'player-page-card.png', publicExpect, 400);
  });

  publicTest('team page roster table', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamID=1');
    const table = page.locator('.ibl-data-table').first();
    await stableScreenshot(page, table, 'team-roster-table.png', publicExpect, 400);
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
    await stableScreenshot(page, teamSelect, 'trading-team-select.png', authExpect, 400);
  });

  authTest('depth chart entry page', async ({ page }) => {
    await page.goto('modules.php?name=DepthChartEntry');
    await authExpect(page.getByText('Sign In')).not.toBeVisible();
    const title = page.locator('.ibl-title').first();
    await stableScreenshot(page, title, 'depth-chart-entry.png', authExpect);
  });

  authTest('free agency page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    const content = page.locator('.ibl-data-table, .ibl-card').first();
    await stableScreenshot(page, content, 'free-agency-content.png', authExpect, 400);
  });

  authTest('desktop navigation bar', async ({ page }) => {
    await page.goto('index.php');
    const nav = page.locator('nav, .ibl-nav, header').first();
    await stableScreenshot(page, nav, 'nav-desktop.png', authExpect);
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
