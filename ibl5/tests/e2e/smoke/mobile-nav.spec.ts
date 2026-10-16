import { test, expect } from '../fixtures/base';
import { openMobileMenu, gotoWithRetry } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });
test.use({ viewport: { width: 375, height: 812 } });

// Whole-token match: \bhidden\b would also match the overlay's lg:hidden.
const HIDDEN_CLASS = /(^|\s)hidden(\s|$)/;

// NOTE: Safari bfcache edge case (not testable here)
// On iOS Safari, using the browser back button restores the page from bfcache,
// which can silently drop element-level event listeners. This kills the
// hamburger button. The fix (navigation.js pageshow handler + cloneNode
// re-init) cannot be E2E-tested because Playwright runs Chromium, whose
// bfcache preserves JS heap state including listeners. To verify the fix,
// test manually on an iPhone: navigate away → Safari back button → tap
// hamburger. See the pageshow handler in navigation.js.

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

  test('hamburger bars animate to X on open and reset on close', async ({ page }) => {
    await openMobileMenu(page);
    await expect(page.locator('#hamburger-top')).not.toHaveCSS('transform', 'none');
    await expect(page.locator('#hamburger-bottom')).not.toHaveCSS('transform', 'none');
    await expect(page.locator('#hamburger-middle')).toHaveCSS('opacity', '0');
    await expect(page.locator('#nav-overlay')).toHaveCSS('opacity', '1');

    await page.keyboard.press('Escape');
    await expect(page.locator('#hamburger-top')).toHaveCSS('transform', 'none');
    await expect(page.locator('#hamburger-middle')).toHaveCSS('opacity', '1');
    await expect(page.locator('#nav-overlay')).toHaveClass(HIDDEN_CLASS);
  });

  test('menu state is class-driven and leaves no inline styles', async ({ page }) => {
    await openMobileMenu(page);
    await expect(page.locator('body')).toHaveClass(/\bmenu-open\b/);
    await expect(page.locator('#nav-overlay')).toHaveClass(/nav-overlay--visible/);

    await page.keyboard.press('Escape');
    await expect(page.locator('#nav-overlay')).toHaveClass(HIDDEN_CLASS);
    const state = await page.evaluate(() => ({
      bodyInlineOverflow: document.body.style.overflow,
      bodyMenuOpen: document.body.classList.contains('menu-open'),
      bodyOverflow: getComputedStyle(document.body).overflow,
      overlayInlineOpacity: (document.getElementById('nav-overlay') as HTMLElement).style.opacity,
      barsWithStyle: ['hamburger-top', 'hamburger-middle', 'hamburger-bottom'].filter((id) =>
        document.getElementById(id)?.hasAttribute('style'),
      ),
    }));
    expect(state.bodyInlineOverflow).toBe('');
    expect(state.bodyMenuOpen).toBe(false);
    expect(state.bodyOverflow).not.toBe('hidden');
    expect(state.overlayInlineOpacity).toBe('');
    expect(state.barsWithStyle).toEqual([]);
  });

  test('reopening inside the close fade keeps the overlay visible', async ({ page }) => {
    await openMobileMenu(page);
    await page.keyboard.press('Escape');
    // Programmatic click so the fading overlay cannot intercept it.
    await page.evaluate(() => (document.getElementById('nav-hamburger') as HTMLElement).click());
    await page.waitForTimeout(450);
    await expect(page.locator('#nav-overlay')).not.toHaveClass(HIDDEN_CLASS);
    await expect(page.locator('#nav-overlay')).toHaveCSS('opacity', '1');
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
