import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  assertSearchFormPresent,
  assertFilterDropdownsPresent,
  assertSearchTypeRadiosPresent,
  assertSearchSubmitsTo,
} from '../helpers/search-form-assertions';

// Topics — public page, no authentication required.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Topics flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Topics');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });

  test('topic cards or empty state render', async ({ page }) => {
    const grid = page.locator('.topics-grid');
    const emptyState = page.locator('.ibl-empty-state');
    const hasGrid = (await grid.count()) > 0;
    const hasEmpty = (await emptyState.count()) > 0;

    expect(hasGrid || hasEmpty).toBe(true);

    if (hasGrid) {
      await expect(page.locator('.topic-card').first()).toBeVisible();
    }
  });

  test('search form present with filters and radios', async ({ page }) => {
    // Skip if no topics (empty state has no search form)
    const grid = page.locator('.topics-grid');
    if ((await grid.count()) === 0) {
      test.skip(true, 'No topics rendered — search form not shown');
    }

    await assertSearchFormPresent(page);
    await assertFilterDropdownsPresent(page);
    await assertSearchTypeRadiosPresent(page);
  });

  test('search form submits to Search module', async ({ page }) => {
    const grid = page.locator('.topics-grid');
    if ((await grid.count()) === 0) {
      test.skip(true, 'No topics rendered — search form not shown');
    }

    await assertSearchSubmitsTo(page, 'trade', 'name=Search');
  });

  test('topic card links work', async ({ page }) => {
    const topicLinks = page.locator('.topic-card__title a');
    if ((await topicLinks.count()) === 0) {
      test.skip(true, 'No topic card links available');
    }

    const href = await topicLinks.first().getAttribute('href');
    expect(href).toContain('name=News');
    expect(href).toContain('topic=');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Topics page');
  });
});
