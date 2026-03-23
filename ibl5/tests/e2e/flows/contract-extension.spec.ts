import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Contract Extension flow — requires authenticated user (Metros GM).
// CI seed has extension-eligible player: pid=30 'Extension Vet' (cy=2, cyt=2 → final year).

test.describe('Contract Extension flow', () => {
  test.describe.configure({ mode: 'serial' });

  test('extension form renders for eligible player', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form page');

    const body = await page.locator('body').textContent();
    expect(body).toContain('Extension Vet');
  });

  test('extension form has salary input fields', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form');

    const inputs = page.locator('input[name^="offerYear"]');
    const count = await inputs.count();
    expect(count).toBeGreaterThanOrEqual(3);
  });

  test('extension form has hidden fields for player data', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form (hidden fields)');

    const playerID = page.locator('input[name="playerID"]');
    await expect(playerID).toBeAttached();
    expect(await playerID.inputValue()).toBe('30');

    const playerName = page.locator('input[name="playerName"]');
    await expect(playerName).toBeAttached();
    expect(await playerName.inputValue()).toBe('Extension Vet');
  });

  test('extension form blocked during free agency phase', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
    await assertNoPhpErrors(page, 'on extension form during FA');

    // Should show an error or redirect — not the extension form
    const formInputs = page.locator('input[name^="offerYear"]');
    const count = await formInputs.count();
    expect(count).toBe(0);
  });

  test('extension link appears on team contracts page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Team&op=team&teamID=1&display=contracts');
    await assertNoPhpErrors(page, 'on team contracts page');

    // CI seed: at least one player should be eligible for extension
    const extLinks = page.locator('a[href*="pa=negotiate"]');
    await expect(extLinks.first()).toBeVisible();
    const href = await extLinks.first().getAttribute('href');
    expect(href).toContain('pid=');
  });

  test('negotiate page for other team player shows no form', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    // Use pid=4 which is on Stars (tid=2), not the test user's team (Metros)
    await page.goto('modules.php?name=Player&pa=negotiate&pid=4');
    await assertNoPhpErrors(page, 'on negotiate page for other team player');

    // Should not render offer inputs for a player not on user's team
    const formInputs = page.locator('input[name^="offerYear"]');
    expect(await formInputs.count()).toBe(0);
  });

  test('submit extension with salary values', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Player&pa=negotiate&pid=30');

    const form = page.locator('form[name="ExtensionOffer"]');
    await expect(form).toBeVisible();

    // Fill salary inputs with reasonable values
    const inputs = page.locator('input[name^="offerYear"]');
    const count = await inputs.count();
    for (let i = 0; i < count; i++) {
      await inputs.nth(i).fill('1500');
    }

    // Submit form — POSTs to modules/Player/extension.php, redirects to Team page
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      form.locator('button[type="submit"], input[type="submit"]').first().click(),
    ]);

    // Should redirect to Team contracts page with result param
    const url = page.url();
    const hasResult =
      url.includes('result=extension_accepted') ||
      url.includes('result=extension_rejected') ||
      url.includes('result=extension_error');
    expect(hasResult).toBe(true);

    await assertNoPhpErrors(page, 'after extension submission');
  });

  test('extension result banner renders on team page', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });

    // Navigate directly to the result page to verify banner rendering
    await page.goto(
      'modules.php?name=Team&op=team&teamID=1&display=contracts&result=extension_accepted&msg=Player+agreed+to+extension',
    );

    const banner = page.locator('.ibl-alert--success');
    await expect(banner).toBeVisible();
    await expect(banner).toContainText('Player response:');
  });

  test('no PHP errors on extension-related pages', async ({ appState, page }) => {
    await appState({ 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' });

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
