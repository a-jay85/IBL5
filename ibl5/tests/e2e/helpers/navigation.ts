import type { Page, Locator } from '@playwright/test';

export function desktopNav(page: Page): Locator {
  return page.locator('.nav-desktop').first();
}

export async function openMobileMenu(page: Page): Promise<Locator> {
  await page.locator('#nav-hamburger').click();
  return page.locator('#nav-mobile-menu');
}

/**
 * Navigate and verify the page actually rendered content.
 * Under parallel load, PHP's built-in server can return blank HTML.
 * Retries up to 4 times with linear back-off (attempt * backoffMs) before failing.
 * backoffMs defaults to 1000, so every existing caller keeps its 1+2+3+4 s schedule.
 * Tests that drive the helper against a routed fixture pass a tiny value.
 */
export async function gotoWithRetry(page: Page, url: string, backoffMs = 1000): Promise<void> {
  if (!Number.isFinite(backoffMs) || backoffMs < 0) {
    throw new RangeError(`gotoWithRetry: backoffMs must be a finite number >= 0, got ${backoffMs}`);
  }
  for (let attempt = 0; attempt < 5; attempt++) {
    // eslint-disable-next-line playwright/no-wait-for-timeout -- deliberate retry back-off between gotoWithRetry attempts, not a page-state wait
    if (attempt > 0) await page.waitForTimeout(attempt * backoffMs);
    let response: Awaited<ReturnType<Page['goto']>>;
    try {
      response = await page.goto(url, { timeout: 15_000 });
    } catch {
      continue;
    }
    const body = await page.locator('body').innerText();
    if (body.trim().length < 20) continue;
    if (response && response.status() >= 400) {
      throw new Error(`Page returned HTTP ${response.status()} for ${url}`);
    }
    return;
  }
  throw new Error(`Page returned blank content after 5 attempts: ${url}`);
}
