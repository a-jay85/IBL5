import { test as base } from '@playwright/test';
import { createAppStateFixture, type SetStateFn } from '../helpers/test-state';

export type { SetStateFn };

/**
 * Authenticated test fixture with optional appState control.
 *
 * - Tests importing `test` from this file use the stored auth state
 *   from auth.setup.ts — no login needed.
 * - The `appState` fixture lets tests set ibl_settings before running
 *   and automatically restores previous values after each test.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  appState: createAppStateFixture(),
});

export { expect } from '@playwright/test';
