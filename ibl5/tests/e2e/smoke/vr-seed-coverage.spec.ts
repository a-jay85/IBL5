import { test, expect } from '@playwright/test';
import { publicStorageState } from '../helpers/public-storage-state';
import { assertNoPhpErrors } from '../helpers/php-errors';

test.use({ storageState: publicStorageState() });

/**
 * Guards the panels the VR `index` row depends on. The CI seed renders the
 * League Leaders block (`ci-seed.sql:1087-1092`: the `block-Leaders.php`
 * `nuke_blocks` row plus `ibl_plr` rows with `stats_gm > 0`). A seed edit that
 * blanks it would leave the VR row passing against a refreshed, emptier
 * baseline, so a later recolor of these panels would go unseen.
 */
test.describe('VR seed coverage: homepage panels', () => {
  test('homepage leaders block renders a leader value', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on homepage leaders value');
    await expect(page.locator('.leaders-tabbed__leader-value').first()).toBeVisible();
  });

  test('homepage leaders block renders runner ranks', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on homepage leaders runner ranks');
    // Presence only: the seed comment does not pin each player's `retired` value.
    await expect(page.locator('.leaders-tabbed__runner-rank').first()).toBeAttached();
  });

  test('news-article links sit outside the masked article body', async ({ page }) => {
    await page.goto('index.php');
    await assertNoPhpErrors(page, 'on homepage news-article link');
    await expect(page.locator('.news-article__link').first()).toBeVisible();
    // The VR `index` row masks `div.news-article__body`. A link moved inside it
    // would hide a future recolor from VR.
    await expect(page.locator('div.news-article__body .news-article__link')).toHaveCount(0);
  });
});
