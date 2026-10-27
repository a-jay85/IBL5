import { test, expect } from '../fixtures/public';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// Player-view tables inside the gold .player-stats-card.
//
// The characterization block is class-agnostic (.player-stats-card table, th,
// td, tr) so it passes both on the legacy markup and after the
// conversion to .ibl-data-table.player-view-table. It locks the card's look:
// if a future .ibl-data-table base change leaks into the card, it fails here.

const AVERAGES_URL = 'modules.php?name=Player&pa=showpage&pid=3&pageView=4';
const TOTALS_URL = 'modules.php?name=Player&pa=showpage&pid=3&pageView=3';
const RATINGS_URL = 'modules.php?name=Player&pa=showpage&pid=3&pageView=9';

type CardTableMetrics = {
  boxShadow: string;
  radius: string;
  overflowX: string;
  display: string;
  thColors: string[];
  thFontSizes: string[];
  titleBackgroundImage: string;
  dataRowBackgrounds: string[];
  edgePadding: { first: string; middle: string; last: string } | null;
};

async function readCardTableMetrics(page: Page): Promise<CardTableMetrics[]> {
  return page.evaluate(() => {
    const pad = (cell: Element): string => {
      const cs = getComputedStyle(cell);
      return `${cs.paddingLeft} ${cs.paddingRight}`;
    };
    return Array.from(document.querySelectorAll<HTMLTableElement>('.player-stats-card table')).map((table) => {
      const cs = getComputedStyle(table);
      // The card title sits above the table's scroller (lifted out of thead)
      const titleCell = table.closest('.player-stats-card')
        ?.querySelector('.stats-table-header, .player-view-table__title') ?? undefined;
      const bodyRows = Array.from(table.tBodies).flatMap((tbody) => Array.from(tbody.rows))
        .concat(table.tFoot ? Array.from(table.tFoot.rows) : []);
      const dataRows = bodyRows.filter((tr) => tr.querySelector('th') === null);
      const firstDataCells = dataRows.length > 0 ? Array.from(dataRows[0].querySelectorAll('td')) : [];
      return {
        boxShadow: cs.boxShadow,
        radius: cs.borderTopLeftRadius,
        overflowX: cs.overflowX,
        display: cs.display,
        thColors: Array.from(table.querySelectorAll('th')).map((th) => getComputedStyle(th).color),
        thFontSizes: Array.from(table.querySelectorAll('th')).map((th) => getComputedStyle(th).fontSize),
        titleBackgroundImage: titleCell ? getComputedStyle(titleCell).backgroundImage : '',
        dataRowBackgrounds: dataRows.map((tr) => getComputedStyle(tr).backgroundColor),
        edgePadding: firstDataCells.length >= 3
          ? {
              first: pad(firstDataCells[0]),
              middle: pad(firstDataCells[Math.floor(firstDataCells.length / 2)]),
              last: pad(firstDataCells[firstDataCells.length - 1]),
            }
          : null,
      };
    });
  });
}

