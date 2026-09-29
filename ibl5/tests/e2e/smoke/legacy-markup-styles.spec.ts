import { test, expect } from '../fixtures/base';
import { gotoWithRetry } from '../helpers/navigation';
import { publicStorageState } from '../helpers/public-storage-state';

test.use({ storageState: publicStorageState() });

const MISSING_FILE_URL = 'modules.php?name=Player&file=zzzmissing';

test.describe('Legacy PHP-Nuke scaffold styling', () => {
  test('OpenTable scaffold computed styles on missing-file page', async ({ page }) => {
    await gotoWithRetry(page, MISSING_FILE_URL);

    const props = await page.evaluate(() => {
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
});
