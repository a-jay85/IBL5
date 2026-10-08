import { describe, it, expect } from 'vitest';
import {
  VISUAL_REVIEW_MARKER,
  extractGalleryShas,
  linkedGallerySha,
  computeKeepList,
  selectRefreshTargets,
  renderSelectionTable,
  parseRefreshArgs,
  type OpenPr,
} from '../e2e/vr-refresh';

const DAY = 86400;
const NOW = 1_800_000_000;
const SHA_A = 'a'.repeat(40);
const SHA_B = 'b'.repeat(40);
const SHA_C = 'c'.repeat(40);
const SHA_D = 'd'.repeat(40);
const SHA_E = 'e'.repeat(40);

function galleryComment(sha: string): string {
  return `## 🖼️ Visual review\n\nhttps://a-jay85.github.io/IBL5/${sha}/visual-review/index.html\n${VISUAL_REVIEW_MARKER}`;
}

function pr(overrides: Partial<OpenPr> = {}): OpenPr {
  return {
    number: 1,
    headRefOid: SHA_E,
    baseRefName: 'master',
    isCrossRepository: false,
    isDraft: false,
    labels: [],
    body: '',
    comments: [galleryComment(SHA_A)],
    behindBy: 0,
    ...overrides,
  };
}

describe('extractGalleryShas', () => {
  it('extracts gallery shas from comment and body urls', () => {
    const text = `see https://a-jay85.github.io/IBL5/${SHA_A}/visual-review/x and https://a-jay85.github.io/IBL5/${SHA_B.toUpperCase()}/visual-review/y then ${SHA_A} again https://a-jay85.github.io/IBL5/${SHA_A}/visual-review/z`;
    expect(extractGalleryShas(text)).toEqual([SHA_A, SHA_B]);
    expect(extractGalleryShas(text)).toEqual([SHA_A, SHA_B]);
  });

  it('ignores non-40-hex and pr-keyed gallery paths', () => {
    // The 39- and 41-hex lines fail if the width in GALLERY_SHA_RE widens to {39,41}.
    const text = [
      `https://a-jay85.github.io/IBL5/${'a'.repeat(39)}/visual-review`,
      `https://a-jay85.github.io/IBL5/${'a'.repeat(41)}/visual-review`,
      'https://a-jay85.github.io/IBL5/pr-123/visual-review',
    ].join('\n');
    expect(extractGalleryShas(text)).toEqual([]);
  });
});

describe('linkedGallerySha', () => {
  it('linked sha comes from the last visual-review sticky comment', () => {
    const p = pr({ comments: [galleryComment(SHA_A), 'unrelated', galleryComment(SHA_B)] });
    expect(linkedGallerySha(p)).toBe(SHA_B);
  });

  it('banner-only visual-review comment is not visual', () => {
    const banner = `No visual changes.\n${VISUAL_REVIEW_MARKER}`;
    expect(linkedGallerySha(pr({ comments: [banner] }))).toBeNull();
    expect(linkedGallerySha(pr({ comments: [] }))).toBeNull();
  });
});

describe('computeKeepList', () => {
  it('keep list unions every comment body head sha across all prs', () => {
    const one = pr({
      number: 1,
      headRefOid: SHA_E,
      body: `https://a-jay85.github.io/IBL5/${SHA_D}/visual-review`,
      comments: [galleryComment(SHA_B), galleryComment(SHA_A)],
    });
    const two = pr({ number: 2, headRefOid: SHA_C, comments: ['someone else: ' + galleryComment(SHA_A)] });
    expect(computeKeepList([one, two])).toEqual([SHA_A, SHA_B, SHA_C, SHA_D, SHA_E]);
  });

  it('keep list includes drafts forks and non-master bases', () => {
    const list = computeKeepList([
      pr({ number: 1, isDraft: true, headRefOid: SHA_B, comments: [] }),
      pr({ number: 2, isCrossRepository: true, headRefOid: SHA_C, comments: [] }),
      pr({ number: 3, baseRefName: 'develop', headRefOid: SHA_D, comments: [] }),
    ]);
    expect(list).toEqual([SHA_B, SHA_C, SHA_D]);
  });

  it('keep list of no prs is empty', () => {
    expect(computeKeepList([])).toEqual([]);
  });
});

