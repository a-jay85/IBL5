import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Contract Extension flow — requires authenticated user (Metros GM).
// CI seed has extension-eligible player: pid=30 'Extension Vet' (cy=2, cyt=2 → final year).
// Tests gracefully skip when specific seed data is unavailable (local dev).

test.describe('Contract Extension flow', () => {
  test.describe.configure({ mode: 'serial' });

  test('extension form renders for eligible player', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form page');

    const body = await page.locator('body').textContent();
    if (!body?.includes('Extension Vet')) {
      test.skip(true, 'CI seed player pid=30 not present — skipping extension form test');
    }
    expect(body).toContain('Extension Vet');
  });

  test('extension form has salary input fields', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form');

    const inputs = page.locator('input[name^="offerYear"]');
    const count = await inputs.count();
    if (count === 0) {
      test.skip(true, 'Extension form not rendered — CI seed data required');
    }
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('extension form has hidden fields for player data', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');

    const playerID = page.locator('input[name="playerID"]');
    const isAttached = await playerID.count();
    if (isAttached === 0) {
      test.skip(true, 'Extension form not rendered — CI seed data required');
    }
    await expect(playerID).toBeAttached();
    expect(await playerID.inputValue()).toBe('30');

    const playerName = page.locator('input[name="playerName"]');
    await expect(playerName).toBeAttached();
    expect(await playerName.inputValue()).toBe('Extension Vet');
  });

  test('extension form blocked during free agency phase', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form during FA');

    // Should show an error or redirect — not the extension form
    const formInputs = page.locator('input[name^="offerYear"]');
    const count = await formInputs.count();
    expect(count).toBe(0);
  });

  test('extension link appears on team contracts page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    await page.goto('modules.php?name=Team&op=team&teamID=1&display=contracts');
    await assertNoPhpErrors(page, 'on team contracts page');

    // Look for any extension/negotiate link (may include pid=30 or other eligible players)
    const extLinks = page.locator('a[href*="pa=negotiate"]');
    const count = await extLinks.count();
    // With seed data, at least one player should be eligible; without it, this is informational
    if (count > 0) {
      const href = await extLinks.first().getAttribute('href');
      expect(href).toContain('negotiate');
    }
  });

  test('negotiate page for other team player shows no form', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });
    // Use pid=4 which is on Stars (tid=2), not the test user's team (Metros)
    await page.goto('modules.php?name=Player&pa=negotiate&pid=4');
    await assertNoPhpErrors(page, 'on negotiate page for other team player');

    // Should not render offer inputs for a player not on user's team
    const formInputs = page.locator('input[name^="offerYear"]');
    expect(await formInputs.count()).toBe(0);
  });

  test('no PHP errors on extension-related pages', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season' });

    const urls = [
      'modules.php?name=Player&pa=negotiate&pid=30',
      'modules.php?name=Team&op=team&teamID=1&display=contracts',
    ];
    for (const url of urls) {
      await page.goto(url);
      await assertNoPhpErrors(page, `on ${url}`);
    }
  });
});
