import type { Browser, BrowserContext, Page } from '@playwright/test';
import { test, expect } from '../fixtures/public';
import { attachConsoleErrorWatchers } from '../helpers/console-errors';

/**
 * Self-tests for the console-error watcher. These bypass the suite-wide
 * `page` fixture override by opening a fresh page from the `browser`
 * fixture — that page has no watcher attached, so we can exercise the
 * helper directly without the fixture asserter double-failing the test.
 */
async function makeRawPage(browser: Browser): Promise<{ context: BrowserContext; rawPage: Page }> {
  const context = await browser.newContext({
    baseURL: process.env.BASE_URL ?? 'http://main.localhost/ibl5/',
  });
  const rawPage = await context.newPage();
  return { context, rawPage };
}

test.describe('Console error watcher — self-tests', () => {
  test('throws when page emits console.error', async ({ browser }) => {
    const { context, rawPage } = await makeRawPage(browser);
    try {
      await rawPage.goto('index.php');
      const watcher = attachConsoleErrorWatchers(rawPage);

      await rawPage.evaluate(() => {
        console.error('intentional console error from watcher self-test');
      });
      // Yield to let the listener run before asserting.
      // eslint-disable-next-line playwright/no-wait-for-timeout
      await rawPage.waitForTimeout(50);

      expect(() => watcher.assertNoConsoleErrors()).toThrow(/intentional console error/);
    } finally {
      await context.close();
    }
  });

  test('throws when page throws an uncaught exception', async ({ browser }) => {
    const { context, rawPage } = await makeRawPage(browser);
    try {
      await rawPage.goto('index.php');
      const watcher = attachConsoleErrorWatchers(rawPage);

      await rawPage.evaluate(() => {
        setTimeout(() => {
          throw new Error('intentional pageerror from watcher self-test');
        }, 0);
      });
      // eslint-disable-next-line playwright/no-wait-for-timeout
      await rawPage.waitForTimeout(50);

      expect(() => watcher.assertNoConsoleErrors()).toThrow(/intentional pageerror/);
    } finally {
      await context.close();
    }
  });

  test('does not throw on a clean page', async ({ browser }) => {
    const { context, rawPage } = await makeRawPage(browser);
    try {
      await rawPage.goto('index.php');
      const watcher = attachConsoleErrorWatchers(rawPage);
      await rawPage.waitForLoadState('load');

      expect(() => watcher.assertNoConsoleErrors()).not.toThrow();
    } finally {
      await context.close();
    }
  });
});
