import { test as base } from './base';
import { createCookieStateFixture, type SetStateFn } from '../helpers/test-state';

export type { SetStateFn };

/**
 * Authenticated non-admin fixture.
 *
 * Contrast with fixtures/auth.ts: that one uses an admin storage state
 * (roles_mask=1), so requests bypass ModuleAccessControl gating and
 * phase/trivia/admin checks always succeed. This fixture uses a
 * roles_mask=0 user with no ibl_team_info row — the "authenticated
 * non-admin without a franchise" path. Use this for tests that need
 * the gating logic to actually run (e.g., asserting a phase-restricted
 * module is hidden, or that an admin-only endpoint returns 403).
 *
 * Logout tests must use fixtures/public.ts instead.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  storageState: 'playwright/.auth/regular.json',
  appState: createCookieStateFixture(),
});

export { expect } from './base';
