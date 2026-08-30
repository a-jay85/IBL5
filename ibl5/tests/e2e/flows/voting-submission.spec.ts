import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { expandVotingCategory } from '../helpers/voting';
import { submitFormAndAssertEffect } from '../helpers/submit-form';
import { getVotes, resetVote } from '../helpers/test-state';

// Voting submission tests — ASG and EOY ballot submission + validation.
// Serial: submission tests mutate server-side vote records.
test.describe.configure({ mode: 'serial' });

// ============================================================
// ASG Voting: submission
// ============================================================

test.describe('ASG Voting: submission', () => {
  test.beforeEach(async ({ request }) => {
    await resetVote(request, 'Metros', 'asg');
  });

  test('submit valid ASG votes', async ({ appState, page, request }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=Voting');

    // Expand all categories and select 4 players per category
    const categories = ['ECF', 'ECB', 'WCF', 'WCB'];

    for (const cat of categories) {
      await expandVotingCategory(page, cat);

      const table = page.locator(`#${cat}`);
      // Wait for table visibility
      await expect(table).toBeVisible();

      // Select first 4 checkboxes
      const checkboxes = table.locator('input[type="checkbox"]');
      const count = await checkboxes.count();
      const toCheck = Math.min(count, 4);
      for (let i = 0; i < toCheck; i++) {
        await checkboxes.nth(i).check();
      }
    }

    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await submitBtn.first().click();
      },
      expectSameSpot: async () => {
        await expect(page.locator('.voting-submission-success')).toBeVisible();
        await expect(page.locator('.voting-submission-success')).toContainText('Thank you for voting');
        await assertNoPhpErrors(page, 'after ASG vote submission');
      },
      readBack: async () => {
        const votes = await getVotes(request, 'Metros');
        expect(votes.asg_voted, 'ASG vote should be recorded in DB').toBe(true);
      },
    });
  });
});

// ============================================================
// ASG Voting: validation errors
// ============================================================

test.describe('ASG Voting: validation errors', () => {
  test('too few ASG votes shows error', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Expand ECF category and select only 2 checkboxes (need 4)
    const ecfHeader = page.locator('.voting-category').first();
    await ecfHeader.click();

    const ecfTable = page.locator('#ECF');
    await expect(ecfTable).toBeVisible();

    const checkboxes = ecfTable.locator('input[type="checkbox"]');
    const count = Math.min(await checkboxes.count(), 2);
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check();
    }

    // Submit with too few votes
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitBtn.first().click();

    await expect(page.locator('body')).toContainText('less than FOUR');
  });

  // Regression: htmx snapshots the DOM into its history cache *between*
  // htmx:beforeRequest and the swap, so the submit-button disable applied in
  // beforeRequest is baked into the snapshot and the afterRequest re-enable
  // never reaches it. Browser Back then restored a ballot whose Submit button
  // was permanently disabled and still read "Submitting…".
  test('submit button is usable after browser Back from a validation error', async ({
    appState,
    page,
  }) => {
    // Witness that the ballot comes back from htmx's history cache rather than
    // a fresh server fetch — a cache miss would render a clean page and make
    // this test pass even with the fix reverted.
    await page.addInitScript(() => {
      (window as unknown as Record<string, unknown>).__iblHistoryRestored = false;
      document.addEventListener('htmx:historyRestore', () => {
        (window as unknown as Record<string, unknown>).__iblHistoryRestored = true;
      });
    });

    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    await expandVotingCategory(page, 'ECF');
    const ecfTable = page.locator('#ECF');
    await expect(ecfTable).toBeVisible();
    await ecfTable.locator('input[type="checkbox"]').first().check();

    await page
      .locator('button, input[type="submit"]')
      .filter({ hasText: /submit votes/i })
      .first()
      .click();
    await expect(page.locator('body')).toContainText('less than FOUR');

    await page.goBack();

    await expect
      .poll(
        () =>
          page.evaluate(
            () => (window as unknown as Record<string, unknown>).__iblHistoryRestored,
          ),
        { message: 'ballot must be restored from the htmx history cache' },
      )
      .toBe(true);

    // Locate by form, not by label — pre-fix the button reads "Submitting…".
    const restoredBtns = page.locator(
      'form[name="ASGVote"] button[type="submit"], form[name="ASGVote"] input[type="submit"]',
    );
    await expect(restoredBtns.first(), 'restored ballot must render a submit button').toBeVisible();
    await expect(restoredBtns.first(), 'submit label must be restored').toHaveText(
      /submit votes/i,
    );
    const disabledCount = await restoredBtns.evaluateAll(
      (els) => els.filter((el) => (el as HTMLButtonElement).disabled).length,
    );
    expect(disabledCount, 'no submit button may stay disabled after Back').toBe(0);
  });

  test('no PHP errors on validation page', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Same flow: expand ECF, select only 2, submit
    const ecfHeader = page.locator('.voting-category').first();
    await ecfHeader.click();

    const ecfTable = page.locator('#ECF');
    await expect(ecfTable).toBeVisible();

    const checkboxes = ecfTable.locator('input[type="checkbox"]');
    const count = Math.min(await checkboxes.count(), 2);
    for (let i = 0; i < count; i++) {
      await checkboxes.nth(i).check();
    }

    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitBtn.first().click();
    await expect(page.locator('body')).toContainText('less than FOUR', { timeout: 10000 });

    await assertNoPhpErrors(page, 'after ASG validation error');
  });
});

