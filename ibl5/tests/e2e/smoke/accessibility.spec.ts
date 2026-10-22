import { test as publicTest, expect } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { assertNoA11yViolations, type A11yOptions } from '../helpers/accessibility';
import { gotoWithRetry } from '../helpers/navigation';

// Site-wide exclusions — explicit a11y debt tracker.
// Each entry should have a comment explaining why it's excluded.
const SITE_WIDE_DISABLED_RULES: string[] = [];

// Per-rule allowlist of pages with known failures — shrinking backlog tracker.
// See a-jay85/IBL5-backlog (labels: a11y for non-contrast, a11y-contrast for contrast).
// Each removal from a set = ratchet tightening (CI enforces that page/rule permanently).
// Keys are axe rule ids; values are sets of page names from the spec page lists.
const KNOWN_FAILING: Record<string, Set<string>> = {
  // PHP-Nuke legacy palette debt. See a-jay85/IBL5-backlog (label: a11y-contrast).
  'color-contrast': new Set([
    // Public pages
    'contracts teams',
    'player page',
    'activity tracker',
    'all-star appearances',
    'contracts players',
    'draft pick locator',
    'franchise history',
    'franchise record book',
    'injuries',
    'player movement',
    'projected draft order',
    'season archive',
    'season highs',
    'team off/def stats',
    // Pages with team-color contrast failures (ibl-team-cell--colored uses DB-configured team colors)
    // 2026-10-07 (IBL5-backlog#1270): 'free agency preview' and 'schedule' passed 10/10 and were removed (DELTA-OK).
    // Re-checked 2026-10-07 (IBL5-backlog#1270): kept, together with 'season leaderboards' below.
    // Their CI flake (commit 5cd079e6d) depends on ORDER BY ties, so local repeat runs cannot prove them clean.
    'league starters',
    // SeasonLeaderboards renders team-color cells via TeamCellHelper; which low-contrast team
    // surfaces in the top-N depends on ORDER BY tie ordering, so the violation appeared
    // intermittently in CI (the dev-seed inventory missed it). Same team-color debt as above.
    'season leaderboards',
    // Auth pages
    'waivers',
    'trading',
    'depth chart entry',
    'gm contact list',
    'draft',
    'next sim',
    // TrainingCampRatingsDiff renders team-color cells via TeamCellHelper. The page's own
    // .delta contrast is FIXED in design/components/ratings-diff.css (900 steps), not
    // allowlisted — the residue here is the same DB-configured team-color debt as
    // 'league starters' / 'season leaderboards' above.
    'training camp ratings diff',
  ]),

  // No <h1> on page — most module pages use <h2 class="ibl-title">. See a-jay85/IBL5-backlog (label: a11y).
  // Burn-down: a11y-2-heading-one-single-title (single-title views), supervised backlog (rest).
  'page-has-heading-one': new Set([
    // Seeded empirically — see plan a11y-1-ratchet-best-practice
    // Auth pages
    'your account',
    // The api keys and game boxscore pages left KNOWN_FAILING['page-has-heading-one'] once each rendered an h1.
  ]),

  // Heading-level skip (h4 after h2, no h3). See a-jay85/IBL5-backlog (label: a11y).
  // Burn-down: a11y-2-heading-one-single-title (record holders view, same render pass).
  'heading-order': new Set([
  ]),

  // Links with no discernible text. Remaining entry is the homepage last-sim-recap
  // team links (data-dependent — out of scope; News-template links carry aria-labels).
  // See a-jay85/IBL5-backlog (label: a11y) — search "link-name".
  'link-name': new Set([
    'homepage',
  ]),

  // Touch targets < 24×24px. Seed-dependent for small-count hits. See a-jay85/IBL5-backlog (label: a11y).
  // Empirically clean on the CI seed (axe target-size: 0 violations on topics/homepage/news article) — ratchet tightened.
  'target-size': new Set([
  ]),

  // Multiple landmarks share role+name. See a-jay85/IBL5-backlog (label: a11y) — search "landmark-unique".
  'landmark-unique': new Set([
  ]),

  // League control panel now has a <main> landmark (LeagueControlPanelView wraps its body in <main>) — ratchet tightened.
  'landmark-one-main': new Set([
  ]),

  // Content outside landmark regions. League control panel renders everything inside its <main> landmark (LeagueControlPanelView) — ratchet tightened.
  'region': new Set([
  ]),
};

