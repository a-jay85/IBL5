import { test, expect } from './fixtures/auth';
import { assertNoPhpErrors } from './helpers/php-errors';
import { desktopNav } from './helpers/navigation';

/**
 * In-app GM notification inbox — end-to-end.
 *
 * All tests run SERIALLY against ONE owner (Metros, teamid=1) because they share
 * and mutate the same gm_notifications rows. Running in parallel (fullyParallel
 * is on) would race the unread-count assertions against the mark-read mutations.
 * No other spec file touches gm_notifications, so cross-file isolation holds.
 *
 * Seed grounding (tests/e2e/fixtures/ci-seed.sql): team_id=1 (the CI E2E user's
 * Metros) has 3 unread rows (ids 1,2,3); team_id=2 has 1 unread row (id=4) used
 * by the authz-negative test. Migration 146 creates the table before the seed.
 *
 * Verification matrix: #11, #12, #14, #15, #16, #17, #19, #20.
 */
test.describe.configure({ mode: 'serial' });

test.describe('GM notification inbox', () => {
  test('Community menu exposes a Notifications link for the owner (#15)', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on index.php');

    await desktopNav(page).getByRole('button', { name: 'Community' }).click();
    const link = page.locator('a[href="modules.php?name=Notifications"]').first();
    await expect(link).toBeVisible();
  });

  test('owner can load the Notifications module and see the list (#14)', async ({ page }) => {
    await page.goto('modules.php?name=Notifications');
    await assertNoPhpErrors(page, 'on Notifications');

    await expect(page.locator('.notification-list')).toBeVisible();
    await expect(page.getByText('Stars sent you a trade offer.')).toBeVisible();
  });

  test('bell shows the unread-count badge (#19)', async ({ page }) => {
    await page.goto('modules.php?name=Notifications');
    await assertNoPhpErrors(page, 'on Notifications');

    await expect(page.locator('.notification-bell__badge').first()).toHaveText('3');
    await expect(page.locator('.notification-card--unread')).toHaveCount(3);
  });

  test('mark POST without a valid CSRF token is rejected, unread unchanged (#11)', async ({ page }) => {
    const response = await page.request.post('modules.php?name=Notifications&op=mark', {
      form: { _csrf_token: 'not-a-real-token', id: '2' },
      maxRedirects: 0,
    });

    expect(response.status()).toBe(302);
    expect(response.headers()['location'] ?? '').toContain('error=');

    // No state change: still 3 unread.
    await page.goto('modules.php?name=Notifications');
    await expect(page.locator('.notification-card--unread')).toHaveCount(3);
  });

  test("marking another team's notification id affects nothing (#12)", async ({ page }) => {
    await page.goto('modules.php?name=Notifications');
    // Scrape the shared notif_mark token from a real mark form.
    const token = await page
      .locator('form[action$="op=mark"] input[name="_csrf_token"]')
      .first()
      .getAttribute('value');
    expect(token).toMatch(/^[0-9a-f]{64}$/);

    // id=4 belongs to team_id=2. The repo's WHERE team_id=<session> scopes the
    // update, so this forged id touches zero of the owner's rows.
    const response = await page.request.post('modules.php?name=Notifications&op=mark', {
      form: { _csrf_token: token!, id: '4' },
      maxRedirects: 0,
    });
    expect(response.status()).toBe(302);

    // Owner's unread count is unchanged (still 3).
    await page.goto('modules.php?name=Notifications');
    await expect(page.locator('.notification-card--unread')).toHaveCount(3);
  });

  test('marking one notification read decrements the unread count (#16)', async ({ page }) => {
    await page.goto('modules.php?name=Notifications');

    await Promise.all([
      page.waitForURL('**/modules.php?name=Notifications'),
      page.locator('form[action$="op=mark"] button[type="submit"]').first().click(),
    ]);

    await expect(page.locator('.notification-card--unread')).toHaveCount(2);
    await expect(page.locator('.notification-bell__badge').first()).toHaveText('2');
  });

  test('mark all read clears every unread row and the badge (#17, #20)', async ({ page }) => {
    await page.goto('modules.php?name=Notifications');

    await Promise.all([
      page.waitForURL('**/modules.php?name=Notifications'),
      page.locator('form[action*="op=mark_all"] button[type="submit"]').first().click(),
    ]);

    await expect(page.locator('.notification-card--unread')).toHaveCount(0);
    // Bell still present, but no count badge when zero unread.
    await expect(page.locator('.notification-bell').first()).toBeVisible();
    await expect(page.locator('.notification-bell__badge')).toHaveCount(0);
  });
});
