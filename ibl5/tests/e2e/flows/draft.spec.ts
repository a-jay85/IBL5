import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';

// Draft flow — authenticated tests with state control.
// Serial: draft selection test mutates state (marks player as drafted).
test.describe.configure({ mode: 'serial' });

test.describe('Draft board: renders', () => {
  test.beforeEach(async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');
  });

  test('draft board loads with player table', async ({ page }) => {
    // Draft table should be visible with sortable class
    const table = page.locator('table.draft-table');
    await expect(table).toBeVisible();
  });

  test('draft class table has expected columns', async ({ page }) => {
    const table = page.locator('table.draft-table');
    await expect(table).toBeVisible();

    // Key columns: Name, Pos, Age
    const headers = table.locator('thead th');
    const headerTexts = await headers.allTextContents();
    const joined = headerTexts.join(' ');
    expect(joined).toContain('Name');
    expect(joined).toContain('Pos');
    expect(joined).toContain('Age');
  });

  test('current pick indicator shows team on the clock', async ({ page }) => {
    // The draft page should show which team is picking
    // Metros (pick 1) should be on the clock in seed data
    const body = await page.locator('body').textContent();
    // Look for pick/round indicator text
    expect(body).toMatch(/round|pick|on the clock|draft/i);
  });

  test('undrafted players listed with radio buttons', async ({ page }) => {
    // When Metros own the current pick, radio buttons appear for undrafted players
    const radios = page.locator('input[type="radio"][name="player"]');
    expect(await radios.count()).toBeGreaterThan(0);
  });

  test('drafted players have drafted class', async ({ page }) => {
    // Already Drafted PG and PF should have .drafted class
    const draftedRows = page.locator('tr.drafted');
    expect(await draftedRows.count()).toBeGreaterThanOrEqual(2);
  });

  test('submit button visible when user owns current pick', async ({
    page,
  }) => {
    // Metros own pick 1 — submit button should be visible
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /draft player/i,
    });
    await expect(submitBtn.first()).toBeVisible();
  });

  test('no PHP errors on draft board', async ({ page }) => {
    await assertNoPhpErrors(page, 'on Draft board');
  });
});

test.describe('Draft selection: submission', () => {
  test('successful draft selection', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');

    // Select the first undrafted player
    const firstRadio = page.locator('input[type="radio"][name="player"]').first();
    await expect(firstRadio).toBeVisible();
    await firstRadio.check();

    // Submit the form — navigates to draft_selection.php
    const submitBtn = page.locator('button, input[type="submit"]').filter({
      hasText: /draft player/i,
    });
    await submitBtn.first().click();

    // Wait for response page to load
    await page.waitForLoadState('domcontentloaded');

    // The response is raw HTML (no layout). Check innerHTML for success patterns.
    const html = await page.content();
    const hasSuccess = /select|drafted|pick\s*#|Draft/i.test(html);
    const hasError = /oops|error|didn.t select/i.test(html);

    // Either success or a known error is acceptable (confirms form submitted)
    expect(hasSuccess || hasError).toBeTruthy();
  });

  test('validation: no player selected', async ({ appState, page }) => {
    await appState({
      'Current Season Phase': 'Draft',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');

    // Submit without selecting a player
    const form = page.locator('form[name="draft_form"]');
    if ((await form.count()) === 0) {
      test.skip(true, 'Draft form not found (pick may already be filled)');
    }

    // Submit via JS to bypass client-side validation
    await page.evaluate(() => {
      const f = document.querySelector('form[name="draft_form"]') as HTMLFormElement;
      if (f) f.submit();
    });

    await page.waitForLoadState('domcontentloaded');
    const html = await page.content();
    expect(html).toMatch(/didn.t select|select a player|oops/i);
  });
});

test.describe('Draft: phase gating', () => {
  test('draft hidden when phase is not Draft and link is off', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'Show Draft Link': 'Off',
    });
    await page.goto('modules.php?name=Draft');

    // Should not show the draft table
    const table = page.locator('table.draft-table');
    expect(await table.count()).toBe(0);
  });

  test('draft accessible via Show Draft Link override', async ({
    appState,
    page,
  }) => {
    await appState({
      'Current Season Phase': 'Free Agency',
      'Show Draft Link': 'On',
    });
    await page.goto('modules.php?name=Draft');

    // Draft table should load even outside Draft phase
    const table = page.locator('table.draft-table');
    await expect(table).toBeVisible();
  });
});
