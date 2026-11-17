import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Commissioner switch for sim-recap Discord posting (League Control Panel).
// Initial state `Off` comes from the ci-seed.sql row ('Sim Recap Discord Posting', 'Off', 'ibl').
// The select renders in every season phase, so this spec never touches the shared phase row.
// Serial: both tests read and write the single ibl_settings row.
test.describe.configure({ mode: 'serial' });

const LCP_URL = 'modules.php?name=LeagueControlPanel';

test.describe('LeagueControlPanel — sim recap Discord posting switch', () => {
  test('commissioner turns sim recap posting on and off', async ({ page }) => {
    await page.goto(LCP_URL);
    await assertNoPhpErrors(page, 'on LeagueControlPanel before toggling');

    const select = page.locator('#lcp-sim-recap-posting');
    const state = page.locator('.lcp-sim-recap-posting-state');
    const submit = page.locator('button[name="action"][value="set_sim_recap_posting"]');

    await expect(select).toHaveValue('Off');
    await expect(state).toHaveText('Currently: Off');

    try {
      await select.selectOption('On');
      await Promise.all([page.waitForURL(/success=/), submit.click()]);
      await assertNoPhpErrors(page, 'after turning sim recap posting on');

      await expect(select).toHaveValue('On');
      await expect(state).toHaveText('Currently: On');
    } finally {
      // Restore the seed state even when an assertion above failed.
      await page.goto(LCP_URL);
      await select.selectOption('Off');
      await Promise.all([page.waitForURL(/success=/), submit.click()]);
    }

    await expect(select).toHaveValue('Off');
    await expect(state).toHaveText('Currently: Off');
  });

  test('posting switch rejects a POST without a CSRF token', async ({ page }) => {
    await page.request.post(LCP_URL, {
      form: { action: 'set_sim_recap_posting', SimRecapPosting: 'On' },
    });

    await page.goto(LCP_URL);
    await expect(page.locator('#lcp-sim-recap-posting')).toHaveValue('Off');
    await expect(page.locator('.lcp-sim-recap-posting-state')).toHaveText('Currently: Off');
  });
});
