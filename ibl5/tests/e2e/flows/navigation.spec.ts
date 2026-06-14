import { test, expect } from '../fixtures/auth';
import { desktopNav, openMobileMenu } from '../helpers/navigation';

// Authenticated navigation flow tests — verify logged-in user experience.

test.describe('Navigation bar (authenticated, desktop)', () => {
  test('my team menu is visible for logged-in users', async ({ page }) => {
    await page.goto('index.php');
    await expect(
      desktopNav(page).getByRole('button', { name: 'My Team' }),
    ).toBeVisible();
  });

  test('account button shows username instead of login', async ({ page }) => {
    await page.goto('index.php');
    const nav = desktopNav(page);

    // "Login" button should NOT be present when authenticated
    await expect(
      nav.getByRole('button', { name: 'Login' }),
    ).not.toBeAttached();
  });

  test('my team dropdown opens and shows team links', async ({ page }) => {
    await page.goto('index.php');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'My Team' }).click();

    const teamPageLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Team Page',
    }).first();
    await expect(teamPageLink).toBeVisible();
  });

  test('my team dropdown shows owner Cap Calculator link', async ({ page }) => {
    // Cap Calculator lives in getIblTeamMenu(), so it is owner-gated (teamId !== null)
    // and IBL-only. The auth fixture owns Metros (teamid 1).
    await page.goto('index.php');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'My Team' }).click();

    const capCalcLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Cap Calculator',
    }).first();
    await expect(capCalcLink).toBeVisible();
    await expect(capCalcLink).toHaveAttribute(
      'href',
      /modules\.php\?name=CapWhatIf/,
    );
  });

  test('Cap Calculator link is absent in Olympics context', async ({ page }) => {
    // getIblTeamMenu() only returns for currentLeague === 'ibl', so the link
    // never appears under Olympics — no OLYMPICS_HIDDEN_NAV_MODULES edit needed.
    await page.goto('index.php?league=olympics');
    await expect(
      desktopNav(page).locator('.nav-dropdown-item', { hasText: 'Cap Calculator' }),
    ).not.toBeAttached();
  });

  test('my team dropdown shows admin-only Voting Results link', async ({ page }) => {
    // The default auth fixture is the CI main user: roles_mask=1 (ADMIN,
    // setup-docker-e2e action.yml ~L128) and gm_username on team Metros
    // (action.yml ~L133). So isLoggedIn && teamId !== null && isAdmin all
    // hold — the My Team menu renders AND the admin-gated link appears.
    await page.goto('index.php');
    const nav = desktopNav(page);
    await nav.getByRole('button', { name: 'My Team' }).click();

    const votingResultsLink = nav.locator('.nav-dropdown-item', {
      hasText: 'Voting Results',
    }).first();
    await expect(votingResultsLink).toBeVisible();
    await expect(votingResultsLink).toHaveAttribute(
      'href',
      /modules\.php\?name=VotingResults/,
    );
  });

  test('my team dropdown shows logout footer', async ({ page }) => {
    await page.goto('index.php');
    const nav = desktopNav(page);

    // Logout is now folded into the My Team dropdown footer for
    // logged-in users with a team — no standalone Account dropdown.
    await nav.getByRole('button', { name: 'My Team' }).click();

    const logoutLink = nav.locator('a', { hasText: 'Logout' }).first();
    await expect(logoutLink).toBeVisible();
  });

  test('teams mega-menu shows team links', async ({ page }) => {
    await page.goto('index.php');
    await desktopNav(page).getByRole('button', { name: 'Teams' }).click();

    const teamLinks = page.locator('a[href*="teamid="]');
    await expect(teamLinks.first()).toBeVisible();
  });

  test('dropdown link navigates to correct page', async ({ page }) => {
    await page.goto('index.php');
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
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('text=Welcome back'),
    ).toBeVisible();
  });

  test('my team section appears in mobile panel', async ({ page }) => {
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'My Team' }).first(),
    ).toBeVisible();
  });

  test('my team accordion shows logout footer instead of separate account section', async ({ page }) => {
    await page.goto('index.php');
    const mobileMenu = await openMobileMenu(page);

    // For logged-in users with a team, neither the Account nor Login
    // accordion button is present — logout lives inside the My Team
    // accordion footer instead.
    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Account' }),
    ).not.toBeAttached();
    await expect(
      mobileMenu.locator('.mobile-dropdown-btn', { hasText: 'Login' }),
    ).not.toBeAttached();

    await mobileMenu.locator('.mobile-dropdown-btn', {
      hasText: 'My Team',
    }).first().click();

    await expect(
      mobileMenu.locator('a', { hasText: 'Logout' }).first(),
    ).toBeVisible();
  });

  test('mobile team section expands to show team links', async ({ page }) => {
    await page.goto('index.php');
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
    await page.goto('index.php');
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
