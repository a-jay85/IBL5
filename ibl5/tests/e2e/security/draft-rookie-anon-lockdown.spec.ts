import { test, expect } from '../fixtures/public';
import { PHP_ERROR_PATTERNS } from '../helpers/php-errors';

/**
 * Anonymous-mutation lockdown — the two CRITICAL endpoints that previously
 * mutated league state while reachable by UNAUTHENTICATED users:
 *
 *   - modules.php?name=Draft&op=select       → submitDraftSelection()
 *   - modules.php?name=Player&pa=processrookieoption → processrookieoption()
 *
 * Both now run an is_user() auth gate FIRST. An anonymous POST hits loginbox(),
 * which emits a JS redirect to the YourAccount login page and die()s before any
 * DB mutation — so landing on that login redirect (and NOT a success body) is
 * itself proof no draft selection / contract exercise occurred.
 *
 * Public (unauthenticated) fixture: these requests carry no session.
 */

/** Assert a rendered body carries no PHP error patterns. */
function expectNoPhpErrors(html: string, context: string): void {
  for (const pattern of PHP_ERROR_PATTERNS) {
    expect(html, `PHP error "${pattern}" ${context}`).not.toContain(pattern);
  }
}

test.describe('Anonymous mutation lockdown', () => {
  test('anon Draft op=select POST is refused (login redirect, no selection)', async ({
    request,
  }) => {
    const response = await request.post('modules.php?name=Draft&op=select', {
      form: {
        teamname: 'Metros',
        player: 'Some Prospect',
        draft_round: '1',
        draft_pick: '1',
      },
    });
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    // loginbox() redirects unauthenticated users to the YourAccount login page.
    expect(html).toContain('YourAccount');
    // No draft-success banner ("select **Name!**") was rendered.
    expect(html).not.toMatch(/select\s*\*\*.*!\*\*/);
    expectNoPhpErrors(html, 'on anon Draft op=select');
  });

  test('anon processrookieoption POST is refused (login redirect, no exercise)', async ({
    request,
  }) => {
    const response = await request.post(
      'modules.php?name=Player&pa=processrookieoption',
      {
        // Do NOT follow the redirect: with maxRedirects:0, HTTP 200 ⟺ loginbox
        // rendered ⟺ the is_user() gate held. Every non-loginbox exit of
        // processrookieoption() ends in HtmxHelper::redirect() → HTTP 302, so a
        // regressed gate returns 302 (not 200) and fails the toBe(200) assert.
        maxRedirects: 0,
        form: {
          teamname: 'Metros',
          playerID: '200000032',
          rookieOptionValue: '1000',
          from: '',
        },
      },
    );
    // Discriminating assertion: loginbox() returns 200 with no Location header;
    // a bypassed gate would 302 here.
    expect(response.status()).toBe(200);
    const html = await response.text();
    // Secondary marker — meaningful ONLY paired with status===200: confirms the
    // 200 is the loginbox page, not some other 200.
    expect(html).toContain('YourAccount');
    expectNoPhpErrors(html, 'on anon processrookieoption');
  });
});
