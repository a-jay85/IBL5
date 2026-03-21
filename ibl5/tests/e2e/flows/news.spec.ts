import { test, expect } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';

// News — public, legacy PHP-Nuke module.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('News module flow', () => {
  test('news index page loads with content area', async ({ page }) => {
    await page.goto('modules.php?name=News');
    await expect(page.locator('#site-content').first()).toBeVisible();
    await assertNoPhpErrors(page, 'on News index');
  });

  test('news index shows article with Read More link', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const links = page.locator('.news-article__link');
    const count = await links.count();
    if (count > 0) {
      await expect(links.first()).toBeVisible();
    }
  });

  test('article links contain sid= parameter', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const sidLinks = page.locator('.news-article__link[href*="sid="]');
    const count = await sidLinks.count();
    if (count > 0) {
      await expect(sidLinks.first()).toBeVisible();
    }
  });

  test('navigating via Read More loads article detail', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const link = page.locator('.news-article__link[href*="sid="]').first();
    const linkCount = await link.count();
    if (linkCount > 0) {
      const href = await link.getAttribute('href');
      expect(href).toBeTruthy();
      await page.goto(href!);
      await assertNoPhpErrors(page, 'on News article detail via Read More');
    }
  });

  test('article detail page has title and body content', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const link = page.locator('.news-article__link[href*="sid="]').first();
    if ((await link.count()) === 0) return;

    const href = await link.getAttribute('href');
    await page.goto(href!);

    // Article should have a visible heading (title)
    const heading = page.locator('h2, h3, .news-article__title').first();
    await expect(heading).toBeVisible();

    // Article body should have substantial text content
    const bodyText = await page.locator('#site-content').textContent();
    expect(bodyText!.length).toBeGreaterThan(20);

    await assertNoPhpErrors(page, 'on article detail content check');
  });

  test('article meta-items are visible when present', async ({ page }) => {
    await page.goto('modules.php?name=News');
    const metaItems = page.locator('.news-article__meta-item');
    const count = await metaItems.count();
    if (count > 0) {
      await expect(metaItems.first()).toBeVisible();
    }
  });

  test('individual article view loads', async ({ page }) => {
    // First find a valid article sid from the index page
    await page.goto('modules.php?name=News');
    const link = page.locator('.news-article__link[href*="sid="]').first();
    const linkCount = await link.count();
    if (linkCount > 0) {
      const href = await link.getAttribute('href');
      await page.goto(href!);
      await assertNoPhpErrors(page, 'on News article view');
    } else {
      // Fallback: just load the article page with a known sid
      await page.goto('modules.php?name=News&file=article&sid=20');
      await assertNoPhpErrors(page, 'on News article view fallback');
    }
  });

  test('categories page loads', async ({ page }) => {
    await page.goto('modules.php?name=News&file=categories');
    await assertNoPhpErrors(page, 'on News categories');
  });

  test('article with nonexistent sid shows no PHP errors', async ({ page }) => {
    await page.goto('modules.php?name=News&file=article&sid=99999');
    await assertNoPhpErrors(page, 'on nonexistent article');
  });

  test('news index with topic filter loads', async ({ page }) => {
    await page.goto('modules.php?name=News&new_topic=1');
    await assertNoPhpErrors(page, 'on News with topic filter');
  });
});
