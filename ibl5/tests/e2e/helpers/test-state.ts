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
