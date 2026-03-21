import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Phase-gating tests as unauthenticated (public) user.
// The admin test user bypasses phase gates, so these tests use the public
// fixture to verify that features are properly gated when disabled.
// Serial mode: describe blocks toggle shared settings.
test.describe.configure({ mode: 'serial' });

test.describe('Trading disabled', () => {
  test('trading page shows disabled message when trades off', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Trades': 'No' });
    await page.goto('modules.php?name=Trading');

    // Should NOT show the trade partner selection form
    const teamSelect = page.locator('.trading-team-select');
    const teamSelectVisible = await teamSelect.isVisible().catch(() => false);
    expect(teamSelectVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Trading with trades disabled');
  });
});

test.describe('Draft hidden', () => {
  test('draft page blocked when not in draft phase and link off', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'Show Draft Link': 'Off',
    });
    await page.goto('modules.php?name=Draft');

    // Should NOT show the draft table
    const draftTable = page.locator('table.draft-table');
    const draftVisible = await draftTable.isVisible().catch(() => false);
    expect(draftVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Draft when hidden');
  });
});

test.describe('Voting closed (ASG)', () => {
  test('ASG voting shows closed when voting disabled', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'No',
    });
    await page.goto('modules.php?name=Voting');

    // Should NOT show the ASG ballot form
    const form = page.locator('form[name="ASGVote"]');
    const formVisible = await form.isVisible().catch(() => false);
    expect(formVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Voting with ASG voting off');
  });
});

test.describe('Voting closed (EOY)', () => {
  test('EOY voting shows closed when voting disabled', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'No',
    });
    await page.goto('modules.php?name=Voting');

    // Should NOT show the EOY ballot form
    const form = page.locator('form[name="EOYVote"]');
    const formVisible = await form.isVisible().catch(() => false);
    expect(formVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Voting with EOY voting off');
  });
});

test.describe('Waivers disabled', () => {
  test('waivers page blocked when waiver moves off', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Waiver Moves': 'No' });
    await page.goto('modules.php?name=Waivers');

    // Should NOT show waiver claim form elements
    const waiverForm = page.locator('form[name="waiver_add"], .waiver-form');
    const formVisible = await waiverForm.isVisible().catch(() => false);
    expect(formVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Waivers with moves disabled');
  });
});
