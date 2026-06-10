import { test } from '../fixtures/public';
import { assertNoA11yViolations } from '../helpers/accessibility';

// Guards WCAG 2.2 SC 2.5.8 (target-size) on the Schedule pages. The score links
// (a.schedule-game__score-link) and "@"/vs links (a.schedule-game__vs) are
// CSS-grid items whose width tracks their grid track; undersized tracks render
// the <a> box below the 24×24px minimum. axe-core measures the element's own
// getBoundingClientRect(), so this rule fails on any too-narrow track edit.
//
// The generic WCAG 2.1 AA a11y sweep (accessibility.spec.ts) does NOT cover
// target-size — it is WCAG 2.2 — so this spec is net-new coverage. The mobile
// viewport block is load-bearing: the smallest tracks live in the 640px
// breakpoint, so a desktop-only check would miss the mobile regression.

const schedulePages: Array<{ name: string; url: string }> = [
  { name: 'league schedule', url: 'modules.php?name=Schedule' },
  { name: 'team schedule', url: 'modules.php?name=Schedule&teamid=1' },
];

test.describe('Schedule tap-target size (WCAG 2.2 SC 2.5.8)', () => {
  test.beforeEach(async ({ appState }) => {
    await appState({ 'Trivia Mode': 'Off' });
  });

  for (const { name, url } of schedulePages) {
    test(`${name} has no target-size violations @desktop`, async ({ page }) => {
      await page.goto(url);
      await assertNoA11yViolations(page, `target-size on ${url} @desktop`, {
        onlyRules: ['target-size'],
      });
    });
  }

  test.describe('mobile viewport', () => {
    test.use({ viewport: { width: 375, height: 812 } });

    for (const { name, url } of schedulePages) {
      test(`${name} has no target-size violations @mobile`, async ({ page }) => {
        await page.goto(url);
        await assertNoA11yViolations(page, `target-size on ${url} @mobile`, {
          onlyRules: ['target-size'],
        });
      });
    }
  });
});
