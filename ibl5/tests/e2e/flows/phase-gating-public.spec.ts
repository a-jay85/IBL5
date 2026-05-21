import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Each public-accessible gate tested in both DENY (off→hidden) and ALLOW (on→visible) directions.

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

  test('draft page shows draft table when in draft phase and link on', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');

    await expect(page.locator('table.draft-table')).toBeVisible();

    await assertNoPhpErrors(page, 'on Draft when enabled');
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

  test('ASG voting shows ballot form when voting enabled', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Regular Season',
      'ASG Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    await expect(page.locator('form[name="ASGVote"]')).toBeVisible();

    await assertNoPhpErrors(page, 'on Voting with ASG voting on');
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

  test('EOY voting shows ballot form when voting enabled', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'EOY Voting': 'Yes',
    });
    await page.goto('modules.php?name=Voting');

    await expect(page.locator('form[name="EOYVote"]')).toBeVisible();

    await assertNoPhpErrors(page, 'on Voting with EOY voting on');
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
