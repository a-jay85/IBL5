import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';
import type { Page } from '@playwright/test';

test.describe.configure({ mode: 'serial' });

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

    const cookies = await page.context().cookies();
    const debugCookie = cookies.find(
      (c) => c.name === 'ibl_debug_extensions',
    );
    expect(debugCookie).toBeDefined();
    expect(debugCookie!.value).toBe('1');

    await page.goto('modules.php?name=Standings');
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

    const cookies = await page.context().cookies();
    const debugCookie = cookies.find(
      (c) => c.name === 'ibl_debug_extensions',
    );
    expect(debugCookie).toBeUndefined();

    await page.goto('index.php');
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
