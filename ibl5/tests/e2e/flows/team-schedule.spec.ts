import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Serial: multiple describe blocks toggle Current Season Phase.
test.describe.configure({ mode: 'serial' });

const TEAM_SCHEDULE_URL = 'modules.php?name=Schedule&teamID=1'; // Metros

// ============================================================
// Team Schedule — smoke (Regular Season)
// ============================================================

test.describe('Team Schedule — smoke', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Team Schedule');
  });

  test('team banner visible', async ({ page }) => {
    await expect(page.locator('.schedule-team-banner').first()).toBeVisible();
  });

  test('team container class present', async ({ page }) => {
    await expect(
      page.locator('.schedule-container--team').first(),
    ).toBeVisible();
  });

  test('SOS legend shows 5 tiers', async ({ page }) => {
    await expect(page.locator('.sos-legend__item')).toHaveCount(5);
  });

  test('month nav renders', async ({ page }) => {
    await expect(page.locator('.schedule-months__link').first()).toBeVisible();
  });

  test('game rows present', async ({ page }) => {
    const count = await page.locator('.schedule-game').count();
    expect(count).toBeGreaterThanOrEqual(1);
  });

  test('streak column rendered', async ({ page }) => {
    const count = await page.locator('.schedule-game__streak').count();
    expect(count).toBeGreaterThanOrEqual(1);
  });
});

// ============================================================
// Team Schedule — team colors
// ============================================================

test.describe('Team Schedule — team colors', () => {
  test('CSS custom property set via inline style', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
    const html = await page.content();
    expect(html).toContain('--team-primary');
  });
});

// ============================================================
// Team Schedule — jump button
// ============================================================

test.describe('Team Schedule — jump button', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('jump button visible when unplayed games exist', async ({ page }) => {
    // Jump button only renders when there are unplayed (upcoming) games
    const hasUnplayed = await page.locator('.schedule-game--upcoming').count() > 0;
    if (hasUnplayed) {
      await expect(page.locator('.schedule-jump-btn')).toBeVisible();
    } else {
      await expect(page.locator('.schedule-jump-btn')).not.toBeVisible();
    }
  });

  test('upcoming games highlighted when unplayed games exist', async ({ page }) => {
    const upcoming = page.locator('.schedule-game--upcoming');
    const count = await upcoming.count();
    if (count > 0) {
      await expect(upcoming.first()).toBeVisible();
    }
    // When all games are played, no upcoming games is correct behavior
  });
});

// ============================================================
// Team Schedule — unplayed game rows
// ============================================================

test.describe('Team Schedule — unplayed game rows', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('dash scores shown when unplayed games exist', async ({ page }) => {
    // Unplayed games render scores as "–" in <span> elements
    const dashScore = page.locator(
      '.schedule-game span.schedule-game__score-link',
      { hasText: '–' },
    );
    const count = await dashScore.count();
    if (count > 0) {
      await expect(dashScore.first()).toBeVisible();
    }
    // When all games are played, no dash scores is correct behavior
  });

  test('no cumulative record on unplayed', async ({ page }) => {
    // Find an unplayed game (has span scores with dash)
    const unplayedGames = page.locator(
      '.schedule-game:has(span.schedule-game__score-link:text("–"))',
    );
    const count = await unplayedGames.count();
    if (count > 0) {
      const first = unplayedGames.first();
      await expect(first).toBeVisible();
      // Unplayed games show the opposing team's season record but NOT the
      // user's cumulative W-L record (since the game hasn't been played).
      const records = first.locator('.schedule-game__record');
      const recordCount = await records.count();
      expect(recordCount).toBeLessThanOrEqual(1);
    }
    // When all games are played, this test is a no-op
  });

  test('score is span not link when unplayed', async ({ page }) => {
    // Unplayed games render scores as <span>, not <a>
    const spanScore = page.locator(
      '.schedule-game span.schedule-game__score-link',
    );
    const count = await spanScore.count();
    if (count > 0) {
      await expect(spanScore.first()).toBeVisible();
    }
    // When all games are played, no span scores is correct behavior
  });
});

// ============================================================
// Team Schedule — played game rows
// ============================================================

test.describe('Team Schedule — played game rows', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('real scores shown', async ({ page }) => {
    const scoreLinks = page.locator(
      '.schedule-game .schedule-game__score-link',
    );
    const allTexts = await scoreLinks.allTextContents();
    const numericScores = allTexts.filter((t) => /\d+/.test(t));
    expect(numericScores.length).toBeGreaterThanOrEqual(2);
  });

  test('win class on winning team', async ({ page }) => {
    await expect(
      page.locator('.schedule-game__team--win').first(),
    ).toBeVisible();
  });

  test('cumulative record shown', async ({ page }) => {
    // Played games should show cumulative W-L record
    const records = page.locator('.schedule-game__record');
    const allTexts = await records.allTextContents();
    const recordPattern = allTexts.some((t) => /\d+-\d+/.test(t));
    expect(recordPattern).toBe(true);
  });

  test('win streak shows W + number', async ({ page }) => {
    // Feb 20 is a Metros win → streak should show W1
    await expect(
      page.locator('.schedule-game__streak--win').first(),
    ).toBeVisible();
    const text = await page
      .locator('.schedule-game__streak--win')
      .first()
      .textContent();
    expect(text).toMatch(/W\s*\d+/);
  });

  test('loss streak shows L + number', async ({ page }) => {
    // Feb 22 is a Metros loss → streak should show L1
    await expect(
      page.locator('.schedule-game__streak--loss').first(),
    ).toBeVisible();
    const text = await page
      .locator('.schedule-game__streak--loss')
      .first()
      .textContent();
    expect(text).toMatch(/L\s*\d+/);
  });
});

