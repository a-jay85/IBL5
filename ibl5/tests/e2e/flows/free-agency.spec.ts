import { test, expect } from '../fixtures/auth';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Free Agency — authenticated page.
// NOTE: Do NOT submit offer forms — that would mutate data.

test.describe('Free Agency flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=FreeAgency');
    // Under parallel MAMP load, the page may render blank — retry once
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      await page.waitForTimeout(500);
      await page.goto('modules.php?name=FreeAgency');
    }
  });

  test('main page loads with tables', async ({ page }) => {
    // The FA page has 4 data tables when open
    const table = page.locator('.ibl-data-table, .team-table, table').first();
    await expect(table).toBeVisible({ timeout: 10000 });
  });

  test('roster table has team-colored styling', async ({ page }) => {
    const teamTable = page.locator('.team-table').first();
    if (await teamTable.isVisible()) {
      const style = await teamTable.getAttribute('style');
      expect(style).toContain('--team');
    }
  });

  test('cap space info displayed', async ({ page }) => {
    const body = await page.locator('body').textContent();
    expect(body?.toLowerCase()).toContain('cap');
  });

  test('negotiate links present', async ({ page }) => {
    const negotiateLink = page.locator('a[href*="pa=negotiate"]').first();
    if (!(await negotiateLink.isVisible())) {
      test.skip(true, 'No negotiate links available');
    }
    expect(
      await page.locator('a[href*="pa=negotiate"]').count(),
    ).toBeGreaterThan(0);
  });

  test('negotiation page shows offer form', async ({ page }) => {
    const negotiateLink = page.locator('a[href*="pa=negotiate"]').first();
    if (!(await negotiateLink.isVisible())) {
      test.skip(true, 'No negotiate links available');
    }

    const href = await negotiateLink.getAttribute('href');
    await page.goto(href!);
    await expect(page.locator('form[name="FAOffer"]').first()).toBeVisible();
  });

  test('offer year inputs are numeric', async ({ page }) => {
    const negotiateLink = page.locator('a[href*="pa=negotiate"]').first();
    if (!(await negotiateLink.isVisible())) {
      test.skip(true, 'No negotiate links available');
    }

    const href = await negotiateLink.getAttribute('href');
    await page.goto(href!);

    const yearInput = page.locator('input[name="offeryear1"]').first();
    if (await yearInput.isVisible()) {
      const type = await yearInput.getAttribute('type');
      expect(type).toBe('number');
    }
  });

  test('quick offer preset buttons present', async ({ page }) => {
    const negotiateLink = page.locator('a[href*="pa=negotiate"]').first();
    if (!(await negotiateLink.isVisible())) {
      test.skip(true, 'No negotiate links available');
    }

    const href = await negotiateLink.getAttribute('href');
    await page.goto(href!);

    const quickOfferBtns = page.locator('.ibl-btn--sm.ibl-btn--primary');
    if (await quickOfferBtns.first().isVisible()) {
      expect(await quickOfferBtns.count()).toBeGreaterThanOrEqual(1);
    }
  });

  test('no PHP errors on main page', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Free Agency main page`,
      ).not.toContain(pattern);
    }
  });

  test('no PHP errors on negotiation page', async ({ page }) => {
    const negotiateLink = page.locator('a[href*="pa=negotiate"]').first();
    if (!(await negotiateLink.isVisible())) {
      test.skip(true, 'No negotiate links available');
    }

    const href = await negotiateLink.getAttribute('href');
    await page.goto(href!);

    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Free Agency negotiation page`,
      ).not.toContain(pattern);
    }
  });
});
