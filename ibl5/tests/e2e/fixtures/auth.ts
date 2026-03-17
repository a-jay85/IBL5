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
 *
 * WARNING: Never use this fixture for logout tests — destroying the
 * server-side session invalidates auth state for ALL parallel workers.
 * Logout tests must use the public fixture with manual login instead.
 *
 * NOTE: The test user has admin privileges, which bypasses
 * ModuleAccessControl phase-gating. Tests asserting "module hidden
 * when phase is X" will always fail for admin users regardless of
 * the phase setting.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  appState: createCookieStateFixture(),
});

export { expect } from '@playwright/test';