describe('selectRefreshTargets', () => {
  const stale = { kind: 'stale', staleDays: 7 } as const;

  it('refreshed at T is not selected at T+1d and is selected at T+8d', () => {
    const ts = { [SHA_A]: NOW };
    expect(selectRefreshTargets([pr()], ts, NOW + DAY, stale, 10).targets).toEqual([]);
    const later = selectRefreshTargets([pr()], ts, NOW + 8 * DAY, stale, 10);
    expect(later.targets).toEqual([{ pr: 1, head_sha: SHA_E, gallery_sha: SHA_A, age_days: 8 }]);
  });

  it('exactly stale-days old is fresh one second later is stale', () => {
    const ts = { [SHA_A]: NOW };
    const exact = selectRefreshTargets([pr()], ts, NOW + 7 * DAY, stale, 10);
    expect(exact.targets).toEqual([]);
    expect(exact.skipped).toEqual([{ pr: 1, reason: 'fresh' }]);
    expect(selectRefreshTargets([pr()], ts, NOW + 7 * DAY + 1, stale, 10).targets).toHaveLength(1);
  });

  it('missing gallery dir counts as stale with null age', () => {
    const missingKey = selectRefreshTargets([pr()], {}, NOW, stale, 10);
    expect(missingKey.targets[0]?.age_days).toBeNull();
    const nullTs = selectRefreshTargets([pr()], { [SHA_A]: null }, NOW, stale, 10);
    expect(nullTs.targets[0]?.age_days).toBeNull();
  });

  it('skips fork update-baselines draft and non-master prs', () => {
    const sel = selectRefreshTargets(
      [
        pr({ number: 1, isCrossRepository: true }),
        pr({ number: 2, labels: ['update-baselines'] }),
        pr({ number: 3, isDraft: true }),
        pr({ number: 4, baseRefName: 'develop' }),
        pr({ number: 5, comments: [] }),
      ],
      {},
      NOW,
      stale,
      10
    );
    expect(sel.targets).toEqual([]);
    expect(sel.skipped).toEqual([
      { pr: 1, reason: 'fork' },
      { pr: 2, reason: 'update-baselines-label' },
      { pr: 3, reason: 'draft' },
      { pr: 4, reason: 'not-master-base' },
      { pr: 5, reason: 'not-visual' },
    ]);
  });

  it('skips behind-master and behind-unknown prs', () => {
    const sel = selectRefreshTargets(
      [pr({ number: 1, behindBy: 3 }), pr({ number: 2, behindBy: null })],
      {},
      NOW,
      stale,
      10
    );
    expect(sel.targets).toEqual([]);
    expect(sel.skipped).toEqual([
      { pr: 1, reason: 'behind-master' },
      { pr: 2, reason: 'behind-unknown' },
    ]);
  });

  it('single mode selects a draft and reports not-open for unknown pr', () => {
    const draft = pr({ number: 7, isDraft: true });
    const other = pr({ number: 8 });
    const ts = { [SHA_A]: NOW };
    const hit = selectRefreshTargets([draft, other], ts, NOW + DAY, { kind: 'single', pr: 7 }, 10);
    expect(hit.targets.map((t) => t.pr)).toEqual([7]);
    expect(hit.skipped).toEqual([]);
    const miss = selectRefreshTargets([draft], ts, NOW, { kind: 'single', pr: 99 }, 10);
    expect(miss).toEqual({ targets: [], skipped: [{ pr: 99, reason: 'not-open' }] });
  });

  it('caps targets at max-prs stalest first', () => {
    const prs = [
      pr({ number: 1, comments: [galleryComment(SHA_A)] }),
      pr({ number: 2, comments: [galleryComment(SHA_B)] }),
      pr({ number: 3, comments: [galleryComment(SHA_C)] }),
      pr({ number: 4, comments: [galleryComment(SHA_D)] }),
    ];
    const ts = { [SHA_A]: NOW - 20 * DAY, [SHA_B]: NOW - 30 * DAY, [SHA_C]: null, [SHA_D]: NOW - 9 * DAY };
    const sel = selectRefreshTargets(prs, ts, NOW, stale, 2);
    expect(sel.targets.map((t) => t.pr)).toEqual([3, 2]);
    expect(sel.skipped).toEqual([
      { pr: 1, reason: 'over-cap' },
      { pr: 4, reason: 'over-cap' },
    ]);
  });
});

describe('renderSelectionTable', () => {
  it('renders a table row per target and skip', () => {
    const out = renderSelectionTable({
      targets: [
        { pr: 3, head_sha: SHA_E, gallery_sha: SHA_A, age_days: 12 },
        { pr: 4, head_sha: SHA_E, gallery_sha: SHA_B, age_days: null },
      ],
      skipped: [{ pr: 5, reason: 'draft' }],
    });
    expect(out.split('\n')).toEqual([
      '| PR | Decision | Reason / age |',
      '|----|----------|--------------|',
      '| #3 | refresh | 12d old |',
      '| #4 | refresh | gallery missing |',
      '| #5 | skip | draft |',
    ]);
    expect(renderSelectionTable({ targets: [], skipped: [] })).toBe('No open PRs considered.');
  });
});

describe('parseRefreshArgs', () => {
  it('parse rejects unknown flag space form and pr with all', () => {
    expect(parseRefreshArgs(['--bogus'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--pr', '5'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode', 'keep'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--pr=5', '--all'])).toHaveProperty('error');
    expect(parseRefreshArgs([])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=select'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--pr=0'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=keep', '--pr=5'])).toHaveProperty('error');
  });

  it('parse defaults stale days 7 and max prs 10', () => {
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x'])).toEqual({
      mode: 'select',
      pagesDir: '/x',
      select: { kind: 'stale', staleDays: 7 },
      maxPrs: 10,
    });
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--pr=42', '--max-prs=3'])).toEqual({
      mode: 'select',
      pagesDir: '/x',
      select: { kind: 'single', pr: 42 },
      maxPrs: 3,
    });
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--all'])).toMatchObject({ select: { kind: 'all' } });
    expect(parseRefreshArgs(['--mode=keep'])).toEqual({ mode: 'keep' });
    expect(parseRefreshArgs(['--help'])).toEqual({ mode: 'help' });
  });
});
