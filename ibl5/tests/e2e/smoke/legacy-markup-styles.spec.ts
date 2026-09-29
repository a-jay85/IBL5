import type { JSHandle, Page } from '@playwright/test';
import { test, expect } from '../fixtures/base';
import { gotoWithRetry } from '../helpers/navigation';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });

// Hits the file_exists() else-branch in modules.php, which always wraps the
// notice in OpenTable().
const MISSING_FILE_URL = 'modules.php?name=Player&file=zzzmissing';

type Scaffold = { outer: HTMLTableElement; inner: HTMLTableElement } | null;

/**
 * Locate the OpenTable() outer/inner tables around the missing-file notice.
 * The lookup walks text nodes instead of class names so it works on the
 * legacy attribute markup and on the nuke-block class markup alike.
 */
async function scaffoldTables(page: Page): Promise<JSHandle<Scaffold>> {
  return page.evaluateHandle((): Scaffold => {
    const root = document.getElementById('site-content');
    if (!root) return null;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let textNode: Node | null = null;
    while (walker.nextNode()) {
      if ((walker.currentNode.textContent ?? '').includes("Sorry, such file doesn't exist")) {
        textNode = walker.currentNode;
        break;
      }
    }
    const inner = textNode?.parentElement?.closest('table') ?? null;
    const outer = inner?.parentElement?.closest('table') ?? null;
    if (!inner || !outer) return null;
    return { outer, inner };
  });
}

test.describe('Legacy PHP-Nuke scaffold styling', () => {
  test.beforeEach(async ({ page }) => {
    await gotoWithRetry(page, MISSING_FILE_URL);
  });

  test('missing-file page renders without PHP errors', async ({ page }) => {
    await assertNoPhpErrors(page, MISSING_FILE_URL);
  });

  test('OpenTable scaffold computed styles on missing-file page', async ({ page }) => {
    const tables = await scaffoldTables(page);
    const props = await tables.evaluate((t) => {
      if (!t) return null;
      const { outer, inner } = t;

      const probe = document.createElement('div');
      probe.style.backgroundColor = 'var(--page-bg)';
      document.body.appendChild(probe);
      const pageBg = getComputedStyle(probe).backgroundColor;
      probe.remove();

      const outerStyle = getComputedStyle(outer);
      const innerStyle = getComputedStyle(inner);
      // Compare against the parent's content box: .site-content has horizontal padding.
      const contentWidth = (el: HTMLElement): number => {
        const cs = getComputedStyle(el);
        return el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      };
      const outerParent = outer.parentElement as HTMLElement;
      const innerParent = inner.parentElement as HTMLElement;
      return {
        outerBg: outerStyle.backgroundColor,
        innerBg: innerStyle.backgroundColor,
        bodyBg: getComputedStyle(document.body).backgroundColor,
        pageBg,
        outerCollapse: outerStyle.borderCollapse,
        innerCollapse: innerStyle.borderCollapse,
        outerSpacing: outerStyle.borderSpacing,
        innerSpacing: innerStyle.borderSpacing,
        outerTdPad: getComputedStyle(outer.rows[0].cells[0]).paddingTop,
        innerTdPad: getComputedStyle(inner.rows[0].cells[0]).paddingTop,
        outerFullWidth:
          Math.abs(outer.getBoundingClientRect().width - contentWidth(outerParent)) < 1,
        innerFullWidth:
          Math.abs(inner.getBoundingClientRect().width - contentWidth(innerParent)) < 1,
      };
    });

    expect(props).not.toBeNull();
    expect(props!.outerBg).toBe('rgb(204, 204, 204)');
    expect(props!.innerBg).toBe(props!.pageBg);
    expect(props!.bodyBg).toBe(props!.pageBg);
    expect(props!.outerFullWidth).toBe(true);
    expect(props!.innerFullWidth).toBe(true);
    // Literals observed on master: Tailwind preflight already neutralizes cellpadding.
    expect(props!.outerCollapse).toBe('collapse');
    expect(props!.innerCollapse).toBe('collapse');
    expect(props!.outerSpacing).toBe('1px');
    expect(props!.innerSpacing).toBe('1px');
    expect(props!.outerTdPad).toBe('0px');
    expect(props!.innerTdPad).toBe('0px');
  });

  test('scaffold tables carry nuke-block classes and no presentational attributes', async ({ page }) => {
    const tables = await scaffoldTables(page);
    const props = await tables.evaluate((t) => {
      if (!t) return null;
      const { outer, inner } = t;
      const attrs = ['bgcolor', 'cellspacing', 'cellpadding', 'border', 'width', 'align'];
      return {
        outerClass: outer.className,
        innerClass: inner.className,
        outerAttrs: attrs.filter((a) => outer.hasAttribute(a)),
        innerAttrs: attrs.filter((a) => inner.hasAttribute(a)),
        bodyBgcolor: document.body.hasAttribute('bgcolor'),
      };
    });

    expect(props).not.toBeNull();
    expect(props!.outerClass).toContain('nuke-block-outer');
    expect(props!.outerClass).toContain('nuke-block--full');
    expect(props!.innerClass).toContain('nuke-block-inner');
    expect(props!.outerAttrs).toEqual([]);
    expect(props!.innerAttrs).toEqual([]);
    expect(props!.bodyBgcolor).toBe(false);
  });

  test('inner block fill follows --page-bg', async ({ page }) => {
    const tables = await scaffoldTables(page);
    const innerBg = await tables.evaluate((t) => {
      if (!t) return null;
      document.body.style.setProperty('--page-bg', 'rgb(1, 2, 3)');
      return getComputedStyle(t.inner).backgroundColor;
    });

    expect(innerBg).toBe('rgb(1, 2, 3)');
  });

  test('missing-file notice is centered via text-center', async ({ page }) => {
    await expect(
      page.locator('#site-content div.text-center', { hasText: "Sorry, such file doesn't exist" }),
    ).toHaveCSS('text-align', 'center');
    await expect(page.locator('#site-content center')).toHaveCount(0);
  });
});

// The Player and FreeAgencyPreview error paths are unreachable anonymously, so
// this proves the compiled-stylesheet contract with injected probe elements.
test('utility classes replacing module inline styles win the cascade', async ({ page }) => {
  await gotoWithRetry(page, 'index.php');
  await page.evaluate(() => {
    const root = document.getElementById('site-content');
    if (!root) return;
    const btn = document.createElement('a');
    btn.className = 'ibl-btn ibl-btn--primary mt-2 inline-block';
    btn.id = 'vr-probe-btn';
    btn.textContent = 'Go Back';
    const p = document.createElement('p');
    p.className = 'text-center p-8';
    p.id = 'vr-probe-p';
    p.textContent = 'x';
    root.append(btn, p);
  });

  // base.css sets the root font size to 18px, so mt-2 (0.5rem) is 9px and
  // p-8 (2rem) is 36px, the same as the inline rem values they replace.
  await expect(page.locator('#vr-probe-btn')).toHaveCSS('display', 'inline-block');
  await expect(page.locator('#vr-probe-btn')).toHaveCSS('margin-top', '9px');
  await expect(page.locator('#vr-probe-p')).toHaveCSS('text-align', 'center');
  await expect(page.locator('#vr-probe-p')).toHaveCSS('padding-top', '36px');
});
