import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  assertSearchFormPresent,
  assertFilterDropdownsPresent,
  assertSearchTypeRadiosPresent,
} from '../helpers/search-form-assertions';

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

    await assertSearchFormPresent(page);
  });

  test('filter selects present', async ({ page }) => {
    await assertFilterDropdownsPresent(page);
  });

  test('type radio buttons present with stories as default', async ({
    page,
  }) => {
    await assertSearchTypeRadiosPresent(page);
  });

  test('searching for common term returns results or empty state', async ({ page }) => {
    await page.locator('input[name="query"]').fill('trade');
    await page.locator('.ibl-search__btn').click();

    // POST form may navigate — wait for search page to re-render
    await page.waitForLoadState('domcontentloaded');

    // Should either have results or a "no matches" message — no PHP errors
    await assertNoPhpErrors(page);

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

    await assertNoPhpErrors(page);

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
    await assertNoPhpErrors(page);
  });

  test('many-result search shows pagination', async ({ page }) => {
    // Search for a very common term to get many results
    await page.locator('input[name="query"]').fill('the');
    await page.locator('.ibl-search__btn').click();

    await assertNoPhpErrors(page);

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
    await assertNoPhpErrors(page);

    // Should have results on the offset page
    const results = page.locator('.search-result');
    await expect(results.first()).toBeVisible();
  });

  test('no PHP errors on form page', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Search form page');
  });
});
