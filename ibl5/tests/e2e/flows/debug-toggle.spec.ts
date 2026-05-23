import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';
import type { Page } from '../fixtures/base';

// All tests in this file write $_SESSION['debug_view_all_extensions'] server-side.
// Parallel execution against the same PHP session causes state races — serial mode required.
test.describe.configure({ mode: 'serial' });

async function clickToggle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle');
  const debugBtn = desktopNav(page).getByRole('button', { name: 'Debug' });
  await debugBtn.click();
  const toggleBtn = page.getByRole('button', { name: /View All Extensions/ });
  await expect(toggleBtn).toBeVisible();
  await toggleBtn.click();
  await page.waitForLoadState('networkidle');
}

async function getToggleLabel(page: Page): Promise<string> {
  await page.waitForLoadState('networkidle');
  const debugBtn = desktopNav(page).getByRole('button', { name: 'Debug' });
  await debugBtn.click();
  const toggleBtn = page.getByRole('button', { name: /View All Extensions/ });
  await expect(toggleBtn).toBeVisible();
  return (await toggleBtn.textContent()) ?? '';
}

async function ensureDebugState(page: Page, desiredOn: boolean): Promise<void> {
  await page.goto('index.php');
  const label = await getToggleLabel(page);
  const isCurrentlyOn = label.includes('ON');
  if (isCurrentlyOn !== desiredOn) {
    const toggleBtn = page.getByRole('button', { name: /View All Extensions/ });
    await toggleBtn.click();
    await page.waitForLoadState('networkidle');
  }
}

async function readDebugCsrfToken(page: Page): Promise<string> {
  const token = await page
    .locator('.debug-toggle-form input[name="_csrf_token"]')
    .first()
    .getAttribute('value');
  return token ?? '';
}

test.describe('Debug toggle', () => {
  test('debug menu is visible for admin user', async ({ page }) => {
    await page.goto('index.php');
    await expect(
      desktopNav(page).getByRole('button', { name: 'Debug' }),
    ).toBeVisible();
  });

  test('debug dropdown shows toggle button', async ({ page }) => {
    await page.goto('index.php');
    const debugBtn = desktopNav(page).getByRole('button', { name: 'Debug' });
    await debugBtn.click();
    await expect(
      page.getByRole('button', { name: /View All Extensions/ }),
    ).toBeVisible();
  });

  test('toggle round-trips, redirects back, and produces no PHP errors', async ({ page }) => {
    // Toggle from homepage: state flips and redirects back
    await page.goto('index.php');
    const initialLabel = await getToggleLabel(page);
    const wasOff = initialLabel.includes('OFF');

    await clickToggle(page);
    await assertNoPhpErrors(page, 'after first toggle');

    const afterFirst = await getToggleLabel(page);
    expect(afterFirst).toContain(wasOff ? 'ON' : 'OFF');

    // Toggle again: state flips back to original
    await clickToggle(page);
    await assertNoPhpErrors(page, 'after second toggle');

    const afterSecond = await getToggleLabel(page);
    expect(afterSecond).toContain(wasOff ? 'OFF' : 'ON');

    // Toggle from a different page: redirects back to that page
    await page.goto('modules.php?name=Standings');
    await expect(page.locator('.ibl-title').first()).toBeVisible();

    await clickToggle(page);
    await expect(page).toHaveURL(/Standings/);
    await assertNoPhpErrors(page, 'after toggle redirect to Standings');
    await expect(page.locator('.ibl-title').first()).toBeVisible();

    // Restore original state
    await clickToggle(page);
  });
});

test.describe('DebugMenu direct POST submission', () => {
  test.afterEach(async ({ page }) => {
    await page.goto('index.php');
    const label = await getToggleLabel(page);
    if (label.includes('ON')) {
      const toggleBtn = page.getByRole('button', {
        name: /View All Extensions/,
      });
      await toggleBtn.click();
      await page.waitForLoadState('networkidle');
    }
    const finalLabel = await getToggleLabel(page);
    expect(finalLabel).toContain('OFF');
  });

  test('direct POST toggles ON, indicator visible', async ({ page }) => {
    await ensureDebugState(page, false);

    const label = await getToggleLabel(page);
    expect(label).toContain('OFF');

    await page.goto('index.php');
    const token = await readDebugCsrfToken(page);

    const response = await page.request.post(
      'modules.php?name=DebugMenu&op=toggle_extensions',
      {
        form: {
          _csrf_token: token,
          redirect: '/ibl5/modules.php?name=Standings',
        },
        maxRedirects: 0,
      },
    );

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe(
      '/ibl5/modules.php?name=Standings',
    );

    await page.goto('modules.php?name=Standings');
    const cookies = await page.context().cookies();
    const debugCookie = cookies.find(
      (c) => c.name === 'ibl_debug_extensions',
    );
    expect(debugCookie).toBeDefined();
    expect(debugCookie!.value).toBe('1');

    const afterLabel = await getToggleLabel(page);
    expect(afterLabel).toContain('ON');

    await assertNoPhpErrors(page, 'after direct POST toggle ON');
  });

  test('direct POST toggles OFF, indicator absent', async ({ page }) => {
    await ensureDebugState(page, true);

    const label = await getToggleLabel(page);
    expect(label).toContain('ON');

    await page.goto('index.php');
    const token = await readDebugCsrfToken(page);

    const response = await page.request.post(
      'modules.php?name=DebugMenu&op=toggle_extensions',
      {
        form: {
          _csrf_token: token,
          redirect: '/ibl5/',
        },
        maxRedirects: 0,
      },
    );

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe('/ibl5/');

    await page.goto('index.php');
    const cookies = await page.context().cookies();
    const debugCookie = cookies.find(
      (c) => c.name === 'ibl_debug_extensions',
    );
    expect(debugCookie).toBeUndefined();

    const afterLabel = await getToggleLabel(page);
    expect(afterLabel).toContain('OFF');

    await assertNoPhpErrors(page, 'after direct POST toggle OFF');
  });

  test('error path: missing CSRF redirects to /ibl5/', async ({ page }) => {
    await page.goto('index.php');

    const response = await page.request.post(
      'modules.php?name=DebugMenu&op=toggle_extensions',
      {
        form: {},
        maxRedirects: 0,
      },
    );

    expect([301, 302, 303]).toContain(response.status());
    expect(response.headers()['location']).toBe('/ibl5/');
  });
});
