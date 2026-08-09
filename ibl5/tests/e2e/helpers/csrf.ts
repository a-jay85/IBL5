import type { APIRequestContext } from '@playwright/test';

/**
 * Fetch a fresh `rookie_option` CSRF token by rendering the eligible
 * rookie-option fixture form (pid 200000032 — Metros, round-1 exp=2).
 *
 * The caller MUST have set the Free Agency phase via `appState` first so the
 * fixture renders eligible and its form (carrying the token) is emitted. The
 * token is formName-bound (`rookie_option`), not pid-bound, so it validates any
 * `pa=processrookieoption` POST regardless of the target playerID.
 *
 * The page chrome renders its OWN `_csrf_token` first (the admin DebugMenu
 * toggle, formName `debug_toggle`), so a naive first-match grab returns the
 * wrong token and the POST is rejected as "Invalid or expired form submission".
 * Scope extraction to the `<form name="RookieExtend">` block — its token is the
 * first `_csrf_token` after that form's opening tag.
 */
export async function fetchRookieOptionCsrfToken(
  request: APIRequestContext,
): Promise<string> {
  const resp = await request.get(
    'modules.php?name=Player&pa=rookieoption&pid=200000032',
  );
  const html = await resp.text();
  const formIdx = html.indexOf('name="RookieExtend"');
  if (formIdx === -1) {
    throw new Error(
      'No RookieExtend form in rendered page — set Free Agency phase ' +
        `before calling. Body head: ${html.slice(0, 300)}`,
    );
  }
  const match = html
    .slice(formIdx)
    .match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error(
      'No rookie_option _csrf_token in RookieExtend form. ' +
        `Body head: ${html.slice(0, 300)}`,
    );
  }
  return match[1];
}

/**
 * Fetch a fresh, valid `_csrf_token` from a GET-rendered form on `path`.
 *
 * Pass `formName` to scope extraction to a specific `<form name="...">`; the
 * token for that form is the first `_csrf_token` after the form's opening tag.
 * Without `formName`, the page must contain exactly one token — if it has more,
 * extraction is ambiguous and this throws rather than silently picking one.
 */
export async function fetchToken(
  request: APIRequestContext,
  path: string,
  formName?: string,
): Promise<string> {
  const resp = await request.get(path);
  if (!resp.ok()) {
    throw new Error(`token fetch GET ${path} failed: ${resp.status()}`);
  }
  let body = await resp.text();
  if (formName === undefined) {
    const tokenCount = (
      body.match(/name="_csrf_token" value="[0-9a-f]+"/g) ?? []
    ).length;
    if (tokenCount > 1) {
      throw new Error(
        `ambiguous _csrf_token on ${path}: ${tokenCount} tokens found — ` +
          `pass a formName to scope extraction to the form under test`,
      );
    }
  } else {
    const formIdx = body.indexOf(`name="${formName}"`);
    if (formIdx === -1) {
      throw new Error(`form name="${formName}" not found on ${path}`);
    }
    body = body.slice(formIdx);
  }
  const match = body.match(/name="_csrf_token" value="([0-9a-f]+)"/);
  if (match === null) {
    throw new Error(
      `no _csrf_token found on ${path}` +
        (formName !== undefined ? ` in form "${formName}"` : ''),
    );
  }
  return match[1];
}
