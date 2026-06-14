import { test, expect } from '../fixtures/auth';
import { test as regularTest, expect as regularExpect } from '../fixtures/auth-regular';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Big Board + Mock Draft (stateful reads) mutate / depend on Metros' (teamid=1)
// gm_draft_big_board rows — shared team-1 state. Per the single-owner rule for
// global tables, ALL Metros board access lives in this one serial file so no
// other spec can read the board mid-mutation.
test.describe.configure({ mode: 'serial' });

// ci-seed.sql seeds exactly ONE Metros board entry: 'Prospect Guard', rank 1.
const SEEDED_PROSPECT = 'Prospect Guard';
const SEEDED_NOTE = 'CI seed sleeper';

async function readCsrfToken(page: import('@playwright/test').Page): Promise<string> {
  return page.locator('form input[name="_csrf_token"]').first().inputValue();
}

test.describe('Big Board: page + mutations', () => {
  test('lists the seeded prospect (non-empty state)', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');

    await expect(page.locator('h2.ibl-title')).toHaveText('My Big Board');
    await expect(page.locator('.ibl-data-table')).toContainText(SEEDED_PROSPECT);
    await assertNoPhpErrors(page, 'Big Board page');
  });

  test('a <script> note is stored raw and rendered escaped', async ({ page }) => {
    const payload = '<script>alert(1)</script>';

    await page.goto('modules.php?name=BigBoard');
    const row = page.locator('.ibl-data-table tbody tr', { hasText: SEEDED_PROSPECT });
    const noteForm = row.locator('form[action*="op=setnote"]');
    await noteForm.locator('textarea[name="note"]').fill(payload);
    await Promise.all([
      page.waitForURL(/result=note_saved|error=/),
      noteForm.locator('button[type="submit"]').click(),
    ]);
    expect(page.url()).toContain('result=note_saved');

    // Raw HTML readback: the payload is escaped, no live <script> is injected.
    const html = await (await page.request.get('/ibl5/modules.php?name=BigBoard')).text();
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    expect(html).not.toContain('<script>alert(1)</script>');

    // Restore the seeded note so re-runs start clean.
    const token = await readCsrfToken(page);
    const entryId = await row.locator('input[name="entry_id"]').first().inputValue();
    await page.request.post('/ibl5/modules.php?name=BigBoard&op=setnote', {
      form: { _csrf_token: token, entry_id: entryId, note: SEEDED_NOTE },
      maxRedirects: 0,
    });
  });

  test('setrank POST with a forged CSRF token is rejected and writes nothing', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');
    const row = page.locator('.ibl-data-table tbody tr', { hasText: SEEDED_PROSPECT });
    const entryId = await row.locator('input[name="entry_id"]').first().inputValue();

    const response = await page.request.post('/ibl5/modules.php?name=BigBoard&op=setrank', {
      form: { _csrf_token: 'garbage-token', entry_id: entryId, rank: '999' },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] ?? '';
    expect(location, 'Expected error redirect').toContain('error=');
    expect(location, 'Must not report success').not.toContain('result=rank_saved');

    // Readback: rank is still the seeded value (1), not 999.
    await page.goto('modules.php?name=BigBoard');
    await expect(row.locator('input[name="rank"]').first()).toHaveValue('1');
  });

  // Row 12 (CSRF, raw HTTP): a POST with NO _csrf_token field exercises the
  // MISSING-token branch of CsrfGuard (distinct from the forged-but-valid-format
  // branch above). The plan placed this in tests/e2e/api-e2e/, but no such wired
  // suite exists — consolidated here (raw request context, no browser state).
  test('add POST with a missing CSRF token is rejected and writes nothing', async ({ page }) => {
    const response = await page.request.post('/ibl5/modules.php?name=BigBoard&op=add', {
      form: { prospect_id: '0', rank: '7', note: 'no token' },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] ?? '';
    expect(location, 'Expected error redirect').toContain('error=');
    expect(location, 'Must not report success').not.toContain('result=added');

    // Readback: the board still holds exactly the one seeded entry — no add landed.
    await page.goto('modules.php?name=BigBoard');
    await expect(page.locator('.ibl-data-table tbody tr')).toHaveCount(1);
    await expect(page.locator('.ibl-data-table')).toContainText(SEEDED_PROSPECT);
  });

  // Read the mock BEFORE the empty-state test mutates the board, so the board
  // holds exactly the seeded entry here.
  test('Mock Draft suggests the seeded prospect at the first owned pick and exhausts later', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard&op=mock');

    await expect(page.locator('h2.ibl-title')).toHaveText('Mock Draft');
    const rows = page.locator('.ibl-data-table tbody tr');
    // First Metros-owned slot (round 1) gets the single seeded prospect.
    await expect(rows.first()).toContainText(SEEDED_PROSPECT);
    // A later Metros-owned slot has no prospect left on the board.
    await expect(page.locator('.ibl-data-table')).toContainText('No prospects left on your board');
    await assertNoPhpErrors(page, 'Mock Draft page');
  });

  test('empty-state message renders when the board has no entries, then restores', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');
    const row = page.locator('.ibl-data-table tbody tr', { hasText: SEEDED_PROSPECT });
    const removeForm = row.locator('form[action*="op=remove"]');
    await Promise.all([
      page.waitForURL(/result=removed|error=/),
      removeForm.locator('button[type="submit"]').click(),
    ]);
    expect(page.url()).toContain('result=removed');
    await expect(page.locator('.ibl-alert--info')).toContainText('Your big board is empty');

    // Restore the seeded entry via the add form so re-runs start clean.
    const addForm = page.locator('form[action*="op=add"]');
    await addForm.locator('select[name="prospect_id"]').selectOption({ label: `${SEEDED_PROSPECT} (PG)` });
    await addForm.locator('input[name="rank"]').fill('1');
    await addForm.locator('input[name="note"]').fill(SEEDED_NOTE);
    await Promise.all([
      page.waitForURL(/result=added|error=/),
      addForm.locator('button[type="submit"]').click(),
    ]);
    expect(page.url()).toContain('result=added');
    await expect(page.locator('.ibl-data-table')).toContainText(SEEDED_PROSPECT);
  });
});

// Row 13 (IDOR / privacy, browser): a non-owner never sees another team's
// private board. auth-regular is a roles_mask=0 user with no franchise, so the
// page falls through to the no-team state and never renders Metros' prospect.
regularTest.describe('Big Board: non-owner cannot see another team\'s board', () => {
  regularTest.skip(
    !process.env.IBL_TEST_USER_REGULAR || !process.env.IBL_TEST_PASS_REGULAR,
    'IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR not set — regular.json is not freshly authenticated',
  );

  regularTest('regular user sees the no-team state, not Metros\' seeded entry', async ({ page }) => {
    await page.goto('modules.php?name=BigBoard');
    await regularExpect(page.locator('.big-board-page')).toContainText('You must own a team');
    await regularExpect(page.locator('body')).not.toContainText(SEEDED_PROSPECT);
  });
});
