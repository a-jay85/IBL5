import { describe, it, expect } from 'vitest';
import type { VrRow } from '../e2e/vr-manifest';
import {
  buildComment,
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
