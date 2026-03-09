import { test, expect } from '../fixtures/auth';
import { test as publicTest, expect as publicExpect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Free Agency E2E tests — authenticated.
// Seed data provides 3 free agent players:
//   pid=10: FA Guard on Metros (tid=1, exp=5, bird=4 — Bird Rights) → "Unsigned Free Agents"
//   pid=11: FA Center pure FA (tid=0, exp=8, bird=0) → "All Other Free Agents"
//   pid=12: FA Forward on Stars (tid=2, exp=3, bird=2) → "All Other Free Agents"

// Helper: scope form inputs to the visible custom offer form (not hidden quick-offer forms)
const offerForm = (page: import('@playwright/test').Page) =>
  page.locator('form[name="FAOffer"]').filter({ has: page.locator('input[type="number"]') });

test.describe('Free Agency -- main page', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
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

  test('no PHP errors on main page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Free Agency main page');
  });
});

test.describe('Free Agency -- negotiation page', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
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
    await appState({ 'Current Season Phase': 'Free Agency' });
    // pid=10 has bird=4 (Bird Rights) in CI seed; in production it may differ
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=10');

    const notesCard = page.locator('.ibl-card').filter({
      has: page.locator('.ibl-card__title', { hasText: 'Notes / Reminders' }),
    });
    // Skip if negotiate page doesn't show notes (player may not exist or no form)
    if (await notesCard.count() === 0) {
      test.skip(true, 'Notes card not visible — player may not exist in this environment');
      return;
    }

    const notesText = await notesCard.textContent() ?? '';
    // In CI, pid=10 has Bird Rights: shows "Bird Rights Player on Your Team" + 12.5% raise
    // In production, pid=10 may not have Bird Rights: shows "do not have Bird Rights" + 10% raise
    // Either way, the notes card should mention raise percentage
    expect(notesText).toMatch(/\d+%/);
  });
});

