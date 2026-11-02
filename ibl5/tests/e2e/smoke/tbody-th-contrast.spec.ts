import type { Page } from '@playwright/test';
import { test as publicTest } from '../fixtures/public';
import { test as authTest } from '../fixtures/auth';
import { test as authRegularTest } from '../fixtures/auth-regular';
import { expect } from '../fixtures/base';
import { assertNoPhpErrors } from '../helpers/php-errors';
import { gotoWithRetry } from '../helpers/navigation';
import { VR_MANIFEST, DEFAULT_STATE, type AuthMode } from '../vr-manifest';

// Guards against white-on-white row labels. The shared `.ibl-data-table th`
// rule paints every th white for the navy thead. A th that lands in a tbody
// (a row label) sits on a white/gray row fill and vanishes unless some rule
// recolors it. The sweep below measures every rendered
// `.ibl-data-table tbody th` on every VR-manifest page (default state,
// desktop) and fails when a label's contrast against its effective
// background drops under 3:1 (WCAG AA large text; the th is --text-xl).

type TbodyThCell = {
  text: string;
  color: string;
  background: string;
  ratio: number | null;
  scope: string | null;
  cls: string;
};

// Runs in the page. Self-contained: Playwright serializes it, so it cannot
// close over module scope. Partial alpha backgrounds are treated as opaque; no
// tbody row background in design/components/tables.css uses alpha.
const MEASURE_TBODY_TH = (): TbodyThCell[] => {
  const parse = (value: string): { r: number; g: number; b: number; a: number } | null => {
    const m = value.match(/rgba?\(([^)]+)\)/);
    if (m === null) return null;
    const parts = m[1].split(/[\s,/]+/).filter((p) => p !== '');
    const r = Number(parts[0]);
    const g = Number(parts[1]);
    const b = Number(parts[2]);
    const a = parts.length > 3 ? Number(parts[3]) : 1;
    if ([r, g, b, a].some((n) => Number.isNaN(n))) return null;
    return { r, g, b, a };
  };
  const lum = (c: { r: number; g: number; b: number }): number => {
    const lin = (v: number): number => {
      const s = v / 255;
      return s <= 0.04045 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  };
  const cells: TbodyThCell[] = [];
  document.querySelectorAll('.ibl-data-table tbody th').forEach((th) => {
    // The readable color belongs to the element that directly holds the first
    // text node, because a nested span (the H2H .ibl-team-cell__name) can set
    // its own color over a team-colored th. A th with no text (logo only) has
    // nothing to read, so it records a null ratio.
    const walker = document.createTreeWalker(th, NodeFilter.SHOW_TEXT);
    let textEl: Element | null = null;
    for (let n = walker.nextNode(); n !== null; n = walker.nextNode()) {
      if ((n.textContent ?? '').trim() !== '') {
        textEl = n.parentElement;
        break;
      }
    }
    const fg = textEl === null ? null : parse(getComputedStyle(textEl).color);
    let bg: { r: number; g: number; b: number; a: number } | null = null;
    let background = 'rgb(255, 255, 255)';
    let isImage = false;
    for (let el: Element | null = th; el !== null; el = el.parentElement) {
      const style = getComputedStyle(el);
      if (style.backgroundImage !== 'none') {
        isImage = true;
        background = 'image';
        break;
      }
      const parsed = parse(style.backgroundColor);
      if (parsed !== null && parsed.a > 0) {
        bg = parsed;
        background = style.backgroundColor;
        break;
      }
    }
    if (!isImage && bg === null) {
      bg = { r: 255, g: 255, b: 255, a: 1 };
    }
    let ratio: number | null = null;
    if (!isImage && bg !== null && fg !== null) {
      const l1 = lum(fg);
      const l2 = lum(bg);
      ratio = Math.round(((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)) * 100) / 100;
    }
    cells.push({
      text: (th.textContent ?? '').trim().slice(0, 30),
      color: textEl === null ? getComputedStyle(th).color : getComputedStyle(textEl).color,
      background,
      ratio,
      scope: th.getAttribute('scope'),
      cls: th.className,
    });
  });
  return cells;
};

const MIN_RATIO = 3;

function findOffenders(cells: TbodyThCell[]): TbodyThCell[] {
  return cells.filter((c) => c.ratio !== null && c.ratio < MIN_RATIO);
}

function register(t: typeof publicTest, auth: AuthMode): void {
  t.describe(`tbody th contrast sweep: ${auth}`, () => {
    for (const row of VR_MANIFEST.filter((r) => r.auth === auth)) {
      // Same default state the visual-regression spec renders: some rows need
      // an app state (a season phase) before their anchor exists at all.
      const state = (row.states ?? [DEFAULT_STATE]).find((s) => s.name === 'default') ?? DEFAULT_STATE;
      t(`tbody th legible: ${row.name}`, async ({ page, appState }) => {
        const settings = auth === 'public' ? { 'Trivia Mode': 'Off', ...state.appState } : state.appState;
        if (Object.keys(settings).length > 0) {
          await appState(settings);
        }
        await gotoWithRetry(page, row.url);
        await page.locator(row.anchor).first().waitFor();
        await assertNoPhpErrors(page, `on ${row.url}`);

        const cells = await page.evaluate(MEASURE_TBODY_TH);
        if (cells.length > 0) {
          t.info().annotations.push({ type: 'tbody-th', description: JSON.stringify(cells) });
        }
        const offenders = findOffenders(cells);
        expect(offenders, JSON.stringify(offenders)).toEqual([]);
      });
    }
  });
}

