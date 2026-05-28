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