test.describe('Free Agency -- submit and manage offers', () => {
  test.describe.configure({ mode: 'serial' });
  let customOfferSkipped = false;

  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
  });

  test('submit valid 1-year custom offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    // FA Center has exp=8, vet min = 89
    await form.locator('input[name="offeryear1"]').fill('200');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    // May get soft cap error if team is over the cap — check after navigation
    await page.waitForURL(/result=offer_success|error=/);
    if (page.url().includes('error=')) {
      customOfferSkipped = true;
      test.skip(true, 'Team cap space insufficient for custom offer — skipping submit tests');
    }
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('amend existing offer', async ({ page }) => {
    if (customOfferSkipped) {
      test.skip(true, 'Previous submit was skipped — no offer to amend');
    }
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    // Previous offer should pre-fill year 1
    const yr1Input = form.locator('input[name="offeryear1"]');
    const currentValue = await yr1Input.inputValue();
    expect(currentValue).toBe('200');
    // Change offer amount
    await yr1Input.fill('250');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('delete existing offer', async ({ page }) => {
    if (customOfferSkipped) {
      test.skip(true, 'Previous submit was skipped — no offer to delete');
    }
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    await expect(deleteBtn).toBeVisible();
    await deleteBtn.click();
    await page.waitForURL(/result=deleted/);
    await expect(page.locator('.ibl-alert--info')).toBeVisible();
  });

  test('submit valid 2-year custom offer', async ({ page }) => {
    if (customOfferSkipped) {
      test.skip(true, 'Custom offers skip due to cap space — skipping multi-year offer');
    }
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    // yr1=200, yr2=210 (raise=10, under 10% max raise of 20)
    await form.locator('input[name="offeryear1"]').fill('200');
    await form.locator('input[name="offeryear2"]').fill('210');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    await page.waitForURL(/result=offer_success|error=/);
    if (page.url().includes('error=')) {
      test.skip(true, 'Team cap space insufficient for multi-year offer — skipping');
    }
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('offer appears in Contract Offers table on main page', async ({ page }) => {
    if (customOfferSkipped) {
      test.skip(true, 'No offer was submitted — skipping verification');
    }
    // Previous test left a 2-year offer for pid=11 — verify it shows on main page
    await page.goto('modules.php?name=FreeAgency');
    const body = await page.locator('body').textContent() ?? '';
    // Verify the offered salary value appears in the Contract Offers section
    expect(body).toContain('200');
  });

  test('cleanup 2-year offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) {
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });
});

test.describe('Free Agency -- quick offer buttons', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
  });

  test('submit veteran minimum offer', async ({ page }) => {
    // FA Forward pid=12 (exp=3, vet min=61)
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=12');
    // Click the Veterans Exception button — use CSS :has(>) to target the direct parent div
    const vetBtn = page.locator('div:has(> span.ibl-label:text("Veterans Exception:")) .ibl-btn--sm.ibl-btn--primary').first();
    await vetBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('submit MLE offer', async ({ page }) => {
    // FA Center pid=11 — team has HasMLE=1
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const mleBtn = page.locator('div:has(> span.ibl-label:text("Mid-Level Exception")) .ibl-btn--sm.ibl-btn--primary').first();
    await mleBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  // LLE test after MLE — delete MLE offer first so HasMLE remains available
  test('delete MLE offer before LLE test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    await page.getByRole('button', { name: /Delete This Offer/i }).click();
    await page.waitForURL(/result=deleted/);
  });

  test('submit LLE offer', async ({ page }) => {
    // FA Center pid=11 — team has HasLLE=1
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const lleBtn = page.locator('div:has(> span.ibl-label:text("Lower-Level Exception:")) .ibl-btn--sm.ibl-btn--primary').first();
    await lleBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  // Delete LLE offer before max contract test
  test('delete LLE offer before max contract test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    await page.getByRole('button', { name: /Delete This Offer/i }).click();
    await page.waitForURL(/result=deleted/);
  });

  test('submit max contract offer', async ({ page }) => {
    // pid=11: max contract buttons are always present under "Max Level Contract" label
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const maxBtn = page.locator('div:has(> span.ibl-label:text("Max Level Contract")) .ibl-btn--sm.ibl-btn--primary').first();
    await maxBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  // Cleanup: delete all offers made during this describe block
  test.afterAll(async ({ request }) => {
    // Delete offer for pid=11 (max contract or LLE offer)
    await request.post('modules.php?name=FreeAgency&pa=deleteoffer', {
      form: { teamname: 'Metros', playerID: '11' },
    });
    // Delete offer for pid=12 (vet min offer)
    await request.post('modules.php?name=FreeAgency&pa=deleteoffer', {
      form: { teamname: 'Metros', playerID: '12' },
    });
  });
});

test.describe('Free Agency -- validation errors', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    // Use FA Center pid=11 for validation tests
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    // Skip if the negotiate page doesn't show the custom offer form
    // (player may not exist or may be already signed in local DB)
    const formCount = await page.locator('form[name="FAOffer"] input[type="number"]').count();
    if (formCount === 0) {
      test.skip(true, 'Negotiate form not available — player may not exist or is already signed');
    }
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
    await expect(alert).toContainText('maximum allowed');
  });

  test('raise too large between years', async ({ page }) => {
    const form = offerForm(page);
    await form.locator('input[name="offeryear1"]').fill('500');
    await form.locator('input[name="offeryear2"]').fill('700');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent() ?? '';
    // If the team is over the hard cap, the cap space error fires before the raise check
    if (text.includes('cap space')) {
      test.skip(true, 'Team is over the hard cap — raise validation unreachable');
    }
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
    // If the team is over the hard cap, the cap space error fires before the gap check
    if (text.includes('cap space')) {
      test.skip(true, 'Team is over the hard cap — gap validation unreachable');
    }
    expect(text).toContain('gaps in contract years');
  });
});

test.describe('Free Agency -- wrong season phase', () => {
  test('page renders without PHP errors in non-FA phase', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=FreeAgency');
    await assertNoPhpErrors(page, 'in non-FA phase');
  });
});

publicTest.describe('Free Agency -- unauthenticated access', () => {
  publicTest('redirects to login page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    // Unauthenticated users are redirected to YourAccount (login) module
    await page.waitForURL(/name=YourAccount/);
    await publicExpect(page.locator('body')).toBeVisible();
  });
});
