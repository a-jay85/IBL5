import { test, expect } from '../../fixtures/auth';
import { test as publicTest, expect as publicExpect } from '../../fixtures/public';
import { assertNoPhpErrors } from '../../helpers/php-errors';

/**
 * Last-Sim Recap Card on the News page.
 *
 * The CI seed (`tests/e2e/fixtures/ci-seed.sql`) populates:
 *   - ibl_sim_dates row: sim 689, 2026-03-01 → 2026-03-07
 *   - ibl_schedule row: Metros (1) @ Cougars (3) on 2026-03-03
 *   - ibl_box_scores_teams pair with quarter scores for that game
 *   - gm_username = `IBL_TEST_USER` mapped to Metros (teamid=1)
 *
 * So the test user (admin GM of Metros) has exactly 1 game in the last sim
 * window — the card should render with 1 tab.
 */

test.describe('Last-Sim Recap card (authenticated GM)', () => {
  test('card renders with 1 tab for GM with games in last sim', async ({ page }) => {
    await page.goto('modules.php?name=News');
    await assertNoPhpErrors(page, 'on News with recap card');

    const card = page.locator('.last-sim-recap');
    await expect(card).toBeVisible();

    const tabs = page.locator('.last-sim-recap__tab');
    await expect(tabs).toHaveCount(1);

    // Tab 0 must be the active tab.
    await expect(tabs.first()).toHaveAttribute('aria-selected', 'true');

    // Active panel for index 0 must be visible; no other panels exist (1 game).
    const panels = page.locator('.last-sim-recap__panel');
    await expect(panels).toHaveCount(1);
    await expect(panels.first()).toBeVisible();
  });

  test('verdict strip shows the game result', async ({ page }) => {
    await page.goto('modules.php?name=News');

    // Metros won 107–91 on 2026-03-03 (seed). Verdict strip is the win variant.
    const verdictStrip = page.locator('.last-sim-recap__strip');
    await expect(verdictStrip).toBeVisible();
    await expect(verdictStrip).toHaveClass(/last-sim-recap__strip(?!--loss)/);
  });
});

// ─────────────────────────────────────────────────────────────────
// Tab keyboard navigation (requires ≥2 tabs to exercise wrap, etc.)
//
// The current CI seed only has 1 schedule row in the sim window. The
// keyboard-nav tests below are runtime-skipped when the card renders
// only a single tab — they exist as forward-compatible coverage for
// when the seed grows additional sim-window games.
// ─────────────────────────────────────────────────────────────────

test.describe('Last-Sim Recap card · tab keyboard nav', () => {
  test('arrow keys move active tab with wraparound', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const tabs = page.locator('.last-sim-recap__tab');
    const count = await tabs.count();
    test.skip(count < 2, 'Need ≥2 tabs to exercise arrow-key nav');

    await tabs.first().focus();
    await page.keyboard.press('ArrowRight');
    await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true');
    await expect(tabs.first()).toHaveAttribute('aria-selected', 'false');

    // ArrowLeft from index 1 → 0; ArrowLeft from 0 wraps to last.
    await page.keyboard.press('ArrowLeft');
    await expect(tabs.first()).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('ArrowLeft');
    await expect(tabs.nth(count - 1)).toHaveAttribute('aria-selected', 'true');
  });

  test('Home/End jump to first/last tab', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const tabs = page.locator('.last-sim-recap__tab');
    const count = await tabs.count();
    test.skip(count < 2, 'Need ≥2 tabs to exercise Home/End nav');

    await tabs.first().focus();
    await page.keyboard.press('End');
    await expect(tabs.nth(count - 1)).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('Home');
    await expect(tabs.first()).toHaveAttribute('aria-selected', 'true');
  });

  test('clicking a tab swaps the visible panel', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const tabs = page.locator('.last-sim-recap__tab');
    const count = await tabs.count();
    test.skip(count < 2, 'Need ≥2 tabs to exercise click-to-switch');

    await tabs.nth(1).click();
    await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true');

    const panel1 = page.locator('.last-sim-recap__panel[data-panel-index="1"]');
    await expect(panel1).toBeVisible();
    const panel0 = page.locator('.last-sim-recap__panel[data-panel-index="0"]');
    await expect(panel0).toBeHidden();
  });
});

// ─────────────────────────────────────────────────────────────────
// Card absent for users without a franchise team
// ─────────────────────────────────────────────────────────────────

publicTest.describe('Last-Sim Recap card · hidden for anonymous', () => {
  publicTest('card is not rendered for anonymous News visitors', async ({ page }) => {
    await page.goto('modules.php?name=News');
    await publicExpect(page.locator('.last-sim-recap')).toHaveCount(0);
  });
});
