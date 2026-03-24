import { test, expect } from '../fixtures/auth';

// Free Agency submission tests — create, amend, delete offers + quick offer buttons.
// Serial: describe blocks share FA offer state (pid=11, pid=12).
// Without this, "submit and manage offers" and "quick offer buttons" interleave,
// causing race conditions where one block's delete removes another block's offer.
test.describe.configure({ mode: 'serial' });

// Helper: scope form inputs to the visible custom offer form (not hidden quick-offer forms)
const offerForm = (page: import('@playwright/test').Page) =>
  page.locator('form[name="FAOffer"]').filter({ has: page.locator('input[type="number"]') });

test.describe('Free Agency -- submit and manage offers', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ appState }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
  });

  test('submit valid 1-year custom offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    // FA Center has exp=8, vet min = 89
    await form.locator('input[name="offeryear1"]').fill('200');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    // CI seed has Metros under soft cap — custom offer must succeed
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('amend existing offer', async ({ page }) => {
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
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    await expect(deleteBtn).toBeVisible();
    await deleteBtn.click();
    await page.waitForURL(/result=deleted/);
    await expect(page.locator('.ibl-alert--info')).toBeVisible();
  });

  test('submit valid 2-year custom offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    // yr1=200, yr2=210 (raise=10, under 10% max raise of 20)
    await form.locator('input[name="offeryear1"]').fill('200');
    await form.locator('input[name="offeryear2"]').fill('210');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    // CI seed has Metros under soft cap — multi-year offer must succeed
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('offer appears in Contract Offers table on main page', async ({ page }) => {
    // Clean up any leftover offer from prior tests before submitting
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const existingDelete = page.getByRole('button', { name: /Delete This Offer/i });
    if (await existingDelete.count() > 0) {
      await existingDelete.click();
      await page.waitForURL(/result=deleted/);
    }

    // Submit a custom offer
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const form = offerForm(page);
    await form.locator('input[name="offeryear1"]').fill('200');
    await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
    // CI seed has Metros under soft cap — offer must succeed
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();

    // Verify the offer row appears in the Contract Offers section on the main page
    await page.goto('modules.php?name=FreeAgency');
    const offersTable = page.locator('table.fa-table', {
      has: page.locator('th', { hasText: 'Contract Offers' }),
    });
    await expect(
      offersTable.locator('a[href*="pa=negotiate&pid=11"]'),
    ).toBeVisible();

    // Cleanup: delete the offer
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) {
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
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
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
  });

  test('submit veteran minimum offer', async ({ page }) => {
    // FA Forward pid=12 (exp=3, vet min=61)
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=12');
    const vetBtn = page.getByTestId('quick-offer-vetmin');
    await vetBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  test('submit MLE offer', async ({ page }) => {
    // FA Center pid=11 — team has HasMLE=1
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const mleBtn = page.getByTestId('quick-offer-mle-yr1');
    await mleBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  // LLE test after MLE — delete MLE offer first so HasMLE remains available
  test('delete MLE offer before LLE test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) {
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });

  test('submit LLE offer', async ({ page }) => {
    // FA Center pid=11 — team has HasLLE=1
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const lleBtn = page.getByTestId('quick-offer-lle');
    await lleBtn.click();
    await page.waitForURL(/result=offer_success/);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
  });

  // Delete LLE offer before max contract test
  test('delete LLE offer before max contract test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) {
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });

  test('submit max contract offer', async ({ page }) => {
    // pid=11: max contract buttons are always present under "Max Level Contract" label
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const maxBtn = page.getByTestId('quick-offer-max-yr1');
    await maxBtn.click();
    // CI seed has Metros under soft cap — max contract offer must succeed
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
