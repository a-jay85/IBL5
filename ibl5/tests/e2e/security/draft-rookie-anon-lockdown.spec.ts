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
        form: {
          teamname: 'Metros',
          playerID: '200000032',
          rookieOptionValue: '1000',
          from: '',
        },
      },
    );
    expect(response.status()).toBeLessThan(400);
    const html = await response.text();
    // loginbox() redirects unauthenticated users to the YourAccount login page.
    expect(html).toContain('YourAccount');
    expect(html).not.toContain('rookie_option_success');
    expectNoPhpErrors(html, 'on anon processrookieoption');
  });
});
