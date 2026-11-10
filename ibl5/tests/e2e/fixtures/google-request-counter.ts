import type { Page } from '@playwright/test';

/** True for google.com / googleapis.com and their subdomains only. */
export function isGoogleHost(hostname: string): boolean {
  return /(^|\.)(google\.com|googleapis\.com)$/.test(hostname);
}

/**
 * Counts requests the BROWSER sends to Google. PHP's server-side Google calls are
 * invisible here; PHPUnit pins those. Attach before the first navigation.
 */
export function countGoogleRequests(page: Page): () => number {
  let count = 0;
  page.on('request', (request) => {
    if (isGoogleHost(new URL(request.url()).hostname)) {
      count++;
    }
  });
  return () => count;
}
