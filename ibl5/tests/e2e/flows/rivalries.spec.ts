import { test, expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

// Rivalries — public pages, no authentication required.
// Seed: franchises 1 and 2 meet exactly 10 times (qualifies); the GM pair
// GM TestUser / GM Stars meets 5 times (below the cutoff).
test.use({ storageState: publicStorageState() });

test.describe('Rivalries flow', () => {
  test('league index lists the one qualifying franchise rivalry', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries');
    await assertNoPhpErrors(page);

    const franchiseRows = page.locator('.ibl-card .ibl-data-table tbody tr');
    await expect(franchiseRows).toHaveCount(1);
    await expect(franchiseRows.first().locator('a[href*="op=teams&a=1&b=2"]')).toHaveCount(1);

    // The GM card is the second section and shows its empty state.
    await expect(page.locator('section.ibl-card').nth(1).locator('.ibl-empty-state')).toBeVisible();
  });

  test('franchise pair page shows record, games, and series sections', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=teams&a=1&b=2');
    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-title').first()).toContainText(' vs ');
    await expect(page.locator('section.ibl-card').first()).toContainText('(10 games)');

    const series = page.locator('section.ibl-card', {
      has: page.getByRole('heading', { name: 'Playoff series' }),
    });
    await expect(series.locator('.ibl-empty-state')).toBeVisible();
  });

  test('GM pair page renders below the cutoff', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=gms&a=GM%20TestUser&b=GM%20Stars');
    await assertNoPhpErrors(page);

    const summary = page.locator('section.ibl-card').first();
    await expect(summary).toContainText('(5 games)');
    await expect(summary).toContainText('Fewer than 10 meetings');
  });

  test('GM view shows the empty rank state', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=gm&gm=GM%20TestUser');
    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-title').first()).toContainText('GM TestUser');
    await expect(page.locator('.ibl-empty-state')).toBeVisible();
  });

  test('invalid pair input renders a clean empty state', async ({ page }) => {
    const cases: Array<{ url: string; message: string }> = [
      // 0/29: out-of-range ids fail the 1..MAX_REAL_TEAMID bound.
      { url: 'modules.php?name=Rivalries&op=teams&a=0&b=29', message: 'Pick two different franchises.' },
      { url: 'modules.php?name=Rivalries&op=teams&a=1&b=1', message: 'Pick two different franchises.' },
      { url: 'modules.php?name=Rivalries&op=teams&a[]=1&b=2', message: 'Pick two different franchises.' },
      {
        url: 'modules.php?name=Rivalries&op=gm&gm=Nobody%20Here',
        message: 'No general manager by that name.',
      },
    ];

    for (const { url, message } of cases) {
      const response = await page.goto(url);
      expect(response?.status(), url).toBe(200);
      await assertNoPhpErrors(page);
      await expect(page.locator('.ibl-empty-state'), url).toBeVisible();
      await expect(page.locator('.ibl-empty-state'), url).toContainText(message);
      await expect(page.locator('.ibl-data-table'), url).toHaveCount(0);
    }
  });

  test('team page shows the rivals card for a qualifying franchise only', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1');
    await assertNoPhpErrors(page);

    const card = page.locator('.team-card', {
      has: page.locator('.team-card__title', { hasText: 'Top Rivals' }),
    });
    await expect(card).toHaveCount(1);
    await expect(card.locator('a[href*="op=teams&a=1&b=2"]')).not.toHaveCount(0);
    await expect(card.locator('a[href*="op=gms"]')).toHaveCount(0);

    await page.goto('modules.php?name=Team&op=team&teamid=5');
    await assertNoPhpErrors(page);
    await expect(page.locator('.team-card__title', { hasText: 'Top Rivals' })).toHaveCount(0);
  });
});
