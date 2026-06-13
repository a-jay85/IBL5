import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Watchlist submission flow — mutates Metros' (teamid=1) watchlist rows.
// Serial: the seeded row (pid 2) and toggle state are shared team-1 state.
test.describe.configure({ mode: 'serial' });

// ci-seed.sql pre-watches pid 2 (Test Player Two) for Metros (teamid 1, the
// E2E user's team). pid 1 (Test Player) is NOT seeded-watched.
const SEEDED_WATCHED_PID = '2';
const UNWATCHED_PID = '1';
const SEEDED_NOTE = 'Seeded scouting note for E2E';

async function readCsrfToken(page: import('@playwright/test').Page): Promise<string> {
  return page.locator('form input[name="csrf_token"]').first().inputValue();
}

test.describe('Watchlist: My Watchlist page', () => {
  test('lists the seeded watched player', async ({ page }) => {
    await page.goto('modules.php?name=Watchlist');

    await expect(page.locator('h2.ibl-title')).toHaveText('My Watchlist');
    await expect(page.locator('.ibl-data-table')).toContainText('Test Player Two');
    await assertNoPhpErrors(page, 'My Watchlist page');
  });
});

test.describe('Watchlist: note XSS is escaped on output', () => {
  test('a <script> note is stored raw and rendered escaped', async ({ page }) => {
    const payload = '<script>alert(1)</script>';

    // Save the malicious note via the owned save-note form for the seeded row.
    await page.goto('modules.php?name=Watchlist');
    const saveForm = page.locator('form[action*="op=savenote"]').first();
    await saveForm.locator('textarea[name="note"]').fill(payload);
    await saveForm.locator('button[type="submit"]').click();
    await page.waitForURL(/name=Watchlist/);
    expect(page.url()).toContain('result=note_saved');

    // Raw HTML readback: the payload is escaped, no live <script> is injected.
    const html = await (await page.request.get('/ibl5/modules.php?name=Watchlist')).text();
    expect(html).toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    expect(html).not.toContain('<script>alert(1)</script>');

    // Restore the seeded note so re-runs start clean.
    const token = await readCsrfToken(page);
    await page.request.post('/ibl5/modules.php?name=Watchlist&op=savenote', {
      form: { csrf_token: token, pid: SEEDED_WATCHED_PID, note: SEEDED_NOTE },
      maxRedirects: 0,
    });
  });
});

test.describe('Watchlist: CSRF rejection', () => {
  test('toggle POST without a valid CSRF token is rejected and writes nothing', async ({ page }) => {
    const response = await page.request.post('/ibl5/modules.php?name=Watchlist&op=toggle', {
      form: { csrf_token: 'garbage-token', pid: UNWATCHED_PID },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] ?? '';
    expect(location, 'Expected error redirect').toContain('error=');
    expect(location, 'Must not report success').not.toContain('result=watched');

    // Readback: pid 1 was not added — only the seeded row (pid 2) remains.
    // (Can't substring-match 'Test Player' — it is contained in 'Test Player Two'.)
    await page.goto('modules.php?name=Watchlist');
    await expect(page.locator('.ibl-data-table tbody tr')).toHaveCount(1);
  });
});

test.describe('Watchlist: Player-page toggle', () => {
  test('watched player shows Unwatch; clicking removes it', async ({ page }) => {
    await page.goto(`modules.php?name=Player&pa=showpage&pid=${SEEDED_WATCHED_PID}`);

    const toggleForm = page.locator('form[action*="name=Watchlist"][action*="op=toggle"]');
    await expect(toggleForm).toBeVisible();
    await expect(toggleForm.locator('button[type="submit"]')).toHaveText(/Unwatch/);

    await toggleForm.locator('button[type="submit"]').click();
    await page.waitForURL(/name=Watchlist/);
    expect(page.url()).toContain('result=unwatched');
    await assertNoPhpErrors(page, 'after unwatch toggle');

    // Restore: re-watch pid 2 from its (now "Watch") toggle form.
    await page.goto(`modules.php?name=Player&pa=showpage&pid=${SEEDED_WATCHED_PID}`);
    const token = await readCsrfToken(page);
    await page.request.post('/ibl5/modules.php?name=Watchlist&op=toggle', {
      form: { csrf_token: token, pid: SEEDED_WATCHED_PID },
      maxRedirects: 0,
    });
  });

  test('unwatched player shows Watch; clicking adds it', async ({ page }) => {
    await page.goto(`modules.php?name=Player&pa=showpage&pid=${UNWATCHED_PID}`);

    const toggleForm = page.locator('form[action*="name=Watchlist"][action*="op=toggle"]');
    await expect(toggleForm).toBeVisible();
    await expect(toggleForm.locator('button[type="submit"]')).toHaveText(/Watch/);

    await toggleForm.locator('button[type="submit"]').click();
    await page.waitForURL(/name=Watchlist/);
    expect(page.url()).toContain('result=watched');
    await assertNoPhpErrors(page, 'after watch toggle');

    // Cleanup: remove pid 1 so it does not leak into other specs/re-runs.
    const token = await readCsrfToken(page);
    await page.request.post('/ibl5/modules.php?name=Watchlist&op=remove', {
      form: { csrf_token: token, pid: UNWATCHED_PID },
      maxRedirects: 0,
    });
  });
});
