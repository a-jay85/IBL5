import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

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
