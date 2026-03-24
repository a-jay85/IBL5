import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Voting tests — ASG and EOY ballot rendering (read-only).
// Submission tests are in voting-submission.spec.ts.

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
