import { describe, it, expect } from 'vitest';
import { execFileSync } from 'child_process';
import { writeFileSync } from 'fs';
import { tmpdir } from 'os';
import { resolve } from 'path';
import type { VrRow } from '../e2e/vr-manifest';
import {
  buildComment,
  classifyCells,
  extractCells,
  extractDiffCells,
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

  it('renders the global-change banner when globalChange is true AND there is pixel work', () => {
    // The banner is gated behind pixel work (ADR-0073): a global change with no
    // pixel diffs has nothing to review, so the banner only renders alongside diff cells.
    const md = buildComment({
      diffCells: [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
      uncoveredChangedPaths: [],
      globalChange: true,
      pagesUrl: PAGES_URL,
    });
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

describe('extractCells / infra-vs-pixel classification', () => {
  const infraSpec = {
    title: 'draft-history',
    ok: false,
    tests: [
      {
        results: [
          {
            status: 'failed',
            attachments: [{ name: 'error-context', contentType: 'text/markdown' }],
            error: { message: 'TimeoutError: page.goto exceeded\nat captureSnapshot' },
          },
        ],
      },
    ],
  };
  const pixelSpec = {
    title: 'standings',
    ok: false,
    tests: [
      {
        results: [
          {
            status: 'failed',
            attachments: [
              { name: 'standings-expected.png', contentType: 'image/png' },
              { name: 'standings-actual.png', contentType: 'image/png' },
              { name: 'standings-diff.png', contentType: 'image/png' },
            ],
          },
        ],
      },
    ],
  };
  const legacySpec = { title: 'player-movement', ok: false };

  const MANIFEST: VrRow[] = [
    { name: 'standings', auth: 'public', url: 'modules.php?name=Standings', anchor: '.t' },
    { name: 'player-movement', auth: 'public', url: 'modules.php?name=PlayerMovement', anchor: '.t' },
    { name: 'draft-history', auth: 'public', url: 'modules.php?name=DraftHistory', anchor: '.t' },
  ];

  it('row 2 (NEG infra): a failed spec with only an error-context attachment classifies as infra, not diff', () => {
    const report = { suites: [{ specs: [infraSpec] }] };
    const { diffCells, infraCells } = extractCells(report, MANIFEST);
    expect(diffCells).toHaveLength(0);
    expect(infraCells).toHaveLength(1);
    expect(infraCells[0]).toMatchObject({ module: 'draft-history', viewport: 'desktop', title: 'draft-history' });
    expect(infraCells[0].error).toContain('TimeoutError');
  });

  it('row 3: a failed spec carrying <title>-diff.png classifies as a pixel diff, not infra', () => {
    const report = { suites: [{ specs: [pixelSpec] }] };
    const { diffCells, infraCells } = extractCells(report, MANIFEST);
    expect(infraCells).toHaveLength(0);
    expect(diffCells).toHaveLength(1);
    expect(diffCells[0]).toMatchObject({ module: 'standings', viewport: 'desktop', title: 'standings' });
  });

  it('row 4 (back-compat): a legacy {title, ok:false} spec with no tests classifies as a pixel diff; infraCells empty', () => {
    const report = { suites: [{ specs: [legacySpec] }] };
    const { diffCells, infraCells } = extractCells(report, MANIFEST);
    expect(infraCells).toHaveLength(0);
    expect(diffCells).toHaveLength(1);
    expect(diffCells[0]).toMatchObject({ module: 'player-movement', title: 'player-movement' });
  });

  it('mixed: pixel + infra + legacy split correctly', () => {
    const report = { suites: [{ specs: [infraSpec, pixelSpec, legacySpec] }] };
    const { diffCells, infraCells } = extractCells(report, MANIFEST);
    expect(infraCells.map((c) => c.title)).toEqual(['draft-history']);
    expect(diffCells.map((c) => c.title).sort()).toEqual(['player-movement', 'standings']);
  });

  it('extractDiffCells back-compat wrapper returns only diffCells', () => {
    const report = { suites: [{ specs: [infraSpec, pixelSpec] }] };
    const cells = extractDiffCells(report, MANIFEST);
    expect(cells.map((c) => c.title)).toEqual(['standings']);
  });
});

describe('buildComment — infra-vs-pixel gating', () => {
  const oneInfra: InfraCell = {
    module: 'draft-history',
    viewport: 'desktop',
    title: 'draft-history',
    error: 'TimeoutError: page.goto exceeded',
  };
  const oneDiff: DiffCell = { module: 'standings', viewport: 'desktop', title: 'standings', isNew: false };

  it('row 5 (NEG suppression): infra-only with globalChange:true omits pixel headline/banner/update-baselines, includes failed-to-render + title', () => {
    const md = buildComment({
      diffCells: [],
      infraCells: [oneInfra],
      uncoveredChangedPaths: [],
      globalChange: true,
      pagesUrl: PAGES_URL,
    });
    expect(md).not.toContain('This PR changed pixels');
    expect(md).not.toContain('Global change detected');
    expect(md).not.toContain('update-baselines');
    expect(md).toContain('failed to render');
    expect(md).toContain('draft-history');
    expect(md).not.toContain('No visual diffs detected');
  });

  it('row 6 (mixed): 1 pixel diff + 1 infra contains BOTH the changed-pixels headline AND the failed-to-render section', () => {
    const md = buildComment({
      diffCells: [oneDiff],
      infraCells: [oneInfra],
      uncoveredChangedPaths: [],
      globalChange: false,
      pagesUrl: PAGES_URL,
    });
    expect(md).toContain('This PR changed pixels');
    expect(md).toContain('failed to render');
    expect(md).toContain('draft-history');
    expect(md).toContain('standings');
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

  it('row 7 (CLI infra-only): an infra-only report produces the "failed to render" comment, not "changed pixels"', () => {
    const repoRoot = resolve(__dirname, '../../..');
    const tmpResults = `${tmpdir()}/vr-review-comment-infra-${Date.now()}.json`;
    const fakeReport = {
      suites: [
        {
          specs: [],
          suites: [
            {
              specs: [
                {
                  title: 'standings',
                  ok: false,
                  tests: [
                    {
                      results: [
                        {
                          status: 'failed',
                          attachments: [{ name: 'error-context', contentType: 'text/markdown' }],
                          error: { message: 'TimeoutError: page.goto exceeded' },
                        },
                      ],
                    },
                  ],
                },
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

    expect(stdout).toContain('failed to render');
    expect(stdout).toContain('standings');
    expect(stdout).not.toContain('This PR changed pixels');
    expect(stdout).not.toContain('update-baselines');
  });
});
