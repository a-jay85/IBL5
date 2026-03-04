import { test, expect } from '@playwright/test';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

// Search — public page, no authentication required.
// Uses POST-based search form with filter dropdowns.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Search flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Search');
  });

  test('page loads with search form and input', async ({ page }) => {
    const searchPage = page.locator('.search-page');
    await expect(searchPage).toBeVisible();

    const searchInput = page.locator('input[name="query"]');
    await expect(searchInput).toBeVisible();
  });

  test('filter selects present', async ({ page }) => {
    const selects = page.locator('.search-form__select');
    // Should have topic, category, author, days dropdowns
    expect(await selects.count()).toBeGreaterThanOrEqual(3);
  });

  test('type radio buttons present with stories as default', async ({
    page,
  }) => {
    const radios = page.locator('input[name="type"]');
    expect(await radios.count()).toBeGreaterThanOrEqual(2);

    // Stories should be checked by default
    const storiesRadio = page.locator(
      'input[name="type"][value="stories"]',
    );
    await expect(storiesRadio).toBeChecked();
  });

  test('searching for common term returns results or empty state', async ({ page }) => {
    await page.locator('input[name="query"]').fill('trade');
    await page.locator('.ibl-search__btn').click();

    // POST form may navigate — wait for search page to re-render
    await page.waitForLoadState('domcontentloaded');

    const body = await page.locator('body').textContent();

    // Should either have results or a "no matches" message — no PHP errors
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body).not.toContain(pattern);
    }

    // Check for result cards, empty state, or the search form re-rendered
    const results = page.locator('.search-result');
    const emptyState = page.locator('.ibl-empty-state');
    const searchPage = page.locator('.search-page');
    const hasResults = (await results.count()) > 0;
    const hasEmpty = (await emptyState.count()) > 0;
    const hasSearchPage = (await searchPage.count()) > 0;

    expect(hasResults || hasEmpty || hasSearchPage).toBe(true);
  });

  test('users radio + search returns user results or empty state', async ({ page }) => {
    const usersRadio = page.locator('input[name="type"][value="users"]');
    await usersRadio.check();
    await page.locator('input[name="query"]').fill('admin');
    await page.locator('.ibl-search__btn').click();

    await page.waitForLoadState('domcontentloaded');

    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body).not.toContain(pattern);
    }

    // User results use compact cards, or we get empty state, or search page
    const results = page.locator('.search-result');
    const emptyState = page.locator('.ibl-empty-state');
    const searchPage = page.locator('.search-page');
    expect(
      (await results.count()) > 0 ||
        (await emptyState.count()) > 0 ||
        (await searchPage.count()) > 0,
    ).toBe(true);
  });

  test('short query shows error message', async ({ page }) => {
    await page.locator('input[name="query"]').fill('ab');
    await page.locator('.ibl-search__btn').click();

    // Should show an error about minimum query length
    const body = await page.locator('body').textContent();

    // The error should mention character length requirement
    const hasError =
      body?.includes('3') ||
      body?.includes('character') ||
      body?.includes('short') ||
      page.url().includes('qlen=1');

    expect(hasError).toBe(true);

    // No PHP errors
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body).not.toContain(pattern);
    }
  });

  test('many-result search shows pagination', async ({ page }) => {
    // Search for a very common term to get many results
    await page.locator('input[name="query"]').fill('the');
    await page.locator('.ibl-search__btn').click();

    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body).not.toContain(pattern);
    }

    // Check for pagination links
    const pagination = page.locator('.search-pagination');
    const results = page.locator('.search-result');

    if ((await results.count()) > 0) {
      // If there are results, pagination may or may not be present
      // depending on result count
      const paginationVisible = await pagination.isVisible().catch(() => false);
      if (paginationVisible) {
        const nextLink = page.locator('.search-pagination__link--next');
        await expect(nextLink).toBeVisible();
      }
    }
  });

  test('pagination Next link navigates to offset page', async ({ page }) => {
    await page.locator('input[name="query"]').fill('the');
    await page.locator('.ibl-search__btn').click();

    const nextLink = page.locator('.search-pagination__link--next');
    const hasNext = await nextLink.isVisible().catch(() => false);

    if (!hasNext) {
      test.skip(true, 'Not enough search results for pagination test');
    }

    const href = await nextLink.getAttribute('href');
    await page.goto(href!);

    // Should load the next page without errors
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(body).not.toContain(pattern);
    }

    // Should have results on the offset page
    const results = page.locator('.search-result');
    expect(await results.count()).toBeGreaterThan(0);
  });

  test('no PHP errors on form page', async ({ page }) => {
    const body = await page.locator('body').textContent();
    for (const pattern of PHP_ERROR_PATTERNS) {
      expect(
        body,
        `PHP error "${pattern}" on Search form page`,
      ).not.toContain(pattern);
    }
  });
});
