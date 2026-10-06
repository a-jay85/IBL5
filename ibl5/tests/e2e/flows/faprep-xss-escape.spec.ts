import { test, expect } from '../fixtures/auth';
import { setPlayerName } from '../helpers/test-state';

// Mutates ibl_plr pid=1 (ci-seed.sql 'Test Player'), so this spec runs only in
// the serial `mutators` project (playwright.config.ts) and never in sharded chromium.
const XSS = '<script>alert(1)</script>';
// ci-seed.sql pid=1 name. Used only when a crashed earlier run left XSS in
// the row, so the observed "previous" value is the hostile string itself.
const SEED_NAME = 'Test Player';

// Restore target captured by beforeAll; null until the seed write succeeds.
let restoreTo: string | null = null;

// Seed in a hook so afterAll (own timeout; runs after a body timeout or a
// failed assertion) owns the restore. A hard kill skips every hook; the
// SEED_NAME fallback heals that on the next run.
test.beforeAll(async ({ request }) => {
  const previous = await setPlayerName(request, 1, XSS);
  restoreTo = previous === XSS ? SEED_NAME : previous;
});

test.afterAll(async ({ request }) => {
  if (restoreTo === null) {
    return;
  }
  const restoredFrom = await setPlayerName(request, 1, restoreTo);
  restoreTo = null;
  expect(restoredFrom).toBe(XSS);
});

test('faprep.php escapes a script-tag player name', async ({ page }) => {
  let dialogs = 0;
  page.on('dialog', async (dialog) => {
    dialogs += 1;
    await dialog.dismiss();
  });
  const response = await page.goto('faprep.php');
  expect(response?.status()).toBe(200);
  // Escaped output reads back as literal text in the cell.
  await expect(page.locator('table')).toContainText(XSS);
  // It never became an element and never executed.
  expect(await page.locator('script').count()).toBe(0);
  expect(dialogs).toBe(0);
});
