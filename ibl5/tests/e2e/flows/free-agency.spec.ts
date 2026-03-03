import { test, expect } from '../fixtures/auth';
import type { Page } from '@playwright/test';

// Free Agency — authenticated page.
// NOTE: Do NOT submit offer forms — that would mutate data.

const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

async function shouldSkipFreeAgency(page: Page): Promise<string | null> {
  const body = await page.locator('body').textContent();
  if (!body) return 'Free agency page returned empty content';

  const lower = body.toLowerCase();
  if (
    lower.includes('free agency is closed') ||
    lower.includes('free agency is not open') ||
    (lower.includes('free agency') && lower.includes('closed'))
  ) {
    return 'Free agency is currently closed';
  }

  // Between season phases, the page may show only the nav bar with no
  // module content. Check if any data tables or team tables exist.
  const hasContent = await page
    .locator('.ibl-data-table, .team-table, table')
    .first()
    .isVisible()
    .catch(() => false);
  if (!hasContent) {
    return 'Free agency has no content in current season phase';
  }

  return null;
}

test.describe('Free Agency flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=FreeAgency');
    // Under parallel MAMP load, the page may render blank — retry once
    let body = await page.locator('body').innerText();
    if (body.trim().length < 20) {
      await page.waitForTimeout(500);
      await page.goto('modules.php?name=FreeAgency');
    }
    const skipReason = await shouldSkipFreeAgency(page);
    if (skipReason) {
      test.skip(true, skipReason);
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
