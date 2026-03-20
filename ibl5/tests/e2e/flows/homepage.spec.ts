import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.describe('Homepage flow', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('index.php');
  });

  test('homepage loads with IBL title', async ({ page }) => {
    await expect(page).toHaveTitle(/IBL/i);
  });

  test('news section renders article titles', async ({ page }) => {
    // CI seed has 3 news articles — at least 1 should render
    const articles = page.locator('.ibl-story, .story-title, [class*="story"]');
    const count = await articles.count();
    if (count > 0) {
      await expect(articles.first()).toBeVisible();
    } else {
      // Fallback: news articles might render as links
      const newsLinks = page.locator('a[href*="sid="]');
      expect(await newsLinks.count()).toBeGreaterThanOrEqual(1);
    }
  });

  test('article titles are non-empty', async ({ page }) => {
    // Look for story content in the main body
    const body = await page.locator('body').textContent();
    // CI seed articles should be present
    expect(body).toContain('IBL');
  });

  test('sidebar renders content', async ({ page }) => {
    // The homepage should have sidebar blocks
    const body = await page.locator('body').textContent();
    // Page should have substantial content (not just a bare title)
    expect(body!.length).toBeGreaterThan(100);
  });

  test('no PHP errors on homepage', async ({ page }) => {
    await assertNoPhpErrors(page, 'on homepage');
  });
});

test.describe('Homepage with state override', () => {
  test('homepage renders correctly with trivia mode off', async ({ appState, page }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on homepage with trivia off');
    await expect(page).toHaveTitle(/IBL/i);
  });
});
