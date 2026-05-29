import { test, expect } from '../fixtures/auth';
import { submitFormAndAssertEffect } from '../helpers/submit-form';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Free Agency submission tests — create, amend, delete offers + quick offer buttons
// + admin clear_offers (block.php).
//
// Serial AND single-file by design: every block here mutates the GLOBAL
// ibl_fa_offers table (pid=11/12 offers, plus the table-wide clear_offers /
// reset-fa-offers). Under Playwright's fullyParallel sharding, two *separate*
// files that both mutate this table run in different workers concurrently, and a
// table-wide wipe (count→0) races against another file's in-flight offer,
// deleting it mid-readback. That is exactly the master CI shard-2 failure the
// PR #884 file-set change exposed: adding spec files reshuffled the shards and
// co-located free-agency-admin-clear-offers.spec.ts with this file.
//
// Fix: this file is the SOLE owner of ibl_fa_offers global mutation. The admin
// clear_offers tests were merged in (and their old file deleted). No other spec
// may reset/clear/submit offers — see bin/check-e2e-fa-offers-owner.
test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ request }) => {
  await request.delete('test-state.php?action=clear-fa-offers');
});

test.afterAll(async ({ request }) => {
  await request.delete('test-state.php?action=reset-fa-offers');
});

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
    await form.locator('input[name="offeryear1"]').fill('200');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
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
    await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
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
    await form.locator('input[name="offeryear1"]').fill('200');
    await form.locator('input[name="offeryear2"]').fill('210');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByRole('button', { name: /Offer.*Free Agent Contract/i }).click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
  });

  test('offer appears in Contract Offers table on main page', async ({ page }) => {
    // Clean up any leftover offer from prior tests before submitting
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const existingDelete = page.getByRole('button', { name: /Delete This Offer/i });
    if (await existingDelete.count() > 0) { // e2e-hygiene-allow: cleanup precondition — element may not exist depending on prior test state
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
    await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();

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
    if (await deleteBtn.count() > 0) { // e2e-hygiene-allow: cleanup precondition — element may not exist depending on prior test state
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });

  test('cleanup 2-year offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) { // e2e-hygiene-allow: cleanup precondition — element may not exist depending on prior test state
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
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=12');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByTestId('quick-offer-vetmin').click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=12');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
  });

  test('submit MLE offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByTestId('quick-offer-mle-yr1').click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
  });

  // LLE test after MLE — delete MLE offer first so HasMLE remains available
  test('delete MLE offer before LLE test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) { // e2e-hygiene-allow: cleanup precondition — element may not exist depending on prior test state
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });

  test('submit LLE offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByTestId('quick-offer-lle').click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
  });

  // Delete LLE offer before max contract test
  test('delete LLE offer before max contract test', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
    const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
    if (await deleteBtn.count() > 0) { // e2e-hygiene-allow: cleanup precondition — element may not exist depending on prior test state
      await deleteBtn.click();
      await page.waitForURL(/result=deleted/);
    }
  });

  test('submit max contract offer', async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await page.getByTestId('quick-offer-max-yr1').click();
        await page.waitForURL(/result=offer_success/);
      },
      expectSameSpot: async () => {
        await expect(page.locator('.ibl-alert--success', { hasText: /offer.*saved/i })).toBeVisible();
      },
      readBack: async () => {
        await page.goto('modules.php?name=FreeAgency&pa=negotiate&pid=11');
        const deleteBtn = page.getByRole('button', { name: /Delete This Offer/i });
        await expect(deleteBtn).toBeVisible();
      },
    });
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

// Admin clear_offers (block.php) — merged from free-agency-admin-clear-offers.spec.ts.
// Kept in this file so its table-wide wipe (count→0) can never run concurrently
// with the offer-submission blocks above (see file header).
test.describe('block.php — Free Agency admin clear_offers flow', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeEach(async ({ request }) => {
    await request.delete('test-state.php?action=reset-fa-offers');
  });

  test('page loads and reports 3 pending offers from seed', async ({
    page,
  }) => {
    const response = await page.goto('block.php');
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page, 'on block.php');
    await expect(
      page.locator('h2', { hasText: /total number of offers:\s*3/i }),
    ).toBeVisible();
  });

  test('clear_offers POST removes all offers from ibl_fa_offers', async ({
    page,
  }) => {
    await page.goto('block.php');
    const csrfToken = await page
      .locator('input[name="_csrf_token"]')
      .first()
      .getAttribute('value');
    expect(csrfToken).toBeTruthy();

    const response = await page.request.post('block.php', {
      form: {
        _csrf_token: csrfToken!,
        action: 'clear_offers',
      },
    });
    expect(response.status()).toBeLessThan(400);

    // Read-back: reload and verify offers are gone
    await page.goto('block.php');
    await assertNoPhpErrors(page, 'after clear_offers');
    await expect(
      page.locator('h2', { hasText: /total number of offers:\s*0/i }),
    ).toBeVisible();
  });

  test('clear_offers without CSRF token fails validation', async ({
    page,
  }) => {
    await page.goto('block.php');
    const response = await page.request.post('block.php', {
      form: { action: 'clear_offers' },
    });
    expect(response.status()).toBeLessThan(400);
    const body = await response.text();
    expect(body).toContain('Security validation failed');

    // Offers should be untouched
    await page.goto('block.php');
    await expect(
      page.locator('h2', { hasText: /total number of offers:\s*3/i }),
    ).toBeVisible();
  });

  test.afterAll(async ({ request }) => {
    await request.delete('test-state.php?action=reset-fa-offers');
  });
});

// Validation errors — merged from free-agency.spec.ts. These submit offers for
// pid=11 and clear the table; as a separate file they raced against the offer
// blocks above. Kept here so all pid=11 / ibl_fa_offers access stays serial.
test.describe('Free Agency -- validation errors', () => {
  test.describe.configure({ mode: 'serial' });

  test.beforeAll(async ({ request }) => {
    await request.delete('test-state.php?action=clear-fa-offers');
  });

  test.afterAll(async ({ request }) => {
    await request.delete('test-state.php?action=reset-fa-offers');
  });

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
