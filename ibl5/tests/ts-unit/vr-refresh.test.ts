import { readFileSync } from 'fs';
import { describe, it, expect } from 'vitest';
import {
  VISUAL_REVIEW_MARKER,
  MANUAL_ROW_MARKER,
  VR_BODY_BEGIN_MARKERS,
  VR_RELEVANT_PATHS,
  baseShaMarker,
  parseBaseSha,
  staleBannerLine,
  stackedNoteBody,
  isVrRelevantPath,
  classifyTidy,
  singleDispatchError,
  extractGalleryShas,
  extractGalleryDirs,
  linkedGalleryDir,
  computeKeepList,
  selectRefreshTargets,
  renderSelectionTable,
  parseRefreshArgs,
  type OpenPr,
} from '../e2e/vr-refresh';
import { AGENT_SHOTS_BEGIN, PR_BODY_MARKER_BEGIN } from '../e2e/vr-pr-body';

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

// Real sticky comment from PR #2943, which moved galleries to pr/<N>/visual-review/.
const PR2943_COMMENT = readFileSync(
  new URL('./fixtures/vr-refresh-pr2943-visual-review-comment.txt', import.meta.url),
  'utf-8'
);

function prGalleryComment(n: number): string {
  return `## Visual review\n\nhttps://a-jay85.github.io/IBL5/pr/${n}/visual-review/#cap-space\n${VISUAL_REVIEW_MARKER}`;
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

describe('extractGalleryDirs', () => {
  it('extracts sha and pr-keyed dirs in first-appearance order', () => {
    const text = [
      `https://a-jay85.github.io/IBL5/pr/2943/visual-review/#cap-space`,
      `https://a-jay85.github.io/IBL5/${SHA_A.toUpperCase()}/visual-review/index.html`,
      `https://a-jay85.github.io/IBL5/pr/2943/visual-review/#standings`,
      `https://a-jay85.github.io/IBL5/pr/7/visual-review`,
    ].join('\n');
    expect(extractGalleryDirs(text)).toEqual(['pr/2943/visual-review', SHA_A, 'pr/7/visual-review']);
  });

  it('rejects malformed pr-keyed and sha gallery paths', () => {
    const text = [
      'https://a-jay85.github.io/IBL5/pr/0/visual-review/',
      'https://a-jay85.github.io/IBL5/pr/007/visual-review/',
      'https://a-jay85.github.io/IBL5/pr/12abc/visual-review/',
      'https://a-jay85.github.io/IBL5/pr-123/visual-review/',
      'https://a-jay85.github.io/IBL5/pr//visual-review/',
      'https://a-jay85.github.io/IBL5/pr/5/other-review/',
      `https://a-jay85.github.io/IBL5/${'a'.repeat(39)}/visual-review/`,
      `https://a-jay85.github.io/IBL5/${'a'.repeat(41)}/visual-review/`,
    ].join('\n');
    expect(extractGalleryDirs(text)).toEqual([]);
  });

  it('pr-keyed fixture yields the pr 2943 gallery dir', () => {
    expect(extractGalleryDirs(PR2943_COMMENT)).toEqual(['pr/2943/visual-review']);
  });
});

describe('linkedGalleryDir', () => {
  it('linked sha comes from the last visual-review sticky comment', () => {
    const p = pr({ comments: [galleryComment(SHA_A), 'unrelated', galleryComment(SHA_B)] });
    expect(linkedGalleryDir(p)).toBe(SHA_B);
  });

  it('banner-only visual-review comment is not visual', () => {
    const banner = `No visual changes.\n${VISUAL_REVIEW_MARKER}`;
    expect(linkedGalleryDir(pr({ comments: [banner] }))).toBeNull();
    expect(linkedGalleryDir(pr({ comments: [] }))).toBeNull();
  });

  it('linked dir comes from the pr-keyed fixture', () => {
    expect(linkedGalleryDir(pr({ comments: [PR2943_COMMENT] }))).toBe('pr/2943/visual-review');
  });

  it('last sticky comment wins across link shapes', () => {
    expect(linkedGalleryDir(pr({ comments: [galleryComment(SHA_A), prGalleryComment(7)] }))).toBe('pr/7/visual-review');
    expect(linkedGalleryDir(pr({ comments: [prGalleryComment(7), galleryComment(SHA_A)] }))).toBe(SHA_A);
  });

  it('pr-keyed link outside the sticky comment is not visual', () => {
    const unmarked = prGalleryComment(7).replace(VISUAL_REVIEW_MARKER, '');
    expect(linkedGalleryDir(pr({ comments: [unmarked] }))).toBeNull();
  });
});

describe('pr-keyed gallery fixture (PR #2943)', () => {
  it('pr 2943 fixture carries the sticky marker and a pr-keyed gallery url', () => {
    expect(PR2943_COMMENT).toContain(VISUAL_REVIEW_MARKER);
    expect(PR2943_COMMENT).toMatch(/github\.io\/IBL5\/pr\/2943\/visual-review\//);
  });

  it('pr-keyed fixture yields no keep-list shas', () => {
    expect(extractGalleryShas(PR2943_COMMENT)).toEqual([]);
  });

  it('keep list for a pr-keyed-only pr is its head sha alone', () => {
    const p = pr({ number: 2943, headRefOid: SHA_E, comments: [PR2943_COMMENT] });
    expect(computeKeepList([p])).toEqual([SHA_E]);
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
    expect(later.targets).toEqual([{ pr: 1, head_sha: SHA_E, gallery_dir: SHA_A, age_days: 8 }]);
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

  it('pr-keyed gallery is aged by its dir and reported as gallery_dir', () => {
    const p = pr({ number: 2943, comments: [PR2943_COMMENT] });
    const ts = { 'pr/2943/visual-review': NOW };
    const fresh = selectRefreshTargets([p], ts, NOW + DAY, stale, 10);
    expect(fresh.skipped).toEqual([{ pr: 2943, reason: 'fresh' }]);
    const later = selectRefreshTargets([p], ts, NOW + 8 * DAY, stale, 10);
    expect(later.targets).toEqual([
      { pr: 2943, head_sha: SHA_E, gallery_dir: 'pr/2943/visual-review', age_days: 8 },
    ]);
  });
});

describe('renderSelectionTable', () => {
  it('renders a table row per target and skip', () => {
    const out = renderSelectionTable({
      targets: [
        { pr: 3, head_sha: SHA_E, gallery_dir: SHA_A, age_days: 12 },
        { pr: 4, head_sha: SHA_E, gallery_dir: SHA_B, age_days: null },
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

describe('classifyTidy', () => {
  it('tidy cleans a stacked PR that still shows a gallery', () => {
    const p = pr({ number: 5, baseRefName: 'feat-x' });
    expect(classifyTidy([p], SHA_E, {})).toEqual({
      actions: [{ pr: 5, kind: 'cleanup', base: 'feat-x', staleSha: null }],
      noops: [],
    });
  });

  it('tidy leaves a stacked PR already showing the note', () => {
    const p = pr({ baseRefName: 'feat-x', comments: [stackedNoteBody('feat-x')] });
    expect(classifyTidy([p], SHA_E, {})).toEqual({ actions: [], noops: [{ pr: 1, kind: 'cleanup' }] });
  });

  it('tidy never touches a fork PR', () => {
    const p = pr({ isCrossRepository: true, baseRefName: 'feat-x' });
    expect(classifyTidy([p], SHA_E, {})).toEqual({ actions: [], noops: [] });
  });

  it('tidy cleans a stacked PR with only a manual-row comment', () => {
    const p = pr({ number: 3, baseRefName: 'feat-x', comments: [`rows\n${MANUAL_ROW_MARKER}`] });
    const plan = classifyTidy([p], SHA_E, {});
    expect(plan.actions).toHaveLength(1);
    expect(plan.actions[0]).toMatchObject({ pr: 3, kind: 'cleanup', base: 'feat-x' });
  });

  it('tidy banners a gallery shot before a VR-relevant master change', () => {
    const p = pr({ comments: [`${baseShaMarker(SHA_A)}\n${galleryComment(SHA_B)}`] });
    const plan = classifyTidy([p], SHA_E, { [SHA_A]: true });
    expect(plan.actions).toEqual([{ pr: 1, kind: 'banner', base: 'master', staleSha: SHA_A }]);
    expect(plan.noops).toEqual([]);
  });

  it('tidy skips a gallery shot against the current master', () => {
    const p = pr({ comments: [`${baseShaMarker(SHA_E)}\n${galleryComment(SHA_B)}`] });
    expect(classifyTidy([p], SHA_E, {})).toEqual({ actions: [], noops: [] });
  });

  it('tidy skips a gallery when master changed only non-VR paths', () => {
    const p = pr({ comments: [`${baseShaMarker(SHA_A)}\n${galleryComment(SHA_B)}`] });
    expect(classifyTidy([p], SHA_E, { [SHA_A]: false })).toEqual({ actions: [], noops: [] });
  });

  it('tidy banners a legacy gallery without a base marker', () => {
    const plan = classifyTidy([pr()], SHA_E, {});
    expect(plan.actions).toEqual([{ pr: 1, kind: 'banner', base: 'master', staleSha: null }]);
    expect(plan.noops).toEqual([]);
  });

  it('tidy leaves an already-bannered gallery alone', () => {
    const p = pr({
      body: '',
      comments: [`${staleBannerLine()}\n\n${baseShaMarker(SHA_A)}\n${galleryComment(SHA_B)}`],
    });
    expect(classifyTidy([p], SHA_E, { [SHA_A]: true })).toEqual({
      actions: [],
      noops: [{ pr: 1, kind: 'banner' }],
    });
  });
});

describe('VR path filter', () => {
  it('isVrRelevantPath matches the e2e push filter and rejects near misses', () => {
    for (const p of [
      'ibl5/classes/Foo.php',
      'docker-compose.ci.yml',
      'ibl5/migrations/sub/001_x.sql',
      'ibl5/design/a/b.png',
      'ibl5/tests/e2e/foo.ts',
    ]) {
      expect(isVrRelevantPath(p), p).toBe(true);
    }
    for (const p of [
      'ibl5/docs/x.md',
      'bin/vr-refresh-targets',
      'docker-compose/x.yml',
      'ibl5/migrations/README.md',
      'Dockerfile.bak',
      'ibl5/tests/e2e-old/x.md',
    ]) {
      expect(isVrRelevantPath(p), p).toBe(false);
    }
  });

  it('VR_RELEVANT_PATHS equals the e2e-tests push paths', () => {
    const lines = readFileSync(new URL('../../../.github/workflows/e2e-tests.yml', import.meta.url), 'utf-8').split('\n');
    const start = lines.findIndex((l) => l === '  push:');
    expect(start).toBeGreaterThanOrEqual(0);
    const globs: string[] = [];
    for (const line of lines.slice(start + 1)) {
      if (/^ {0,2}\S/.test(line)) break;
      const m = /^\s*-\s+(.+?)\s*$/.exec(line);
      if (m !== null) globs.push((m[1] as string).replace(/^(['"])(.*)\1$/, '$2'));
    }
    expect(globs.length).toBeGreaterThan(0);
    expect(globs).toEqual([...VR_RELEVANT_PATHS]);
  });
});

describe('parseBaseSha', () => {
  it('parseBaseSha rejects short and uppercase markers', () => {
    expect(parseBaseSha(`<!-- vr-base-sha: ${'a'.repeat(39)} -->`)).toBeNull();
    expect(parseBaseSha(`<!-- vr-base-sha: ${'A'.repeat(40)} -->`)).toBeNull();
    expect(parseBaseSha(`<!-- vr-base-sha: ${SHA_A} -->`)).toBe(SHA_A);
  });
});

describe('stacked note refresh', () => {
  it('a master PR showing the stacked note is a refresh target', () => {
    const sel = selectRefreshTargets(
      [pr({ number: 9, comments: [stackedNoteBody('feat-x')] })],
      {},
      NOW,
      { kind: 'stale', staleDays: 7 },
      10
    );
    expect(sel.targets[0]).toMatchObject({ pr: 9, gallery_dir: 'pr/9/visual-review', age_days: null });
  });
});

describe('singleDispatchError', () => {
  const skippedSel = { targets: [], skipped: [{ pr: 7, reason: 'not-master-base' as const }] };

  it('single dispatch error names the skip reason', () => {
    const err = singleDispatchError({ kind: 'single', pr: 7 }, skippedSel, { actions: [], noops: [] });
    expect(typeof err).toBe('string');
    expect(err).toContain('not-master-base');
    expect(err).toContain('PR #7');
  });

  it('single dispatch with a tidy action is not an error', () => {
    const tidy = { actions: [{ pr: 7, kind: 'cleanup' as const, base: 'feat-x', staleSha: null }], noops: [] };
    expect(singleDispatchError({ kind: 'single', pr: 7 }, skippedSel, tidy)).toBeNull();
  });

  it('non-single modes never raise a dispatch error', () => {
    const tidy = { actions: [], noops: [] };
    expect(singleDispatchError({ kind: 'stale', staleDays: 7 }, skippedSel, tidy)).toBeNull();
    expect(singleDispatchError({ kind: 'all' }, skippedSel, tidy)).toBeNull();
  });
});

describe('parseRefreshArgs tidy', () => {
  it('parseRefreshArgs accepts tidy with pr and dry-run', () => {
    expect(parseRefreshArgs(['--mode=tidy', '--pr=5', '--dry-run'])).toEqual({ mode: 'tidy', pr: 5, dryRun: true });
    expect(parseRefreshArgs(['--mode=tidy'])).toEqual({ mode: 'tidy', pr: null, dryRun: false });
  });

  it('parseRefreshArgs rejects dry-run outside tidy', () => {
    expect(parseRefreshArgs(['--mode=select', '--pages-dir=/x', '--dry-run'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=keep', '--dry-run'])).toHaveProperty('error');
    expect(parseRefreshArgs(['--mode=tidy', '--all', '--dry-run'])).toHaveProperty('error');
  });
});

describe('vr-pr-body marker drift', () => {
  it('VR_BODY_BEGIN_MARKERS match vr-pr-body markers', () => {
    expect([...VR_BODY_BEGIN_MARKERS]).toEqual([PR_BODY_MARKER_BEGIN, AGENT_SHOTS_BEGIN]);
  });
});
