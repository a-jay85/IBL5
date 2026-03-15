import { test as base } from '@playwright/test';
import { createCookieStateFixture, type SetStateFn } from '../helpers/test-state';

/**
 * Public (unauthenticated) test fixture with appState control.
 *
 * - Uses empty storageState (no auth cookies).
 * - Uses cookie-based state overrides — no DB races in parallel tests.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  storageState: async ({}, use) => {
    await use({ cookies: [], origins: [] });
  },
  appState: createCookieStateFixture(),
});

export { expect } from '@playwright/test';