// Per-rule allowlist of MOBILE-viewport (375px) known failures — separate from the
// desktop map above because a page can be desktop-clean yet mobile-fail (hit-area
// shrink at 375px) or vice-versa. Same ratchet semantics: each removal permanently
// enforces that page/rule at 375px.
//
// target-size ships EMPTY on purpose: the two 375px offenders (topics'
// .topic-card__cat, homepage's .leaders-tabbed__leader-team) are FIXED in CSS
// by this PR (Phases 4–5), not allowlisted. This key exists for future-regression
// tracking and to give the viewport-aware machinery a home. See
// a-jay85/IBL5-backlog (label: a11y) — search "target-size".
const MOBILE_KNOWN_FAILING: Record<string, Set<string>> = {
  'target-size': new Set([]),
};

function getA11yOptions(
  pageName: string,
  viewport: 'desktop' | 'mobile' = 'desktop',
): A11yOptions {
  const disableRules = [...SITE_WIDE_DISABLED_RULES];
  const allowlist =
    viewport === 'mobile' ? MOBILE_KNOWN_FAILING : KNOWN_FAILING;
  for (const [rule, pages] of Object.entries(allowlist)) {
    if (pages.has(pageName)) {
      disableRules.push(rule);
    }
  }
  return { disableRules };
}

// --- Public pages ---

// Not listed on purpose: TransactionHistory and VotingResults are retired redirect
// stubs. TransactionHistory lands on 'search transactions preset' below, and
// VotingResults lands on Voting, covered by 'voting ASG ballot' / 'voting EOY ballot'.
const publicPages: Array<{ name: string; url: string }> = [
  { name: 'homepage', url: 'index.php' },
  { name: 'standings', url: 'modules.php?name=Standings' },
  { name: 'season leaderboards', url: 'modules.php?name=Leaderboards&tab=season&submitted=1' },
  { name: 'career leaderboards', url: 'modules.php?name=Leaderboards&tab=career&submitted=1' },
  { name: 'draft history', url: 'modules.php?name=DraftHistory' },
  { name: 'contracts teams', url: 'modules.php?name=Contracts&tab=teams' },
  { name: 'player page', url: 'modules.php?name=Player&pa=showpage&pid=1' },
  { name: 'team page', url: 'modules.php?name=Team&op=team&teamid=1' },
  { name: 'activity tracker', url: 'modules.php?name=ActivityTracker' },
  { name: 'all-star appearances', url: 'modules.php?name=AllStarAppearances' },
  { name: 'award history', url: 'modules.php?name=AwardHistory' },
  { name: 'contracts players', url: 'modules.php?name=Contracts&tab=players' },
  { name: 'draft pick locator', url: 'modules.php?name=DraftPickLocator' },
  { name: 'franchise history', url: 'modules.php?name=FranchiseHistory' },
  { name: 'franchise record book', url: 'modules.php?name=Records&tab=byfranchise' },
  { name: 'free agency preview', url: 'modules.php?name=FreeAgencyPreview' },
  { name: 'injuries', url: 'modules.php?name=Injuries' },
  { name: 'league starters', url: 'modules.php?name=LeagueStarters' },
  { name: 'one on one game', url: 'modules.php?name=OneOnOneGame' },
  { name: 'player database', url: 'modules.php?name=PlayerSearch' },
  { name: 'player movement', url: 'modules.php?name=SeasonRosterChanges' },
  { name: 'projected draft order', url: 'modules.php?name=ProjectedDraftOrder' },
  { name: 'record holders', url: 'modules.php?name=Records&tab=alltime' },
  { name: 'schedule', url: 'modules.php?name=Schedule' },
  { name: 'season archive', url: 'modules.php?name=SeasonArchive' },
  { name: 'season highs', url: 'modules.php?name=Records&tab=thisseason' },
  { name: 'head-to-head records', url: 'modules.php?name=HeadToHeadRecords' },
  { name: 'team off/def stats', url: 'modules.php?name=TeamOffDefStats' },
  { name: 'search transactions preset', url: 'modules.php?name=Search&preset=transactions' },
  { name: 'search', url: 'modules.php?name=Search' },
  { name: 'topics', url: 'modules.php?name=Topics' },
  { name: 'news index', url: 'modules.php?name=News' },
  { name: 'news categories', url: 'modules.php?name=News&file=categories&op=newindex&catid=15' },
  { name: 'news article', url: 'modules.php?name=News&file=article&sid=1' },
  { name: 'team schedule', url: 'modules.php?name=Schedule&teamid=1' },
  // Seed rows for this game: ibl5/tests/e2e/fixtures/ci-seed.sql:124 and :814-823. An unknown pair 404s.
  { name: 'game boxscore', url: 'modules.php?name=GameBoxscore&date=2026-02-20&game=1' },
  // Anonymous sign-in form. Gated modules (Voting, Trading, FreeAgency, ...) JS-redirect here via loginbox().
  // Deliberately absent from KNOWN_FAILING['color-contrast'] (the .auth-links colors are fixed in auth.css)
  // and from MOBILE_KNOWN_FAILING['target-size'] (the 375px loop passes without an allowlist entry).
  { name: 'sign in', url: 'modules.php?name=YourAccount' },
];

