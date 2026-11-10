import type { Page } from '@playwright/test';

/** True for google.com / googleapis.com and their subdomains only. */
export function isGoogleHost(hostname: string): boolean {
  return /(^|\.)(google\.com|googleapis\.com)$/.test(hostname);
}

/** The theme loads its web fonts from here on every page, so it says nothing about the Sheets flow. */
const FONTS_HOST = 'fonts.googleapis.com';

/**
 * Counts requests the BROWSER sends to Google, apart from the theme's web-font CSS.
 * PHP's server-side Google calls are invisible here; PHPUnit pins those.
 * Attach before the first navigation.
 */
export function countGoogleRequests(page: Page): () => number {
  let count = 0;
  page.on('request', (request) => {
    const { hostname } = new URL(request.url());
    if (hostname !== FONTS_HOST && isGoogleHost(hostname)) {
      count++;
    }
  });
  return () => count;
}
