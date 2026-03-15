/**
 * E2E Test State Control helper.
 *
 * Wraps HTTP calls to test-state.php so Playwright tests can set
 * application state (season phase, trades, waivers, trivia, etc.)
 * and restore it after each test.
 */
import type { APIRequestContext } from '@playwright/test';

export type Settings = Record<string, string>;

export interface SetStateResult {
  previous: Settings;
  applied: Settings;
}

export async function getState(
  request: APIRequestContext,
): Promise<Settings> {
  const response = await request.get('test-state.php');
  if (!response.ok()) {
    throw new Error(
      `test-state.php GET failed: ${response.status()} ${await response.text()}`,
    );
  }
  return (await response.json()) as Settings;
}

export async function setState(
  request: APIRequestContext,
  settings: Settings,
): Promise<SetStateResult> {
  const response = await request.post('test-state.php', {
    data: settings,
  });
  if (!response.ok()) {
    throw new Error(
      `test-state.php POST failed: ${response.status()} ${await response.text()}`,
    );
  }
  return (await response.json()) as SetStateResult;
}

export type SetStateFn = (settings: Settings) => Promise<SetStateResult>;

/**
 * Creates the legacy DB-mutating appState fixture.
 * Retained for test-state.spec.ts and future DB-mutation use cases.
 */
export function createAppStateFixture() {
  return async ({ request }: { request: APIRequestContext }, use: (fn: SetStateFn) => Promise<void>) => {
    const restoreStack: Settings[] = [];

    const setStateFn: SetStateFn = async (settings) => {
      const result = await setState(request, settings);
      restoreStack.push(result.previous);
      return result;
    };

    await use(setStateFn);

    // Teardown: restore previous values in reverse order
    for (const previous of restoreStack.reverse()) {
      const toRestore: Settings = {};
      for (const [key, value] of Object.entries(previous)) {
        if (value !== null) {
          toRestore[key] = value;
        }
      }
      if (Object.keys(toRestore).length > 0) {
        await setState(request, toRestore);
      }
    }
  };
}

/**
 * Creates a cookie-based appState fixture that eliminates DB race conditions.
 *
 * Instead of mutating ibl_settings rows (which parallel tests can overwrite),
 * this sets a `_test_overrides` cookie that PHP reads per-request. State travels
 * with the request — zero race window, no cleanup needed.
 */
export function createCookieStateFixture() {
  return async (
    { context }: { context: import('@playwright/test').BrowserContext },
    use: (fn: SetStateFn) => Promise<void>,
  ) => {
    let mergedOverrides: Settings = {};

    const setStateFn: SetStateFn = async (settings) => {
      mergedOverrides = { ...mergedOverrides, ...settings };
      const encoded = encodeURIComponent(JSON.stringify(mergedOverrides));
      const baseUrl = process.env.BASE_URL ?? 'http://main.localhost/ibl5/';
      await context.addCookies([{
        name: '_test_overrides',
        value: encoded,
        domain: new URL(baseUrl).hostname,
        path: '/',
      }]);
      return { previous: {}, applied: settings };
    };

    await use(setStateFn);
    // No teardown needed — cookies are scoped to the browser context
  };
}
