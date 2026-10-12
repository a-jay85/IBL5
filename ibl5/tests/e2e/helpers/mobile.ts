import type { Page, Locator } from '@playwright/test';
import { expect } from '../fixtures/base';

/**
 * Assert the page body has no horizontal overflow at the current viewport width.
 * This catches elements wider than the viewport (e.g., fixed-width tables, images).
 */
export async function assertNoHorizontalOverflow(page: Page, context?: string): Promise<void> {
  const overflow = await page.evaluate(() => {
    const body = document.body;
    return {
      scrollWidth: body.scrollWidth,
      clientWidth: body.clientWidth,
    };
  });
  expect(
    overflow.scrollWidth,
    `Horizontal overflow detected${context ? ` ${context}` : ''}: scrollWidth=${overflow.scrollWidth} > clientWidth=${overflow.clientWidth}`,
  ).toBeLessThanOrEqual(overflow.clientWidth);
}

/**
 * The two scroll-container mechanisms a page can use. A page uses exactly one:
 * - '.table-scroll-container': server-rendered (TeamView, StandingsView,
 *   SeasonLeaderboardsView) or created by responsive-tables.js for an overflowing table.
 * - '.sticky-scroll-wrapper': server-rendered Pattern 3 wrapper; responsive-tables.js
 *   skips tables inside it, so these pages never get a .table-scroll-container.
 */
export type ScrollWrapperSelector = '.table-scroll-container' | '.sticky-scroll-wrapper';

/**
 * Assert that at least one table sits in the scroll container the page uses.
 * Uses toBeAttached() instead of toBeVisible() — scroll containers may be hidden inside overflow parents.
 */
export async function assertScrollWrappersPresent(
  page: Page,
  context?: string,
  wrapper: ScrollWrapperSelector = '.table-scroll-container',
): Promise<void> {
  await expect(
    page.locator(wrapper).first(),
    `No ${wrapper} found${context ? ` ${context}` : ''}`,
  ).toBeAttached();
}

/**
 * Assert that a scroll container element is scrollable (content overflows the container).
 * Unlike assertNoHorizontalOverflow (which checks document.body), this checks the container itself.
 */
export async function assertScrollContainerIsScrollable(
  page: Page,
  containerLocator: Locator,
  context?: string,
): Promise<void> {
  const metrics = await containerLocator.evaluate((el: Element) => ({
    scrollWidth: (el as HTMLElement).scrollWidth,
    clientWidth: (el as HTMLElement).clientWidth,
  }));
  expect(
    metrics.scrollWidth,
    `Scroll container is not scrollable${context ? ` ${context}` : ''}: scrollWidth=${metrics.scrollWidth} <= clientWidth=${metrics.clientWidth}`,
  ).toBeGreaterThan(metrics.clientWidth);
}
