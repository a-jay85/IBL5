import type { Page } from '@playwright/test';
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
