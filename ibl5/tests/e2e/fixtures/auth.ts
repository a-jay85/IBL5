import { test as base } from '@playwright/test';
import { setState, type Settings, type SetStateResult } from '../helpers/test-state';

export type SetStateFn = (settings: Settings) => Promise<SetStateResult>;

/**
 * Authenticated test fixture with optional appState control.
 *
 * - Tests importing `test` from this file use the stored auth state
 *   from auth.setup.ts — no login needed.
 * - The `appState` fixture lets tests set ibl_settings before running
 *   and automatically restores previous values after each test.
 */
export const test = base.extend<{ appState: SetStateFn }>({
  appState: async ({ request }, use) => {
    const restoreStack: Settings[] = [];

    const setStateFn: SetStateFn = async (settings) => {
      const result = await setState(request, settings);
      restoreStack.push(result.previous);
      return result;
    };

    await use(setStateFn);

    // Teardown: restore previous values in reverse order
    for (const previous of restoreStack.reverse()) {
      // Filter out null values (settings that didn't exist before)
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
  },
});

export { expect } from '@playwright/test';
