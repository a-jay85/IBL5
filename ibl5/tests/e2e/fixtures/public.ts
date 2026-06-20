import { test as base } from './base';
import { createCookieStateFixture, type SetStateFn } from '../helpers/test-state';
import { publicStorageState } from '../helpers/public-storage-state';

/**
 * Public (unauthenticated) test fixture with appState control.
 *
 * - Sets _e2e cookie so PageCache is skipped; stays logged out (no auth opt-in).
 * - Uses cookie-based state overrides — no DB races in parallel tests.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  storageState: async ({}, use) => {
    await use(publicStorageState());
  },
  appState: createCookieStateFixture(),
});

export { expect } from './base';
