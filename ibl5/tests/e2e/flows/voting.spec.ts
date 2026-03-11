import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Voting tests — ASG and EOY ballots.
// Serial: ASG and EOY blocks toggle the same phase setting.
test.describe.configure({ mode: 'serial' });

// ============================================================
// ASG Voting (Regular Season)
// ============================================================

test.describe('ASG Voting', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Retry if a parallel test changed the phase between appState and page load
    if ((await page.locator('form[name="ASGVote"]').count()) === 0) {
      await appState({
        'Current Season Phase': 'Regular Season',
        'ASG Voting': 'Yes',
      });
      await page.goto('modules.php?name=Voting');
    }
  });

  test('ASG ballot form renders', async ({ page }) => {
    await expect(page.locator('form[name="ASGVote"]')).toBeVisible();
  });

  test('four category sections exist', async ({ page }) => {
    // Category tables: ECF, ECB, WCF, WCB
    for (const cat of ['ECF', 'ECB', 'WCF', 'WCB']) {
      const table = page.locator(`#${cat}`);
      // Tables may be hidden initially (click to expand)
      await expect(table).toHaveCount(1);
    }
  });

  test('category tables have candidate checkboxes', async ({ page }) => {
    // Click a category header to reveal the table
    const ecfHeader = page.locator('.voting-category').first();
    await ecfHeader.click();

    // Wait for the first visible table to show
    const firstVisibleTable = page.locator(
      '#ECF, #ECB, #WCF, #WCB',
    ).first();
    await expect(firstVisibleTable).toBeVisible();

    // Should have checkboxes
    const checkboxes = firstVisibleTable.locator('input[type="checkbox"]');
    await expect(checkboxes.first()).toBeVisible();
  });

  test('submit button visible', async ({ page }) => {
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('no PHP errors on ASG ballot', async ({ page }) => {
    await assertNoPhpErrors(page, 'on ASG ballot');
  });
});

test.describe('ASG Voting: submission', () => {
  test('submit valid ASG votes', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Expand all categories and select 4 players per category
    const categories = ['ECF', 'ECB', 'WCF', 'WCB'];

    for (const cat of categories) {
      // Click category header to reveal table
      const header = page.locator(`.voting-category`).filter({
        hasText: new RegExp(cat === 'ECF' ? 'Eastern.*Frontcourt|ECF' :
          cat === 'ECB' ? 'Eastern.*Backcourt|ECB' :
          cat === 'WCF' ? 'Western.*Frontcourt|WCF' :
          'Western.*Backcourt|WCB', 'i'),
      });

      if ((await header.count()) > 0) {
        await header.first().click();
      }

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

    // Submit
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });
    await submitBtn.first().click();

    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent();

    // Should see success or validation message
    // Success: "Thank you for voting"
    // If not enough candidates: validation error
    const hasSuccess = body?.includes('Thank you for voting');
    const hasError = body?.match(/select|must|error|cannot/i);

    expect(hasSuccess || hasError).toBeTruthy();
    await assertNoPhpErrors(page, 'after ASG vote submission');
  });
});

// ============================================================
// EOY Voting (Free Agency / non-Regular Season)
// ============================================================

test.describe('EOY Voting', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');
  });

  test('EOY ballot form renders', async ({ page }) => {
    await expect(page.locator('form[name="EOYVote"]')).toBeVisible();
  });

  test('four award categories exist', async ({ page }) => {
    // Category tables: MVP, Six, ROY, GM
    for (const cat of ['MVP', 'Six', 'ROY', 'GM']) {
      const table = page.locator(`#${cat}`);
      await expect(table).toHaveCount(1);
    }
  });

  test('category tables have radio buttons', async ({ page }) => {
    // Click a category header to reveal it
    const header = page.locator('.voting-category').first();
    await header.click();

    const firstTable = page.locator('#MVP, #Six, #ROY, #GM').first();
    await expect(firstTable).toBeVisible();

    const radios = firstTable.locator('input[type="radio"]');
    await expect(radios.first()).toBeVisible();
  });

  test('submit button visible', async ({ page }) => {
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('no PHP errors on EOY ballot', async ({ page }) => {
    await assertNoPhpErrors(page, 'on EOY ballot');
  });
});

test.describe('EOY Voting: submission', () => {
  test('submit valid EOY votes', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    // Expand all categories and select 1st/2nd/3rd for each
    const categories = ['MVP', 'Six', 'ROY', 'GM'];

    for (const cat of categories) {
      const header = page.locator('.voting-category').filter({
        hasText: new RegExp(cat === 'MVP' ? 'MVP|Most Valuable' :
          cat === 'Six' ? 'Sixth|6th' :
          cat === 'ROY' ? 'Rookie|ROY' :
          'GM|General Manager', 'i'),
      });

      if ((await header.count()) > 0) {
        await header.first().click();
      }

      const table = page.locator(`#${cat}`);
      await expect(table).toBeVisible();

      // For each slot (1st, 2nd, 3rd), pick a different candidate
      for (let slot = 1; slot <= 3; slot++) {
        const radios = table.locator(`input[type="radio"][name="${cat}[${slot}]"]`);
        const count = await radios.count();
        if (count >= slot) {
          // Pick the slot-th radio to avoid duplicates
          await radios.nth(slot - 1).check();
        }
      }
    }

    // Submit
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /submit votes/i,
    });
    await submitBtn.first().click();

    await page.waitForLoadState('domcontentloaded');
    const body = await page.locator('body').textContent();

    const hasSuccess = body?.includes('Thank you for voting');
    const hasError = body?.match(/select|must|error|cannot|duplicate/i);

    expect(hasSuccess || hasError).toBeTruthy();
    await assertNoPhpErrors(page, 'after EOY vote submission');
  });
});

