import { test, expect } from '../fixtures/auth';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { offerForm } from '../helpers/free-agency';

// Free Agency E2E tests — read-only rendering and validation.
// Seed data provides 3 free agent players:
//   pid=10: FA Guard on Metros (tid=1, exp=5, bird=4 — Bird Rights) → "Unsigned Free Agents"
//   pid=11: FA Center pure FA (tid=0, exp=8, bird=0) → "All Other Free Agents"
//   pid=12: FA Forward on Stars (tid=2, exp=3, bird=2) → "All Other Free Agents"
// And 1 cash consideration (ibl_cash_considerations table):
//   Cash from Trade on Metros (tid=1) → "Players Under Contract" (not FA tables)
// Submission tests are in free-agency-submission.spec.ts.
// The `offerForm` locator helper is shared via helpers/free-agency.ts.

test.describe('Free Agency -- main page', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=FreeAgency');
  });

  test('main page loads with four table sections', async ({ page }) => {
    // Table headers include team name prefix, e.g. "Metros Players Under Contract"
    await expect(page.getByText('Players Under Contract').first()).toBeVisible();
    await expect(page.getByText('Contract Offers').first()).toBeVisible();
    await expect(page.getByText('Unsigned Free Agents').first()).toBeVisible();
    await expect(page.getByText('All Other Free Agents').first()).toBeVisible();
  });

  test('roster table has team-colored styling', async ({ page }) => {
    const teamTable = page.locator('.team-table').first();
    await expect(teamTable).toBeVisible();
    const style = await teamTable.getAttribute('style');
    expect(style).toContain('--team');
  });

  test('tables use proper scroll wrappers instead of inline styles', async ({ page }) => {
    // Tables 1-3 use .table-scroll-wrapper
    const scrollWrappers = await page.locator('.table-scroll-wrapper').count();
    expect(scrollWrappers).toBeGreaterThanOrEqual(3);
    // No inline overflow-x: auto wrapper divs
    const inlineScrollDivs = await page.locator('div[style*="overflow-x: auto"]').count();
    expect(inlineScrollDivs).toBe(0);
  });

  test('All Other Free Agents uses sticky scroll wrapper', async ({ page }) => {
    const stickyWrapper = page.locator('.sticky-scroll-wrapper.page-sticky');
    await expect(stickyWrapper).toBeVisible();
  });

  test('no empty separator cells in FA tables', async ({ page }) => {
    const emptySepTeam = await page.locator('.fa-table td.sep-team').count();
    const emptySepWeak = await page.locator('.fa-table td.sep-weak').count();
    expect(emptySepTeam + emptySepWeak).toBe(0);
  });

  test('cap space footer shows financial metrics', async ({ page }) => {
    const body = await page.locator('body').textContent();
    expect(body).toContain('Soft Cap Space');
    expect(body).toContain('Hard Cap Space');
    expect(body).toContain('Empty Roster Slots');
  });

  test('MLE and LLE status indicators visible in cap space footer', async ({ page }) => {
    const body = await page.locator('body').textContent();
    expect(body).toContain('MLE:');
    expect(body).toContain('LLE:');
  });

  test('negotiate links present for free agents', async ({ page }) => {
    const count = await page.locator('a[href*="pa=negotiate"]').count();
    expect(count).toBeGreaterThan(0);
  });

  test('unsigned free agents section shows bird rights notation', async ({ page }) => {
    const body = await page.locator('body').textContent();
    expect(body).toContain('Bird Rights');
  });

  test('result=offer_success shows success banner', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&result=offer_success');
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('result=deleted shows info banner', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&result=deleted');
    await expect(page.locator('.ibl-alert--info')).toBeVisible();
  });

  test('result=already_signed shows warning banner', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&result=already_signed');
    await expect(page.locator('.ibl-alert--warning')).toBeVisible();
  });

  test('cash placeholder appears in Players Under Contract', async ({ page }) => {
    // Name abbreviation JS turns "Cash from Trade" into "C.f. Trade" in .fa-table,
    // storing the original in data-full-name. Use the attribute for a stable locator.
    const underContract = page.locator('[aria-label="Players under contract"]');
    await expect(underContract.locator('[data-full-name="Cash from Trade"]')).toBeVisible();
  });

  test('cash placeholder does not appear in Unsigned Free Agents', async ({ page }) => {
    const unsigned = page.locator('[aria-label="Unsigned free agents"]');
    await expect(unsigned.locator('[data-full-name="Cash from Trade"]')).not.toBeVisible();
  });

  test('cash placeholder does not appear in All Other Free Agents', async ({ page }) => {
    const allOther = page.locator('.sticky-scroll-wrapper.page-sticky');
    await expect(allOther.locator('[data-full-name="Cash from Trade"]')).not.toBeVisible();
  });

  test('no PHP errors on main page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Free Agency main page');
  });
});

