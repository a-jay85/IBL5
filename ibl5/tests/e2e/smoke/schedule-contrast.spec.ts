import { test, expect } from '../fixtures/public';
import { assertNoA11yViolations } from '../helpers/accessibility';
import { gotoWithRetry } from '../helpers/navigation';

// NOTE: the historically-reported flake here could not be reproduced or traced
// (the failing CI artifacts had expired and the test passed in every surviving
// run). The hardening below is deliberately NON-MASKING: gotoWithRetry plus an
// explicit `.schedule-container` visibility gate before axe only make a
// blank/partial-render page fail louder — they cannot turn a real color-contrast
// violation into a silent pass. If it still flakes, capture the trace before
// touching the axe assertion itself.

const PHP_ERROR_STRINGS = ['Fatal error', 'Warning:', 'Parse error', 'Uncaught', 'Stack trace:'];

test.describe('Schedule color-contrast (issue #908)', () => {
  // League schedule renders all teams — slower under parallel CI shard load.
  // schedule-target-size.spec.ts (PR #1022) hits the same URL concurrently; 60s gives both headroom.
  test.setTimeout(60000);

  test('default phase — no color-contrast violations in .schedule-container', async ({ page, appState }) => {
    await appState({ 'Trivia Mode': 'Off', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=Schedule');
    await expect(page.locator('.schedule-container')).toBeVisible();
    await assertNoA11yViolations(page, 'on Schedule (.schedule-container, color-contrast)', {
      include: '.schedule-container',
      onlyRules: ['color-contrast'],
    });
  });

  test('playoff phase — no color-contrast violations in .schedule-container', async ({ page, appState }) => {
    await appState({ 'Trivia Mode': 'Off', 'Current Season Phase': 'Playoffs', 'Current Season Ending Year': '2026' });
    await gotoWithRetry(page, 'modules.php?name=Schedule');
    await expect(page.locator('.schedule-container')).toBeVisible();
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
