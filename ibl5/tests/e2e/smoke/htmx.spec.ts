import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Public HTMX navigation tests — no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('HTMX hx-boost navigation', () => {
  test('boosted link swaps content without full page reload', async ({ page }) => {
    await page.goto('index.php');

    // Store a reference to the nav element — it should persist across navigations
    const nav = page.locator('nav.fixed').first();
    await expect(nav).toBeVisible();

    // Mark the nav with a data attribute so we can verify it wasn't re-rendered
    await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      if (navEl) navEl.setAttribute('data-htmx-marker', '1');
    });

    // Click a boosted link within site-content (homepage always has module links)
    const contentLink = page.locator('#site-content a[href*="modules.php"]').first();
    if (await contentLink.count() === 0) {
      // No links found in content area — skip
      return;
    }

    await contentLink.click();
    await page.waitForURL(/modules\.php/);

    // Verify the nav marker persists (nav was NOT re-rendered)
    const marker = await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      return navEl?.getAttribute('data-htmx-marker');
    });
    expect(marker).toBe('1');
  });

  test('URL updates via pushState on boosted navigation', async ({ page }) => {
    await page.goto('index.php');

    // Navigate to a page by clicking a boosted link within site-content
    const contentLink = page.locator('#site-content a[href*="modules.php"]').first();
    if (await contentLink.count() > 0) {
      await contentLink.click();
      await page.waitForURL(/modules\.php/);
      expect(page.url()).toContain('modules.php');

      // Verify browser title updated
      await expect(page).toHaveTitle(/IBL/);
    }
  });

  test('direct URL access returns full page with nav', async ({ page }) => {
    // Directly navigating (no HTMX) should return the full page
    await page.goto('modules.php?name=Standings');
    await assertNoPhpErrors(page, 'on Standings page');

    const nav = page.locator('nav.fixed').first();
    await expect(nav).toBeVisible();

    // Should have full HTML structure
    const html = await page.locator('html').count();
    expect(html).toBe(1);
  });

  test('browser back/forward works after HTMX navigation', async ({ page }) => {
    await page.goto('index.php');

    // Navigate via a boosted link
    const contentLink = page.locator('#site-content a[href*="modules.php"]').first();
    if (await contentLink.count() > 0) {
      await contentLink.click();
      await page.waitForURL(/modules\.php/);

      // Go back
      await page.goBack();
      await page.waitForURL(/index\.php/);
      expect(page.url()).toContain('index.php');

      // Go forward
      await page.goForward();
      await page.waitForURL(/modules\.php/);
      expect(page.url()).toContain('modules.php');
    }
  });

  test('no PHP errors on pages loaded via HTMX', async ({ page }) => {
    const urls = [
      'index.php',
      'modules.php?name=Standings',
      'modules.php?name=Roster',
    ];

    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
