import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { setAward } from '../helpers/test-state';

// Finals MVP flow — sets Finals MVP for the current season year.
// Uses auth fixture (admin access required for leagueControlPanel.php).
// The LCP reads settings from DB directly (not cookie overrides), so
// the test uses the LCP's own Set Season Phase form to switch to Playoffs.
// This flow INSERTS a real 'IBL Finals MVP' award row for SEASON_YEAR; the
// afterAll below deletes it so it cannot leak into concurrent workers. Without
// cleanup it persisted in the shared DB and flipped updater-awards' hasFinalsMvp
// (which reads ibl_awards directly, not via cookie override) — #906 follow-up.

const SEASON_YEAR = 2026; // CI seed 'Current Season Ending Year'

test.describe('LeagueControlPanel — Finals MVP flow', () => {
  test.describe.configure({ mode: 'serial' });

  test.afterEach(async ({ request }) => {
    // Remove the Finals MVP award this flow inserts so it cannot contaminate
    // updater-awards (which requires the 2026 'IBL Finals MVP' row to be absent).
    // afterEach (not afterAll) keeps the cross-worker contamination window to the
    // single test that creates the award, rather than the whole serial describe.
    await setAward(request, SEASON_YEAR, 'IBL Finals MVP', false);
  });

  test('page loads without PHP errors', async ({ page }) => {
    await page.goto('leagueControlPanel.php');
    await assertNoPhpErrors(page, 'on LeagueControlPanel page');

    await expect(page.locator('form')).toBeVisible();
  });

  test('submits Finals MVP and hides input on reload', async ({ page }) => {
    // Step 1: Set phase to Playoffs so awards controls appear
    await page.goto('leagueControlPanel.php');
    await assertNoPhpErrors(page, 'before phase change');

    const phaseSelect = page.locator('select[name="SeasonPhase"]');
    await phaseSelect.selectOption('Playoffs');
    const phaseButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      phaseButton.click(),
    ]);
    await assertNoPhpErrors(page, 'after setting phase to Playoffs');

    // Step 2: Assert Finals MVP input is visible
    const mvpInput = page.locator('input[name="finals_mvp_name"]');
    await expect(mvpInput).toBeVisible();

    const mvpButton = page.locator('button[value="set_finals_mvp"]');
    await expect(mvpButton).toBeVisible();

    // Step 3: Fill and submit Finals MVP.
    // Use the '__e2e_test' sentinel name so the afterEach cleanup (which deletes
    // ibl_awards rows WHERE name='__e2e_test', matching test-state.php's
    // set-award handler) actually removes this real, form-inserted award row.
    // A human-readable name would slip past that name-scoped delete and leak the
    // 2026 'IBL Finals MVP' award into updater-awards (which reads it by year+award).
    await mvpInput.fill('__e2e_test');
    await Promise.all([
      page.waitForURL(/success=/),
      mvpButton.click(),
    ]);

    // Step 4: Assert success flash message
    await assertNoPhpErrors(page, 'after Finals MVP submission');
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    const body = await page.locator('body').textContent();
    expect(body).toContain('__e2e_test');

    // Step 5: Reload and verify input is hidden (hasFinalsMvp is now true)
    await page.reload();
    await assertNoPhpErrors(page, 'after reload');
    await expect(page.locator('input[name="finals_mvp_name"]')).toHaveCount(0);

    // Step 6: Restore phase to Free Agency (CI seed default)
    const restoreSelect = page.locator('select[name="SeasonPhase"]');
    await restoreSelect.selectOption('Free Agency');
    const restoreButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      restoreButton.click(),
    ]);
  });
});

// Generate Season Awards — tests the button visibility and error path.
// The LCP reads phase from the DB directly (not cookie overrides), so
// phase must be set via form submission. CI uses a fresh DB per run;
// local re-runs may need: UPDATE ibl_settings SET value='Free Agency' WHERE name='Current Season Phase';

test.describe('LeagueControlPanel — Generate Season Awards', () => {
  test.describe.configure({ mode: 'serial' });

  test('generate_awards button visible in Playoffs', async ({ page }) => {
    // Set phase to Playoffs via the LCP form
    await page.goto('leagueControlPanel.php');
    await assertNoPhpErrors(page, 'before phase change');

    const phaseSelect = page.locator('select[name="SeasonPhase"]');
    await phaseSelect.selectOption('Playoffs');
    const phaseButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      phaseButton.click(),
    ]);
    await assertNoPhpErrors(page, 'after setting phase to Playoffs');

    await expect(
      page.locator('button[value="generate_awards"]'),
    ).toBeVisible();
  });

  test('generate_awards shows error when Leaders.htm absent', async ({
    page,
  }) => {
    // Explicitly set Playoffs phase (self-sufficient — no reliance on prior test)
    await page.goto('leagueControlPanel.php');
    const phaseSelect = page.locator('select[name="SeasonPhase"]');
    await phaseSelect.selectOption('Playoffs');
    const phaseButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      phaseButton.click(),
    ]);
    await assertNoPhpErrors(page, 'before clicking generate awards');

    const generateButton = page.locator('button[value="generate_awards"]');
    await expect(generateButton).toBeVisible();

    await Promise.all([
      page.waitForURL(/error=/),
      generateButton.click(),
    ]);

    await assertNoPhpErrors(page, 'after generate awards click');
    await expect(page.locator('.ibl-alert--error')).toBeVisible();

    const body = await page.locator('body').textContent();
    expect(body).toContain('Leaders.htm');
  });

  test('generate_awards button absent in Regular Season', async ({
    page,
  }) => {
    // Set phase to Regular Season
    await page.goto('leagueControlPanel.php');
    const phaseSelect = page.locator('select[name="SeasonPhase"]');
    await phaseSelect.selectOption('Regular Season');
    const phaseButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      phaseButton.click(),
    ]);

    await expect(
      page.locator('button[value="generate_awards"]'),
    ).toHaveCount(0);

    // Restore phase to Free Agency (CI seed default)
    await page.goto('leagueControlPanel.php');
    const restoreSelect = page.locator('select[name="SeasonPhase"]');
    await restoreSelect.selectOption('Free Agency');
    const restoreButton = page.locator('button[value="set_season_phase"]');
    await Promise.all([
      page.waitForURL(/success=/),
      restoreButton.click(),
    ]);
  });
});
