import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  assertSearchFormPresent,
  assertFilterDropdownsPresent,
  assertSearchTypeRadiosPresent,
} from '../helpers/search-form-assertions';
import { publicStorageState } from '../helpers/public-storage-state';

// Search — public page, no authentication required.
// Uses POST-based search form with filter dropdowns.
test.use({ storageState: publicStorageState() });

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

  test('searching for common term returns results', async ({ page }) => {
    await page.locator('input[name="query"]').fill('trade');
    await page.locator('.ibl-search__btn').click();

    // POST form may navigate — wait for search page to re-render
    await page.waitForLoadState('domcontentloaded');

    await assertNoPhpErrors(page);

    // 'trade' is a seeded term — real result rows must be present
    await expect(page.locator('.search-result').first()).toBeVisible();
  });

  test('no-match query shows empty state and zero result rows', async ({ page }) => {
    await page.locator('input[name="query"]').fill('zzznomatch999');
    await page.locator('.ibl-search__btn').click();

    await page.waitForLoadState('domcontentloaded');

    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-empty-state')).toBeVisible();
    await expect(page.locator('.search-result')).toHaveCount(0);
  });

  test('users radio + search returns compact user result for ibl_demo', async ({ page }) => {
    const usersRadio = page.locator('input[name="type"][value="users"]');
    await usersRadio.check();
    await page.locator('input[name="query"]').fill('demo');
    await page.locator('.ibl-search__btn').click();

    await page.waitForLoadState('domcontentloaded');

    await assertNoPhpErrors(page);

    // ibl_demo is the only seeded auth_users row matching 'demo'
    const compactResult = page.locator('.search-result--compact').first();
    await expect(compactResult).toBeVisible();
    await expect(compactResult.locator('.search-result__title')).toContainText('ibl_demo');
  });

  test('comments search type shows empty state — comment system is removed', async ({ page }) => {
    // The comments radio was removed from the form, but the backend type=comments
    // branch still calls the stubbed searchComments() which always returns empty.
    // Exercise it via query param to assert the empty-state path stays intact.
    await page.goto('modules.php?name=Search&query=trade&type=comments');

    await page.waitForLoadState('domcontentloaded');

    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-empty-state').first()).toBeVisible();
    await expect(page.locator('.search-result')).toHaveCount(0);
  });

  test('topic filter Trades returns trade stories without waive stories', async ({ page }) => {
    await page.locator('input[name="query"]').fill('the');
    // Trades is the Topic dropdown (topic=2). Waive-move stories live under the
    // IBL News topic (topic=1), so filtering to Trades must exclude them.
    await page.locator('select[name="topic"]').selectOption('2');
    await page.locator('.ibl-search__btn').click();

    await page.waitForLoadState('domcontentloaded');

    await assertNoPhpErrors(page);

    // Trade stories must be present under this category filter
    await expect(page.locator('.search-result').first()).toBeVisible();
    // Waive-only category (catid=1) stories must NOT appear
    const heading = page.locator('.search-results__heading');
    await expect(heading).not.toContainText('waive', { ignoreCase: true });
    const resultList = page.locator('.search-result');
    const resultText = await resultList.allTextContents();
    expect(resultText.join(' ').toLowerCase()).not.toContain('waive');
  });

  test('short query shows error message', async ({ page }) => {
    await page.locator('input[name="query"]').fill('ab');
    await page.locator('.ibl-search__btn').click();

    // A 2-char query must be rejected: the module redirects with the qlen marker
    // and renders the min-length error in the alert, not merely somewhere in <body>.
    await expect(page).toHaveURL(/qlen=1/);
    const alert = page.locator('.ibl-alert').first();
    await expect(alert).toBeVisible();
    await expect(alert).toContainText('Your query should be at least 3 characters long.');
    // Negative path: a rejected query must render no results.
    await expect(page.locator('.search-result__title')).toHaveCount(0);

    // No PHP errors
    await assertNoPhpErrors(page);
  });

  test('many-result search shows pagination', async ({ page }) => {
    // Search for a very common term to get many results
    await page.locator('input[name="query"]').fill('the');
    await page.locator('.ibl-search__btn').click();

    await assertNoPhpErrors(page);

    // CI seed has enough stories for pagination
    const pagination = page.locator('.search-pagination');
    await expect(pagination).toBeVisible();

    const nextLink = page.locator('.search-pagination__link--next');
    await expect(nextLink).toBeVisible();
  });

  test('pagination Next link navigates to offset page', async ({ page }) => {
    await page.locator('input[name="query"]').fill('the');
    await page.locator('.ibl-search__btn').click();

    const nextLink = page.locator('.search-pagination__link--next');
    await expect(nextLink).toBeVisible();

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

  test('preset select offers No Preset and Transactions', async ({ page }) => {
    const select = page.locator('select[name="preset"]');
    await expect(select).toBeVisible();
    const values = await select.locator('option').evaluateAll(
      (opts: HTMLOptionElement[]) => opts.map((o) => o.value),
    );
    expect(values).toEqual(['', 'transactions']);
  });

  test('transactions preset with no query lists transaction stories', async ({ page }) => {
    await page.goto('modules.php?name=Search&preset=transactions');
    await page.waitForLoadState('domcontentloaded');
    await assertNoPhpErrors(page);
    await expect(page.locator('.search-results .search-result')).toHaveCount(10);
    const nextLink = page.locator('.search-pagination__link--next');
    await expect(nextLink).toBeVisible();
    await expect(nextLink).toHaveAttribute('href', /preset=transactions/);
    await expect(page.locator('select[name="preset"]')).toHaveValue('transactions');
  });

  test('transactions preset narrows a text query', async ({ page }) => {
    await page.goto('modules.php?name=Search&preset=transactions&query=waive');
    await page.waitForLoadState('domcontentloaded');
    await assertNoPhpErrors(page);
    const results = page.locator('.search-result');
    expect(await results.count()).toBeGreaterThanOrEqual(3);
    const titles = await page.locator('.search-result__title').allTextContents();
    for (const title of titles) {
      expect(title).toMatch(/waiv/i);
    }
  });

  test('transactions preset excludes non-transaction categories', async ({ page }) => {
    // Control: blockbuster story (catid=0) is visible in a plain query
    await page.goto('modules.php?name=Search&query=blockbuster');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.search-result')).toHaveCount(1);

    // With preset: catid=0 story must be absent
    await page.goto('modules.php?name=Search&preset=transactions&query=blockbuster');
    await page.waitForLoadState('domcontentloaded');
    await assertNoPhpErrors(page);
    await expect(page.locator('.ibl-empty-state')).toBeVisible();
    await expect(page.locator('.search-result')).toHaveCount(0);
  });

  test('unknown preset is ignored', async ({ page }) => {
    await page.goto('modules.php?name=Search&preset=bogus');
    await page.waitForLoadState('domcontentloaded');
    await assertNoPhpErrors(page);
    await expect(page.locator('.search-results')).toHaveCount(0);
    await expect(page.locator('select[name="preset"]')).toHaveValue('');
  });

  test('choosing Transactions in the Search form returns results', async ({ page }) => {
    await page.locator('select[name="preset"]').selectOption('transactions');
    await page.locator('.ibl-search__btn').click();
    await page.waitForLoadState('domcontentloaded');
    await assertNoPhpErrors(page);
    await expect(page.locator('.search-results .search-result')).toHaveCount(10);
  });
});
