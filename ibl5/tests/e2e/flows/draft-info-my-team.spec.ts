import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// DraftInfo History: My team sub-tab, logged in as the CI GM.
// ci-seed.sql:244 documents that CI assigns gm_username to franchise 1, so the
// viewer's own team id is 1. Grounded with an auto-login curl of
// &tab=history&view=team: the h1 names the viewer's team and the table renders.
const OWN_TEAMID = 1;
const OTHER_TEAMID = 2;

test.describe('DraftInfo History: My team (logged in)', () => {
  test("My team sub-tab shows the viewer's team history", async ({ page }) => {
    await page.goto('modules.php?name=DraftInfo&tab=history&view=team');
    await assertNoPhpErrors(page, 'DraftInfo My team');

    const active = page.locator('.ibl-tabs--sub .ibl-tab[aria-current="page"]');
    await expect(active).toHaveText('My team');
    await expect(page.locator('h1.ibl-title').first()).toContainText('Draft History');
    await expect(page.locator('.ibl-subtab-panel .ibl-data-table').first()).toBeVisible();
    await expect(page.locator('.ibl-subtab-login-prompt')).toHaveCount(0);
  });

  test('own teamid is labeled My team and links to view=team', async ({ page }) => {
    await page.goto(`modules.php?name=DraftInfo&tab=history&teamid=${OWN_TEAMID}`);

    const active = page.locator('.ibl-tabs--sub .ibl-tab[aria-current="page"]');
    await expect(active).toHaveText('My team');
    const href = (await active.getAttribute('href')) ?? '';
    expect(href).toContain('view=team');
    expect(href).not.toContain('teamid=');
  });

  test("other teamid is labeled with that team's name when logged in", async ({ page }) => {
    await page.goto(`modules.php?name=DraftInfo&tab=history&teamid=${OTHER_TEAMID}`);

    const title = ((await page.locator('h1.ibl-title').first().textContent()) ?? '').trim();
    const teamName = title.replace(/ Draft History$/i, '');

    const active = page.locator('.ibl-tabs--sub .ibl-tab[aria-current="page"]');
    await expect(active).not.toHaveText('My team');
    await expect(active).toHaveText(teamName, { ignoreCase: true });
  });
});
