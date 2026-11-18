import { test as publicTest, expect } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Admin-only, read-only trade suggestions tuning page. Modelled on
// league-control-panel-access.spec.ts. Non-admin 403 coverage lives in
// role-gating-non-admin.spec.ts. The seed writes one tid 8 hide with an `e2e-`
// key that no generated deal has, so it shows in the "not in this run" table.

const TUNING_URL = 'modules.php?name=TradeSuggestionsTuning';
const TEAM_COUNT = 28;

// The all-teams page can use the whole 20 s budget on a cold cache.
authTest.setTimeout(90_000);

publicTest.describe('Trade suggestions tuning — unauthenticated', () => {
  publicTest('anonymous visitor is redirected to login', async ({ page }) => {
    await page.goto(TUNING_URL);

    await expect(page).toHaveURL(/name=YourAccount/);
  });
});

authTest.describe('Trade suggestions tuning — admin', () => {
  authTest.beforeAll(async ({ request }) => {
    const response = await request.post('test-state.php?action=trade-suggestions-feedback-seed', { data: {} });
    expect(response.ok(), 'trade-suggestions-feedback-seed must answer 200').toBe(true);
  });

  authTest('admin sees the tuning page with filters and config', async ({ page }) => {
    await page.goto(`${TUNING_URL}&team=all&status=all`);
    await assertNoPhpErrors(page, 'on the tuning page for all teams');

    await expect(page.locator('h1.ibl-title')).toHaveText('Trade suggestions tuning');
    await expect(page.locator('#tuning-team')).toBeVisible();
    await expect(page.locator('#tuning-status')).toBeVisible();
    await expect(page.locator('#tuning-config')).toBeVisible();

    // A truncated run names the teams it skipped; together they cover all 28.
    // allTextContents() is [] when the note is absent, so no branch is needed.
    const notes = await page.locator('#tuning-truncated').allTextContents();
    const notRun = notes
      .flatMap((note) => note.replace(/^.*Teams not run:/s, '').split(','))
      .filter((tid) => tid.trim() !== '').length;
    expect((await page.locator('section.tuning-team').count()) + notRun).toBe(TEAM_COUNT);
  });

  authTest('team filter narrows to one section', async ({ page }) => {
    await page.goto(`${TUNING_URL}&team=8&status=all`);

    await expect(page.locator('section.tuning-team')).toHaveCount(1);
    await expect(page.locator('#tuning-team-8')).toBeVisible();
    await expect(page.locator('#tuning-team option[selected]')).toHaveAttribute('value', '8');
  });

  authTest('unknown status value is reported not fatal', async ({ page }) => {
    await page.goto(`${TUNING_URL}&status=bogus`);
    await assertNoPhpErrors(page, 'on the tuning page with an unknown status');

    await expect(page.locator('#tuning-ignored')).toContainText('status');
  });

  authTest('stored script tag renders as text', async ({ page }) => {
    await page.goto(`${TUNING_URL}&team=8&status=all`);

    const cell = page.locator('#tuning-unmatched-8 .tuning-note', { hasText: 'alert("tuning")' });
    await expect(cell).toHaveText('<script>alert("tuning")</script> & co');
    await expect(page.locator('#tuning-unmatched-8 script')).toHaveCount(0);
  });
});
