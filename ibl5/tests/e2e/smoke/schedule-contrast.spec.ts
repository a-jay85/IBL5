import { test, expect } from '../fixtures/public';
import { assertNoA11yViolations } from '../helpers/accessibility';

const PHP_ERROR_STRINGS = ['Fatal error', 'Warning:', 'Parse error', 'Uncaught', 'Stack trace:'];

test.describe('Schedule color-contrast (issue #908)', () => {
  test('default phase — no color-contrast violations in .schedule-container', async ({ page, appState }) => {
    await appState({ 'Trivia Mode': 'Off', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Schedule');
    await assertNoA11yViolations(page, 'on Schedule (.schedule-container, color-contrast)', {
      include: '.schedule-container',
      onlyRules: ['color-contrast'],
    });
  });

  test('playoff phase — no color-contrast violations in .schedule-container', async ({ page, appState }) => {
    await appState({ 'Trivia Mode': 'Off', 'Current Season Phase': 'Playoffs', 'Current Season Ending Year': '2026' });
    await page.goto('modules.php?name=Schedule');
    await assertNoA11yViolations(page, 'on Schedule (.schedule-container, color-contrast) — playoffs header', {
      include: '.schedule-container',
      onlyRules: ['color-contrast'],
    });
  });

  test('no PHP errors on Schedule page', async ({ page, appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
    await page.goto('modules.php?name=Schedule');
    const body = await page.textContent('body') ?? '';
    for (const errStr of PHP_ERROR_STRINGS) {
      expect(body, `PHP error on Schedule page: ${errStr}`).not.toContain(errStr);
    }
  });
});
