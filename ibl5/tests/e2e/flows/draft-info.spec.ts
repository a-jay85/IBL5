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

  test('clicking Past drafts loads the history tab', async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo');

    await page.locator('a.ibl-tab[data-tab="history"]').click();
    await expect(page).toHaveURL(/tab=history/);

    await expect(page.locator('.ibl-data-table').first()).toBeVisible();
    await expect(
      page.locator('a.ibl-tab--active[data-tab="history"]'),
    ).toBeVisible();
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
  test('nav exposes the three draft tabs and no legacy draft link', async ({ page }) => {
    await page.goto('index.php');

    await expect(
      page.locator('a[href*="name=DraftInfo&tab=order"]').first(),
    ).toBeAttached();
    await expect(
      page.locator('a[href*="name=DraftInfo&tab=picks"]').first(),
    ).toBeAttached();
    await expect(
      page.locator('a[href*="name=DraftInfo&tab=history"]').first(),
    ).toBeAttached();

    await expect(page.locator('a[href*="name=DraftPickLocator"]')).toHaveCount(0);
    await expect(page.locator('a[href*="name=ProjectedDraftOrder"]')).toHaveCount(0);
    await expect(
      page.locator('a[href="modules.php?name=DraftHistory"]'),
    ).toHaveCount(0);

    const picksHref = await page
      .locator('a[href*="name=DraftInfo&tab=picks"]')
      .first()
      .getAttribute('href');
    expect(picksHref).not.toBeNull();
    await page.goto(picksHref as string);
    await expect(
      page.locator('a.ibl-tab--active[data-tab="picks"]'),
    ).toBeVisible();
  });
});
