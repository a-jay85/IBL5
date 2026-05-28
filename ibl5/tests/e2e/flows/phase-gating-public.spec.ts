import { test, expect } from '../fixtures/public';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Phase-gating DENY tests as unauthenticated (public) user.
// ALLOW-path tests are not possible here: all gated modules check is_user()
// before evaluating the gate, so public users always see loginBox().

test.describe('Trading disabled', () => {
  test('trading page shows disabled message when trades off', async ({
    appState,
    page,
  }) => {
    await appState({ 'Allow Trades': 'No' });
    await page.goto('modules.php?name=Trading');

    // Positive: the gating UI (loginBox) actually rendered — a blank PHP crash
    // would fail this, where the absence check alone would pass.
    await expect(page.locator('#login-username')).toBeVisible();

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

    // Positive: the page rendered its content shell (Draft is a public,
    // read-only view — no loginBox), so a blank PHP crash would fail here.
    await expect(page.locator('#site-content')).toBeVisible();

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

    // Positive: gating UI (loginBox) rendered, not a blank crash.
    await expect(page.locator('#login-username')).toBeVisible();

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

    // Positive: gating UI (loginBox) rendered, not a blank crash.
    await expect(page.locator('#login-username')).toBeVisible();

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

    // Positive: the page rendered its content shell (Waivers is a public,
    // read-only view — no loginBox), so a blank PHP crash would fail here.
    await expect(page.locator('#site-content')).toBeVisible();

    // Should NOT show waiver claim form elements
    const waiverForm = page.locator('form[name="waiver_add"], .waiver-form');
    const formVisible = await waiverForm.isVisible().catch(() => false);
    expect(formVisible).toBe(false);

    await assertNoPhpErrors(page, 'on Waivers with moves disabled');
  });
});
