import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoA11yViolations, type A11yOptions } from '../helpers/accessibility';

// Site-wide exclusions — explicit a11y debt tracker.
// Each entry should have a comment explaining why it's excluded.
const SITE_WIDE_DISABLED_RULES: string[] = [
  'color-contrast', // PHP-Nuke legacy palette — nearly every page affected
  'link-name', // Team cell links (ibl-team-cell__name) render as empty <a> tags
  'image-alt', // Team logos and legacy images missing alt text
];

const A11Y_OPTIONS: A11yOptions = { disableRules: SITE_WIDE_DISABLED_RULES };

// --- Public pages ---

const publicPages: Array<{ name: string; url: string }> = [
  { name: 'homepage', url: 'index.php' },
  { name: 'standings', url: 'modules.php?name=Standings' },
  { name: 'season leaderboards', url: 'modules.php?name=SeasonLeaderboards' },
  { name: 'career leaderboards', url: 'modules.php?name=CareerLeaderboards' },
  { name: 'draft history', url: 'modules.php?name=DraftHistory' },
  { name: 'cap space', url: 'modules.php?name=CapSpace' },
  { name: 'player page', url: 'modules.php?name=Player&pa=showpage&pid=1' },
  { name: 'team page', url: 'modules.php?name=Team&op=team&teamID=1' },
];

publicTest.describe('Public page accessibility', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const { name, url } of publicPages) {
    publicTest(`${name} has no WCAG 2.1 AA violations`, async ({ page }) => {
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, A11Y_OPTIONS);
    });
  }
});

// --- Authenticated pages ---

const authPages: Array<{
  name: string;
  url: string;
  state?: Record<string, string>;
}> = [
  { name: 'trading', url: 'modules.php?name=Trading', state: { 'Allow Trades': 'Yes' } },
  {
    name: 'free agency',
    url: 'modules.php?name=FreeAgency',
    state: { 'Current Season Phase': 'Free Agency' },
  },
  { name: 'depth chart entry', url: 'modules.php?name=DepthChartEntry' },
  { name: 'waivers', url: 'modules.php?name=Waivers' },
  { name: 'gm contact list', url: 'modules.php?name=GMContactList' },
];

authTest.describe('Authenticated page accessibility', () => {
  for (const { name, url, state } of authPages) {
    authTest(`${name} has no WCAG 2.1 AA violations`, async ({ appState, page }) => {
      if (state) {
        await appState(state);
      }
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, A11Y_OPTIONS);
    });
  }
});
