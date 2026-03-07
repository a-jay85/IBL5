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
 * Creates the appState fixture function for use in both
 * authenticated and public test fixtures.
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
