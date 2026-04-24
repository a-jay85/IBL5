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
 * Retries up to 4 times with increasing back-off before failing.
 */
export async function gotoWithRetry(page: Page, url: string): Promise<void> {
  for (let attempt = 0; attempt < 5; attempt++) {
    if (attempt > 0) await page.waitForTimeout(attempt * 1000);
    try {
      await page.goto(url, { timeout: 15_000 });
    } catch {
      continue;
    }
    const body = await page.locator('body').innerText();
    if (body.trim().length >= 20) return;
  }
  throw new Error(`Page returned blank content after 5 attempts: ${url}`);
}
