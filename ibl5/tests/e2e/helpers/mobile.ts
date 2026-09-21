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
 * Assert that at least one table sits in a scroll container: either the
 * responsive-tables.js wrapper or a server-rendered Pattern 3 sticky wrapper.
 * Uses toBeAttached() instead of toBeVisible() — scroll containers may be hidden inside overflow parents.
 */
export async function assertScrollWrappersPresent(page: Page, context?: string): Promise<void> {
  await expect(
    page.locator('.table-scroll-container, .sticky-scroll-wrapper').first(),
    `No .table-scroll-container or .sticky-scroll-wrapper found${context ? ` ${context}` : ''}`,
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
