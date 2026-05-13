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
 *
 * When IBL_TEST_USER_REGULAR is unset (local dev hasn't opted in),
 * auth-regular.setup.ts skips and the file at playwright/.auth/regular.json
 * is either absent or stale. Fall back to an empty storage state so the
 * fixture itself doesn't error during loading — consumer specs guard
 * their bodies with `test.skip()` against the same env var so the
 * assertions don't run against an unauthenticated session.
 */
const REGULAR_STORAGE_STATE = process.env.IBL_TEST_USER_REGULAR
  ? 'playwright/.auth/regular.json'
  : { cookies: [], origins: [] };

export const test = base.extend<{ appState: SetStateFn }>({
  storageState: REGULAR_STORAGE_STATE,
  appState: createCookieStateFixture(),
});

export { expect } from './base';
