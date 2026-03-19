import { test, expect } from '@playwright/test';
import { openMobileMenu, gotoWithRetry } from '../helpers/navigation';

test.use({ storageState: { cookies: [], origins: [] } });
test.use({ viewport: { width: 375, height: 812 } });

test.describe('Mobile nav interaction tests', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, 'index.php');
  });

  test('overlay closes menu', async ({ page }) => {
    await openMobileMenu(page);
    await expect(page.locator('#nav-mobile-menu')).toBeVisible();
    // Overlay is z-40, menu panel is z-50 on the right — click the exposed left side
    await page.locator('#nav-overlay').click({ position: { x: 20, y: 400 } });
    await expect(page.locator('#nav-mobile-menu')).not.toBeVisible();
  });

  test('escape key closes menu', async ({ page }) => {
    await openMobileMenu(page);
    await expect(page.locator('#nav-mobile-menu')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#nav-mobile-menu')).not.toBeVisible();
  });

  test('exclusive dropdowns — opening one closes the other', async ({ page }) => {
    await openMobileMenu(page);

    // Open "Season" dropdown and verify "Standings" link is visible
    await page.getByRole('button', { name: /season/i }).click();
    await expect(page.locator('#nav-mobile-menu').getByText('Standings').first()).toBeVisible();

    // Open "Stats" dropdown — "Standings" should no longer be visible
    await page.getByRole('button', { name: /stats/i }).click();
    await expect(page.locator('#nav-mobile-menu').getByText('Standings').first()).not.toBeVisible();
  });

  test('body scroll lock when menu is open', async ({ page }) => {
    await openMobileMenu(page);
    const overflow = await page.evaluate(() => getComputedStyle(document.body).overflow);
    expect(overflow).toBe('hidden');
  });

  test('league switcher present in mobile menu', async ({ page }) => {
    await openMobileMenu(page);
    await page.getByRole('button', { name: /season/i }).click();
    await expect(page.locator('#mobile-league-select')).toBeAttached();
  });

  test('aria-expanded toggles on hamburger', async ({ page }) => {
    const hamburger = page.locator('#nav-hamburger');
    await expect(hamburger).toHaveAttribute('aria-expanded', 'false');
    await hamburger.click();
    await expect(hamburger).toHaveAttribute('aria-expanded', 'true');
  });

  test('nav link navigates to correct page', async ({ page }) => {
    await openMobileMenu(page);
    await page.getByRole('button', { name: /season/i }).click();
    const standingsLink = page.locator('#nav-mobile-menu').getByRole('link', { name: 'Standings' });
    const href = await standingsLink.getAttribute('href');
    expect(href).toBeTruthy();
    await gotoWithRetry(page, href!);
    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });
});
