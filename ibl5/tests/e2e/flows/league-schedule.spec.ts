import { test, expect } from '../fixtures/auth';
import {
  assertScheduleStructure,
  assertUnplayedGameDash,
  assertPlayedGameScores,
  assertPlayoffPhaseLabels,
  injectScrollRoom,
  readTargetPosition,
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
// League Schedule — scroll behavior (characterization, jslib extract)
// ============================================================

test.describe('League Schedule — scroll behavior', () => {
  // team-schedule.spec.ts also loads modules.php?name=Schedule in the same shard.
  // Both files use 60s (playwright-gotchas.md, CI & environment).
  test.describe.configure({ timeout: 60000 });

  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(SCHEDULE_URL);
  });

  test('month link scrolls its target to the 70px header offset', async ({ page }) => {
    const monthLink = page.locator('.schedule-months__link').last();
    const href = await monthLink.getAttribute('href');
    expect(href).toMatch(/^#month-\d{4}-\d{2}$/);
    const targetId = (href ?? '').slice(1);

    await injectScrollRoom(page, targetId);
    const before = await readTargetPosition(page, targetId);
    // Precondition: the target starts below the fold, so the click must scroll.
    expect(before.top).toBeGreaterThan(before.viewport);

    await monthLink.click();
    await expect
      .poll(async () => Math.abs((await readTargetPosition(page, targetId)).top - 70), { timeout: 10000 })
      .toBeLessThanOrEqual(2);
  });

  test('Next Games button centres its target vertically', async ({ page }) => {
    const jumpBtn = page.locator('.schedule-jump-btn');
    const href = await jumpBtn.getAttribute('href');
    expect(href).toMatch(/^#game-.+/);
    const targetId = (href ?? '').slice(1);

    await injectScrollRoom(page, targetId);
    const before = await readTargetPosition(page, targetId);
    expect(before.top).toBeGreaterThan(before.viewport);

    await jumpBtn.click();
    await expect
      .poll(async () => {
        const pos = await readTargetPosition(page, targetId);
        return Math.abs(pos.centre - pos.viewport / 2);
      }, { timeout: 10000 })
      .toBeLessThanOrEqual(2);
    // Centred, not header-offset: the top edge sits well below the 70px offset.
    const after = await readTargetPosition(page, targetId);
    expect(Math.abs(after.top - 70)).toBeGreaterThan(20);
  });

  test('script re-injection keeps exactly one scroll handler', async ({ page }) => {
    const monthLink = page.locator('.schedule-months__link').last();
    expect(await monthLink.getAttribute('href')).toMatch(/^#month-\d{4}-\d{2}$/);

    await page.evaluate(() => {
      const spyWindow = window as Window & { __scrollToCalls?: number };
      spyWindow.__scrollToCalls = 0;
      window.scrollTo = (() => {
        spyWindow.__scrollToCalls = (spyWindow.__scrollToCalls ?? 0) + 1;
      }) as typeof window.scrollTo;
    });
    // hx-boost swaps re-run the page's <script src>; simulate two more executions.
    await page.addScriptTag({ url: 'jslib/schedule-scroll.js' });
    await page.addScriptTag({ url: 'jslib/schedule-scroll.js' });

    await monthLink.click();
    const calls = await page.evaluate(
      () => (window as Window & { __scrollToCalls?: number }).__scrollToCalls,
    );
    expect(calls).toBe(1);
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

  test('played game with game_of_that_day has internal GameBoxscore link', async ({ page }) => {
    // Feb 20 game has ibl_box_scores_teams row with game_of_that_day=1
    const boxscoreLink = page.locator(
      '.schedule-game a.schedule-game__score-link[href*="GameBoxscore"]',
    );
    await expect(boxscoreLink.first()).toBeVisible();
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
