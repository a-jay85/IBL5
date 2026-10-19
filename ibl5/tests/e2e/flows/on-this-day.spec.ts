import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// CI seed: sim 689 ends 2026-03-07, so the window centers on 03-07 and season 2026
// is excluded. The two past-season rows seeded for this block are a 52-point game
// (2024-03-07) and a triple-double (2021-03-07).
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

    const second = items.nth(1);
    await expect(second).toHaveAttribute('data-moment-kind', 'triple_double');
    await expect(second).toContainText('5 seasons ago');
    await expect(second).toContainText('triple-double');

    await assertNoPhpErrors(page);
  });

  test('topic-filtered news page omits on-this-day block', async ({ page }) => {
    await page.goto('modules.php?name=News&new_topic=1');

    await expect(page.locator('.on-this-day__list')).toHaveCount(0);
  });
});
