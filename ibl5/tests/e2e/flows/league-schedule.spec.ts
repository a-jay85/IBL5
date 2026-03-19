import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Serial: multiple describe blocks toggle Current Season Phase.
test.describe.configure({ mode: 'serial' });

const SCHEDULE_URL = 'modules.php?name=Schedule';

// ============================================================
// League Schedule — smoke (Regular Season)
// ============================================================

test.describe('League Schedule — smoke', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(SCHEDULE_URL);
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on League Schedule');
  });

  test('sim length note visible', async ({ page }) => {
    await expect(page.locator('.schedule-highlight-note')).toBeVisible();
  });

  test('SOS legend shows 5 tiers', async ({ page }) => {
    await expect(page.locator('.sos-legend__item')).toHaveCount(5);
  });

  test('month nav renders', async ({ page }) => {
    await expect(page.locator('.schedule-months__link').first()).toBeVisible();
  });

  test('game rows present', async ({ page }) => {
    const count = await page.locator('.schedule-game').count();
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('unplayed game shows dash', async ({ page }) => {
    // Unplayed games render scores as "–" in <span> elements
    const dashScore = page.locator(
      '.schedule-game span.schedule-game__score-link',
      { hasText: '–' },
    );
    await expect(dashScore.first()).toBeVisible();
  });

  test('team links point to Team module', async ({ page }) => {
    const teamLink = page.locator('.schedule-game__team-link').first();
    await expect(teamLink).toBeVisible();
    const href = await teamLink.getAttribute('href');
    expect(href).toContain('name=Team');
  });

  test('team logos present', async ({ page }) => {
    await expect(page.locator('.schedule-game__logo').first()).toBeAttached();
  });
});

// ============================================================
// League Schedule — Next Games button
// ============================================================

test.describe('League Schedule — Next Games button', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(SCHEDULE_URL);
  });

  test('jump button visible when upcoming games exist', async ({ page }) => {
    await expect(page.locator('.schedule-jump-btn')).toBeVisible();
  });

  test('upcoming games have highlight class', async ({ page }) => {
    await expect(
      page.locator('.schedule-game--upcoming').first(),
    ).toBeVisible();
  });
});

// ============================================================
// League Schedule — played games
// ============================================================

test.describe('League Schedule — played games', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(SCHEDULE_URL);
  });

  test('played game shows numeric scores', async ({ page }) => {
    // Feb 20 game: Metros 105 @ Stars 98
    const scoreLinks = page.locator(
      '.schedule-game .schedule-game__score-link',
    );
    const allTexts = await scoreLinks.allTextContents();
    const numericScores = allTexts.filter((t) => /\d+/.test(t));
    expect(numericScores.length).toBeGreaterThanOrEqual(2);
  });

  test('winning team has win class', async ({ page }) => {
    await expect(
      page.locator('.schedule-game__team--win').first(),
    ).toBeVisible();
  });

  test('played game with gameOfThatDay has IBL6 link', async ({ page }) => {
    // Feb 20 game has ibl_box_scores_teams row with gameOfThatDay=1
    const ibl6Link = page.locator(
      '.schedule-game a.schedule-game__score-link[href*="boxscore"]',
    );
    await expect(ibl6Link.first()).toBeVisible();
  });

  test('played game with BoxID has legacy link', async ({ page }) => {
    // Feb 24 game has BoxID=42 but no ibl_box_scores_teams row
    const legacyLink = page.locator(
      '.schedule-game a.schedule-game__score-link[href*=".htm"]',
    );
    await expect(legacyLink.first()).toBeVisible();
  });

  test('unplayed game score is span not link', async ({ page }) => {
    // March unplayed games should render as spans, not <a> tags
    const unplayedSpan = page.locator(
      '.schedule-game span.schedule-game__score-link',
    );
    await expect(unplayedSpan.first()).toBeVisible();
  });
});

// ============================================================
// League Schedule — SOS tier dots
// ============================================================

test.describe('League Schedule — SOS tier dots', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
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
    await appState({ 'Current Season Phase': 'Playoffs' });
    await page.goto(SCHEDULE_URL);
  });

  test('no PHP errors in playoff phase', async ({ page }) => {
    await assertNoPhpErrors(page, 'on League Schedule (Playoffs)');
  });

  test('June relabeled Playoffs', async ({ page }) => {
    // In playoff phase, the first month header should be "Playoffs" (June moved to front)
    const firstHeader = page.locator('.schedule-month__header').first();
    await expect(firstHeader).toContainText('Playoffs');
  });

  test('Playoffs header has --playoffs class', async ({ page }) => {
    await expect(
      page.locator('.schedule-month__header--playoffs').first(),
    ).toBeVisible();
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
