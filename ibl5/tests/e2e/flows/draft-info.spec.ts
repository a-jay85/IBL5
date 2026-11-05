import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// DraftInfo — consolidated Draft Order / Pick Ownership / Past Drafts module.
test.use({ storageState: publicStorageState() });

test.describe('DraftInfo: default route', () => {
  test('default route shows three tabs with Order active', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo');
    await assertNoPhpErrors(page, 'DraftInfo default route');

    const tabs = page.locator('.ibl-tabs a.ibl-tab');
    await expect(tabs).toHaveCount(3);

    const displayValues = await tabs.evaluateAll((els) =>
      els.map((el) => el.getAttribute('data-tab')),
    );
    expect(displayValues).toEqual(['order', 'picks', 'history']);

    await expect(
      page.locator('a.ibl-tab[data-tab="order"]'),
    ).toHaveClass(/ibl-tab--active/);
    await expect(
      page.locator('a.ibl-tab[data-tab="picks"]'),
    ).not.toHaveClass(/ibl-tab--active/);
    await expect(
      page.locator('a.ibl-tab[data-tab="history"]'),
    ).not.toHaveClass(/ibl-tab--active/);

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  });
});

test.describe('DraftInfo: tab navigation', () => {
  test('clicking Pick ownership loads the locator tab', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo');

    await page.locator('a.ibl-tab[data-tab="picks"]').click();
    await expect(page).toHaveURL(/name=DraftInfo&tab=picks/);

    await expect(page.locator('.draft-pick-locator-container')).toBeVisible();
    expect(await page.locator('tr[data-team-id]').count()).toBeGreaterThanOrEqual(28);
  });

  test('clicking History loads the League sub-tab', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo');

    await page.locator('a.ibl-tab[data-tab="history"]').click();
    await expect(page).toHaveURL(/tab=history/);

    await expect(
      page.locator('a.ibl-tab--active[data-tab="history"]'),
    ).toHaveText('History');
    await expect(
      page.locator('.ibl-tabs--sub .ibl-tab[aria-current="page"]'),
    ).toHaveText('League');
    await expect(
      page.locator('.ibl-subtab-panel .ibl-data-table').first(),
    ).toBeVisible();
  });

  test('My team sub-tab shows a login prompt when logged out', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=history');

    await page.locator('.ibl-tabs--sub a.ibl-tab[data-tab="team"]').click();
    await expect(page).toHaveURL(/view=team/);

    await expect(
      page.locator('.ibl-subtab-login-prompt a[href="modules.php?name=YourAccount"]'),
    ).toBeVisible();
    await expect(page.locator('.ibl-subtab-panel .ibl-data-table')).toHaveCount(0);
  });

  test('year switcher swaps the table and keeps the sub-tab strip', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=history');

    const select = page.locator('#draft-year-select');
    const year = await select.locator('option').nth(1).getAttribute('value');
    expect(year).toBeTruthy();

    const apiResponse = page.waitForResponse(
      (r) => r.url().includes('op=api') && r.status() === 200,
    );
    await select.selectOption(year!);
    await apiResponse;

    await expect(page).toHaveURL(new RegExp('year=' + year));
    await expect(page.locator('.ibl-tabs--sub .ibl-tab')).toHaveCount(2);
    await expect(
      page.locator('.ibl-tabs--sub .ibl-tab[data-tab="league"]'),
    ).toHaveAttribute('aria-current', 'page');
  });

  test('teamid link selects a team sub-tab labeled with the team name', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=history&teamid=2');

    const title = ((await page.locator('h1.ibl-title').first().textContent()) ?? '').trim();
    const teamName = title.replace(/ Draft History$/i, '');

    const active = page.locator('.ibl-tabs--sub .ibl-tab[aria-current="page"]');
    await expect(active).toHaveText(teamName, { ignoreCase: true });
    await expect(active).not.toHaveText('My team');
    await expect(active).toHaveAttribute('href', /teamid=2/);
  });

  test('Season menu has one Draft Info link to the Order tab', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=history');

    const link = page.locator('.nav-dropdown-item', { hasText: /^\s*Draft Info\s*$/ }).first();
    await expect(link).toBeAttached();
    await expect(link).toHaveAttribute('href', 'modules.php?name=DraftInfo&tab=order');
    expect(((await link.textContent()) ?? '').trim()).toBe('Draft Info');
    await expect(
      page.locator('.nav-dropdown-item', { hasText: 'Draft Pick Locator' }),
    ).not.toBeAttached();
    await expect(
      page.locator('.nav-dropdown-item', { hasText: 'Projected Draft Order' }),
    ).not.toBeAttached();

    await page.goto('modules.php?name=DraftInfo&tab=order');
    await expect(page.locator('.ibl-tabs a.ibl-tab--active').first()).toHaveText('Order');
  });

  test('unknown tab falls back to Order', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=bogus');
    await assertNoPhpErrors(page, 'DraftInfo bogus tab');

    await expect(
      page.locator('a.ibl-tab--active'),
    ).toHaveAttribute('data-tab', 'order');
  });
});

