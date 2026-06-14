/**
 * GM Dashboard E2E flow spec.
 *
 * Owner-scoped module: the CI auth user maps to Metros (teamid=1) via
 * getTeamnameFromUsername(). The module resolves owner from the session
 * cookie — never from a ?teamid= request param.
 *
 * ci-seed.sql grounding:
 *   - line 71:  tid=1, team_name='Metros'
 *   - line 294: pid=10 'FA Guard', teamid=1, pos='SG', cy=0, salary_yr1=0
 *               → nextYearSalary = salary_yr[cy+1] = salary_yr1 = 0 → upcoming FA
 *   - lines 1459-1460: only pid=5 (Stars tid=2) and pid=7 (Phoenixes tid=14) injured;
 *                      no Metros player has injured > 0 → injuries card renders empty-state
 *   - lines 2159-2161: 'All-Star Game recap' is the last-inserted nuke_stories row (highest
 *                      auto-increment sid); buildNews() sorts by sid DESC and slices to 5,
 *                      so this title is always first in the League News list
 *   - lines 893-914: trade offers 1-6 involve Metros but are consumed by parallel
 *                    trading-submission specs → no count assertion, only assert section renders
 */
import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

const MODULE_URL = 'modules.php?name=GMDashboard';

// All authenticated tests use the stored auth session (CI user = Metros, tid=1).
// Pin the season phase to avoid flakiness from concurrent phase-mutating tests.
test.describe('GM Dashboard — authenticated (Metros tid=1)', () => {
  test.beforeEach(async ({ page, appState }) => {
    // Pin phase so NextSim and Cap Space resolve the same season as the seed.
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto(MODULE_URL);
  });

  // Matrix #13: route renders + no PHP errors
  test('page renders Metros Dashboard title without PHP errors', async ({ page }) => {
    // ci-seed.sql line 71: team_name = 'Metros' for tid=1
    await expect(page.locator('h1.ibl-title')).toContainText('Metros Dashboard');
    await assertNoPhpErrors(page, 'on GMDashboard');
  });

  // Matrix #14: trades section heading renders
  // Offers 1-6 involve Metros (ci-seed lines 893-914) but are consumed by parallel
  // trading-submission specs — assert only the card heading and link, not the count.
  test('Pending Trades section heading and link render', async ({ page }) => {
    const card = page.locator('section.gm-dashboard-card').filter({
      has: page.locator('h2.ibl-title', { hasText: 'Pending Trades' }),
    });
    await expect(card.locator('h2.ibl-title')).toBeVisible();
    await expect(card.locator('.gm-dashboard-link a')).toBeVisible();
  });

  // Matrix #15: next-sim section heading renders
  // Whether a game is scheduled is not verified by seed → assert heading only.
  test('Next Sim section heading and link render', async ({ page }) => {
    const card = page.locator('section.gm-dashboard-card').filter({
      has: page.locator('h2.ibl-title', { hasText: 'Next Sim' }),
    });
    await expect(card.locator('h2.ibl-title')).toBeVisible();
    await expect(card.locator('.gm-dashboard-link a')).toBeVisible();
  });

  // Matrix #16: cap headroom numeric strong renders
  // Value depends on seed salaries and cap ceiling — only assert label + numeric content.
  test('Cap Space section shows cap headroom label and a number', async ({ page }) => {
    const card = page.locator('section.gm-dashboard-card').filter({
      has: page.locator('h2.ibl-title', { hasText: 'Cap Space' }),
    });
    await expect(card.locator('.gm-dashboard-stat')).toContainText('Cap headroom:');
    await expect(card.locator('.gm-dashboard-stat strong')).toBeVisible();
  });

  // Matrix #17: FA section lists "FA Guard"
  // ci-seed line 294: pid=10, name='FA Guard', teamid=1, pos='SG', cy=0, salary_yr1=0
  // FreeAgencyPreviewService: nextYearSalary = salary_yr[cy+1] = salary_yr1 = 0 → upcoming FA.
  // DashboardView renders each FA as "<li>{pos} {name}</li>" → "SG FA Guard".
  test('Upcoming Free Agents section lists FA Guard for Metros', async ({ page }) => {
    const card = page.locator('section.gm-dashboard-card').filter({
      has: page.locator('h2.ibl-title', { hasText: 'Upcoming Free Agents' }),
    });
    await expect(card.locator('.gm-dashboard-list li', { hasText: 'FA Guard' })).toBeVisible();
  });

  // Matrix #18: injuries empty-state
  // ci-seed lines 1459-1460: only pid=5 (Stars tid=2, injured=5) and pid=7
  // (Phoenixes tid=14, injured=3) are injured; no Metros player has injured > 0.
  test('Injuries section shows empty-state because no Metros players are injured', async ({ page }) => {
    await expect(page.getByText('No injured players on your roster.')).toBeVisible();
  });

  // Matrix #19: news section shows the highest-sid headline
  // buildNews() flattens nuke_stories by topic, sorts by sid DESC, slices to 5.
  // ci-seed lines 2159-2161: 'All-Star Game recap' is inserted last → highest sid → first item.
  test('League News section shows All-Star Game recap as first headline', async ({ page }) => {
    const card = page.locator('section.gm-dashboard-card').filter({
      has: page.locator('h2.ibl-title', { hasText: 'League News' }),
    });
    await expect(card.locator('.gm-dashboard-list li').first()).toContainText('All-Star Game recap');
  });

  // Matrix #20: ?teamid=2 param is ignored — still resolves Metros from session
  test('teamid query param is ignored — still renders Metros Dashboard', async ({ page }) => {
    await page.goto(`${MODULE_URL}&teamid=2`);
    // Owner identity comes from the session cookie, never from request params.
    // ci-seed line 71: tid=1 is Metros; tid=2 is Stars.
    await expect(page.locator('h1.ibl-title')).toContainText('Metros Dashboard');
  });
});

// Matrix #21: unauthenticated → JS redirect to login page
// loginbox() emits: window.location.href = "modules.php?name=YourAccount"
test.describe('GM Dashboard — unauthenticated', () => {
  test.use({ storageState: publicStorageState() });

  test('unauthenticated request redirects to YourAccount login', async ({ page }) => {
    await page.goto(MODULE_URL);
    // Same pattern as smoke/auth-redirect.spec.ts — loginbox() is a JS redirect.
    await page.waitForURL(/name=YourAccount/, { timeout: 10_000 });
    await expect(page.locator('#login-username')).toBeVisible();
  });
});
