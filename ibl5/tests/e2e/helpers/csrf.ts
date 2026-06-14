import type { APIRequestContext } from '@playwright/test';

/**
 * Fetch a fresh `rookie_option` CSRF token by rendering the eligible
 * rookie-option fixture form (pid 200000032 — Metros, round-1 exp=2).
 *
 * The caller MUST have set the Free Agency phase via `appState` first so the
 * fixture renders eligible and its form (carrying the token) is emitted. The
 * token is formName-bound (`rookie_option`), not pid-bound, so it validates any
 * `pa=processrookieoption` POST regardless of the target playerID.
 */
export async function fetchRookieOptionCsrfToken(
  request: APIRequestContext,
): Promise<string> {
  const resp = await request.get(
    'modules.php?name=Player&pa=rookieoption&pid=200000032',
  );
  const html = await resp.text();
  const match = html.match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error(
      'No rookie_option _csrf_token in rendered form — set Free Agency phase ' +
        `before calling. Body head: ${html.slice(0, 300)}`,
    );
  }
  return match[1];
}
