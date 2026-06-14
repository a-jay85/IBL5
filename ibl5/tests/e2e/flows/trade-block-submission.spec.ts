import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { resetTradeBlock } from '../helpers/cleanup';

// Trade Block submission flow — mutates Metros' (teamid=1) block rows.
// Serial: toggle ON/OFF + seeking note share team-1 state.
test.describe.configure({ mode: 'serial' });

// A known Metros (teamid=1) roster player seeded in ci-seed.sql.
const METROS_PID = '20';
const METROS_PLAYER_NAME = 'Metros PG';

async function submitEditForm(page: import('@playwright/test').Page): Promise<void> {
  const form = page.locator('form[name="Trade_Block"]');
  const responsePromise = page.waitForResponse(
    resp => resp.url().includes('modules.php') && resp.request().method() === 'POST',
  );
  await form.locator('button[type="submit"]').first().click();
  const response = await responsePromise;
  await page.waitForLoadState('networkidle');
  expect(page.url(), `POST status=${response.status()}`).toContain('result=block_updated');
}

test.describe('Trade Block: toggle on/off', () => {
  test('toggle ON: player appears on the browse board under Metros', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock&op=edit');

    const form = page.locator('form[name="Trade_Block"]');
    await expect(form).toBeVisible();

    await form.locator(`input[name="on_block[]"][value="${METROS_PID}"]`).check();
    await form.locator(`input[name="note[${METROS_PID}]"]`).fill('Available for the right deal');

    await submitEditForm(page);
    await expect(page.locator('.ibl-alert--success')).toBeVisible();
    await assertNoPhpErrors(page, 'after trade-block toggle ON');

    // Browse readback: the player now shows under Metros.
    await page.goto('modules.php?name=TradeBlock');
    await expect(page.locator('body')).toContainText(METROS_PLAYER_NAME);
  });

  test('toggle OFF: player no longer appears on the browse board', async ({ page }) => {
    await page.goto('modules.php?name=TradeBlock&op=edit');

    const form = page.locator('form[name="Trade_Block"]');
    await expect(form).toBeVisible();

    await form.locator(`input[name="on_block[]"][value="${METROS_PID}"]`).uncheck();
    await submitEditForm(page);

    // Browse readback: the player is gone (reconcile removed the block row).
    await page.goto('modules.php?name=TradeBlock');
    await expect(page.locator('body')).not.toContainText(METROS_PLAYER_NAME);
  });
});

test.describe('Trade Block: seeking note persists', () => {
  test('submitted seeking note is retained on reload', async ({ page }) => {
    const note = 'Seeking a stretch four and draft picks';

    await page.goto('modules.php?name=TradeBlock&op=edit');
    const form = page.locator('form[name="Trade_Block"]');
    await expect(form).toBeVisible();

    await form.locator('textarea[name="seeking_note"]').fill(note);
    await submitEditForm(page);

    // Reload the edit form — the textarea retains the saved value.
    await page.goto('modules.php?name=TradeBlock&op=edit');
    await expect(page.locator('textarea[name="seeking_note"]')).toHaveValue(note);
  });
});

test.describe('Trade Block: CSRF rejection', () => {
  test('POST without a valid CSRF token is rejected and writes nothing', async ({ page }) => {
    const response = await page.request.post('/ibl5/modules.php?name=TradeBlock', {
      form: {
        op: 'edit',
        Action: 'save',
        'on_block[]': METROS_PID,
        _csrf_token: 'garbage-token',
      },
      maxRedirects: 0,
    });

    const location = response.headers()['location'] ?? '';
    expect(location, 'Expected error redirect').toContain('error=');
    expect(location, 'Must not report success').not.toContain('result=block_updated');

    // Readback: the player was not added to the board.
    await page.goto('modules.php?name=TradeBlock');
    await expect(page.locator('body')).not.toContainText(METROS_PLAYER_NAME);
  });
});

test.describe('Trade Block: cross-team IDOR rejection', () => {
  test('forged pid for another team creates no block row', async ({ page }) => {
    // Read a valid CSRF token from A-Jay's (team 1) own edit form.
    await page.goto('modules.php?name=TradeBlock&op=edit');
    const csrfToken = await page
      .locator('form[name="Trade_Block"] input[name="_csrf_token"]')
      .inputValue();

    // Forge pid=24 "Cougars Forward" (teamid=3) — not on Metros' roster.
    const response = await page.request.post('/ibl5/modules.php?name=TradeBlock', {
      form: {
        op: 'edit',
        Action: 'save',
        'on_block[]': '24',
        _csrf_token: csrfToken,
      },
      maxRedirects: 0,
    });

    // The submission itself succeeds (own-roster reconcile), but the forged pid
    // is silently dropped — it is not on the resolved Metros roster.
    const location = response.headers()['location'] ?? '';
    expect(location).toContain('result=block_updated');

    // Readback: the forged Cougars player is NOT on the board.
    await page.goto('modules.php?name=TradeBlock');
    await expect(page.locator('body')).not.toContainText('Cougars Forward');
  });

  test.afterAll(async ({ request }) => {
    await resetTradeBlock(request);
  });
});
