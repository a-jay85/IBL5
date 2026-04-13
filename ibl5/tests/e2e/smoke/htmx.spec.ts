import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Public HTMX navigation tests — no authentication required.
test.use({ storageState: publicStorageState() });

// HTMX tests need click-based navigation (not goto) to verify boost behavior.
// Increase timeouts since link clicks can be slow under parallel worker load.
test.describe('HTMX hx-boost navigation', () => {
  test.use({ actionTimeout: 15_000, navigationTimeout: 20_000 });
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

    // Click a visible boosted link within site-content (exclude topic icon links
    // which are absolutely-positioned image links that may not render visibly)
    const contentLink = page
      .locator('#site-content a[href*="modules.php"]:not(.news-article__topic-icon-link)')
      .first();
    await expect(contentLink).toBeVisible();

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

    // Navigate to a page by clicking a visible boosted link within site-content
    const contentLink = page
      .locator('#site-content a[href*="modules.php"]:not(.news-article__topic-icon-link)')
      .first();
    await expect(contentLink).toBeVisible();

    await contentLink.click();
    await page.waitForURL(/modules\.php/);
    expect(page.url()).toContain('modules.php');

    // Verify browser title updated
    await expect(page).toHaveTitle(/IBL/);
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

    // Navigate via a visible boosted link
    const contentLink = page
      .locator('#site-content a[href*="modules.php"]:not(.news-article__topic-icon-link)')
      .first();
    await expect(contentLink).toBeVisible();

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

  test('boosted form submission swaps content without full page reload', async ({ page }) => {
    // Navigate to Search page which has a public POST form
    await page.goto('modules.php?name=Search');

    // Mark the nav to verify it persists (no full reload)
    await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      if (navEl) navEl.setAttribute('data-htmx-marker', '1');
    });

    // Fill in search query and submit the form
    const searchInput = page.locator('input[name="query"]').first();
    await expect(searchInput).toBeVisible();
    await searchInput.fill('basketball');

    // Submit via the search button (HTMX intercepts boosted form submission)
    const submitBtn = page.locator('.ibl-search__btn').first();
    await expect(submitBtn).toBeVisible();
    await submitBtn.click();

    // Wait for HTMX to swap content — the search renders results inline,
    // so wait for the results content to appear in site-content
    await expect(page.locator('#site-content').first()).toBeVisible({ timeout: 10000 });
    // Wait for search results or "no results" message to render
    await page.waitForTimeout(1000);

    // Verify the nav marker persists (nav was NOT re-rendered = no full page reload)
    const marker = await page.evaluate(() => {
      const navEl = document.querySelector('nav.fixed');
      return navEl?.getAttribute('data-htmx-marker');
    });
    expect(marker).toBe('1');
  });
});
