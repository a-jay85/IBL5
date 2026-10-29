import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// CI seed: sim 689 ends 2026-03-07, so the window centers on 03-07 and season 2026
// is excluded. The two past-season rows are stored moments seeded in ci-seed.sql (a
// 52-point game on 2024-03-07 and a triple-double on 2021-03-07). Both rows carry game ordinal 1,
// so each renders box-score and player links; no box-score rows back them, so the spec asserts hrefs only.
test.describe('On this day block', () => {
  test('homepage shows on-this-day block with seeded moments', async ({ page }) => {
    await page.goto('index.php');

    const list = page.locator('.on-this-day__list');
    await expect(list).toBeVisible();

    const items = list.locator('> li');
    await expect(items).toHaveCount(2);

    const first = items.nth(0);
    await expect(first).toHaveAttribute('data-moment-kind', 'fifty_point');
    await expect(first).toContainText('2 seasons ago');
    await expect(first).toContainText('Test Player');
    await expect(first).toContainText('52 points');
    await expect(first.locator('a[href="modules.php?name=Player&pa=showpage&pid=1"]')).toHaveText('Test Player (Metros)');
    await expect(first.locator('a[href="modules.php?name=GameBoxscore&date=2024-03-07&game=1&team=1"]')).toHaveText('scored 52 points against the Stars');

    const second = items.nth(1);
    await expect(second).toHaveAttribute('data-moment-kind', 'triple_double');
    await expect(second).toContainText('5 seasons ago');
    await expect(second).toContainText('triple-double');
    await expect(second.locator('a[href*="name=GameBoxscore&date=2021-03-07&game=1&team=1"]')).toHaveCount(1);

    await assertNoPhpErrors(page);
  });

  test('topic-filtered news page omits on-this-day block', async ({ page }) => {
    const response = await page.goto('modules.php?name=News&new_topic=1');

    // A 404 or a failed News render would also leave the block absent.
    expect(response?.status()).toBe(200);
    await assertNoPhpErrors(page);
    await expect(page.locator('.on-this-day__list')).toHaveCount(0);
  });
});
