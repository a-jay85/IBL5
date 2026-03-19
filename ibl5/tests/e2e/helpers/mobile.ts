import type { Page, Locator } from '@playwright/test';
import { expect } from '@playwright/test';

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
 * Assert that responsive-tables.js has wrapped at least one table in a scroll container.
 * Uses toBeAttached() instead of toBeVisible() — scroll containers may be hidden inside overflow parents.
 */
export async function assertScrollWrappersPresent(page: Page, context?: string): Promise<void> {
  await expect(
    page.locator('.table-scroll-container').first(),
    `No .table-scroll-container found${context ? ` ${context}` : ''}`,
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