test.describe('player stats card look (characterization)', () => {
  // Master's computed title-cell background differs per path: the styleTable()
  // flip cards lose the gold gradient to the later `.player-stats-card
  // .stats-table td { background: transparent }` rule, while the wrap() ratings
  // card keeps it. Both values are locked as master computes them.
  const PAGES = [
    { name: 'averages', url: AVERAGES_URL, titleBackgroundImage: 'none' },
    { name: 'ratings', url: RATINGS_URL, titleBackgroundImage: 'linear-gradient' },
  ] as const;

  for (const { name, url, titleBackgroundImage } of PAGES) {
    test(`gold card tables keep the card look on ${name}`, async ({ page }) => {
      await gotoWithRetry(page, url);
      await page.waitForLoadState('load');
      await assertNoPhpErrors(page, `on player ${name} page`);

      const tables = await readCardTableMetrics(page);
      expect(tables.length, 'card tables present').toBeGreaterThan(0);

      for (const [i, t] of tables.entries()) {
        const label = `${name} table #${i}`;
        expect(t.boxShadow, `${label} box-shadow`).toBe('none');
        expect(t.radius, `${label} radius`).toBe('0px');
        expect(t.overflowX, `${label} overflow-x`).toBe('visible');
        expect(t.display, `${label} display`).toBe('table');
        for (const color of t.thColors) {
          expect(color, `${label} th color`).not.toBe('rgb(255, 255, 255)');
        }
        for (const size of t.thFontSizes) {
          expect(size, `${label} th font-size (--pc-text-sm)`).toBe('11px');
        }
        expect(t.titleBackgroundImage, `${label} title cell`).toContain(titleBackgroundImage);
        for (const bg of t.dataRowBackgrounds) {
          expect(bg, `${label} data row background`).toBe('rgba(0, 0, 0, 0)');
        }
        if (t.edgePadding !== null) {
          expect(t.edgePadding.first, `${label} first td padding`).toBe(t.edgePadding.middle);
          expect(t.edgePadding.last, `${label} last td padding`).toBe(t.edgePadding.middle);
        }
      }
    });
  }
});