// Pages whose entrance animation (.search-result searchResultIn) would be sampled mid-fade by
// axe, which reads transient colours. These pages load under prefers-reduced-motion: reduce
// (design/base.css zeroes animations) so axe sees the settled colours. Keyed on a publicPages name.
const SETTLED_ANIMATION_PAGES = new Set(['search transactions preset']);

publicTest.describe('Public page accessibility', () => {
  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const { name, url } of publicPages) {
    publicTest(`${name} has no accessibility violations`, async ({ page }) => {
      if (SETTLED_ANIMATION_PAGES.has(name)) {
        await page.emulateMedia({ reducedMotion: 'reduce' });
      }
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, getA11yOptions(name));
    });
  }
});

// --- Sign-in divider contrast ---
// axe skips punctuation-only text, so the "|" in <span class="auth-links__divider">
// (LoginView) is never contrast-checked by the 'sign in' row above. Compute the WCAG
// ratio directly. The divider is display:none at narrow widths, so pin a desktop viewport.

type Rgb = [number, number, number];

function parseCssRgb(value: string): { rgb: Rgb; alpha: number } {
  const match = value.match(/^rgba?\(\s*(\d+(?:\.\d+)?)[,\s]+(\d+(?:\.\d+)?)[,\s]+(\d+(?:\.\d+)?)(?:[,\s/]+(\d+(?:\.\d+)?%?))?\s*\)$/);
  if (!match) {
    throw new Error(`Unparseable CSS color: ${value}`);
  }
  const alphaRaw = match[4];
  const alpha =
    alphaRaw === undefined
      ? 1
      : alphaRaw.endsWith('%')
        ? parseFloat(alphaRaw) / 100
        : parseFloat(alphaRaw);
  return { rgb: [Number(match[1]), Number(match[2]), Number(match[3])], alpha };
}

