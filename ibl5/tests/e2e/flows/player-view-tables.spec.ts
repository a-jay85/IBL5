import { test, expect } from '../fixtures/public';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';

// Player-view tables inside the gold .player-stats-card.
//
// The characterization block is class-agnostic (.player-stats-card table, th,
// td, tr) so it passes both on the legacy player-table markup and after the
// conversion to .ibl-data-table.player-view-table. It locks the card's look:
// if a future .ibl-data-table base change leaks into the card, it fails here.

const AVERAGES_URL = 'modules.php?name=Player&pa=showpage&pid=1&pageView=4';
const RATINGS_URL = 'modules.php?name=Player&pa=showpage&pid=1&pageView=9';

type CardTableMetrics = {
  boxShadow: string;
  radius: string;
  overflowX: string;
  display: string;
  thColors: string[];
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
      const firstRow = table.rows[0];
      const titleCell = firstRow ? firstRow.cells[0] : undefined;
      const bodyRows = Array.from(table.tBodies).flatMap((tbody) => Array.from(tbody.rows));
      const dataRows = bodyRows.filter((tr) => tr.querySelector('th') === null);
      const firstDataCells = dataRows.length > 0 ? Array.from(dataRows[0].querySelectorAll('td')) : [];
      return {
        boxShadow: cs.boxShadow,
        radius: cs.borderTopLeftRadius,
        overflowX: cs.overflowX,
        display: cs.display,
        thColors: Array.from(table.querySelectorAll('th')).map((th) => getComputedStyle(th).color),
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
