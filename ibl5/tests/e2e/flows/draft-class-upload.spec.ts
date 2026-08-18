import { randomBytes } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors, detectPhpError } from '../helpers/php-errors';
import { fetchToken } from '../helpers/csrf';

/**
 * uploadDraftClass.php — CSRF rejection, preview flow, output escaping, and the
 * empty-session commit guard.
 *
 * NOTHING HERE EVER PRESSES COMMIT. The commit path wipes ibl_draft_class and
 * replaces it wholesale; draft.spec.ts, draft-history.spec.ts and
 * projected-draft-order*.spec.ts all read that table under fullyParallel
 * sharding, so a commit from here would corrupt them. Rollback safety is
 * covered by the DB integration test
 * (tests/DatabaseIntegration/DraftClassImport/DraftClassImportIntegrationTest.php),
 * which owns its own transaction.
 *
 * Session hygiene: the `page` and `request` fixtures share one pinned
 * server-side PHPSESSID (see playwright-tests.md § Shared server session), and a
 * successful upload parks the parsed rows in $_SESSION until commit or cancel.
 * Every test therefore ends by GETting ?action=cancel, so no test — here or in
 * another worker — inherits a primed preview.
 *
 * Role gating (403 for a non-admin) lives in flows/role-gating-non-admin.spec.ts
 * Block A: that file already carries the IBL_TEST_USER_REGULAR skip guard and
 * the matching .e2e-hygiene-skip-allowlist entry, and its header commits to
 * holding the whole role-gating matrix. Putting the guard in THIS file would
 * skip the CSRF/preview/escaping tests too whenever the regular-user env vars
 * are unset.
 */

const PAGE = 'uploadDraftClass.php';

/** A random 64-hex token — correct format, wrong value. */
function forgedToken(): string {
  return randomBytes(32).toString('hex');
}

/**
 * A syntactically valid 27-field row. Field 4 (team) is deliberately blank —
 * that is what the real export ships.
 */
function csvRow(name: string, pos = 'PG'): string {
  const ratings = [
    50, 45, 20, 80, 10, 35, 5, 10, 8, 4, 3, 2, 60, 55, 50, 45, 50, 50, 50, 50,
  ].join(',');
  return `${name},${pos},20,,${ratings},70,60,55`;
}

/** 28 characters — inside the 32-char name cap, so it reaches the preview cell. */
const XSS_PAYLOAD = '<img src=x onerror=alert(1)>';

