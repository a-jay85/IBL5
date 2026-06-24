import { describe, it, expect } from 'vitest';
import { execFileSync } from 'child_process';
import { writeFileSync } from 'fs';
import { tmpdir } from 'os';
import { resolve } from 'path';
import type { VrRow } from '../e2e/vr-manifest';
import {
  buildComment,
  classifyCells,
  extractDiffCells,
  titleToModule,
  type DiffCell,
} from '../e2e/vr-review-comment';

const PAGES_URL = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review/';

const FIXTURE_MANIFEST: VrRow[] = [
  { name: 'standings', auth: 'public', url: 'modules.php?name=Standings', anchor: '.t' },
  { name: 'player-movement', auth: 'public', url: 'modules.php?name=PlayerMovement', anchor: '.t' },
];

describe('buildComment', () => {
  it('row 6: groups diffing cells by module with desktop + mobile entries linking into the Pages URL', () => {
    const diffCells: DiffCell[] = [
      { module: 'standings', viewport: 'desktop', title: 'standings' },
      { module: 'standings', viewport: 'mobile', title: 'standings-mobile' },
    ];
    const md = buildComment({ diffCells, uncoveredChangedPaths: [], globalChange: false, pagesUrl: PAGES_URL });

    expect(md).toContain('<strong>standings</strong>');
    expect(md).toContain('standings — desktop');
    expect(md).toContain('standings-mobile — mobile');
    // Links deep-link into the per-SHA Playwright HTML report, filtered by test title.
    expect(md).toContain(`${PAGES_URL}#?q=standings`);
    expect(md).toContain(`${PAGES_URL}#?q=standings-mobile`);
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

  it('renders the global-change banner when globalChange is true', () => {
    const md = buildComment({ diffCells: [], uncoveredChangedPaths: [], globalChange: true, pagesUrl: PAGES_URL });
    expect(md).toContain('Global change detected');
  });
});

describe('extractDiffCells / titleToModule', () => {
  it('titleToModule picks the longest matching row name (hyphenated names)', () => {
    expect(titleToModule('player-movement-empty-mobile', FIXTURE_MANIFEST)).toBe('player-movement');
    expect(titleToModule('standings-mobile', FIXTURE_MANIFEST)).toBe('standings');
    expect(titleToModule('unknown-thing', FIXTURE_MANIFEST)).toBeNull();
  });

  it('extractDiffCells parses only failed specs from a Playwright JSON report', () => {
    const report = {
      suites: [
        {
          specs: [{ title: 'standings', ok: true }],
          suites: [
            {
              specs: [
                { title: 'standings-mobile', ok: false },
                { title: 'player-movement-empty', ok: false },
              ],
            },
          ],
        },
      ],
    };
    const cells = extractDiffCells(report, FIXTURE_MANIFEST);
    expect(cells).toHaveLength(2);
    expect(cells).toContainEqual({ module: 'standings', viewport: 'mobile', title: 'standings-mobile' });
    expect(cells).toContainEqual({ module: 'player-movement', viewport: 'desktop', title: 'player-movement-empty' });
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

describe('CLI end-to-end (bin/vr-review-comment against real git index)', () => {
  it('3 (CLI row): classifies committed-baseline title as CHANGED and uncommitted title as NEW', () => {
    // standings.png is a committed baseline; standings-nobaselinexyz.png is not.
    const repoRoot = resolve(__dirname, '../../..');
    const tmpResults = `${tmpdir()}/vr-review-comment-test-${Date.now()}.json`;
    const fakeReport = {
      suites: [
        {
          specs: [],
          suites: [
            {
              specs: [
                { title: 'standings', ok: false },
                { title: 'standings-nobaselinexyz', ok: false },
              ],
            },
          ],
        },
      ],
    };
    writeFileSync(tmpResults, JSON.stringify(fakeReport));

    const stdout = execFileSync(
      'bun',
      [
        'bin/vr-review-comment',
        `--results=${tmpResults}`,
        '--coverage=/nonexistent',
        '--pages-url=https://example/IBL5/deadbeef/visual-review/',
      ],
      { cwd: repoRoot, encoding: 'utf-8' },
    );

    expect(stdout).toContain('🆕 New views');
    expect(stdout).toContain('standings-nobaselinexyz');
    expect(stdout).toContain('1 changed view(s)');
    // The NEW title must NOT appear in the CHANGED section
    const changedSectionEnd = stdout.indexOf('🆕 New views');
    expect(changedSectionEnd).toBeGreaterThan(-1);
    const standsNoBaselineInChanged = stdout.substring(0, changedSectionEnd).indexOf('standings-nobaselinexyz');
    expect(standsNoBaselineInChanged).toBe(-1);
  });
});
