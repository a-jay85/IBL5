import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * Mark the fixed nav element with a data attribute.
 * Call BEFORE the action that should be intercepted by HTMX.
 */
export async function setNavMarker(page: Page): Promise<void> {
  await page.evaluate(() => {
    const navEl = document.querySelector('nav.fixed');
    if (navEl) navEl.setAttribute('data-htmx-marker', '1');
  });
}

/**
 * Assert the nav marker still exists — nav was NOT re-rendered,
 * confirming HTMX boosted the navigation (no full page reload).
 */
export async function assertNavMarkerPersists(page: Page): Promise<void> {
  const marker = await page.evaluate(() => {
    return document.querySelector('nav.fixed')?.getAttribute('data-htmx-marker');
  });
  expect(marker, 'nav marker should persist (HTMX boost, no full reload)').toBe('1');
}

/**
 * Assert the nav marker is gone — nav WAS re-rendered,
 * confirming a full page reload occurred (hx-boost="false" worked).
 */
export async function assertNavMarkerGone(page: Page): Promise<void> {
  const marker = await page.evaluate(() => {
    return document.querySelector('nav.fixed')?.getAttribute('data-htmx-marker');
  });
  expect(marker, 'nav marker should be gone (full page reload)').toBeNull();
}
