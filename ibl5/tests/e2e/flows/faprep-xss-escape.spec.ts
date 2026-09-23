import { test, expect } from '../fixtures/auth';
import { setPlayerName } from '../helpers/test-state';

// Mutates ibl_plr pid=1 (ci-seed.sql 'Test Player'), so this spec runs only in
// the serial `mutators` project (playwright.config.ts) and never in sharded chromium.
const XSS = '<script>alert(1)</script>';

test('faprep.php escapes a script-tag player name', async ({ page, request }) => {
  const previous = await setPlayerName(request, 1, XSS);
  try {
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
  } finally {
    const restoredFrom = await setPlayerName(request, 1, previous);
    expect(restoredFrom).toBe(XSS);
  }
});
