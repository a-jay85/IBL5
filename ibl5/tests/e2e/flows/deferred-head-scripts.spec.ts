import { test, expect } from '../fixtures/base';
import { publicStorageState } from '../helpers/public-storage-state';
import { gotoWithRetry } from '../helpers/navigation';

// The eight head scripts carry `defer` (PageLayout::renderScriptTags). These tests prove the
// deferred globals still initialise on a fully parsed DOM. The base fixture also fails any test
// that raises a pageerror or console.error, such as "htmx is not defined".
test.use({ storageState: publicStorageState() });

test.describe('Deferred head scripts', () => {
  test('head jslib scripts are all deferred and keep their order', async ({ page }) => {
    await gotoWithRetry(page, 'modules.php?name=PlayerSearch');

    const scripts = await page.evaluate(() =>
      Array.from(document.querySelectorAll<HTMLScriptElement>('head script[src*="jslib/"]')).map((el) => ({
        name: (el.getAttribute('src') ?? '').split('/').pop()!.split('?')[0]!.replace(/\.js$/, ''),
        defer: el.defer,
      })),
    );

    expect(scripts.map((s) => s.name)).toEqual([
      'htmx.min',
      'sorttable',
      'responsive-tables',
      'name-abbreviation',
      'user-team-highlighter',
      'sticky-page-header',
      'htmx-init',
      'local-time',
    ]);
    for (const script of scripts) {
      expect(script.defer, `${script.name} must be deferred`).toBe(true);
    }
  });

  test('local-time rewrites the server UTC text after deferred load', async ({ page }) => {
    // Seed row nuke_stories "Welcome to the new IBL season" (ci-seed.sql). The server fallback
    // text is "Tuesday, March 10 @ 10:00 GMT"; the converted text never contains "@".
    await gotoWithRetry(page, 'modules.php?name=Search&query=Welcome');

    const time = page.locator('time.local-time').first();
    await expect(time).toHaveAttribute('datetime', '2026-03-10T10:00:00+00:00');
    await expect(time).not.toContainText('@');
  });

  test.describe('sticky page header', () => {
    // The seed table is short (page height ~485 px), so a short viewport guarantees scroll room.
    test.use({ viewport: { width: 1280, height: 250 } });

    test('sticky page header clone appears after scrolling past the table head', async ({ page }) => {
      await gotoWithRetry(page, 'modules.php?name=HeadToHeadRecords');

      // The script appends the clone only from its window scroll listener.
      await expect(page.locator('.page-sticky-header-clone')).toHaveCount(0);

      await page.evaluate(() => {
        const thead = document.querySelector('.sticky-scroll-wrapper thead');
        if (!thead) {
          throw new Error('no thead inside .sticky-scroll-wrapper');
        }
        window.scrollTo(0, thead.getBoundingClientRect().top + window.scrollY - 10);
      });

      await expect(page.locator('.page-sticky-header-clone')).toHaveCount(1);
    });
  });
});
