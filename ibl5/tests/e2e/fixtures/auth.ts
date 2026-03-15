import { test as base } from '@playwright/test';
import { createCookieStateFixture, type SetStateFn } from '../helpers/test-state';

export type { SetStateFn };

/**
 * Authenticated test fixture with optional appState control.
 *
 * - Tests importing `test` from this file use the stored auth state
 *   from auth.setup.ts — no login needed.
 * - The `appState` fixture sets a cookie-based override that PHP reads
 *   per-request, eliminating race conditions between parallel tests.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  appState: createCookieStateFixture(),
});

export { expect } from '@playwright/test';
