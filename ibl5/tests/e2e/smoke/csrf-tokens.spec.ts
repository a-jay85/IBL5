import { test, expect } from '../fixtures/auth';
import { gotoWithRetry } from '../helpers/navigation';

/**
 * Verify that CSRF tokens are present on all protected forms.
 * Catches regressions where CsrfGuard::generateToken() calls are accidentally removed.
 */

const CSRF_PROTECTED_PAGES: Array<{
  name: string;
  url: string;
  state: Record<string, string>;
  formSelector: string;
}> = [
  {
    name: 'trading offer form',
    url: 'modules.php?name=Trading&op=offertrade&partner=Stars',
    state: { 'Allow Trades': 'Yes' },
    formSelector: 'form[name="Trade_Offer"]',
  },
  {
    name: 'waivers form',
    url: 'modules.php?name=Waivers',
    state: { 'Allow Waiver Moves': 'Yes' },
    formSelector: 'form[name="Waiver_Move"]',
  },
  {
    name: 'depth chart entry form',
    url: 'modules.php?name=DepthChartEntry',
    state: {},
    formSelector: 'form[name="DepthChartEntry"]',
  },
  {
    name: 'voting ballot (ASG)',
    url: 'modules.php?name=Voting',
    state: { 'ASG Voting': 'Yes', 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' },
    formSelector: 'form[name="ASGVote"]',
  },
  {
    name: 'voting ballot (EOY)',
    url: 'modules.php?name=Voting',
    state: { 'EOY Voting': 'Yes', 'Current Season Phase': 'Free Agency', 'Current Season Ending Year': '2026' },
    formSelector: 'form[name="EOYVote"]',
  },
];

test.describe('CSRF token presence on protected forms', () => {
  for (const page_def of CSRF_PROTECTED_PAGES) {
    test(`${page_def.name} has _csrf_token hidden input`, async ({ appState, page }) => {
      if (Object.keys(page_def.state).length > 0) {
        await appState(page_def.state);
      }
      await gotoWithRetry(page, page_def.url);

      const form = page.locator(page_def.formSelector);
      await expect(form).toBeVisible();

      const csrfInput = form.locator('input[name="_csrf_token"]');
      await expect(csrfInput).toBeAttached();

      const tokenValue = await csrfInput.getAttribute('value');
      expect(tokenValue, 'CSRF token must be a non-empty hex string').toMatch(/^[0-9a-f]{64}$/);
    });
  }
});