test.describe('player view tables (post-conversion)', () => {
  const AVERAGES_TABLE = '.stats-front .player-stats-card table.ibl-data-table';

  test('column headers live in thead and are the sort targets', async ({ page }) => {
    await gotoWithRetry(page, AVERAGES_URL);
    await page.waitForLoadState('load');
    await assertNoPhpErrors(page, 'on player averages page');

    const table = page.locator(AVERAGES_TABLE);
    await expect(table).toHaveCount(1);
    await expect(table.locator('tbody th')).toHaveCount(0);

    const headerCells = table.locator('thead tr:last-child > *');
    expect(await headerCells.count(), 'header cells present').toBeGreaterThan(1);
    const roles = await headerCells.evaluateAll((cells) => cells.map((c) => c.getAttribute('role')));
    for (const role of roles) {
      expect(role, 'thead header cell role').toBe('columnheader');
    }

    const titleRole = await page.locator('.stats-front .player-stats-card > .stats-table-header').getAttribute('role');
    expect(titleRole, 'title role').toBeNull();
    await expect(table.locator('.stats-table-header')).toHaveCount(0);
  });

  // pid=3 averages table: 4 <tr> through </tbody> (2 thead rows + 2 season rows); pid=1 has only one season.
  test('sorting a column keeps the career row last in tfoot', async ({ page }) => {
    await gotoWithRetry(page, AVERAGES_URL);
    await page.waitForLoadState('load');

    const table = page.locator(AVERAGES_TABLE);
    const secondHeader = table.locator('thead tr:last-child th').nth(1);
    await secondHeader.click();
    await expect(secondHeader).toHaveAttribute('aria-sort', 'descending');

    // The season rows themselves must be reordered: team column non-increasing
    const teams = await table.locator('tbody tr').evaluateAll(
      (rows) => rows.map((r) => (r.children[1].textContent ?? '').trim().toLowerCase()),
    );
    expect(teams.length, 'season rows present').toBeGreaterThan(1);
    const descending = [...teams].sort((a, b) => b.localeCompare(a));
    expect(teams, 'season rows in descending team order after sort').toEqual(descending);

    const lastRow = await table.evaluate((t: HTMLTableElement) => {
      const last = t.rows[t.rows.length - 1];
      return { isCareer: last.classList.contains('career-row'), inTfoot: last.closest('tfoot') !== null };
    });
    expect(lastRow.isCareer, 'last row is the career row').toBe(true);
    expect(lastRow.inTfoot, 'career row sits in tfoot').toBe(true);
  });

  test('ratings card title keeps the gold bar over the legacy blue bar', async ({ page }) => {
    await gotoWithRetry(page, RATINGS_URL);
    await page.waitForLoadState('load');
    await assertNoPhpErrors(page, 'on player ratings page');

    const titles = page.locator('.player-stats-card > .player-view-table__title');
    await expect(titles.first()).toBeAttached();
    const styles = await titles.evaluateAll((els) => els.map((el) => {
      const cs = getComputedStyle(el);
      return { image: cs.backgroundImage, color: cs.backgroundColor, padding: cs.padding };
    }));
    for (const [i, bg] of styles.entries()) {
      expect(bg.image, `title #${i} background-image`).toContain('linear-gradient');
      expect(bg.color, `title #${i} background-color`).not.toBe('rgb(0, 0, 204)');
      expect(bg.padding, `title #${i} padding (10px --pc-pad-xl)`).toBe('10px 16px');
    }
  });

  // pid=3 totals table: made-attempt cells carry sorttable_customkey = makes,
  // so "97-259" sorts by 97 and not as text.
  test('made-attempt columns sort numerically by makes', async ({ page }) => {
    await gotoWithRetry(page, TOTALS_URL);
    await page.waitForLoadState('load');
    await assertNoPhpErrors(page, 'on player totals page');

    const table = page.locator(AVERAGES_TABLE);
    await expect(table).toHaveCount(1);
    const header = table.locator('thead tr:last-child th', { hasText: 'FGM-FGA' });
    await header.click();
    await expect(header).toHaveAttribute('aria-sort', 'descending');

    const makes = await table.locator('tbody tr').evaluateAll(
      (rows) => rows.map((r) => Number.parseInt(r.children[4].textContent ?? '', 10)),
    );
    expect(makes.length, 'season rows present').toBeGreaterThan(1);
    for (let i = 1; i < makes.length; i++) {
      expect(makes[i - 1], `makes row ${i - 1} >= row ${i}`).toBeGreaterThanOrEqual(makes[i]);
    }
  });

  test.describe('mobile', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('responsive-tables skips card tables and edge padding matches', async ({ page }) => {
      await gotoWithRetry(page, AVERAGES_URL);
      await page.waitForLoadState('load');
      await assertNoPhpErrors(page, 'on player averages page (mobile)');

      const tables = page.locator('.player-stats-card table.ibl-data-table');
      expect(await tables.count(), 'card data tables present').toBeGreaterThan(0);

      const states = await tables.evaluateAll((ts) => ts.map((t) => ({
        responsiveInit: t.getAttribute('data-responsive-init'),
        wrapped: t.closest('.table-scroll-wrapper') !== null,
      })));
      for (const [i, s] of states.entries()) {
        expect(s.responsiveInit, `table #${i} data-responsive-init`).toBeNull();
        expect(s.wrapped, `table #${i} inside .table-scroll-wrapper`).toBe(false);
      }

      const padding = await page.locator(AVERAGES_TABLE).evaluate((t: HTMLTableElement) => {
        const cells = Array.from(t.tBodies[0].rows[0].querySelectorAll('td'));
        const pad = (c: Element): string => {
          const cs = getComputedStyle(c);
          return `${cs.paddingLeft} ${cs.paddingRight}`;
        };
        return {
          first: pad(cells[0]),
          middle: pad(cells[Math.floor(cells.length / 2)]),
          last: pad(cells[cells.length - 1]),
        };
      });
      expect(padding.first, 'first td padding').toBe(padding.middle);
      expect(padding.last, 'last td padding').toBe(padding.middle);

      // The card's mobile sizing out-ranks the .ibl-data-table mobile compact rule
      const thFontSizes = await page.locator(`${AVERAGES_TABLE} thead th`).evaluateAll(
        (ths) => ths.map((th) => getComputedStyle(th).fontSize),
      );
      expect(thFontSizes.length, 'header cells present').toBeGreaterThan(0);
      for (const size of thFontSizes) {
        expect(size, 'mobile th font-size (--pc-text-xs)').toBe('10px');
      }
      expect(padding.middle, 'mobile td padding').toBe('4px 4px');

      // The gold title bar keeps its card padding (10px --pc-pad-md / 16px --pc-pad-lg)
      const titlePadding = await page.locator('.stats-front .player-stats-card > .stats-table-header').evaluate(
        (el) => getComputedStyle(el).padding,
      );
      expect(titlePadding, 'mobile title padding').toBe('10px 16px');
    });

    // Owner decision on #2897 ("B2-2"): on a phone the title stays in view while
    // the columns scroll sideways under it.
    const TITLE_PAGES = [
      { name: 'averages', url: AVERAGES_URL, title: '.stats-front .player-stats-card .stats-table-header' },
      { name: 'totals', url: TOTALS_URL, title: '.stats-front .player-stats-card .stats-table-header' },
      { name: 'ratings', url: RATINGS_URL, title: '.player-stats-card .player-view-table__title' },
    ] as const;

    for (const { name, url, title } of TITLE_PAGES) {
      test(`${name} title stays in the viewport when the columns scroll`, async ({ page }) => {
        await gotoWithRetry(page, url);
        await page.waitForLoadState('load');
        await assertNoPhpErrors(page, `on player ${name} page (mobile)`);

        const titleEl = page.locator(title).first();
        await expect(titleEl).toBeVisible();

        // Scroll every horizontal scroller in the card (or the card itself) to its far end
        const scrolled = await titleEl.evaluate((el) => {
          const card = el.closest('.player-stats-card');
          if (card === null) return 0;
          const scrollers = [card, ...Array.from(card.querySelectorAll('*'))]
            .filter((s) => s.scrollWidth > s.clientWidth + 1);
          for (const s of scrollers) s.scrollLeft = s.scrollWidth;
          return scrollers.length;
        });
        expect(scrolled, `${name}: the wide table scrolls sideways`).toBeGreaterThan(0);

        const box = await titleEl.boundingBox();
        expect(box, `${name}: title has a box`).not.toBeNull();
        const viewportWidth = page.viewportSize()?.width ?? 0;
        // e2e-hygiene-allow: a title that scrolls away has a negative x, so >= 0 is the real bound
        expect(box!.x, `${name}: title left edge in viewport`).toBeGreaterThanOrEqual(0);
        expect(box!.x + box!.width, `${name}: title right edge in viewport`).toBeLessThanOrEqual(viewportWidth);
      });
    }
  });

  // Owner decision on #2897 ("B2-2"): the Career row is one even shade under the gold rule.
  for (const { name, url } of [{ name: 'averages', url: AVERAGES_URL }, { name: 'totals', url: TOTALS_URL }]) {
    test(`${name} career row has one even background`, async ({ page }) => {
      await gotoWithRetry(page, url);
      await page.waitForLoadState('load');
      await assertNoPhpErrors(page, `on player ${name} page`);

      const cells = await page.locator(`${AVERAGES_TABLE} tfoot tr.career-row td`).evaluateAll(
        (tds) => tds.map((td) => {
          const cs = getComputedStyle(td);
          return { image: cs.backgroundImage, color: cs.backgroundColor, rule: `${cs.borderTopWidth} ${cs.borderTopColor}` };
        }),
      );
      expect(cells.length, `${name}: career cells present`).toBeGreaterThan(2);
      for (const [i, c] of cells.entries()) {
        expect(c.image, `${name}: career cell #${i} background-image`).toBe('none');
        expect(c.color, `${name}: career cell #${i} background-color`).toBe(cells[0].color);
        expect(c.rule, `${name}: career cell #${i} top rule`).toBe(cells[0].rule);
      }
      expect(cells[0].color, `${name}: career row is shaded`).not.toBe('rgba(0, 0, 0, 0)');
      expect(cells[0].rule, `${name}: gold 2px top rule`).toMatch(/^2px /);
    });
  }
});
