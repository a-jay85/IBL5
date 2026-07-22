import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

/**
 * Admin viewer for the sim recap queue (`simSummaries.php`).
 *
 * Every count, order and string below is bound to the `ibl_sim_summaries`
 * block in `tests/e2e/fixtures/ci-seed.sql`, which seeds exactly four rows
 * (686 failed, 687 pending, 688 done, 689 done) after clearing the single
 * row migration 155 plants. Sim 999999 is deliberately left unseeded so the
 * "valid but absent" path has a stable fixture.
 */

// Byte-exact `recap_text` of the newest seeded row — the download/copy paths
// must reproduce it without HTML wrapping or entity encoding.
const SIM_689_BODY =
  'Sim 689 recap: the Cannons erased a nine-point fourth-quarter deficit to win by three.';

const SEEDED_ROW_COUNT = 4;

test.describe('Sim recap admin viewer', () => {
  test('index renders every seeded row', async ({ page }) => {
    const response = await page.goto('simSummaries.php');

    expect(response?.status()).toBe(200);
    await expect(page.locator('table.ibl-data-table tbody tr')).toHaveCount(SEEDED_ROW_COUNT);
    await assertNoPhpErrors(page);
  });

  test('index is ordered newest sim first', async ({ page }) => {
    await page.goto('simSummaries.php');

    const rows = page.locator('table.ibl-data-table tbody tr');
    await expect(rows.first().locator('td').first()).toHaveText('689');
    await expect(rows.last().locator('td').first()).toHaveText('686');
    await assertNoPhpErrors(page);
  });

  test('single recap renders its stored body and themes', async ({ page }) => {
    const response = await page.goto('simSummaries.php?sim=689');

    expect(response?.status()).toBe(200);
    await expect(page.locator('#recap-body')).toHaveValue(SIM_689_BODY);
    await expect(page.locator('#recap-themes')).toContainText('comeback');
    await assertNoPhpErrors(page);
  });

  test('single recap offers copy and download controls', async ({ page }) => {
    await page.goto('simSummaries.php?sim=689');

    await expect(page.locator('#recap-copy')).toBeVisible();
    const download = page.locator('#recap-download');
    await expect(download).toBeVisible();
    await expect(download).toHaveAttribute('href', /sim=689&format=txt/);
    await assertNoPhpErrors(page);
  });

  test('a queued row with no text shows its status instead of a body', async ({ page }) => {
    // Sim 687 is seeded `pending` with recap_text NULL.
    const response = await page.goto('simSummaries.php?sim=687');

    expect(response?.status()).toBe(200);
    await expect(page.locator('#recap-body')).toHaveCount(0);
    await expect(page.locator('#recap-download')).toHaveCount(0);
    await expect(page.locator('#recap-missing')).toContainText('pending');
    await assertNoPhpErrors(page);
  });

  test('a malformed sim is rejected with 400 and still renders the index', async ({ page }) => {
    const response = await page.goto('simSummaries.php?sim=abc');

    expect(response?.status()).toBe(400);
    await expect(page.locator('#recap-error')).toHaveText('Invalid sim number.');
    await expect(page.locator('table.ibl-data-table tbody tr')).toHaveCount(SEEDED_ROW_COUNT);
    await assertNoPhpErrors(page);
  });

  test('a signed sim is rejected before any cast', async ({ page }) => {
    // The sign character fails ctype_digit(), so no cast and no query happen.
    const response = await page.goto('simSummaries.php?sim=-3');

    expect(response?.status()).toBe(400);
    await expect(page.locator('#recap-error')).toHaveText('Invalid sim number.');
  });

  test('sim=0 is rejected by the lower bound', async ({ page }) => {
    // All digits, so ctype_digit() passes — the `< 1` floor is what rejects it.
    const response = await page.goto('simSummaries.php?sim=0');

    expect(response?.status()).toBe(400);
    await expect(page.locator('#recap-error')).toHaveText('Invalid sim number.');
  });

  test('an over-long sim is rejected before any cast', async ({ page }) => {
    // 11 digits — the strlen > 10 cap rejects it ahead of the (int) cast.
    const response = await page.goto('simSummaries.php?sim=99999999999');

    expect(response?.status()).toBe(400);
    await expect(page.locator('#recap-error')).toHaveText('Invalid sim number.');
  });

  test('a valid but absent sim is a 404 that names the sim', async ({ page }) => {
    const response = await page.goto('simSummaries.php?sim=999999');

    expect(response?.status()).toBe(404);
    await expect(page.locator('#recap-error')).toHaveText('No recap stored for sim 999999.');
    await assertNoPhpErrors(page);
  });

  test('an injection attempt never reaches SQL', async ({ page }) => {
    const response = await page.goto('simSummaries.php?sim=1%20OR%201%3D1');

    expect(response?.status()).toBe(400);
    // A predicate that had reached SQL would change the row count.
    await expect(page.locator('table.ibl-data-table tbody tr')).toHaveCount(SEEDED_ROW_COUNT);
    await assertNoPhpErrors(page);
  });
});

test.describe('Sim recap plain-text export', () => {
  test('exports the stored body byte-for-byte', async ({ request }) => {
    const response = await request.get('simSummaries.php?sim=689&format=txt');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toMatch(/^text\/plain/);
    expect(response.headers()['content-disposition']).toContain('sim-689-recap.txt');
    // nosniff is re-asserted by the export itself, so a recap body containing
    // markup is displayed as text rather than sniffed as HTML.
    expect(response.headers()['x-content-type-options']).toBe('nosniff');
    expect(await response.text()).toBe(SIM_689_BODY);
  });

  test('format=txt without a sim is a 400 text response, not the HTML index', async ({
    request,
  }) => {
    const response = await request.get('simSummaries.php?format=txt');

    expect(response.status()).toBe(400);
    expect(response.headers()['content-type']).toMatch(/^text\/plain/);
    expect(await response.text()).not.toContain('<table');
  });

  test('a malformed sim is a 400 that never echoes the input', async ({ request }) => {
    const response = await request.get('simSummaries.php?sim=abc&format=txt');

    expect(response.status()).toBe(400);
    expect(response.headers()['content-type']).toMatch(/^text\/plain/);
    expect(await response.text()).not.toContain('abc');
  });

  test('a row with no stored text is a 404, not an empty 200', async ({ request }) => {
    const response = await request.get('simSummaries.php?sim=687&format=txt');

    expect(response.status()).toBe(404);
    expect(response.headers()['content-type']).toMatch(/^text\/plain/);
    expect(await response.text()).toContain('No recap text available.');
  });
});