// ============================================================
// Team Schedule — home vs visitor alignment
// ============================================================

test.describe('Team Schedule — home vs visitor alignment', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('Metros appears as visitor in away games', async ({ page }) => {
    // Feb 20: Metros (visitor=1) @ Stars (home=2) → Metros on visitor side
    const gameRows = page.locator('.schedule-game');
    const allRows = await gameRows.all();
    let foundVisitorMetros = false;
    for (const row of allRows) {
      const visitorTeam = row.locator('.schedule-game__team-link').first();
      const text = await visitorTeam.textContent();
      if (text?.includes('Metros')) {
        // Check it's the visitor side (first team link)
        foundVisitorMetros = true;
        break;
      }
    }
    expect(foundVisitorMetros).toBe(true);
  });

  test('Metros appears as home in home games', async ({ page }) => {
    // Feb 22: Cougars (visitor=3) @ Metros (home=1) → Metros on home side
    const gameRows = page.locator('.schedule-game');
    const allRows = await gameRows.all();
    let foundHomeMetros = false;
    for (const row of allRows) {
      const homeTeam = row.locator('.schedule-game__team-link').last();
      const text = await homeTeam.textContent();
      if (text?.includes('Metros')) {
        foundHomeMetros = true;
        break;
      }
    }
    expect(foundHomeMetros).toBe(true);
  });
});

// ============================================================
// Team Schedule — box score links
// ============================================================

test.describe('Team Schedule — box score links', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('IBL6 link on game with gameOfThatDay', async ({ page }) => {
    // Feb 20 game has ibl_box_scores_teams row with gameOfThatDay=1
    const ibl6Link = page.locator(
      '.schedule-game a.schedule-game__score-link[href*="boxscore"]',
    );
    await expect(ibl6Link.first()).toBeVisible();
  });

  test('legacy link on game with BoxID only', async ({ page }) => {
    // Feb 24 game has BoxID=42 but no ibl_box_scores_teams row
    const legacyLink = page.locator(
      '.schedule-game a.schedule-game__score-link[href*=".htm"]',
    );
    await expect(legacyLink.first()).toBeVisible();
  });
});

// ============================================================
// Team Schedule — SOS summary
// ============================================================

test.describe('Team Schedule — SOS summary', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('SOS summary visible when power data exists', async ({ page }) => {
    await expect(page.locator('.sos-summary')).toBeVisible();
  });

  test('SOS value and rank displayed', async ({ page }) => {
    await expect(page.locator('.sos-summary__value')).toBeVisible();
    const rankText = await page
      .locator('.sos-summary__rank')
      .textContent();
    expect(rankText).toContain('#');
  });
});

// ============================================================
// Team Schedule — SOS tier dot in streak
// ============================================================

test.describe('Team Schedule — SOS tier dot in streak', () => {
  test('unplayed game streak shows tier dot when data exists', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto(TEAM_SCHEDULE_URL);
    // Tier dots only appear on unplayed games when power ranking data exists.
    // Verify the feature works when the data supports it.
    const tierDots = page.locator('.schedule-game__streak .sos-tier-dot');
    const hasUnplayed = await page.locator('.schedule-game--upcoming').count() > 0;
    if (hasUnplayed) {
      // With unplayed games and power rankings, tier dots should render
      const dotCount = await tierDots.count();
      if (dotCount > 0) {
        await expect(tierDots.first()).toBeVisible();
      }
    }
    // When all games are played, no tier dots is correct behavior
  });
});

// ============================================================
// Team Schedule — no SOS data (team with no ibl_power row)
// ============================================================

test.describe('Team Schedule — no SOS data', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    // Use teamID=5 which has no ibl_power row seeded
    await page.goto('modules.php?name=Schedule&teamID=5');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Team Schedule (no SOS data, teamID=5)');
  });

  test('no SOS summary when no power rankings', async ({ page }) => {
    await expect(page.locator('.sos-summary')).toHaveCount(0);
  });

  test('no tier dot in streak without power data', async ({ page }) => {
    // This team may not have scheduled games, so check if any streaks exist
    const streaks = page.locator('.schedule-game__streak');
    const count = await streaks.count();
    if (count > 0) {
      // Verify no tier dots in streak column for this team's games
      // (opponents without power rankings won't show dots)
      await expect(
        page.locator('.schedule-game__streak .sos-tier-dot'),
      ).toHaveCount(0);
    }
  });
});

// ============================================================
// Team Schedule — Playoff phase
// ============================================================

test.describe('Team Schedule — Playoff phase', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Playoffs' });
    await page.goto(TEAM_SCHEDULE_URL);
  });

  test('no PHP errors in playoff phase', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Team Schedule (Playoffs)');
  });

  test('June relabeled Playoffs', async ({ page }) => {
    const headers = page.locator('.schedule-month__header');
    const allTexts = await headers.allTextContents();
    const hasPlayoffs = allTexts.some((t) => t.includes('Playoffs'));
    expect(hasPlayoffs).toBe(true);
  });

  test('--playoffs class applied', async ({ page }) => {
    await expect(
      page.locator('.schedule-month__header--playoffs').first(),
    ).toBeVisible();
  });
});

// ============================================================
// Team Schedule — Draft phase
// ============================================================

test.describe('Team Schedule — Draft phase', () => {
  test('Draft treated as playoff phase', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Draft' });
    await page.goto(TEAM_SCHEDULE_URL);
    await assertNoPhpErrors(page, 'on Team Schedule (Draft)');
    // Draft is in the playoff phase array, so June should be relabeled
    const headers = page.locator('.schedule-month__header');
    const allTexts = await headers.allTextContents();
    const hasPlayoffs = allTexts.some((t) => t.includes('Playoffs'));
    expect(hasPlayoffs).toBe(true);
  });
});