test.describe('Free Agency -- negotiation page', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    // Navigate to negotiate page for FA Center (pid=11, pure free agent)
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
  });

  test('player info card shows ratings', async ({ page }) => {
    await expect(page.locator('.ibl-card__title').first()).toContainText('Contract Negotiation');
    // Ratings table headers
    await expect(page.locator('th').filter({ hasText: '2ga' }).first()).toBeVisible();
  });

  test('demand display shows player demands', async ({ page }) => {
    await expect(page.getByText('Player Demands').first()).toBeVisible();
    await expect(page.getByText('Yr 1').first()).toBeVisible();
  });

  test('offer form has six year inputs', async ({ page }) => {
    const form = offerForm(page);
    for (let i = 1; i <= 6; i++) {
      const input = form.locator(`input[name="offeryear${i}"]`);
      await expect(input).toBeVisible();
      expect(await input.getAttribute('type')).toBe('number');
    }
  });

  test('quick offer presets present', async ({ page }) => {
    await expect(page.locator('.ibl-card__title').filter({ hasText: 'Quick Offer Presets' })).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).toContain('Max Level Contract');
    expect(body).toContain('Mid-Level Exception');
    expect(body).toContain('Lower-Level Exception');
    expect(body).toContain('Veterans Exception');
  });

  test('notes/reminders card shows rules', async ({ page }) => {
    await expect(page.locator('.ibl-card__title').filter({ hasText: 'Notes / Reminders' })).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).toContain('cap');
  });

  test('no PHP errors on negotiation page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on negotiation page');
  });
});

test.describe('Free Agency -- Bird Rights negotiation', () => {
  test.beforeAll(async ({ request }) => {
    // Seed the Metros offer for pid=10 so hasExistingOffer=true on the negotiate page,
    // bypassing the 0-roster-spots guard (the CI test team is full).
    await request.delete('test-state.php?action=reset-fa-offers');
  });
  // No afterAll cleanup needed: beforeAll seeds the same 3 rows as the ci-seed, so the
  // offers table remains in seed state after this test. clear-fa-offers would race with
  // the MLE offer test in free-agency-submission.spec.ts (both run in parallel workers).

  test('Bird Rights player shows raise info in notes', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    // pid=10 has bird=4 (Bird Rights) in CI seed
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=10');

    const notesCard = page.locator('.ibl-card').filter({
      has: page.locator('.ibl-card__title', { hasText: 'Notes / Reminders' }),
    });
    await expect(notesCard).toBeVisible();

    const notesText = await notesCard.textContent() ?? '';
    // pid=10 has Bird Rights: shows "Bird Rights Player on Your Team" + 12.5% raise
    expect(notesText).toMatch(/\d+%/);
  });
});

// NOTE: the "Free Agency -- validation errors" block moved to
// free-agency-submission.spec.ts. Those tests submit offers for pid=11 and did a
// table-wide clear-fa-offers, which raced against the submission spec's in-flight
// offers under fullyParallel sharding. All ibl_fa_offers mutation now lives in
// that one serial file. See its header for the full rationale.

test.describe('Free Agency -- wrong season phase', () => {
  test('page renders without PHP errors in non-FA phase', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=FreeAgency');
    await assertNoPhpErrors(page, 'in non-FA phase');
  });
});

publicTest.describe('Free Agency -- unauthenticated access', () => {
  publicTest('redirects to login page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=FreeAgency');
    // Unauthenticated users are redirected to YourAccount (login) module
    await page.waitForURL(/name=YourAccount/);
    await publicExpect(page.locator('#login-username'), 'YourAccount login form must render after redirect').toBeVisible();
  });
});
