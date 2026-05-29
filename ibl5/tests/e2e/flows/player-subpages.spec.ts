import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { resetRookieOption } from '../helpers/cleanup';

// Player sub-pages — routes beyond the main showpage view.
// These test articles.php, negotiate, rookieoption, and extension.php.

test.describe('Player articles sub-page', () => {
  test('articles page renders for known player name', async ({ page }) => {
    // "Test Player" appears in seeded nuke_stories titles.
    // articles.php searches hometext/bodytext with LIKE — may return 0 results
    // if player name only appears in titles. Either way, no PHP errors.
    await page.goto(
      'modules.php?name=Player&file=articles&player=Test+Player',
    );
    await assertNoPhpErrors(page, 'on Player articles page');
  });

  test('articles page with empty player param shows no PHP errors', async ({
    page,
  }) => {
    await page.goto('modules.php?name=Player&file=articles&player=');
    await assertNoPhpErrors(page, 'on Player articles with empty player');
  });

  test('articles page with nonexistent player shows no PHP errors', async ({
    page,
  }) => {
    await page.goto(
      'modules.php?name=Player&file=articles&player=ZZZ+Nonexistent+Name',
    );
    await assertNoPhpErrors(page, 'on Player articles for nonexistent player');
  });
});

test.describe('Player negotiate sub-page', () => {
  test('negotiate page renders during Free Agency', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=1');
    await assertNoPhpErrors(page, 'on Player negotiate page');

    await expect(
      page.locator('h2.ibl-title').filter({ hasText: 'Contract Extension' }),
    ).toBeVisible();
    await expect(page.locator('.ibl-alert--error').first()).toBeVisible();
  });

  test('negotiate with invalid PID shows no PHP errors', async ({
    appState,
    page,
  }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=99999');
    await assertNoPhpErrors(page, 'on negotiate with invalid PID');
  });
});

test.describe('Player rookie option sub-page', () => {
  test('rookie option page renders for non-eligible player', async ({
    page,
  }) => {
    // pid=1 (Test Player) is not rookie-eligible — expect ineligibility error
    await page.goto('modules.php?name=Player&pa=rookieoption&pid=1');
    await assertNoPhpErrors(page, 'on Player rookie option page');

    // Should show either the form or an ineligibility alert
    const alert = page.locator('.ibl-alert--error');
    const form = page.locator('form');
    const hasAlert = (await alert.count()) > 0;
    const hasForm = (await form.count()) > 0;
    expect(hasAlert || hasForm).toBe(true);
  });

  test('error message explains why option is unavailable', async ({
    page,
  }) => {
    await page.goto('modules.php?name=Player&pa=rookieoption&pid=1');
    const alert = page.locator('.ibl-alert--error');
    await expect(alert).toBeVisible();
    const text = await alert.textContent();
    // Could be ownership error ("not on your team") or eligibility error
    expect(text).toMatch(/not eligible|rookie option|not on your team/i);
  });

  test('rookie option with invalid PID shows no PHP errors', async ({
    page,
  }) => {
    await page.goto('modules.php?name=Player&pa=rookieoption&pid=99999');
    await assertNoPhpErrors(page, 'on rookie option with invalid PID');
  });

  test.afterAll(async ({ request }) => {
    await resetRookieOption(request, 200000032);
  });

  test('POST to processrookieoption with eligible round-1 player succeeds', async ({
    appState,
    request,
  }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    const response = await request.post(
      '/ibl5/modules.php?name=Player&pa=processrookieoption',
      {
        form: {
          teamname: 'Metros',
          playerID: '200000032',
          rookieOptionValue: '1000',
          from: '',
        },
        maxRedirects: 0,
      },
    );
    const location = response.headers()['location'] ?? '';
    expect(
      location.includes('result=rookie_option_success') ||
        location.includes('result=email_failed'),
    ).toBe(true);
    expect(location).not.toContain('error=');
  });

  // Success path is covered above (pid=200000032, Free Agency phase).
  // The reset-rookie-option endpoint restores seed values after the success test.
  test('POST to processrookieoption with ineligible player returns error redirect', async ({
    appState,
    request,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Current Season Ending Year': '2026',
    });

    const response = await request.post(
      '/ibl5/modules.php?name=Player&pa=processrookieoption',
      {
        form: {
          teamname: 'Metros',
          playerID: '25',
          rookieOptionValue: '1000',
          from: '',
        },
        maxRedirects: 0,
      },
    );
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('error=');
    expect(location).toContain('pa=rookieoption');
  });
});

test.describe('Player extension sub-page (POST handler)', () => {
  test('GET request to extension.php shows no PHP fatal errors', async ({
    page,
  }) => {
    // extension.php is a POST handler — GET should not cause a fatal error
    await page.goto('modules.php?name=Player&file=extension');
    await assertNoPhpErrors(page, 'on Player extension GET request');
  });
});

test.describe('Player invalid route', () => {
  test('nonexistent pa= route shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Player&pa=nonexistent');
    await assertNoPhpErrors(page, 'on Player nonexistent route');
  });

  test('missing pa= and pid= shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=Player');
    await assertNoPhpErrors(page, 'on Player with no params');
  });
});
