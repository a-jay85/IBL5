import type { Page } from '@playwright/test';
import { expect } from '../fixtures/base';

/**
 * Matcher for the HTMX `op=api` response URL. Accepts a substring, a RegExp,
 * or a predicate. The helper additionally requires `op=api` and HTTP 200, so
 * callers only need to identify their module (e.g. `'DraftHistory'`).
 */
export type ApiUrlMatcher = string | RegExp | ((url: string) => boolean);

export interface HtmxSwapOptions {
  /** Performs the interaction that fires the HTMX request (selectOption, tab click, …). */
  trigger: () => Promise<void>;
  /** Identifies the `op=api` response to wait for. */
  apiUrlPattern: ApiUrlMatcher;
  /** Regex/string the pushed URL must match after the swap (HX-Push-Url). */
  expectedUrl: string | RegExp;
  /** Content region that holds the swapped markup; asserted visible afterwards. */
  contentSelector: string;
}

function matchesApiUrl(url: string, pattern: ApiUrlMatcher): boolean {
  if (typeof pattern === 'function') {
    return pattern(url);
  }
  if (pattern instanceof RegExp) {
    return pattern.test(url);
  }
  return url.includes(pattern);
}

/**
 * Assert the *mechanics* of an HTMX swap: the fixed nav survives (no full page
 * reload), the `op=api` response lands, the URL is pushed, and the swapped
 * content region is visible. Spec-specific content-delta assertions (which
 * columns/rows/title changed) stay in the calling spec — this helper covers
 * only the swap plumbing shared across DraftHistory / LeagueStarters /
 * FranchiseRecordBook.
 */
export async function assertHtmxSwap(
  page: Page,
  { trigger, apiUrlPattern, expectedUrl, contentSelector }: HtmxSwapOptions,
): Promise<void> {
  // Mark the fixed nav so we can prove it persists (no full reload).
  await page.evaluate(() => {
    const navEl = document.querySelector('nav.fixed');
    if (navEl) navEl.setAttribute('data-htmx-marker', '1');
  });

  await Promise.all([
    page.waitForResponse(
      (r) =>
        matchesApiUrl(r.url(), apiUrlPattern) &&
        r.url().includes('op=api') &&
        r.status() === 200,
    ),
    trigger(),
  ]);

  // HX-Push-Url fires after the swap — allow time for pushState.
  await page.waitForURL(expectedUrl, { timeout: 10_000 });
  await expect(page.locator(contentSelector).first()).toBeVisible();

  // Nav marker survived — confirms an HTMX swap, not a full navigation.
  const marker = await page.evaluate(() =>
    document.querySelector('nav.fixed')?.getAttribute('data-htmx-marker'),
  );
  expect(marker).toBe('1');
}
