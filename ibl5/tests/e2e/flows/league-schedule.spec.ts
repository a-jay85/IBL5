import { test, expect } from '../fixtures/auth';
import {
  assertScheduleStructure,
  assertUnplayedGameDash,
  assertPlayedGameScores,
  assertPlayoffPhaseLabels,
} from '../helpers/schedule-page';

const SCHEDULE_URL = 'modules.php?name=Schedule';

// ============================================================
// League Schedule — smoke (Regular Season)
// ============================================================

test.describe('League Schedule — smoke', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('shared schedule structure (title, legend, month nav, game rows, no PHP errors)', async ({ page }) => {
    await assertScheduleStructure(page, { minGames: 3 });
  });

  test('unplayed game shows dash', async ({ page }) => {
    await assertUnplayedGameDash(page);
  });

  test('played games show numeric scores with win class', async ({ page }) => {
    await assertPlayedGameScores(page);
  });

  test('team links point to Team module', async ({ page }) => {
    const teamLink = page.locator('.schedule-game__team-link').first();
    await expect(teamLink).toBeVisible();
    const href = await teamLink.getAttribute('href');
    expect(href).toContain('name=Team');
  });
});

// ============================================================
// League Schedule — Next Games button
// ============================================================

test.describe('League Schedule — Next Games button', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('jump button visible when upcoming games exist', async ({ page }) => {
    const jumpBtn = page.locator('.schedule-jump-btn');
    await expect(jumpBtn).toBeVisible();
  });

  test('upcoming games have highlight class', async ({ page }) => {
    const upcoming = page.locator('.schedule-game--upcoming');
    await expect(upcoming.first()).toBeVisible();
  });
});

// ============================================================
// League Schedule — box score links
// ============================================================

test.describe('League Schedule — box score links', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('played game with game_of_that_day has IBL6 link', async ({ page }) => {
    // Feb 20 game has ibl_box_scores_teams row with game_of_that_day=1
    const ibl6Link = page.locator(
      '.schedule-game a.schedule-game__score-link[href*="boxscore"]',
    );
    await expect(ibl6Link.first()).toBeVisible();
  });

  test('played game with box_id has legacy link', async ({ page }) => {
    // Feb 24 game has box_id=42 but no ibl_box_scores_teams row
    const legacyLink = page.locator(
      '.schedule-game a.schedule-game__score-link[href*=".htm"]',
    );
    await expect(legacyLink.first()).toBeVisible();
  });
});

// ============================================================
// League Schedule — SOS tier dots
// ============================================================

test.describe('League Schedule — SOS tier dots', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('SOS dots appear on unplayed games', async ({ page }) => {
    await expect(
      page
        .locator('.schedule-game__sos-indicator .sos-tier-dot--sm')
        .first(),
    ).toBeVisible();
  });

  test('no SOS dots on played games', async ({ page }) => {
    // Find a played game (has numeric score) and verify no SOS dots
    const playedGames = page.locator(
      '.schedule-game:has(a.schedule-game__score-link)',
    );
    const firstPlayed = playedGames.first();
    await expect(firstPlayed).toBeVisible();
    await expect(
      firstPlayed.locator('.sos-tier-dot--sm'),
    ).toHaveCount(0);
  });
});

// ============================================================
// League Schedule — Playoff phase
// ============================================================

test.describe('League Schedule — Playoff phase', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Playoffs', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('playoff phase labels render without PHP errors', async ({ page }) => {
    await assertPlayoffPhaseLabels(page);
  });

  test('June skipped in month nav', async ({ page }) => {
    const navLinks = page.locator('.schedule-months__link');
    const allTexts = await navLinks.allTextContents();
    const combined = allTexts.join(' ');
    expect(combined).not.toContain('Jun');
  });
});

// NOTE: Invalid teamID (e.g., 9999) is handled by the module's $isValidTeam
// check, falling back to the league schedule. This path is implicitly tested
// by the league schedule tests (no teamID = same fallback path).
