import type { ConsoleMessage, Page, TestInfo } from '@playwright/test';

export const CONSOLE_ALLOWLIST: RegExp[] = [
  // Browsers log "Failed to load resource" for any non-2xx response. 404/4xx
  // routes are first-class behavior tested explicitly — these logs are noise.
  /Failed to load resource:.*status of 4\d\d/,
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
