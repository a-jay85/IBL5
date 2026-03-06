import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';

// Authenticated navigation flow tests — verify logged-in user experience.

function desktopNav(page: Page) {
  return page.locator('.hidden.lg\\:flex').first();
}

async function openMobileMenu(page: Page) {
  await page.locator('#nav-hamburger').click();
  return page.locator('#nav-mobile-menu');
}

test.describe('Navigation bar (authenticated, desktop)', () => {
  test('my team menu is visible for logged-in users', async ({ page }) => {
    await page.goto('/');
    await expect(
      desktopNav(page).getByRole('button', { name: 'My Team' }),
    ).toBeVisible();
  });

  test('account button shows username instead of login', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);

    // "Login" button should NOT be present when authenticated
    await expect(
      nav.getByRole('button', { name: 'Login' }),
    ).not.toBeAttached();
  });

  test('my team dropdown opens and shows team links', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'My Team' }).click();

    const teamPageLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Team Page',
    }).first();
    await expect(teamPageLink).toBeVisible();
  });

  test('account dropdown shows logout link', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);

    // Find the last dropdown button (account) and click to pin open
    const accountBtn = nav.locator('.relative.group > button').last();
    await accountBtn.click();

    const logoutLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Logout',
    }).first();
    await expect(logoutLink).toBeVisible();
  });

  test('teams mega-menu shows team links', async ({ page }) => {
    await page.goto('/');
    await desktopNav(page).getByRole('button', { name: 'Teams' }).click();

    const teamLinks = page.locator('a[href*="teamID="]');
    await expect(teamLinks.first()).toBeVisible();
    expect(await teamLinks.count()).toBeGreaterThan(0);
  });

  test('dropdown link navigates to correct page', async ({ page }) => {
    await page.goto('/');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'Season' }).click();

    const standingsLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Standings',
    }).first();
    await expect(standingsLink).toBeVisible();

    const href = await standingsLink.getAttribute('href');
    await page.goto(href!);

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });
});

test.describe('Navigation bar (authenticated, mobile viewport)', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('welcome greeting shows in mobile panel', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('text=Welcome back'),
    ).toBeVisible();
  });

  test('my team section appears in mobile panel', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'My Team' }).first(),
    ).toBeVisible();
  });

  test('account section shows Account instead of Login', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Account' }).first(),
    ).toBeVisible();

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Login' }),
    ).not.toBeAttached();
  });

  test('mobile team section expands to show team links', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await mobileMenu.locator('.mobile-dropdown-btn', {
      hasText: 'My Team',
    }).first().click();

    const teamPageLink = mobileMenu.locator('.mobile-dropdown-link', {
      hasText: 'Team Page',
    }).first();
    await expect(teamPageLink).toBeVisible();
  });

  test('mobile link navigates to correct page', async ({ page }) => {
    await page.goto('/');
    const mobileMenu = await openMobileMenu(page);

    await mobileMenu.locator('.mobile-dropdown-btn', {
      hasText: 'Season',
    }).first().click();

    const standingsLink = mobileMenu.locator('.mobile-dropdown-link', {
      hasText: 'Standings',
    }).first();
    await expect(standingsLink).toBeVisible();

    const href = await standingsLink.getAttribute('href');
    await page.goto(href!);

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });
});
