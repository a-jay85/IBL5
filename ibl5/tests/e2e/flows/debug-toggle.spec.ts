import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav } from '../helpers/navigation';
import type { Page } from '@playwright/test';

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
