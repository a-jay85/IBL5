import type { ConsoleMessage, Page, TestInfo } from '@playwright/test';

/**
 * Allowlist of console errors / page errors that are intentionally tolerated.
 *
 * Each entry must include a comment naming the origin so future readers can
 * decide whether to remove the entry once the source is fixed.
 *
 * Bias toward fixing real bugs over adding allowlist entries. An entry here
 * masks defects in any test that visits the affected page.
 */
export const CONSOLE_ALLOWLIST: RegExp[] = [
  // Browser-level resource-load failures (missing images, icons, vendor
  // assets) emitted by Chromium as `console.error` with status 4xx/5xx.
  // These are environment / asset-pipeline issues, not application bugs;
  // they fire on virtually every page in CI seed where some referenced
  // image isn't present. Filtering keeps the watcher focused on actual
  // app-emitted errors.
  /^Failed to load resource: the server responded with a status of/,
  // CSP `connect-src 'self'` blocks Google Fonts on every page. The
  // restriction is intentional (CSP header in themes/IBL/theme.php);
  // the fonts load via stylesheet instead. The "blocked" console
  // message is the browser reporting the policy working as designed.
  /Content Security Policy directive: "connect-src 'self'"/,
];

export interface ConsoleErrorWatcher {
  assertNoConsoleErrors(testInfo?: TestInfo): void;
}

interface CollectedError {
  kind: 'console' | 'pageerror';
  text: string;
  url: string;
}

function isAllowlisted(text: string): boolean {
  return CONSOLE_ALLOWLIST.some((pattern) => pattern.test(text));
}

/**
 * Register listeners on `page` to collect `console.error` events and uncaught
 * page exceptions. Returns an asserter that callers (typically a fixture
 * teardown) invoke after the test body completes.
 *
 * Listeners must be attached BEFORE any navigation, otherwise events fired
 * during the first `page.goto` are lost. The `page` fixture override in
 * `fixtures/base.ts` wires this up before tests receive the page.
 */
export function attachConsoleErrorWatchers(page: Page): ConsoleErrorWatcher {
  const collected: CollectedError[] = [];

  const onConsole = (message: ConsoleMessage): void => {
    if (message.type() !== 'error') {
      return;
    }
    const text = message.text();
    if (isAllowlisted(text)) {
      return;
    }
    collected.push({ kind: 'console', text, url: page.url() });
  };

  const onPageError = (error: Error): void => {
    const text = `${error.name}: ${error.message}`;
    if (isAllowlisted(text)) {
      return;
    }
    collected.push({ kind: 'pageerror', text, url: page.url() });
  };

  page.on('console', onConsole);
  page.on('pageerror', onPageError);

  return {
    assertNoConsoleErrors(testInfo?: TestInfo): void {
      // Skip the assertion when the test already failed — its primary failure
      // is more useful than a derived console-noise failure.
      if (testInfo?.status === 'failed' || testInfo?.status === 'timedOut') {
        return;
      }
      if (collected.length === 0) {
        return;
      }
      const lines = collected.map(
        (e) => `  [${e.kind}] at ${e.url}\n    ${e.text}`,
      );
      throw new Error(
        `Browser emitted ${collected.length} unexpected console.error / pageerror event(s):\n${lines.join('\n')}`,
      );
    },
  };
}