function relativeLuminance([r, g, b]: Rgb): number {
  const [lr, lg, lb] = [r, g, b].map((channel) => {
    const c = channel / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * lr! + 0.7152 * lg! + 0.0722 * lb!;
}

function contrastRatio(foreground: Rgb, background: Rgb): number {
  const [lighter, darker] = [relativeLuminance(foreground), relativeLuminance(background)].sort(
    (a, b) => b - a,
  );
  return (lighter! + 0.05) / (darker! + 0.05);
}

publicTest.describe('Sign-in divider contrast (WCAG 1.4.3)', () => {
  publicTest.use({ viewport: { width: 1280, height: 720 } });

  publicTest('.auth-links__divider meets 4.5:1 against its effective background', async ({
    page,
  }) => {
    await gotoWithRetry(page, 'modules.php?name=YourAccount');

    const divider = page.locator('.auth-links__divider').first();
    await expect(divider).toBeVisible();

    const { color, backgrounds } = await divider.evaluate((el) => {
      const chain: string[] = [];
      for (let node: Element | null = el; node; node = node.parentElement) {
        chain.push(getComputedStyle(node).backgroundColor);
      }
      return { color: getComputedStyle(el).color, backgrounds: chain };
    });

    // Effective background: first ancestor with a non-transparent fill; white if none.
    const solid = backgrounds.map(parseCssRgb).find(({ alpha }) => alpha > 0);
    const background: Rgb = solid?.rgb ?? [255, 255, 255];

    const ratio = contrastRatio(parseCssRgb(color).rgb, background);
    expect(
      ratio,
      `divider color ${color} on rgb(${background.join(', ')}) = ${ratio.toFixed(2)}:1`,
    ).toBeGreaterThanOrEqual(4.5);
  });
});

// --- Search pagination link contrast ---
// The CI seed paginates the transactions preset (10 results plus a Next link). Pin the
// link colour to --accent-800 (#9a3412) and its ratio on the page background, so a
// revert to --accent-500 (2.41:1 on #eee) fails here directly. The VR baselines cannot
// catch it: two recoloured words fall under the 0.5% maxDiffPixelRatio.

publicTest.describe('Search pagination link contrast (WCAG 1.4.3)', () => {
  publicTest('.search-pagination__link uses accent-800 and meets 4.5:1', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await gotoWithRetry(page, 'modules.php?name=Search&preset=transactions');

    const link = page.locator('.search-pagination__link').first();
    await expect(link).toBeVisible();

    const { color, backgrounds } = await link.evaluate((el) => {
      const chain: string[] = [];
      for (let node: Element | null = el; node; node = node.parentElement) {
        chain.push(getComputedStyle(node).backgroundColor);
      }
      return { color: getComputedStyle(el).color, backgrounds: chain };
    });

    expect(color).toBe('rgb(154, 52, 18)');

    const solid = backgrounds.map(parseCssRgb).find(({ alpha }) => alpha > 0);
    const background: Rgb = solid?.rgb ?? [255, 255, 255];

    const ratio = contrastRatio(parseCssRgb(color).rgb, background);
    expect(
      ratio,
      `pagination link color ${color} on rgb(${background.join(', ')}) = ${ratio.toFixed(2)}:1`,
    ).toBeGreaterThanOrEqual(4.5);
  });
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
  { name: 'compare players', url: 'modules.php?name=ComparePlayers' },
  { name: 'draft', url: 'modules.php?name=Draft', state: { 'Show Draft Link': 'Yes' } },
  { name: 'next sim', url: 'modules.php?name=NextSim' },
  { name: 'your account', url: 'modules.php?name=YourAccount' },
  {
    name: 'voting ASG ballot',
    url: 'modules.php?name=Voting',
    state: { 'Current Season Phase': 'Regular Season', 'ASG Voting': 'Yes' },
  },
  {
    name: 'voting EOY ballot',
    url: 'modules.php?name=Voting',
    state: { 'Current Season Phase': 'Free Agency', 'EOY Voting': 'Yes' },
  },
  { name: 'training camp ratings diff', url: 'modules.php?name=TrainingCampRatingsDiff' },
  { name: 'api keys', url: 'modules.php?name=ApiKeys' },
  {
    name: 'league control panel',
    url: 'modules.php?name=LeagueControlPanel',
    state: { 'Current Season Phase': 'Regular Season', 'Current Season Ending Year': '2026' },
  },
];

authTest.describe('Authenticated page accessibility', () => {
  for (const { name, url, state } of authPages) {
    authTest(`${name} has no accessibility violations`, async ({ appState, page }) => {
      if (state) {
        await appState(state);
      }
      await page.goto(url);
      await assertNoA11yViolations(page, `on ${url}`, getA11yOptions(name));
    });
  }
});

// --- Mobile (375px) tap-target sweep — WCAG 2.2 SC 2.5.8 ---
// Site-wide generalization of the retired schedule-target-size.spec.ts (Phase 3).
// axe measures each element's own getBoundingClientRect at 375px, catching hit-area
// shrink that screenshot VR diffs are blind to (GH #909; reviewer m13v). Reuses the
// publicPages / authPages arrays and the viewport-aware ratchet (MOBILE_KNOWN_FAILING).
// gotoWithRetry guards blank-page retries under parallel PHP-built-in-server load.

publicTest.describe('Public mobile tap-target size (WCAG 2.2 SC 2.5.8)', () => {
  publicTest.describe.configure({ timeout: 60_000 }); // league schedule renders all teams — slow under shard load
  publicTest.use({ viewport: { width: 375, height: 812 } });

  publicTest.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off', 'Current Season Ending Year': '2026' });
  });

  for (const { name, url } of publicPages) {
    publicTest(`${name} has no target-size violations @mobile`, async ({ page }) => {
      await gotoWithRetry(page, url);
      await assertNoA11yViolations(page, `target-size on ${url} @mobile`, {
        ...getA11yOptions(name, 'mobile'),
        onlyRules: ['target-size'],
      });
    });
  }
});

authTest.describe('Authenticated mobile tap-target size (WCAG 2.2 SC 2.5.8)', () => {
  authTest.use({ viewport: { width: 375, height: 812 } });

  for (const { name, url, state } of authPages) {
    authTest(`${name} has no target-size violations @mobile`, async ({ appState, page }) => {
      if (state) {
        await appState(state);
      }
      await gotoWithRetry(page, url);
      await assertNoA11yViolations(page, `target-size on ${url} @mobile`, {
        ...getA11yOptions(name, 'mobile'),
        onlyRules: ['target-size'],
      });
    });
  }
});
