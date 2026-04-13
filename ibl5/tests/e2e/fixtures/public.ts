import { test as base } from '@playwright/test';
import { createCookieStateFixture, type SetStateFn } from '../helpers/test-state';
import { publicStorageState } from '../helpers/public-storage-state';

/**
 * Public (unauthenticated) test fixture with appState control.
 *
 * - Sets _no_auto_login cookie so DevAutoLogin doesn't fire.
 * - Uses cookie-based state overrides — no DB races in parallel tests.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  storageState: async ({}, use) => {
    await use(publicStorageState());
  },
  appState: createCookieStateFixture(),
});

export { expect } from '@playwright/test';
