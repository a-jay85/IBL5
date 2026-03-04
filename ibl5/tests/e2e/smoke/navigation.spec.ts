import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Public navigation tests — no authentication required.
// The navigation bar renders on every page via themeheader().

test.use({ storageState: { cookies: [], origins: [] } });

function desktopNav(page: Page) {
  return page.locator('.hidden.lg\\:flex').first();
}

async function openMobileMenu(page: Page) {
  await page.locator('#nav-hamburger').click();
  return page.locator('#nav-mobile-menu');
}

test.describe('Navigation bar smoke tests (public)', () => {
  test('nav bar is visible on homepage', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('nav.fixed').first()).toBeVisible();
  });

  test('logo links to homepage and displays IBL branding', async ({ page }) => {
    await page.goto('/');
    const logo = page.locator('nav a[href="index.php"]').first();
    await expect(logo).toBeVisible();
    await expect(logo.locator('text=IBL')).toBeVisible();
  });

  test('desktop menu buttons are visible', async ({ page }) => {
    // Default viewport (1280x720) is above lg breakpoint — desktop nav shows
    await page.goto('/');
    const nav = desktopNav(page);
    await expect(nav).toBeVisible();

    for (const label of ['Season', 'Stats', 'History', 'Community', 'Teams']) {
      await expect(nav.getByRole('button', { name: label })).toBeVisible();
    }
  });

  test('login button shown for unauthenticated users', async ({ page }) => {
    await page.goto('/');
    await expect(
      desktopNav(page).getByRole('button', { name: 'Login' }),
    ).toBeVisible();
  });

  test('my team menu not shown for unauthenticated users', async ({ page }) => {
    await page.goto('/');
    await expect(
      desktopNav(page).getByRole('button', { name: 'My Team' }),
    ).not.toBeAttached();
  });

  test('mobile hamburger and menu panel exist in DOM', async ({ page }) => {
    // On desktop viewport these are in the DOM but hidden (lg:hidden)
    await page.goto('/');
    await expect(page.locator('#nav-hamburger')).toBeAttached();
    await expect(page.locator('#nav-mobile-menu')).toBeAttached();
  });

  test('desktop dropdown opens on hover and shows links', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).hover();

    const standingsLink = nav.locator(
      '.nav-dropdown-item',
      { hasText: 'Standings' },
    ).first();
    await expect(standingsLink).toBeVisible({ timeout: 3000 });
  });

  test('league switcher is inside season dropdown', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).hover();

    const leagueSelect = nav.locator('select').first();
    await expect(leagueSelect).toBeVisible({ timeout: 3000 });
    await expect(leagueSelect.locator('option')).toHaveCount(2);
  });

  test('login form appears in login dropdown', async ({ page }) => {
    await page.goto('/');
    await desktopNav(page).getByRole('button', { name: 'Login' }).hover();

    await expect(page.locator('#nav-username')).toBeVisible({ timeout: 3000 });
    await expect(page.locator('#nav-password')).toBeVisible();
  });

  test('no PHP errors on homepage', async ({ page }) => {
    await page.goto('/');
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body, `PHP error "${pattern}" found`).not.toContain(pattern);
    }
  });
});

test.describe('Navigation bar smoke tests (mobile viewport)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('hamburger button is visible on mobile', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#nav-hamburger')).toBeVisible();
  });

  test('desktop nav is hidden on mobile', async ({ page }) => {
    await page.goto('/');
    await expect(desktopNav(page)).not.toBeVisible();
  });

  test('desktop view toggle button is visible on mobile', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#desktop-view-toggle')).toBeVisible();
  });

  test('mobile menu sections exist when panel is opened', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    for (const label of ['Season', 'Stats', 'History', 'Community', 'Teams']) {
      await expect(
        mobileMenu.locator('.mobile-dropdown-btn', { hasText: label }).first(),
      ).toBeVisible({ timeout: 3000 });
    }
  });

  test('mobile accordion expands on tap', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await mobileMenu.locator('.mobile-dropdown-btn', {
      hasText: 'Season',
    }).first().click();

    const standingsLink = mobileMenu.locator('.mobile-dropdown-link', {
      hasText: 'Standings',
    }).first();
    await expect(standingsLink).toBeVisible({ timeout: 3000 });
  });

  test('login section appears for unauthenticated mobile users', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Login' }).first(),
    ).toBeVisible({ timeout: 3000 });
  });
});
