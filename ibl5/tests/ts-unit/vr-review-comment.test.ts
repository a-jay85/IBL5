import { describe, it, expect } from 'vitest';
import type { VrRow } from '../e2e/vr-manifest';
import {
  buildComment,
  classifyCells,
  titleToModule,
  type DiffCell,
  type InfraCell,
} from '../e2e/vr-review-comment';

const PAGES_URL = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review/';

const FIXTURE_MANIFEST: VrRow[] = [
  { name: 'standings', auth: 'public', url: 'modules.php?name=Standings', anchor: '.t' },
  { name: 'player-movement', auth: 'public', url: 'modules.php?name=PlayerMovement', anchor: '.t' },
];

describe('buildComment', () => {
  it('row 6: groups diffing cells by module with desktop + mobile entries linking into the gallery', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
      { module: 'standings', viewport: 'mobile', title: 'standings-mobile' },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('<strong>standings</strong>');
    expect(md).toContain('standings — desktop');
    expect(md).toContain('standings-mobile — mobile');
    // V6a: links anchor into the static side-by-side gallery by cell title (no #?q= query).
    expect(md).toContain(`${PAGES_URL}#standings`);
    expect(md).toContain(`${PAGES_URL}#standings-mobile`);
    expect(md).not.toContain('#?q=');
    // Approval instructions present.
    expect(md).toContain('update-baselines');
  });

  it('row 7: a non-empty uncoveredChangedPaths ALWAYS emits the "Changed but NOT covered" section', () => {
    const md = buildComment({
      diffCells: [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      uncoveredChangedPaths: ['ibl5/modules/Ghost/Ghost.php'],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('Changed but NOT covered');
    expect(md).toContain('`ibl5/modules/Ghost/Ghost.php`');
  });

  it('row 7 (gap with zero diffs): uncovered section still renders when there are no diff cells', () => {
    const md = buildComment({
      diffCells: [],
      uncoveredChangedPaths: ['ibl5/modules/Ghost/Ghost.php'],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('Changed but NOT covered');
    expect(md).toContain('`ibl5/modules/Ghost/Ghost.php`');
  });

  it('row 8: zero diffing cells produces a safe no-diff body with no broken links', () => {
    const md = buildComment({ diffCells: [], uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });
    expect(md).toContain('No visual diffs detected');
    expect(md).not.toContain('#?q=');
    expect(md).not.toContain('<details>');
  });

  it('renders the global-change banner when globalChange is true AND there is pixel work', () => {
    const md = buildComment({
      diffCells: [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      uncoveredChangedPaths: [],
      globalChange: true,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('Global change detected');
  });
});

describe('titleToModule', () => {
  it('picks the longest matching row name (hyphenated names)', () => {
    expect(titleToModule('player-movement-empty-mobile', FIXTURE_MANIFEST)).toBe('player-movement');
    expect(titleToModule('standings-mobile', FIXTURE_MANIFEST)).toBe('standings');
    expect(titleToModule('unknown-thing', FIXTURE_MANIFEST)).toBeNull();
  });
});

describe('classifyCells', () => {
  it('3A: sets isNew=false for tracked title and isNew=true for untracked', () => {
    const cells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
      { module: 'standings', viewport: 'desktop', title: 'brand-new' },
    ];
    const result = classifyCells(cells, new Set(['standings']));
    expect(result.find((c) => c.title === 'standings')!.isNew).toBe(false);
    expect(result.find((c) => c.title === 'brand-new')!.isNew).toBe(true);
  });

  it('3A boundary: all-tracked ⇒ every isNew=false', () => {
    const cells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
      { module: 'standings', viewport: 'mobile', title: 'standings-mobile' },
    ];
    const result = classifyCells(cells, new Set(['standings', 'standings-mobile']));
    expect(result.every((c) => c.isNew === false)).toBe(true);
  });

  it('3A boundary: empty Set ⇒ every isNew=true', () => {
    const cells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
    ];
    const result = classifyCells(cells, new Set());
    expect(result.every((c) => c.isNew === true)).toBe(true);
  });

  it('3A purity: input cells are not mutated', () => {
    const cells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
    ];
    classifyCells(cells, new Set());
    expect(cells[0].isNew).toBeUndefined();
  });

  it('3G: classifyCells on empty array returns []', () => {
    expect(classifyCells([], new Set(['standings']))).toEqual([]);
  });
});

describe('buildComment — NEW vs CHANGED', () => {
  it('3B: mixed input renders NEW in 🆕 section and CHANGED in changed section', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings', isNew: false },
      { module: 'player-movement', viewport: 'desktop', title: 'player-movement', isNew: true },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('🆕 New views');
    expect(md).toContain('changed view(s)');
    expect(md).toContain('no committed baseline');
    // standings is CHANGED, not NEW
    const newSectionStart = md.indexOf('🆕 New views');
    const changedSectionStart = md.indexOf('changed view(s)');
    expect(changedSectionStart).toBeLessThan(newSectionStart);
    // player-movement link appears after the NEW heading
    const playerMovementIdx = md.indexOf('player-movement');
    expect(playerMovementIdx).toBeGreaterThan(newSectionStart);
    // standings link appears before NEW heading (in CHANGED section)
    const standingsIdx = md.indexOf('[standings');
    expect(standingsIdx).toBeGreaterThan(-1);
    expect(standingsIdx).toBeLessThan(newSectionStart);
  });

  it('3C: half-finish guard — changed header reads 1 changed view(s) with 2 NEW cells present', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings', isNew: false },
      { module: 'player-movement', viewport: 'desktop', title: 'player-movement', isNew: true },
      { module: 'player-movement', viewport: 'mobile', title: 'player-movement-mobile', isNew: true },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('1 changed view(s)');
    expect(md).toContain('2 new view(s)');
    expect(md).not.toContain('3 changed view(s)');
  });

  it('3D: all-NEW — emits 🆕 New views, no changed view(s) header, no No visual diffs detected', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings', isNew: true },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('🆕 New views');
    expect(md).not.toContain('changed view(s)');
    expect(md).not.toContain('No visual diffs detected');
  });

  it('3E: all-CHANGED — emits changed view(s), no 🆕 New views (regression guard)', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings', isNew: false },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('changed view(s)');
    expect(md).not.toContain('🆕 New views');
  });

  it('3F: boundary none — No visual diffs detected body still renders, no 🆕, no #?q=', () => {
    const md = buildComment({ diffCells: [], uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('No visual diffs detected');
    expect(md).not.toContain('🆕');
    expect(md).not.toContain('#?q=');
  });
});

describe('buildComment — flake/infra gating', () => {
  const oneInfra: InfraCell = {
    module: 'draft-history',
    viewport: 'desktop',
    title: 'draft-history',
    error: 'TimeoutError: page.goto exceeded',
  };
  const oneDiff: DiffCell = { module: 'standings', viewport: 'desktop', title: 'standings', isNew: false };

  it('flake-only omits the changed-pixels headline + update-baselines, includes the flake section + title', () => {
    const md = buildComment({
      diffCells: [],
      infraCells: [oneInfra],
      uncoveredChangedPaths: [],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).not.toContain('render differently from master');
    expect(md).not.toContain('update-baselines');
    expect(md).toContain('failed to render');
    expect(md).toContain('draft-history');
    expect(md).not.toContain('No visual diffs detected');
  });

  it('mixed: 1 changed cell + 1 flake contains BOTH the changed section AND the flake section', () => {
    const md = buildComment({
      diffCells: [oneDiff],
      infraCells: [oneInfra],
      uncoveredChangedPaths: [],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('render differently from master');
    expect(md).toContain('failed to render');
    expect(md).toContain('standings');
    expect(md).toContain('draft-history');
  });
});

describe('buildComment — change-driven prose truth (V6b/V6c)', () => {
  it('V6b: globalChange + empty diffCells renders the banner but NO "changed view(s)" section', () => {
    // In the change-driven design coverage drives ONLY the banner, never the
    // gallery — a shared-CSS change with no row diff still warns, but lists nothing.
    const md = buildComment({
      diffCells: [],
      infraCells: [],
      uncoveredChangedPaths: [],
      globalChange: true,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('Global change detected');
    expect(md).not.toContain('changed view(s) across');
  });

  it('V6c: changed cells present — comment asserts NO check color and never says "failed to render"', () => {
    // The gate may be green (baselines regenerated in-branch) while the gallery
    // still shows changes vs master's committed baseline — the copy must be true
    // in BOTH red and green, so it must not claim the check "stays red", and a
    // changed cell is never a render failure.
    const md = buildComment({
      diffCells: [{ module: 'standings', viewport: 'desktop', title: 'standings', isNew: false }],
      infraCells: [],
      uncoveredChangedPaths: [],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).not.toContain('stays red until then');
    expect(md).not.toContain('failed to render');
  });
});