register(publicTest, 'public');
register(authTest, 'auth');
register(authRegularTest, 'auth-regular');

// In-page probe helpers. Each test builds markup on a page that already loads
// the real stylesheet, so the assertions run against the live CSS.
const PROBE_PAGE = 'modules.php?name=RecordHolders';

async function buildAndRead(
  page: Page,
  tableHtml: string,
  probeStyle: string,
  ids: string[],
): Promise<{ probe: string; values: Record<string, Record<string, string>> }> {
  return page.evaluate(
    ({ html, style, targetIds }) => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML = html;
      document.body.appendChild(wrapper);
      const probe = document.createElement('span');
      probe.setAttribute('style', style);
      document.body.appendChild(probe);
      const probeStyleValues = getComputedStyle(probe);
      const values: Record<string, Record<string, string>> = {};
      for (const id of targetIds) {
        const el = document.getElementById(id);
        const cs = el === null ? null : getComputedStyle(el);
        values[id] = {
          color: cs === null ? '' : cs.color,
          paddingLeft: cs === null ? '' : cs.paddingLeft,
        };
      }
      return {
        probe: probeStyleValues.color + '|' + probeStyleValues.paddingLeft,
        values,
      };
    },
    { html: tableHtml, style: probeStyle, targetIds: ids },
  );
}

publicTest.describe('row-label th rule', () => {
  publicTest('scoped row label computes to gray-800', async ({ page }) => {
    await gotoWithRetry(page, PROBE_PAGE);
    const result = await buildAndRead(
      page,
      '<table class="ibl-data-table"><tbody>' +
        '<tr><th scope="row" id="probe-row">Label</th><td>1</td></tr>' +
        '<tr><th id="probe-plain">Head</th><td>2</td></tr>' +
        '</tbody></table>',
      'color: var(--gray-800)',
      ['probe-row'],
    );
    const expected = result.probe.split('|')[0];
    expect(result.values['probe-row'].color).not.toBe('');
    expect(result.values['probe-row'].color).toBe(expected);
  });

  publicTest('unscoped tbody th keeps the white header color', async ({ page }) => {
    await gotoWithRetry(page, PROBE_PAGE);
    const result = await buildAndRead(
      page,
      '<table class="ibl-data-table"><tbody>' +
        '<tr><th scope="row" id="probe-row">Label</th><td>1</td></tr>' +
        '<tr><th id="probe-plain">Head</th><td>2</td></tr>' +
        '</tbody></table>',
      'color: var(--gray-800)',
      ['probe-plain'],
    );
    expect(result.values['probe-plain'].color).toBe('rgb(255, 255, 255)');
  });

  publicTest('api-keys row labels use body text color', async ({ page }) => {
    await gotoWithRetry(page, PROBE_PAGE);
    // The e2e user has no key and no Google connection, so the real
    // .api-keys-table never renders. Build its markup in-page against the real
    // stylesheet instead.
    const result = await page.evaluate(() => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML =
        '<table class="ibl-data-table api-keys-table"><tbody>' +
        '<tr><th scope="row" class="text-left" id="probe-api">Status</th><td>Connected</td></tr>' +
        '</tbody></table>';
      document.body.appendChild(wrapper);
      const colorProbe = document.createElement('span');
      colorProbe.setAttribute('style', 'color: var(--gray-800)');
      document.body.appendChild(colorProbe);
      const padProbe = document.createElement('span');
      padProbe.setAttribute('style', 'padding-left: var(--space-3)');
      document.body.appendChild(padProbe);
      const th = document.getElementById('probe-api');
      const cs = th === null ? null : getComputedStyle(th);
      return {
        color: cs === null ? '' : cs.color,
        paddingLeft: cs === null ? '' : cs.paddingLeft,
        expectedColor: getComputedStyle(colorProbe).color,
        expectedPadding: getComputedStyle(padProbe).paddingLeft,
      };
    });
    expect(result.color).not.toBe('');
    expect(result.color).toBe(result.expectedColor);
    expect(result.paddingLeft).toBe(result.expectedPadding);
  });

  publicTest('sweep detector flags an unscoped tbody th', async ({ page }) => {
    await gotoWithRetry(page, PROBE_PAGE);
    await page.evaluate(() => {
      const wrapper = document.createElement('div');
      wrapper.innerHTML =
        '<table class="ibl-data-table"><tbody><tr><th>x</th><td>1</td></tr></tbody></table>';
      document.body.appendChild(wrapper);
    });
    const cells = await page.evaluate(MEASURE_TBODY_TH);
    const offenders = findOffenders(cells);
    expect(offenders, JSON.stringify(cells)).toHaveLength(1);
    expect(offenders[0].text).toBe('x');
    expect(offenders[0].ratio).toBe(1);
  });
});
