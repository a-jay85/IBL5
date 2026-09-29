import { test, expect, type Page } from '../fixtures/base';
import { publicStorageState } from '../helpers/public-storage-state';

// Pins computed styles captured on master before the shared-primitive refactor.
// Each case injects the post-migration class string into a live page, so the
// same assertions prove the migration changed no rendered value.
test.use({ storageState: publicStorageState() });

type Styles = Record<string, string>;

async function probe(page: Page, parentSelector: string, html: string, props: string[]): Promise<Styles> {
  return page.evaluate(
    ({ parentSelector, html, props }) => {
      const parent = document.querySelector(parentSelector) ?? document.body;
      const holder = document.createElement('div');
      holder.innerHTML = html;
      const node = holder.firstElementChild as HTMLElement;
      parent.appendChild(node);
      const target = (node.querySelector('[data-probe]') as HTMLElement | null) ?? node;
      const computed = getComputedStyle(target);
      const out: Record<string, string> = {};
      for (const prop of props) {
        out[prop] = computed.getPropertyValue(prop.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`));
      }
      node.remove();
      return out;
    },
    { parentSelector, html, props },
  );
}

function differsFromControl(actual: Styles, control: Styles): boolean {
  return Object.keys(actual).some((prop) => actual[prop] !== control[prop]);
}

const CHIP_PROPS = [
  'display', 'borderTopLeftRadius', 'fontWeight', 'whiteSpace', 'textTransform', 'paddingTop',
  'paddingLeft', 'backgroundColor', 'color', 'fontSize', 'letterSpacing', 'fontFamily',
];

const CHIP_CASES: Record<string, string> = {
  txn: '<span class="ibl-chip txn-badge txn-badge--2">Trade</span>',
  dc: '<div style="display:flex"><span data-probe class="ibl-chip ibl-chip--navy dc-card__pos-badge">PG</span></div>',
  league: '<span class="ibl-chip league-badge league-badge-ibl">IBL</span>',
  nav: '<span class="ibl-chip nav-badge">LIVE</span>',
  recap: '<div class="last-sim-recap__poslbl"><span data-probe class="ibl-chip ibl-chip--navy last-sim-recap__pos-chip">C</span></div>',
  allstar: '<span class="ibl-chip ibl-chip--navy all-star-rename__chip">Name</span>',
};

const BARLOW = 'Barlow, -apple-system, "system-ui", "Segoe UI", Roboto, sans-serif';

const EXPECTED_CHIPS: Record<string, Styles> = {
  txn: {
    display: 'inline-block', borderTopLeftRadius: '4.5px', fontWeight: '600', whiteSpace: 'nowrap',
    textTransform: 'none', paddingTop: '2.25px', paddingLeft: '9px', backgroundColor: 'rgb(255, 243, 224)',
    color: 'rgb(230, 81, 0)', fontSize: '15.75px', letterSpacing: '0.63px', fontFamily: BARLOW,
  },
  dc: {
    display: 'block', borderTopLeftRadius: '4.5px', fontWeight: '700', whiteSpace: 'normal',
    textTransform: 'uppercase', paddingTop: '2px', paddingLeft: '6px', backgroundColor: 'rgb(17, 24, 39)',
    color: 'rgb(255, 255, 255)', fontSize: '12.375px', letterSpacing: 'normal', fontFamily: BARLOW,
  },
  league: {
    display: 'inline-flex', borderTopLeftRadius: '6.75px', fontWeight: '700', whiteSpace: 'normal',
    textTransform: 'uppercase', paddingTop: '4.5px', paddingLeft: '13.5px', backgroundColor: 'rgb(17, 24, 39)',
    color: 'rgb(255, 255, 255)', fontSize: '15.75px', letterSpacing: '1.26px',
    fontFamily: '"Barlow Condensed", -apple-system, "system-ui", "Segoe UI", sans-serif',
  },
  nav: {
    display: 'inline-flex', borderTopLeftRadius: '4.5px', fontWeight: '700', whiteSpace: 'normal',
    textTransform: 'none', paddingTop: '2.25px', paddingLeft: '6.75px', backgroundColor: 'rgb(249, 115, 22)',
    color: 'rgb(255, 255, 255)', fontSize: '18px', letterSpacing: '0.72px', fontFamily: BARLOW,
  },
  recap: {
    display: 'block', borderTopLeftRadius: '4.5px', fontWeight: '700', whiteSpace: 'normal',
    textTransform: 'uppercase', paddingTop: '1px', paddingLeft: '6px', backgroundColor: 'rgb(17, 24, 39)',
    color: 'rgb(255, 255, 255)', fontSize: '12.006px', letterSpacing: '1.2006px',
    fontFamily: '"JetBrains Mono", "Fira Code", monospace',
  },
  allstar: {
    display: 'inline', borderTopLeftRadius: '9999px', fontWeight: '500', whiteSpace: 'nowrap',
    textTransform: 'none', paddingTop: '2.25px', paddingLeft: '9px', backgroundColor: 'rgb(17, 24, 39)',
    color: 'rgb(255, 255, 255)', fontSize: '15.75px', letterSpacing: 'normal', fontFamily: BARLOW,
  },
};

const TABLE_PROPS = [
  'boxShadow', 'borderTopWidth', 'borderLeftWidth', 'borderBottomWidth', 'borderBottomColor', 'borderTopLeftRadius',
];

const EXPECTED_TABLE: Styles = {
  boxShadow: 'none', borderTopWidth: '0px', borderLeftWidth: '0px', borderBottomWidth: '1px',
  borderBottomColor: 'rgb(229, 231, 235)', borderTopLeftRadius: '0px',
};

const LABEL_PROPS = [
  'display', 'fontSize', 'lineHeight', 'fontWeight', 'letterSpacing', 'textTransform', 'color', 'marginBottom',
];

const EXPECTED_LABEL: Styles = {
  display: 'block', fontSize: '18px', lineHeight: '27px', fontWeight: '600', letterSpacing: '1.8px',
  textTransform: 'uppercase', color: 'rgb(107, 114, 128)', marginBottom: '9px',
};

test.describe('CSS shared primitives', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('modules.php?name=Schedule');
  });

  test('chip styles stay pixel-identical', async ({ page }) => {
    const control = await probe(page, 'body', '<span>x</span>', CHIP_PROPS);
    const captured: Record<string, Styles> = {};
    for (const [key, html] of Object.entries(CHIP_CASES)) {
      captured[key] = await probe(page, 'body', html, CHIP_PROPS);
    }
    for (const [key, actual] of Object.entries(captured)) {
      expect(actual, key).toEqual(EXPECTED_CHIPS[key]);
      expect(differsFromControl(EXPECTED_CHIPS[key], control), `${key} differs from a plain span`).toBe(true);
    }
  });

  test('borderless record table keeps its compact chrome', async ({ page }) => {
    const control = await probe(page, 'body', '<span>x</span>', TABLE_PROPS);
    const actual = await probe(
      page,
      'body',
      '<table data-probe class="ibl-data-table ibl-data-table--borderless record-table"><tbody><tr><td>x</td></tr></tbody></table>',
      TABLE_PROPS,
    );
    expect(actual).toEqual(EXPECTED_TABLE);
    expect(differsFromControl(EXPECTED_TABLE, control)).toBe(true);
  });

  test('playoffs month header keeps its accent background', async ({ page }) => {
    const reference = await probe(page, 'body', '<div style="background-color: var(--accent-700)">x</div>', ['backgroundColor']);
    const actual = await probe(
      page,
      'body',
      '<div class="schedule-month"><div data-probe class="ibl-card__header schedule-month__header schedule-month__header--playoffs">P</div></div>',
      ['backgroundImage', 'backgroundColor'],
    );
    expect(actual.backgroundImage).toBe('none');
    expect(actual.backgroundColor).toBe(reference.backgroundColor);
  });

  test('month header adopts the card gradient and keeps compact padding', async ({ page }) => {
    const actual = await probe(
      page,
      'body',
      '<div class="schedule-month"><div data-probe class="ibl-card__header schedule-month__header">M</div></div>',
      ['backgroundImage', 'paddingTop', 'paddingLeft'],
    );
    expect(actual.backgroundImage).toContain('linear-gradient');
    expect(actual.paddingTop).toBe('4px');
    expect(actual.paddingLeft).toBe('8px');
  });

  test('nav section label matches its utility string', async ({ page }) => {
    const control = await probe(page, 'nav', '<div><span data-probe>x</span></div>', LABEL_PROPS);
    const actual = await probe(
      page,
      'nav',
      '<div class="px-4 py-3 border-t border-white/10 bg-black/20"><label data-probe class="block text-base font-semibold tracking-widest uppercase text-gray-500 mb-2">League</label></div>',
      LABEL_PROPS,
    );
    expect(actual).toEqual(EXPECTED_LABEL);
    expect(differsFromControl(EXPECTED_LABEL, control)).toBe(true);
  });
});
