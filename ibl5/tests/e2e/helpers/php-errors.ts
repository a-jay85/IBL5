import type { Page } from '@playwright/test';
import { expect } from '../fixtures/base';

/**
 * PHP error patterns to check for in page body text. These strings never
 * appear in legitimate UI copy, so a bare substring match is safe.
 * Every smoke test must check visited pages for these patterns.
 */
export const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

/**
 * A real PHP warning rendered with `display_errors` always carries the
 * structural "Warning: <msg> in <file> on line <N>" frame on a single line.
 * The bare word "Warning:" also appears in legitimate UI copy (e.g. the
 * rookie-option warning alert), so we match the full frame instead of the
 * bare word to avoid false positives. `.` does not cross newlines, which
 * confines the match to one line and prevents it spanning unrelated body text.
 */
export const PHP_WARNING_REGEX = /Warning:.* in .* on line \d+/;

/**
 * Pure detector: returns the matched PHP-error label, or null if the body is
 * clean. Exported so it can be self-tested without a live page.
 */
export function detectPhpError(body: string): string | null {
  for (const pattern of PHP_ERROR_PATTERNS) {
    if (body.includes(pattern)) {
      return pattern;
    }
  }
  if (PHP_WARNING_REGEX.test(body)) {
    return 'Warning:';
  }
  return null;
}

/**
 * Assert that a page contains no PHP errors.
 * Extracts body text and checks against all known PHP error patterns.
 */
export async function assertNoPhpErrors(page: Page, context?: string): Promise<void> {
  const body = (await page.locator('body').textContent()) ?? '';
  const found = detectPhpError(body);
  expect(
    found,
    `PHP error ${found ? `"${found}" ` : ''}${context ?? 'found'}`,
  ).toBeNull();
}
