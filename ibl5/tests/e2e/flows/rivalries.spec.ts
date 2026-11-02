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

    const franchiseRows = page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Top franchise rivalries' }),
    }).locator('tbody tr');
    await expect(franchiseRows).toHaveCount(1);
    await expect(franchiseRows.first().locator('a[href*="op=teams&a=1&b=2"]')).toHaveCount(1);

    // The GM table keeps its title and shows its empty state.
    await expect(page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Top GM rivalries' }),
    }).locator('.ibl-empty-state')).toBeVisible();
  });

  test('franchise pair page shows record, games, and series sections', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=teams&a=1&b=2');
    await assertNoPhpErrors(page);

    // The heading is the two Team page banners with "vs" between them.
    const heading = page.locator('h1.rivalries-banner-title');
    await expect(heading.locator('.team-banner-box > img')).toHaveCount(2);
    await expect(heading.locator('.rivalries-banner-vs')).toHaveText('vs');
    // Seed 1-vs-2 games: Metros win 7 (incl. the 2026-06-05 playoff game), Stars win 3.
    const summary = page.locator('.rivalries-summary');
    await expect(summary).toContainText('All-time 7-3 (10 games)');
    await expect(summary).toContainText('Playoffs 1-0');

    const series = page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Playoff series' }),
    });
    await expect(series.locator('.ibl-empty-state')).toBeVisible();

    // Each list shows the top 5 of the 10 meetings. The 4-point 2025-10-18 game is the
    // closest; the 9-point 2026-02-26 game is the biggest blowout.
    const closest = page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Closest games' }),
    }).locator('tbody tr');
    await expect(closest).toHaveCount(5);
    await expect(closest.first()).toContainText('2025-10-18');
    await expect(closest.first()).toContainText('102-98');

    const blowouts = page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Biggest blowouts' }),
    }).locator('tbody tr');
    await expect(blowouts).toHaveCount(5);
    await expect(blowouts.first()).toContainText('2026-02-26');
    await expect(blowouts.first()).toContainText('108-99');
  });

  // No Olympics gate, matching the H2H index. Rivalries and the H2H matrix read
  // IBL tables in either league, so the Olympics page ranks the same seed pairs.
  test('Olympics league renders the IBL rivalry rankings', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&league=olympics');
    await assertNoPhpErrors(page);

    const franchiseRows = page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Top franchise rivalries' }),
    }).locator('tbody tr');
    await expect(franchiseRows).toHaveCount(1);
    await expect(franchiseRows.first().locator('a[href*="op=teams&a=1&b=2"]')).toHaveCount(1);
    // Same seed record as the IBL index: Metros 7, Stars 3, over 10 games.
    await expect(franchiseRows.first().locator('td').nth(2)).toHaveText('7-3');
    await expect(franchiseRows.first().locator('td').nth(3)).toHaveText('10');

    await expect(page.locator('section.rivalries-section', {
      has: page.getByRole('heading', { name: 'Top GM rivalries' }),
    }).locator('.ibl-empty-state')).toBeVisible();
  });

  test('anonymous visitor sees no highlighted rivalry row', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries');
    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-data-table tbody tr')).not.toHaveCount(0);
    await expect(page.locator('tr.user-team-row')).toHaveCount(0);
  });

  test('GM pair page renders below the cutoff', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=gms&a=GM%20TestUser&b=GM%20Stars');
    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-title').first()).toContainText(' vs ');
    const summary = page.locator('.rivalries-summary');
    await expect(summary).toContainText('(5 games)');
    await expect(summary).toContainText('Fewer than 10 meetings');
  });

  test('GM view shows the empty rank state', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=gm&gm=GM%20TestUser');
    await assertNoPhpErrors(page);

    await expect(page.locator('.ibl-title').first()).toContainText('GM TestUser');
    await expect(page.locator('.ibl-empty-state')).toContainText(
      'This general manager has no rival with 10 or more games yet.',
    );
  });

  test('franchise view lists the qualifying rival', async ({ page }) => {
    await page.goto('modules.php?name=Rivalries&op=team&team=1');
    await assertNoPhpErrors(page);

    await expect(page.locator('h1.ibl-title')).toHaveText('Rivalries');
    await expect(page.locator('.ibl-data-table a[href*="op=teams&a=1&b=2"]')).not.toHaveCount(0);
    // Two stacked cards: the franchise's rivals, then its current GM's rivals (GM TestUser owns team 1).
    // Captions are CSS-uppercased; the accessible name keeps the source case.
    await expect(page.getByRole('heading', { name: "New York Metros's Rivals" })).toBeVisible();
    await expect(page.getByRole('heading', { name: "GM TestUser's Rivals" })).toBeVisible();
    await expect(
      page.locator('td.rivalries-gradient a[href*="op=teams&a=1&b=2"]'),
    ).not.toHaveCount(0);
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

  test.describe('layout at 1280', () => {
    test.use({ viewport: { width: 1280, height: 900 } });

    test('Top Rivals card lines up with the franchise history card above it', async ({ page }) => {
      await page.goto('modules.php?name=Team&op=team&teamid=1');
      await assertNoPhpErrors(page);

      const above = await page.locator('.franchise-history-wrapper').boundingBox();
      const card = await page
        .locator('.team-card', { has: page.locator('.team-card__title', { hasText: 'Top Rivals' }) })
        .boundingBox();
      expect(above).not.toBeNull();
      expect(card).not.toBeNull();
      expect(Math.abs(card!.x - above!.x)).toBeLessThanOrEqual(2);
      expect(Math.abs(card!.x + card!.width - (above!.x + above!.width))).toBeLessThanOrEqual(2);
    });

    test('pair page header cells keep the table edge padding on both sides', async ({ page }) => {
      await page.goto('modules.php?name=Rivalries&op=teams&a=1&b=2');
      await assertNoPhpErrors(page);

      // 12px = the 0.75rem .ibl-data-table gives its first and last cells.
      const paddings = await page.locator('.ibl-data-table th').evaluateAll((cells) =>
        cells.map((c) => {
          const s = getComputedStyle(c);
          return { text: c.textContent, left: parseFloat(s.paddingLeft), right: parseFloat(s.paddingRight) };
        }),
      );
      expect(paddings.length).toBeGreaterThan(0);
      for (const p of paddings) {
        expect(p.left, `${p.text} padding-left`).toBeGreaterThanOrEqual(12);
        expect(p.right, `${p.text} padding-right`).toBeGreaterThanOrEqual(12);
      }
    });
  });

  test.describe('layout at 375', () => {
    test.use({ viewport: { width: 375, height: 667 } });

    // A full-page screenshot grows the viewport to the page height and back.
    // responsive-tables.js re-measures on each resize, so the pair-page cards
    // must settle on one layout whatever resizes came before.
    test('GM pair page keeps one layout across resizes', async ({ page }) => {
      await page.goto('modules.php?name=Rivalries&op=gms&a=GM%20TestUser&b=GM%20Stars');
      await assertNoPhpErrors(page);
      await expect(page.locator('.rivalries-section').first()).toBeVisible();

      const layout = () =>
        page.locator('.rivalries-section').evaluateAll((sections) =>
          sections.map((s) => {
            const r = s.getBoundingClientRect();
            return `${Math.round(r.width)}x${Math.round(r.height)}`;
          }),
        );
      const refresh = () =>
        page.evaluate(() => (window as unknown as { IBL_refreshResponsiveTables: () => void }).IBL_refreshResponsiveTables());

      const before = await layout();
      await refresh();
      await page.setViewportSize({ width: 375, height: 2600 });
      await refresh();
      await page.setViewportSize({ width: 375, height: 667 });
      await refresh();
      expect(await layout()).toEqual(before);
    });
  });

  test('Olympics team page has no rivals card', async ({ page }) => {
    await page.goto('modules.php?name=Team&op=team&teamid=1&league=olympics');
    await assertNoPhpErrors(page);

    await expect(page.locator('.team-page-layout')).toBeVisible();
    await expect(page.locator('.team-card__title', { hasText: 'Top Rivals' })).toHaveCount(0);
  });
});
