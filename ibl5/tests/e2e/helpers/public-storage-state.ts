/**
 * Shared storageState for unauthenticated (public) E2E tests.
 *
 * Sets the `_no_auto_login` cookie so DevAutoLogin (PHP) doesn't
 * auto-authenticate the request. Without this cookie, all "public"
 * tests on *.localhost get silently promoted to an admin session.
 *
 * Usage (bare @playwright/test files):
 *   import { publicStorageState } from '../helpers/public-storage-state';
 *   test.use({ storageState: publicStorageState() });
 *
 * The `public` fixture (`../fixtures/public`) sets this automatically.
 */

interface StorageState {
  cookies: Array<{
    name: string;
    value: string;
    domain: string;
    path: string;
  }>;
  origins: Array<Record<string, unknown>>;
}

export function publicStorageState(): StorageState {
  const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
  const domain = new URL(baseUrl).hostname;
  return {
    cookies: [
      {
        name: '_no_auto_login',
        value: '1',
        domain,
        path: '/',
      },
    ],
    origins: [],
  };
}
