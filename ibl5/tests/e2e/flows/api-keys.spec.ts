import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// API Keys management flow — tests the full generate → view → revoke cycle.
// Serial: key operations mutate user state.
test.describe.configure({ mode: 'serial' });

test.describe('API Keys flow', () => {
  test('page loads for authenticated user', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');
    await expect(page.locator('.ibl-card__title')).toContainText(/API Key/i);
    await assertNoPhpErrors(page, 'on ApiKeys page');
  });

  test('shows generate button when no key exists', async ({ page }) => {
    // Clean up any existing key first by revoking it
    await page.goto('modules.php?name=ApiKeys');

    const revokeButton = page.getByRole('button', { name: /Revoke Key/i });
    if (await revokeButton.isVisible({ timeout: 1000 }).catch(() => false)) { // e2e-hygiene-allow: cleanup precondition — revoke button presence depends on prior test state
      // Accept the confirmation dialog
      page.once('dialog', (dialog) => dialog.accept());
      await revokeButton.click();
      await page.waitForURL(/name=ApiKeys/);
    }

    // Now should see the generate state
    await page.goto('modules.php?name=ApiKeys');
    await expect(page.getByRole('button', { name: /Generate API Key/i })).toBeVisible();
    await expect(page.getByText("don't have an API key yet")).toBeVisible();
  });

  test('generate key shows raw key once', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    // Click generate
    await page.getByRole('button', { name: /Generate API Key/i }).click();

    // Should see the key generated state
    await expect(page.locator('.ibl-card__title')).toContainText(/Generated/i);
    await expect(page.getByText("won't be shown again")).toBeVisible();

    // The raw key input should contain an ibl_ prefixed key
    const keyInput = page.locator('input.ibl-input[readonly]').first();
    await expect(keyInput).toBeVisible();
    const keyValue = await keyInput.inputValue();
    expect(keyValue).toMatch(/^ibl_[0-9a-f]{32}$/);

    // IMPORTDATA formula should be in the second readonly input
    const formulaInput = page.locator('input.ibl-input[readonly]').nth(1);
    await expect(formulaInput).toHaveValue(/IMPORTDATA/);

    await assertNoPhpErrors(page, 'on ApiKeys generate page');
  });

  test('revisit shows prefix and revoke button', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    // Should see the active key state (not the generate button)
    await expect(page.getByRole('button', { name: /Revoke Key/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Generate API Key/i })).not.toBeVisible();

    // Key prefix should be visible (ibl_ + 4 chars + ...)
    await expect(page.locator('code')).toContainText(/^ibl_[0-9a-f]{4}\.\.\.$/);

    // Should show permission and rate limit info
    await expect(page.getByText('public')).toBeVisible();
    await expect(page.getByText('60 requests/min')).toBeVisible();

    // Player Export Guide link should be present
    await expect(page.getByRole('link', { name: /Player Export Guide/i })).toBeVisible();
  });

  test('revoke key returns to no-key state', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    // Accept the confirmation dialog
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: /Revoke Key/i }).click();

    // Should redirect back to ApiKeys showing the no-key state
    await page.waitForURL(/name=ApiKeys/);
    await expect(page.getByRole('button', { name: /Generate API Key/i })).toBeVisible();
  });

  test('can generate new key after revoking', async ({ page }) => {
    await page.goto('modules.php?name=ApiKeys');

    // Should be in no-key state from previous test
    await page.getByRole('button', { name: /Generate API Key/i }).click();

    // Should see the new key
    await expect(page.locator('.ibl-card__title')).toContainText(/Generated/i);
    const keyInput = page.locator('input.ibl-input[readonly]').first();
    const keyValue = await keyInput.inputValue();
    expect(keyValue).toMatch(/^ibl_[0-9a-f]{32}$/);

    await assertNoPhpErrors(page, 'on ApiKeys regenerate page');
  });
});

async function revokeIfPresent(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('modules.php?name=ApiKeys');
  const revokeButton = page.getByRole('button', { name: /Revoke Key/i });
  if (await revokeButton.isVisible({ timeout: 1000 }).catch(() => false)) { // e2e-hygiene-allow: cleanup precondition — revoke button presence depends on prior test state
    page.once('dialog', (dialog) => dialog.accept());
    await revokeButton.click();
    await page.waitForLoadState('load');
  }
}

test.describe('API Keys direct POST submission', () => {
  test.afterEach(async ({ page }) => {
    await revokeIfPresent(page);
    await page.goto('modules.php?name=ApiKeys');
    await expect(
      page.getByRole('button', { name: /Generate API Key/i }),
    ).toBeVisible();
  });

  test('generate POST returns key shown once', async ({ page, request }) => {
    await revokeIfPresent(page);

    await page.goto('modules.php?name=ApiKeys');
    await expect(page.locator('form[action*="op=generate"]')).toBeVisible();
    const token = await page
      .locator('form[action*="op=generate"] input[name="_csrf_token"]')
      .getAttribute('value');

    const response = await request.post(
      'modules.php?name=ApiKeys&op=generate',
      {
        form: { _csrf_token: token ?? '' },
        maxRedirects: 0,
      },
    );

    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain('API Key Generated');
    expect(body).toContain("won't be shown again");

    const keyMatch = body.match(/value="(ibl_[0-9a-f]{32})"/);
    expect(keyMatch).not.toBeNull();
    const rawKey = keyMatch![1];

    const exactMatches = body.match(/value="ibl_[0-9a-f]{32}"/g);
    expect(exactMatches?.length).toBe(1);

    await page.goto('modules.php?name=ApiKeys');
    await expect(page.locator('code')).toContainText(
      rawKey.substring(0, 8) + '...',
    );

    await assertNoPhpErrors(page, 'after generate submission');
  });

  test('revoke POST removes key from list', async ({ page, request }) => {
    await revokeIfPresent(page);

    await page.goto('modules.php?name=ApiKeys');
    const generateBtn = page.getByRole('button', {
      name: /Generate API Key/i,
    });
    if (await generateBtn.isVisible({ timeout: 1000 }).catch(() => false)) { // e2e-hygiene-allow: setup precondition — generate if no active key exists
      await generateBtn.click();
      await expect(page.locator('.ibl-card__title')).toContainText(
        /Generated/i,
      );
    }

    await page.goto('modules.php?name=ApiKeys');
    await expect(page.locator('form[action*="op=revoke"]')).toBeVisible();
    const token = await page
      .locator('form[action*="op=revoke"] input[name="_csrf_token"]')
      .getAttribute('value');

    const response = await request.post(
      'modules.php?name=ApiKeys&op=revoke',
      {
        form: { _csrf_token: token ?? '' },
        maxRedirects: 0,
      },
    );

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe('modules.php?name=ApiKeys');

    await page.goto('modules.php?name=ApiKeys');
    await expect(
      page.getByRole('button', { name: /Generate API Key/i }),
    ).toBeVisible();
    await expect(
      page.getByRole('button', { name: /Revoke Key/i }),
    ).not.toBeVisible();
  });

  test('generate without CSRF token renders error banner', async ({
    page,
    request,
  }) => {
    await revokeIfPresent(page);

    const response = await request.post(
      'modules.php?name=ApiKeys&op=generate',
      {
        form: {},
        maxRedirects: 0,
      },
    );

    expect(response.status()).toBe(200);
    const body = await response.text();
    expect(body).toContain('Invalid or expired form submission');

    await page.goto('modules.php?name=ApiKeys');
    await expect(
      page.getByRole('button', { name: /Generate API Key/i }),
    ).toBeVisible();
  });
});
