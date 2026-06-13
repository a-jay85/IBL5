import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Notification preferences flow — toggle → save → reload persistence.
// Serial: each test mutates the authenticated user's single prefs row.
test.describe.configure({ mode: 'serial' });

test.describe('Notification Settings flow', () => {
  // Matrix #10 — nav entry exists and the page renders the form (defaults).
  test('account menu links to the page and it loads with the form', async ({ page }) => {
    await page.goto('modules.php?name=NextSim');
    // The account-menu link is present in the DOM (may live inside a dropdown).
    await expect(
      page.locator('a[href*="name=NotificationSettings"]'),
    ).toHaveCount(1);

    await page.goto('modules.php?name=NotificationSettings');
    await expect(page.locator('.ibl-card__title')).toContainText(/Notification Preferences/i);
    await expect(page.locator('input[name="notify_trade_offers"]')).toBeVisible();
    await expect(page.locator('input[name="digest_weekly_transactions"]')).toBeVisible();
    await assertNoPhpErrors(page, 'on NotificationSettings page');
  });

  // Matrix #11 — turning a default-OFF digest ON persists across reload.
  test('saving a toggle ON renders it checked after reload', async ({ page }) => {
    await page.goto('modules.php?name=NotificationSettings');

    const digest = page.locator('input[name="digest_weekly_transactions"]');
    await digest.check();
    await page.getByRole('button', { name: /Save preferences/i }).click();
    await page.waitForURL(/name=NotificationSettings/);

    await page.goto('modules.php?name=NotificationSettings');
    await expect(page.locator('input[name="digest_weekly_transactions"]')).toBeChecked();
    await assertNoPhpErrors(page, 'after saving toggle on');
  });

  // Matrix #12 — unchecking a default-ON event toggle persists OFF across reload.
  // Proves checkbox-absent-means-OFF end to end (unchecked boxes are not submitted).
  test('saving a toggle OFF renders it unchecked after reload', async ({ page }) => {
    await page.goto('modules.php?name=NotificationSettings');

    const tradeOffers = page.locator('input[name="notify_trade_offers"]');
    await tradeOffers.uncheck();
    await page.getByRole('button', { name: /Save preferences/i }).click();
    await page.waitForURL(/name=NotificationSettings/);

    await page.goto('modules.php?name=NotificationSettings');
    await expect(page.locator('input[name="notify_trade_offers"]')).not.toBeChecked();
    await assertNoPhpErrors(page, 'after saving toggle off');
  });
});

test.describe('Notification Settings direct POST submission', () => {
  // Matrix #13 — POST without a CSRF token shows the error banner and persists nothing.
  test('save without CSRF token renders error banner', async ({ page, request }) => {
    const response = await request.post(
      'modules.php?name=NotificationSettings&op=save',
      {
        form: { notify_trade_offers: '1' }, // no _csrf_token
        maxRedirects: 0,
      },
    );

    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain('Invalid or expired form submission');

    // The page still loads normally afterward.
    await page.goto('modules.php?name=NotificationSettings');
    await expect(page.locator('.ibl-card__title')).toContainText(/Notification Preferences/i);
  });
});
