import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';

/**
 * PHP error patterns to check for in page body text.
 * Every smoke test must check visited pages for these patterns.
 */
export const PHP_ERROR_PATTERNS = [
  'Fatal error',
  'Warning:',
  'Parse error',
  'Uncaught',
  'Stack trace:',
];

/**
 * Assert that a page contains no PHP errors.
 * Extracts body text and checks against all known PHP error patterns.
 */
export async function assertNoPhpErrors(page: Page, context?: string): Promise<void> {
  const body = await page.locator('body').textContent();
  for (const pattern of PHP_ERROR_PATTERNS) {
    expect(
      body,
      `PHP error "${pattern}" ${context ?? 'found'}`,
    ).not.toContain(pattern);
  }
}
