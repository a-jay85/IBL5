import { test as base } from '@playwright/test';
import { attachConsoleErrorWatchers } from './console-errors';

export const test = base.extend({
  page: async ({ page }, use, testInfo) => {
    const watcher = attachConsoleErrorWatchers(page);
    try {
      await use(page);
    } finally {
      watcher.assertNoConsoleErrors(testInfo);
    }
  },
});

export { expect } from '@playwright/test';
