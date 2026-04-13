import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { desktopNav, openMobileMenu } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

// Public navigation tests — no authentication required.
// The navigation bar renders on every page via themeheader().

test.use({ storageState: publicStorageState() });

test.describe('Navigation bar smoke tests (public)', () => {
  test('nav bar is visible on homepage', async ({ page }) => {
    await page.goto('index.php');
    await expect(page.locator('nav.fixed').first()).toBeVisible();
  });

  test('logo links to homepage and displays IBL branding', async ({ page }) => {
    await page.goto('index.php');
    const logo = page.locator('nav a[href="index.php"]').first();
    await expect(logo).toBeVisible();
    await expect(logo.locator('text=IBL')).toBeVisible();
  });

  test('desktop menu buttons are visible', async ({ page }) => {
    // Default viewport (1280x720) is above lg breakpoint — desktop nav shows
    await page.goto('index.php');
    const nav = desktopNav(page);
    await expect(nav).toBeVisible();

    // Static menu buttons (always present regardless of DB state)
    for (const label of ['Season', 'Stats', 'History', 'Community']) {
      await expect(nav.getByRole('button', { name: label })).toBeVisible();
    }

    // Teams is database-driven (JOIN ibl_team_info + ibl_standings).
    // CI seed has team data, so the button should render.
    await expect(nav.getByRole('button', { name: 'Teams' })).toBeVisible();
  });

  test('login button shown for unauthenticated users', async ({ page }) => {
    await page.goto('index.php');
    await expect(
      desktopNav(page).getByRole('button', { name: 'Login' }),
    ).toBeVisible();
  });

  test('my team menu not shown for unauthenticated users', async ({ page }) => {
    await page.goto('index.php');
    await expect(
      desktopNav(page).getByRole('button', { name: 'My Team' }),
    ).not.toBeAttached();
  });

  test('mobile hamburger and menu panel exist in DOM', async ({ page }) => {
    // On desktop viewport these are in the DOM but hidden (lg:hidden)
    await page.goto('index.php');
    await expect(page.locator('#nav-hamburger')).toBeAttached();
    await expect(page.locator('#nav-mobile-menu')).toBeAttached();
  });

  test('desktop dropdown opens on click and shows links', async ({ page }) => {
    await page.goto('index.php');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).click();

    const standingsLink = nav.locator(
      '.nav-dropdown-item',
      { hasText: 'Standings' },
    ).first();
    await expect(standingsLink).toBeVisible();
  });

  test('league switcher is inside season dropdown', async ({ page }) => {
    await page.goto('index.php');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).click();

    const leagueSelect = nav.locator('select').first();
    await expect(leagueSelect).toBeVisible();
    await expect(leagueSelect.locator('option')).toHaveCount(2);
  });

  test('login form appears in login dropdown', async ({ page }) => {
    await page.goto('index.php');
    await desktopNav(page).getByRole('button', { name: 'Login' }).click();

    await expect(page.locator('#nav-username')).toBeVisible();
    await expect(page.locator('#nav-password')).toBeVisible();
  });

  test('no PHP errors on homepage', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page);
  });
});

test.describe('Navigation bar smoke tests (mobile viewport)', () => {
  test.use({ navigationTimeout: 30_000 });
  test.use({ viewport: { width: 375, height: 812 } });

  test('hamburger button is visible on mobile', async ({ page }) => {
    await page.goto('index.php');
    await expect(page.locator('#nav-hamburger')).toBeVisible();
  });

  test('desktop nav is hidden on mobile', async ({ page }) => {
    await page.goto('index.php');
    await expect(desktopNav(page)).not.toBeVisible();
  });

  test('desktop view toggle button is visible on mobile', async ({ page }) => {
    await page.goto('index.php');
    await expect(page.locator('#desktop-view-toggle')).toBeVisible();
  });

  test('mobile menu sections exist when panel is opened', async ({ page }) => {
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    // Static sections (always present regardless of DB state)
    for (const label of ['Season', 'Stats', 'History', 'Community']) {
      await expect(
        mobileMenu.locator('.mobile-dropdown-btn', { hasText: label }).first(),
      ).toBeVisible();
    }

    // Teams is database-driven — CI seed has team data, so the button should render
    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Teams' }).first(),
    ).toBeVisible();
  });

  test('mobile accordion expands on tap', async ({ page }) => {
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    await mobileMenu.locator('.mobile-dropdown-btn', {
      hasText: 'Season',
    }).first().click();

    const standingsLink = mobileMenu.locator('.mobile-dropdown-link', {
      hasText: 'Standings',
    }).first();
    await expect(standingsLink).toBeVisible();
  });

  test('login section appears for unauthenticated mobile users', async ({ page }) => {
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Login' }).first(),
    ).toBeVisible();
  });
});
