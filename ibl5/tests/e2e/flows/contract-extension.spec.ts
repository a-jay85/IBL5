import { test, expect } from '../fixtures/auth';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { resetExtension } from '../helpers/cleanup';
import { withPhases, CONTRACT_BOUNDARY_PHASES } from '../fixtures/phase';

// Contract Extension flow — requires authenticated user (Metros GM).
// CI seed has extension-eligible player: pid=30 'Extension Vet' (cy=2, cyt=2 → final year).

test.describe('Contract Extension flow', () => {
  withPhases(['Regular Season'], () => {
    test('extension form renders for eligible player', async ({ page }) => {
      await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
      await assertNoPhpErrors(page, 'on extension form page');

      const body = await page.locator('body').textContent();
      expect(body).toContain('Extension Vet');
    });

    test('extension negotiate page renders form or eligibility message', async ({ page, request }) => {
      await resetExtension(request, 30);
      await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
      await assertNoPhpErrors(page, 'on extension form');

      // The negotiate page renders either the extension form (offerYear inputs) or a
      // validation message (eligibility/ownership). Both are valid renders — pid=30's
      // exact eligibility depends on parallel-test contract state, so assert that the
      // page produced a meaningful render rather than over-pinning the form.
      // .ibl-card__title was removed: it renders on every page in the site chrome and
      // satisfied the OR even when the negotiate page rendered neither form nor message. // e2e-hygiene-allow: form-or-message is the asserted contract here, not a silent fallback
      const formOrMessage = page.locator('input[name^="offerYear"], .ibl-alert').first();
      await expect(formOrMessage).toBeVisible();
    });

    test('negotiate page renders flippable trading card', async ({ page }) => {
      // pid=200000033 "Extension Card Target" (Metros, owned by CI user) is in his
      // final contract year (cy=2, yr3 salary 0), so the negotiate happy path renders
      // outside Free Agency — now hosting the Player module's flippable trading card.
      // This player is touched by no other spec, so the hard assertion is race-free.
      await page.goto('modules.php?name=Player&pa=negotiate&pid=200000033');
      await assertNoPhpErrors(page, 'on negotiate page with trading card');
      await expect(page.locator('.card-flip-container')).toBeVisible();
    });

    test('negotiate page for other team player shows no form', async ({ page }) => {
      // Use pid=4 which is on Stars (tid=2), not the test user's team (Metros)
      await page.goto('modules.php?name=Player&pa=negotiate&pid=4');
      await assertNoPhpErrors(page, 'on negotiate page for other team player');

      // Should not render offer inputs for a player not on user's team
      const formInputs = page.locator('input[name^="offerYear"]');
      expect(await formInputs.count()).toBe(0);
    });

    test('extension result banner renders on team page', async ({ page }) => {
      // Navigate directly to the result page to verify banner rendering
      await page.goto(
        'modules.php?name=Team&op=team&teamid=1&display=contracts&result=extension_accepted&msg=Player+agreed+to+extension',
      );

      const banner = page.locator('.ibl-alert--success');
      await expect(banner).toBeVisible();
      await expect(banner).toContainText('Player response:');
    });

    test('extension negotiate page contains player identity', async ({ page }) => {
      await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
      await assertNoPhpErrors(page, 'on extension form (hidden fields)');

      // Regular Season only. NegotiationOfferView::renderHeader() prints just the
      // "Contract Extension" title, so the player name reaches the page via the
      // offer form / eligibility message — both of which the Free Agency early
      // return in NegotiationService::processNegotiation() skips.
      const body = await page.locator('body').textContent();
      expect(body).toContain('Extension Vet');
    });
  });

  withPhases(['Free Agency'], () => {
    test('extension form blocked during free agency phase', async ({ page }) => {
      await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
      await assertNoPhpErrors(page, 'on extension form during FA');

      // Positive half: NegotiationValidator::validateFreeAgencyNotActive() makes
      // processNegotiation() return early with this alert. Without it a blank PHP
      // crash would read as "form absent" and satisfy the count check below.
      await expect(page.locator('.ibl-alert--error')).toContainText(
        'not available during free agency',
      );

      // Should show an error or redirect — not the extension form
      const formInputs = page.locator('input[name^="offerYear"]');
      const count = await formInputs.count();
      expect(count).toBe(0);
    });
  });

  withPhases(CONTRACT_BOUNDARY_PHASES, () => {
    test('team contracts page renders without errors', async ({ page }) => {
      await page.goto('modules.php?name=Team&op=team&teamid=1&display=contracts');
      await assertNoPhpErrors(page, 'on team contracts page');

      // Contracts display should show player rows
      const table = page.locator('.ibl-data-table').first();
      await expect(table).toBeVisible();
    });

    test('extension negotiate page renders without errors', async ({ page }) => {
      await page.goto('modules.php?name=Player&pa=negotiate&pid=30');
      await assertNoPhpErrors(page, 'on extension negotiate page');

      // Phase-invariant structure only. Every return path in
      // NegotiationService::processNegotiation() wraps its output in
      // .ibl-form-container and prepends renderHeader()'s title, on both sides of
      // Season::advancesContractYears(). A wrong-basis fatal drops both.
      await expect(page.locator('.ibl-form-container')).toBeVisible();
      await expect(page.locator('h1.ibl-title')).toContainText('Contract Extension');
    });

    test('no PHP errors on extension-related pages', async ({ page }) => {
      const urls = [
        'modules.php?name=Player&pa=negotiate&pid=30',
        'modules.php?name=Team&op=team&teamid=1&display=contracts',
      ];
      for (const url of urls) {
        await page.goto(url);
        await assertNoPhpErrors(page, `on ${url}`);
      }
    });
  });
});
