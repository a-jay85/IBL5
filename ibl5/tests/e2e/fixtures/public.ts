import { test as base } from '@playwright/test';
import { createAppStateFixture, type SetStateFn } from '../helpers/test-state';

/**
 * Public (unauthenticated) test fixture with appState control.
 *
 * - Uses empty storageState (no auth cookies).
 * - Provides the same `appState` auto-restore fixture as the auth fixture.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  storageState: async ({}, use) => {
    await use({ cookies: [], origins: [] });
  },
  appState: createAppStateFixture(),
});

export { expect } from '@playwright/test';
