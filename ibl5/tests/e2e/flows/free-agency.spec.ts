import { test, expect } from '../fixtures/auth';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Free Agency E2E tests — read-only rendering and validation.
// Seed data provides 3 free agent players:
//   pid=10: FA Guard on Metros (tid=1, exp=5, bird=4 — Bird Rights) → "Unsigned Free Agents"
//   pid=11: FA Center pure FA (tid=0, exp=8, bird=0) → "All Other Free Agents"
//   pid=12: FA Forward on Stars (tid=2, exp=3, bird=2) → "All Other Free Agents"
// And 1 cash consideration (ibl_cash_considerations table):
//   Cash from Trade on Metros (tid=1) → "Players Under Contract" (not FA tables)
// Submission tests are in free-agency-submission.spec.ts.

// Helper: scope form inputs to the visible custom offer form (not hidden quick-offer forms)
const offerForm = (page: import('@playwright/test').Page) =>
  page.locator('form[name="FAOffer"]').filter({ has: page.locator('input[type="number"]') });

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

test.describe('Free Agency -- validation errors', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    // Use FA Center pid=11 for validation tests
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    // CI seed provides pid=11 as an unsigned free agent — form must be present
    const formCount = await page.locator('form[name="FAOffer"] input[type="number"]').count();
    expect(formCount, 'CI seed must provide negotiate form for pid=11').toBeGreaterThan(0);
  });

  test('zero first year shows error', async ({ page }) => {
    const form = offerForm(page);
    await form.locator('input[name="offeryear1"]').fill('0');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    await expect(page.locator('.ibl-alert--error')).toContainText('must enter an amount greater than zero');
  });

  test('below veteran minimum shows error', async ({ page }) => {
    const form = offerForm(page);
    // FA Center exp=8, vet min=89; offer 1 is below that
    await form.locator('input[name="offeryear1"]').fill('1');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    await expect(page.locator('.ibl-alert--error')).toContainText("Veteran's Minimum");
  });

  test('hard cap exceeded shows error', async ({ page }) => {
    const form = offerForm(page);
    // Offer 9999 — exceeds hard cap space for any team
    await form.locator('input[name="offeryear1"]').fill('9999');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent() ?? '';
    // Either hard cap or max contract error fires (both are valid for 9999)
    expect(text.includes('hard cap') || text.includes('maximum allowed')).toBe(true);
  });

  test('max contract exceeded shows error', async ({ page }) => {
    const form = offerForm(page);
    // Offer above max contract but plausibly under hard cap
    // Max contract for exp 0-6 = 1063, exp 7-9 = 1275, exp 10+ = 1451
    // Use 1500 which exceeds all max contract tiers
    await form.locator('input[name="offeryear1"]').fill('1500');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent() ?? '';
    // Cap space error may fire before max contract check if team is over the cap
    expect(text.includes('maximum allowed') || text.includes('cap space')).toBe(true);
  });

  test('raise too large between years', async ({ page }) => {
    const form = offerForm(page);
    await form.locator('input[name="offeryear1"]').fill('500');
    await form.locator('input[name="offeryear2"]').fill('700');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent() ?? '';
    // CI seed has Metros under hard cap — raise validation fires (not cap space error)
    expect(text).toContain('larger raise than is permitted');
  });

  test('gap in contract years', async ({ page }) => {
    const form = offerForm(page);
    await form.locator('input[name="offeryear1"]').fill('200');
    await form.locator('input[name="offeryear2"]').fill('0');
    await form.locator('input[name="offeryear3"]').fill('200');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent() ?? '';
    // CI seed has Metros under hard cap — gap validation fires (not cap space error)
    expect(text).toContain('gaps in contract years');
  });
});

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
    await publicExpect(page.locator('body')).toBeVisible();
  });
});
