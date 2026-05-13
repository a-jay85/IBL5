import { test as base } from '@playwright/test';
import { attachConsoleErrorWatchers } from '../helpers/console-errors';

/**
 * Root e2e fixture. Wraps `@playwright/test` so every `page` in our suite
 * carries a console-error and pageerror watcher attached BEFORE the first
 * navigation. The watcher's asserter runs in teardown and fails the test if
 * any non-allowlisted error was captured.
 *
 * All spec files and downstream fixtures should import `test` / `expect`
 * from this module (or from `fixtures/auth.ts` / `fixtures/public.ts`, which
 * extend this one). Importing directly from `@playwright/test` inside
 * `tests/e2e/**` bypasses the watcher and is enforced-banned by
 * `eslint.config.js`.
 *
 * Exception: `tests/e2e/auth.setup.ts` runs before any fixture is established
 * and must keep its direct `@playwright/test` import.
 */
export const test = base.extend({
  page: async ({ page }, use, testInfo) => {
    const watcher = attachConsoleErrorWatchers(page);
    try {
      await use(page);
    } finally {
      // assertNoConsoleErrors no-ops when the test already failed — its
      // primary error is more useful than derived console-noise failure.
      watcher.assertNoConsoleErrors(testInfo);
    }
  },
});

export { expect } from '@playwright/test';
