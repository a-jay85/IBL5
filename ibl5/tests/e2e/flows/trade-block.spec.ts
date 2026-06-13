import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Trade Block — read-only browse board + navigation. No mutations here; the
// toggle/submit flow lives in trade-block-submission.spec.ts.

test.describe('Trade Block: browse board', () => {
  test('browse page loads without PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock');
    await assertNoPhpErrors(page, 'on Trade Block browse page');
  });

  test('lists the seeded cross-team available player and seeking note', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock');

    // ci-seed.sql seeds pid=23 "Cougars Guard" (teamid=3) onto the block and a
    // Cougars seeking note — present regardless of test ordering.
    await expect(page.locator('body')).toContainText('Cougars Guard');
    await expect(page.locator('body')).toContainText('Seeking shooting and a backup big');
  });

  test('browse board contains no form elements', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock');
    await expect(page.locator('.trade-block-page form')).toHaveCount(0);
  });
});

test.describe('Trade Block: navigation', () => {
  test('Community browse link and My-Team edit link are present', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock');

    // Browse anchor (Community menu): bare module URL.
    await expect(
      page.locator('a[href="modules.php?name=TradeBlock"]').first(),
    ).toBeAttached();

    // Edit anchor (IBL My-Team menu): op=edit.
    await expect(
      page.locator('a[href="modules.php?name=TradeBlock&op=edit"]').first(),
    ).toBeAttached();
  });
});

test.describe('Trade Block: edit form loads', () => {
  test('edit form has one CSRF token and a checkbox per roster player', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock&op=edit');

    const form = page.locator('form[name="Trade_Block"]');
    await expect(form).toBeVisible();

    // Exactly one CSRF token (bulk single-form design, well under MAX_TOKENS).
    await expect(form.locator('input[name="_csrf_token"]')).toHaveCount(1);

    // At least one roster checkbox + the seeking textarea.
    expect(await form.locator('input[name="on_block[]"]').count()).toBeGreaterThanOrEqual(1);
    await expect(form.locator('textarea[name="seeking_note"]')).toBeVisible();

    await assertNoPhpErrors(page, 'on Trade Block edit page');
  });
});