test.describe('uploadDraftClass.php', () => {
  test.describe.configure({ mode: 'serial' });

  test.afterEach(async ({ request }) => {
    // Drop any parked preview payload from the shared server session.
    await request.get(`${PAGE}?action=cancel`);
  });

  // ── CSRF (matrix rows 21, 22) ─────────────────────────────────────────────

  test('upload POST with a forged token is rejected before parsing', async ({
    request,
  }) => {
    const response = await request.post(PAGE, {
      form: { action: 'upload', _csrf_token: forgedToken() },
    });
    expect(response.status()).toBe(403);
    const html = await response.text();
    // The gate precedes the file read, so this message is itself proof that
    // nothing was parsed and nothing was parked in $_SESSION.
    expect(html).toContain('Invalid or expired form submission');
    expect(detectPhpError(html)).toBeNull();
  });

  test('commit POST with a forged token is rejected before any DB write', async ({
    request,
  }) => {
    const response = await request.post(PAGE, {
      form: { action: 'commit', _csrf_token: forgedToken() },
    });
    expect(response.status()).toBe(403);
    const html = await response.text();
    // Rejected before the DELETE/INSERT transaction opens.
    expect(html).toContain('Invalid or expired form submission');
    expect(html).not.toContain('Imported');
    expect(detectPhpError(html)).toBeNull();
  });

  // ── Empty-session commit guard (matrix row 31) ────────────────────────────

  test('commit with a valid token but no parked upload redirects to the expired notice', async ({
    request,
  }) => {
    // Guarantee an empty payload first — the shared session may carry one from
    // an earlier test in this file.
    await request.get(`${PAGE}?action=cancel`);

    const token = await fetchToken(request, PAGE);
    const response = await request.post(PAGE, {
      form: { action: 'commit', _csrf_token: token },
    });

    expect(response.url()).toContain('uploadDraftClass.php');
    const html = await response.text();
    // Proves the empty-session guard fires ahead of the transaction: a valid
    // token got past CSRF, and the request still never reached a DB write.
    expect(html).toContain('Your upload expired');
    expect(html).not.toContain('Imported');
    expect(detectPhpError(html)).toBeNull();
  });

  // ── Preview flow (matrix row 24) ──────────────────────────────────────────

  test('admin uploading 98rookies.csv sees a 67-row preview announcing 67 insertions', async ({
    page,
  }) => {
    const fixture = readFileSync(
      resolve(
        test.info().project.testDir,
        '../DraftClassImport/fixtures/98rookies.csv',
      ),
    );

    await page.goto(PAGE);
    // The picker label is the only visible control, and choosing a file submits
    // the form — there is no second button to press.
    await expect(page.getByText('Click to choose .csv')).toBeVisible();
    await page.locator('#draftClassFile').setInputFiles({
      name: '98rookies.csv',
      mimeType: 'text/csv',
      buffer: fixture,
    });

    await expect(page.locator('.ibl-data-table tbody tr')).toHaveCount(67);

    // The insert count (67) is deterministic from the fixture. The delete count
    // is a live SELECT COUNT(*) over ibl_draft_class, which differs between the
    // CI seed (6 rows, fixtures/ci-seed.sql) and a developer's own database, so
    // it is matched structurally rather than pinned to an environment-specific
    // number — pinning it would make this test pass only in CI.
    await expect(page.getByText(/^This will delete /)).toHaveText(
      /^This will delete \d+ existing rows? and insert 67 new ones\.$/,
    );
    // Nothing may have been written yet.
    await expect(page.getByText('Nothing has been written yet.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Commit Import' })).toBeVisible();

    // Headers are the labels the league reads on the ratings table, not the
    // SQL-safe DB column names (r_3ga, r_drive_off, r_trans_off).
    await expect(
      page.locator('.ibl-data-table thead th').allTextContents(),
    ).resolves.toEqual([
      'Player', 'Pos', 'Age', 'Team',
      '2ga', '2g%', 'fta', 'ft%', '3ga', '3g%',
      'orb', 'drb', 'ast', 'stl', 'tvr', 'blk',
      'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td',
      'Tal', 'Skl', 'Int',
    ]);

    // Column-group separators (csv-import.css) mirror the ratings table's
    // grouping. Asserting the computed border, not just the class, is what proves
    // the rule survives: Ratings.php's own sep-r rules are scoped to .team-table,
    // so a class alone would render nothing here.
    const strongBorder = page
      .locator('.ibl-data-table tbody tr')
      .first()
      .locator('td.sep-r-team')
      .first();
    await expect(strongBorder).toHaveCSS('border-right-width', '2px');
    // --gray-400: the strong rule has to stay visibly darker than the weak one,
    // or the grouping it draws collapses into a single flat tier.
    await expect(strongBorder).toHaveCSS('border-right-color', 'rgb(156, 163, 175)');
    const weakBorder = page
      .locator('.ibl-data-table tbody tr')
      .first()
      .locator('td.sep-r-weak')
      .first();
    await expect(weakBorder).toHaveCSS('border-right-width', '2px');
    await expect(weakBorder).toHaveCSS('border-right-color', 'rgb(229, 231, 235)'); // --gray-200

    await assertNoPhpErrors(page, 'on the draft class preview');
  });

  test('choosing a non-.csv file is refused in the browser and never uploaded', async ({
    page,
  }) => {
    await page.goto(PAGE);
    await page.locator('#draftClassFile').setInputFiles({
      name: '98rookies.txt',
      mimeType: 'text/plain',
      buffer: Buffer.from(`${csvRow('Safe Prospect')}\n`),
    });

    await expect(page.getByText('That is not a .csv file.')).toBeVisible();
    // No submit fired: the picker button is still what renders, and no preview exists.
    await expect(page.getByText('Click to choose .csv')).toBeVisible();
    await expect(page.locator('.ibl-data-table')).toHaveCount(0);

    await assertNoPhpErrors(page, 'on the rejected-extension form');
  });

  // ── Escaping (matrix rows 25, 26) ─────────────────────────────────────────

  test('an HTML payload in a name renders as literal text in the preview cell', async ({
    page,
  }) => {
    let dialogFired = false;
    page.on('dialog', (dialog) => {
      dialogFired = true;
      void dialog.dismiss();
    });

    await page.goto(PAGE);
    await page.locator('#draftClassFile').setInputFiles({
      name: 'xss-name.csv',
      mimeType: 'text/csv',
      buffer: Buffer.from(
        `${csvRow(XSS_PAYLOAD)}\n${csvRow('Safe Prospect')}\n`,
      ),
    });

    const firstNameCell = page.locator('.ibl-data-table tbody tr').first().locator('td').first();
    await expect(firstNameCell).toHaveText(XSS_PAYLOAD);
    // The payload must never have been parsed as markup.
    await expect(page.locator('img[onerror]')).toHaveCount(0);
    expect(dialogFired, 'an alert() dialog fired — the payload executed').toBe(false);

    await assertNoPhpErrors(page, 'on the escaped-name preview');
  });

  test('an HTML payload echoed inside an error message renders as literal text', async ({
    page,
  }) => {
    let dialogFired = false;
    page.on('dialog', (dialog) => {
      dialogFired = true;
      void dialog.dismiss();
    });

    await page.goto(PAGE);
    await page.locator('#draftClassFile').setInputFiles({
      name: 'xss-pos.csv',
      mimeType: 'text/csv',
      // The payload sits in `pos`, which is invalid, so the parser quotes it
      // back inside a per-line error message rather than a table cell.
      buffer: Buffer.from(
        `${csvRow('Bad Position Guy', XSS_PAYLOAD)}\n${csvRow('Safe Prospect')}\n`,
      ),
    });

    const firstError = page.locator('.ibl-alert--error li').first();
    await expect(firstError).toContainText(XSS_PAYLOAD);
    await expect(page.locator('img[onerror]')).toHaveCount(0);
    expect(dialogFired, 'an alert() dialog fired — the payload executed').toBe(false);
    // A rejected file is never parked, so the upload form is still what renders.
    await expect(page.locator('.ibl-data-table')).toHaveCount(0);

    await assertNoPhpErrors(page, 'on the escaped-error page');
  });
});
