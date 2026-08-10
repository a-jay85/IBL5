import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import {
  assertSearchFormPresent,
  assertFilterDropdownsPresent,
  assertSearchTypeRadiosPresent,
  assertSearchSubmitsTo,
} from '../helpers/search-form-assertions';
import { publicStorageState } from '../helpers/public-storage-state';

// Topics — public page, no authentication required.
test.use({ storageState: publicStorageState() });

test.describe('Topics flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Topics');
  });

  test('page loads with title', async ({ page }) => {
    await expect(page.locator('.ibl-title').first()).toBeVisible();
  });

  test('topic cards render for the seeded topics', async ({ page }) => {
    await expect(page.locator('.topics-grid')).toBeVisible();
    await expect(page.locator('.ibl-empty-state')).toHaveCount(0);
    await expect(page.locator('.topic-card').first()).toBeVisible();
    // AGREE: no — ci-seed has 3 topics, wtdb has 33; using toBeGreaterThanOrEqual(1)
    expect(await page.locator('.topic-card').count()).toBeGreaterThanOrEqual(1);
  });

  // CI seed has 3 nuke_topics rows and stories linked to topic=1,
  // so the topics grid will render with search form visible.

  test('search form present with filters and radios', async ({ page }) => {
    await expect(page.locator('.topics-grid')).toBeVisible();
    await assertSearchFormPresent(page);
    await assertFilterDropdownsPresent(page);
    await assertSearchTypeRadiosPresent(page);
  });

  test('search form submits to Search module', async ({ page }) => {
    await expect(page.locator('.topics-grid')).toBeVisible();
    await assertSearchSubmitsTo(page, 'trade', 'name=Search');
  });

  test('topic card links work', async ({ page }) => {
    const topicLinks = page.locator('.topic-card__title a');
    await expect(topicLinks.first()).toBeVisible();

    const href = await topicLinks.first().getAttribute('href');
    expect(href).toContain('name=News');
    expect(href).toContain('topic=');
  });

  test('no PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Topics page');
  });
});