test.describe('DraftInfo: legacy redirects', () => {
  test('legacy URLs redirect to the matching tab', async ({ request, page }) => {
    const legacyMap = [
      { old: 'ProjectedDraftOrder', tab: 'order' },
      { old: 'DraftPickLocator', tab: 'picks' },
      { old: 'DraftHistory', tab: 'history' },
    ];

    for (const { old, tab } of legacyMap) {
      const res = await request.get(`modules.php?name=${old}`, {
        maxRedirects: 0,
      });
      expect(res.status(), `${old} should redirect with 302`).toBe(302);
      const location = res.headers()['location'] ?? '';
      expect(location, `${old} should redirect to DraftInfo&tab=${tab}`).toContain(
        `name=DraftInfo&tab=${tab}`,
      );

      await page.goto(`modules.php?name=${old}`);
      await expect(page).toHaveURL(new RegExp(`name=DraftInfo&tab=${tab}`));
    }
  });

  test('legacy DraftHistory forwards only year and teamid', async ({ request }) => {
    const res = await request.get(
      'modules.php?name=DraftHistory&year=2020&teamid=1&foo=bar&name2=x',
      { maxRedirects: 0 },
    );
    const location = res.headers()['location'] ?? '';
    expect(location).toBe('modules.php?name=DraftInfo&tab=history&year=2020&teamid=1');
  });

  test('legacy save_order hop is a 307', async ({ request }) => {
    const res = await request.post(
      'modules.php?name=ProjectedDraftOrder&op=save_order',
      { maxRedirects: 0, data: {} },
    );
    expect(res.status()).toBe(307);
    const location = res.headers()['location'] ?? '';
    expect(location).toBe('modules.php?name=DraftInfo&op=save_order');
  });
});

test.describe('DraftInfo: nav links', () => {
  test('nav exposes one Draft Info link and no legacy draft link', async ({ page }) => {
    await page.goto('index.php');

    await expect(
      page.locator('a[href*="name=DraftInfo&tab=order"]').first(),
    ).toBeAttached();
    await expect(
      page.locator('a[href*="name=DraftInfo&tab=picks"]'),
    ).toHaveCount(0);

    await expect(page.locator('a[href*="name=DraftPickLocator"]')).toHaveCount(0);
    await expect(page.locator('a[href*="name=ProjectedDraftOrder"]')).toHaveCount(0);
    await expect(
      page.locator('a[href="modules.php?name=DraftHistory"]'),
    ).toHaveCount(0);

    const orderHref = await page
      .locator('a[href*="name=DraftInfo&tab=order"]')
      .first()
      .getAttribute('href');
    expect(orderHref).not.toBeNull();
    await page.goto(orderHref as string);
    await expect(
      page.locator('a.ibl-tab--active[data-tab="order"]'),
    ).toBeVisible();
    await expect(page.locator('a.ibl-tab[data-tab="picks"]')).toBeVisible();
    await expect(page.locator('a.ibl-tab[data-tab="history"]')).toBeVisible();
  });
});