// ============================================================
// EOY Voting: validation errors
// ============================================================

test.describe('EOY Voting: validation errors', () => {
  test('missing EOY vote shows error', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Fill ROY, Six, GM but leave MVP empty
    for (const cat of ['ROY', 'Six', 'GM']) {
      await expandVotingCategory(page, cat);

      const table = page.locator(`#${cat}`);
      await expect(table).toBeVisible();

      for (let slot = 1; slot <= 3; slot++) {
        const radios = table.locator(
          `input[type="radio"][name="${cat}[${slot}]"]`,
        );
        const radioCount = await radios.count();
        if (radioCount >= slot) {
          await radios.nth(slot - 1).check();
        }
      }
    }

    // Submit without selecting MVP
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitBtn.first().click();

    await expect(page.locator('body')).toContainText('you must select an MVP');
  });

  test('duplicate EOY selections shows error', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Expand all categories and fill them
    for (const cat of ['MVP', 'Six', 'ROY', 'GM']) {
      await expandVotingCategory(page, cat);

      const table = page.locator(`#${cat}`);
      await expect(table).toBeVisible();

      if (cat === 'MVP') {
        // Set same player for MVP[1] and MVP[2] to trigger duplicate error
        const firstRadio = table.locator(
          'input[type="radio"][name="MVP[1]"]',
        ).first();
        const value = await firstRadio.getAttribute('value');

        // Check the first radio for slot 1
        await firstRadio.check();

        // Check the radio with the same value for slot 2
        const slot2Radio = table.locator(
          `input[type="radio"][name="MVP[2]"][value="${value}"]`,
        );
        await slot2Radio.check();

        // Pick a different player for slot 3
        const slot3Radios = table.locator(
          'input[type="radio"][name="MVP[3]"]',
        );
        await slot3Radios.nth(2).check();
      } else {
        for (let slot = 1; slot <= 3; slot++) {
          const radios = table.locator(
            `input[type="radio"][name="${cat}[${slot}]"]`,
          );
          const radioCount = await radios.count();
          if (radioCount >= slot) {
            await radios.nth(slot - 1).check();
          }
        }
      }
    }

    // Submit
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitBtn.first().click();

    await expect(page.locator('body')).toContainText(
      'same player for multiple MVP slots',
    );
  });
});

// ============================================================
// EOY Voting: submission
// ============================================================

test.describe('EOY Voting: submission', () => {
  test.beforeEach(async ({ request }) => {
    await resetVote(request, 'Metros', 'eoy');
  });

  test('submit valid EOY votes', async ({ appState, page, request }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
      'Current Season Ending Year': '2026',
    });
    await page.goto('modules.php?name=Voting');

    // Expand all categories and select 1st/2nd/3rd for each
    const categories = ['MVP', 'Six', 'ROY', 'GM'];

    for (const cat of categories) {
      await expandVotingCategory(page, cat);

      const table = page.locator(`#${cat}`);
      await expect(table).toBeVisible();

      // For each slot (1st, 2nd, 3rd), pick a different candidate
      for (let slot = 1; slot <= 3; slot++) {
        const radios = table.locator(`input[type="radio"][name="${cat}[${slot}]"]`);
        const count = await radios.count();
        if (count >= slot) {
          await radios.nth(slot - 1).check();
        }
      }
    }

    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });

    await submitFormAndAssertEffect(page, {
      submit: async () => {
        await submitBtn.first().click();
      },
      expectSameSpot: async () => {
        await expect(page.locator('.voting-submission-success')).toBeVisible();
        await expect(page.locator('.voting-submission-success')).toContainText('Thank you for voting');
        await assertNoPhpErrors(page, 'after EOY vote submission');
      },
      readBack: async () => {
        const votes = await getVotes(request, 'Metros');
        expect(votes.eoy_voted, 'EOY vote should be recorded in DB').toBe(true);
      },
    });
  });
});
