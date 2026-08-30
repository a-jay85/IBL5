import { expect } from '../fixtures/base';
import type { Page } from '@playwright/test';
import { assertNoPhpErrors } from './php-errors';

/**
 * Assert the standard structure of a sortable `.ibl-data-table` page:
 * navigates to the URL, checks the page title, the data table is visible,
 * it has at least `minRows` body rows, and the page contains no PHP errors.
 *
 * Callers that depend on season state must call `appState` themselves before
 * invoking this helper (the helper only takes the resolved URL).
 */
export async function assertSortableTablePage(
  page: Page,
  opts: { url: string; minRows: number; expectedTitle: RegExp },
): Promise<void> {
  await page.goto(opts.url);
  await expect(page.locator('.ibl-title').first()).toContainText(opts.expectedTitle);
  await expect(page.locator('.ibl-data-table').first()).toBeVisible();
  await expect(page.locator('.ibl-data-table tbody tr')).not.toHaveCount(0);
  // minRows is a hard lower bound on team/data rows
  expect(await page.locator('.ibl-data-table tbody tr').count())
    .toBeGreaterThanOrEqual(opts.minRows);
  await assertNoPhpErrors(page, `on ${opts.url}`);
}

/**
 * Assert a sortable table's column actually sorts: sorttable initialised, the
 * header takes the sorted-reverse state, aria-sort advances, and the column's
 * cell order genuinely changes. `columnIndex` is 1-based (CSS nth-child).
 */
export async function assertColumnSorts(
  page: Page,
  opts: { tableSelector: string; columnIndex: number; minRows: number },
): Promise<void> {
  const table = page.locator(opts.tableSelector).first();
  await expect(table).toHaveAttribute('data-sorttable', 'true');

  const cells = table.locator(`tbody tr td:nth-child(${opts.columnIndex})`);
  expect(await cells.count()).toBeGreaterThanOrEqual(opts.minRows);
  const before = await cells.allTextContents();

  const header = table.locator(`thead th:nth-child(${opts.columnIndex})`);
  await expect(header).toHaveAttribute('aria-sort', 'none');
  await header.click();

  await expect(header).toHaveClass(/sorttable_sorted_reverse/);
  await expect(header).toHaveAttribute('aria-sort', 'descending');
  await expect(page.locator('#sorttable_sortrevind')).toBeAttached();

  const after = await cells.allTextContents();
  // Assert the order genuinely changed. We deliberately do NOT assert the exact
  // descending sequence (`[...before].sort().reverse()`): sorttable.js uses a
  // diacritic-insensitive comparator, so real data with non-ASCII names (e.g.
  // "Dariuš Lavrinovich") diverges from JS `.sort()`'s UTF-16 code-unit order —
  // a comparator mismatch, not a sorting defect. The state assertions above
  // (aria-sort, sorttable_sorted_reverse, #sorttable_sortrevind) prove the sort ran.
  expect(after).not.toEqual(before);
}
