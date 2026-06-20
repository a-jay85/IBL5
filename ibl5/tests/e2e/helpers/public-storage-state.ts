/**
 * Shared storageState for unauthenticated (public) E2E tests.
 *
 * Sets the `_e2e=1` cookie so PageCache is skipped for unauthenticated GETs,
 * avoiding cross-worker cache pollution. Does NOT set `_auto_login`, so these
 * contexts stay logged out (auto-login is opt-in; absent the cookie DevAutoLogin
 * does not fire).
 *
 * Usage (bare @playwright/test files):
 *   import { publicStorageState } from '../helpers/public-storage-state';
 *   test.use({ storageState: publicStorageState() });
 *
 * The `public` fixture (`../fixtures/public`) sets this automatically.
 */

interface StorageStateCookie {
  name: string;
  value: string;
  domain: string;
  path: string;
  expires: number;
  httpOnly: boolean;
  secure: boolean;
  sameSite: 'Strict' | 'Lax' | 'None';
}

interface StorageState {
  cookies: StorageStateCookie[];
  origins: Array<Record<string, unknown>>;
}

export function publicStorageState(): StorageState {
  const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
  const domain = new URL(baseUrl).hostname;
  return {
    cookies: [
      {
        name: '_e2e',
        value: '1',
        domain,
        path: '/',
        // APIRequestContext.storageState validates these fields strictly
        // (BrowserContext is lenient); supply explicit defaults so tests
        // that take `{ request }` from fixtures/public don't crash with
        // "storageState.cookies[0].expires: expected float, got undefined".
        expires: -1,
        httpOnly: false,
        secure: false,
        sameSite: 'Lax',
      },
    ],
    origins: [],
  };
}
