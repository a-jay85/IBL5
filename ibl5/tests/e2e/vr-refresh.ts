// Pure decisions for the VR stale-gallery refresh and the gh-pages keep-list.
// No I/O and no imports on purpose: the vr-pages-cleanup job runs this from a
// sparse checkout with no node_modules. bin/vr-refresh-targets does the gh/git
// glue; tests/ts-unit/vr-refresh.test.ts covers this module.
export const GALLERY_SHA_RE = /github\.io\/IBL5\/([0-9a-f]{40})\/visual-review/g;
// A gallery link of either shape: legacy `<sha>/visual-review` or PR-keyed
// `pr/<N>/visual-review` (N a positive integer, no leading zero).
export const GALLERY_DIR_RE = /github\.io\/IBL5\/(?:([0-9a-f]{40})|pr\/([1-9][0-9]*))\/visual-review/g;
export const VISUAL_REVIEW_MARKER = '<!-- Sticky Pull Request Commentvisual-review -->';
export const DEFAULT_STALE_DAYS = 7;
export const DEFAULT_MAX_PRS = 10;

export interface OpenPr {
  number: number;
  headRefOid: string;
  baseRefName: string;
  isCrossRepository: boolean;
  isDraft: boolean;
  labels: string[]; // label names
  body: string; // PR body ('' when null)
  comments: string[]; // EVERY issue-comment body on the PR, any author, API order
  behindBy: number | null; // compare(master...head).behind_by; null = lookup failed/not fetched
}
export type SelectMode =
  | { kind: 'stale'; staleDays: number } // schedule
  | { kind: 'all' } // dispatch, blank pr input
  | { kind: 'single'; pr: number }; // dispatch, pr input set
export type SkipReason =
  | 'not-open'
  | 'not-master-base'
  | 'fork'
  | 'update-baselines-label'
  | 'draft'
  | 'not-visual'
  | 'behind-master'
  | 'behind-unknown'
  | 'fresh'
  | 'over-cap';
export interface RefreshTarget {
  pr: number;
  head_sha: string;
  gallery_dir: string; // gh-pages-relative: `<sha>` or `pr/<N>/visual-review`
  age_days: number | null;
}
export interface Selection {
  targets: RefreshTarget[];
  skipped: { pr: number; reason: SkipReason }[];
}

const SECONDS_PER_DAY = 86400;
const SHA_40_RE = /^[0-9a-f]{40}$/;

// Every gallery-URL SHA in text: lowercased, de-duplicated, first-appearance order.
export function extractGalleryShas(text: string): string[] {
  const seen = new Set<string>();
  for (const m of text.matchAll(new RegExp(GALLERY_SHA_RE.source, 'gi'))) {
    seen.add((m[1] as string).toLowerCase());
  }
  return [...seen];
}

// Every gallery dir linked in text, relative to the gh-pages root: a lowercased
// `<sha>` or `pr/<N>/visual-review`. De-duplicated, first-appearance order.
// Not for the keep-list: prune-vr-galleries accepts 40-hex lines only.
export function extractGalleryDirs(text: string): string[] {
  const seen = new Set<string>();
  for (const m of text.matchAll(new RegExp(GALLERY_DIR_RE.source, 'gi'))) {
    const sha = m[1];
    seen.add(sha !== undefined ? sha.toLowerCase() : `pr/${m[2] as string}/visual-review`);
  }
  return [...seen];
}

// The gallery dir the PR's latest visual-review sticky comment links to, or null.
export function linkedGalleryDir(pr: OpenPr): string | null {
  const marked = pr.comments.filter((c) => c.includes(VISUAL_REVIEW_MARKER));
  const last = marked[marked.length - 1];
  if (last === undefined) return null;
  return extractGalleryDirs(last)[0] ?? null;
}

// Every gallery SHA any open PR still links, plus every head SHA. No base,
// draft, fork, or label filter: over-keeping costs disk, under-keeping breaks images.
export function computeKeepList(prs: OpenPr[]): string[] {
  const keep = new Set<string>();
  for (const pr of prs) {
    for (const sha of extractGalleryShas(pr.body)) keep.add(sha);
    for (const c of pr.comments) {
      for (const sha of extractGalleryShas(c)) keep.add(sha);
    }
    const head = pr.headRefOid.toLowerCase();
    if (SHA_40_RE.test(head)) keep.add(head);
  }
  return [...keep].sort();
}

export function selectRefreshTargets(
  prs: OpenPr[],
  galleryCommitTs: Record<string, number | null>,
  nowSec: number,
  mode: SelectMode,
  maxPrs: number
): Selection {
  const skipped: Selection['skipped'] = [];
  const survivors: RefreshTarget[] = [];

  let considered = prs;
  if (mode.kind === 'single') {
    considered = prs.filter((p) => p.number === mode.pr);
    if (considered.length === 0) {
      return { targets: [], skipped: [{ pr: mode.pr, reason: 'not-open' }] };
    }
  }

  for (const pr of considered) {
    const skip = (reason: SkipReason): void => {
      skipped.push({ pr: pr.number, reason });
    };
    if (pr.baseRefName !== 'master') {
      skip('not-master-base');
      continue;
    }
    if (pr.isCrossRepository) {
      skip('fork');
      continue;
    }
    if (pr.labels.includes('update-baselines')) {
      skip('update-baselines-label');
      continue;
    }
    if (pr.isDraft && mode.kind !== 'single') {
      skip('draft');
      continue;
    }
    const galleryDir = linkedGalleryDir(pr);
    if (galleryDir === null) {
      skip('not-visual');
      continue;
    }
    if (pr.behindBy === null) {
      skip('behind-unknown');
      continue;
    }
    if (pr.behindBy > 0) {
      skip('behind-master');
      continue;
    }
    const ts = galleryCommitTs[galleryDir] ?? null;
    const ageDays = ts === null ? null : (nowSec - ts) / SECONDS_PER_DAY;
    if (mode.kind === 'stale' && ageDays !== null && !(ageDays > mode.staleDays)) {
      skip('fresh');
      continue;
    }
    survivors.push({
      pr: pr.number,
      head_sha: pr.headRefOid,
      gallery_dir: galleryDir,
      age_days: ageDays === null ? null : Math.floor(ageDays),
    });
  }

  // Stalest first: unknown age (dir gone) first, then older, then lower PR number.
  // Compare the floored ages the targets carry, then fall back to PR number.
  survivors.sort((a, b) => {
    if (a.age_days === null && b.age_days !== null) return -1;
    if (a.age_days !== null && b.age_days === null) return 1;
    if (a.age_days !== null && b.age_days !== null && a.age_days !== b.age_days) {
      return b.age_days - a.age_days;
    }
    return a.pr - b.pr;
  });

  const targets = survivors.slice(0, maxPrs);
  for (const over of survivors.slice(maxPrs)) {
    skipped.push({ pr: over.pr, reason: 'over-cap' });
  }
  return { targets, skipped };
}

export function renderSelectionTable(sel: Selection): string {
  if (sel.targets.length === 0 && sel.skipped.length === 0) {
    return 'No open PRs considered.';
  }
  const rows = ['| PR | Decision | Reason / age |', '|----|----------|--------------|'];
  for (const t of sel.targets) {
    const age = t.age_days === null ? 'gallery missing' : `${t.age_days}d old`;
    rows.push(`| #${t.pr} | refresh | ${age} |`);
  }
  for (const s of sel.skipped) {
    rows.push(`| #${s.pr} | skip | ${s.reason} |`);
  }
  return rows.join('\n');
}

export type CliOptions =
  | { mode: 'select'; pagesDir: string; select: SelectMode; maxPrs: number }
  | { mode: 'keep' }
  | { mode: 'help' };

const POSITIVE_INT_RE = /^[1-9][0-9]*$/;

// Flags take the `=` form only (`--pr=5`, never `--pr 5`).
export function parseRefreshArgs(argv: string[]): CliOptions | { error: string } {
  let mode: string | null = null;
  let pagesDir: string | null = null;
  let pr: string | null = null;
  let all = false;
  let staleDays: string | null = null;
  let maxPrs: string | null = null;

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') return { mode: 'help' };
    if (arg === '--all') {
      all = true;
      continue;
    }
    const eq = arg.indexOf('=');
    const name = eq === -1 ? arg : arg.slice(0, eq);
    const value = eq === -1 ? null : arg.slice(eq + 1);
    if (name === '--mode' || name === '--pages-dir' || name === '--pr' || name === '--stale-days' || name === '--max-prs') {
      if (value === null || value === '') {
        return { error: `${name} needs a value in the ${name}=VALUE form` };
      }
      if (name === '--mode') mode = value;
      else if (name === '--pages-dir') pagesDir = value;
      else if (name === '--pr') pr = value;
      else if (name === '--stale-days') staleDays = value;
      else maxPrs = value;
      continue;
    }
    return { error: `unknown argument: ${arg}` };
  }

  if (mode === null) return { error: '--mode=select|keep is required' };
  if (mode !== 'select' && mode !== 'keep') {
    return { error: `--mode must be select or keep, got: ${mode}` };
  }
  if (mode === 'keep') {
    if (pagesDir !== null || pr !== null || all || staleDays !== null || maxPrs !== null) {
      return { error: '--mode=keep takes no other flags' };
    }
    return { mode: 'keep' };
  }

  if (pagesDir === null) return { error: '--mode=select requires --pages-dir=PATH' };
  if (pr !== null && all) return { error: '--pr and --all cannot be combined' };
  for (const [flag, v] of [['--pr', pr], ['--stale-days', staleDays], ['--max-prs', maxPrs]] as const) {
    if (v !== null && !POSITIVE_INT_RE.test(v)) {
      return { error: `${flag} must be a positive integer, got: ${v}` };
    }
  }

  let select: SelectMode;
  if (pr !== null) select = { kind: 'single', pr: Number(pr) };
  else if (all) select = { kind: 'all' };
  else select = { kind: 'stale', staleDays: staleDays === null ? DEFAULT_STALE_DAYS : Number(staleDays) };

  return {
    mode: 'select',
    pagesDir,
    select,
    maxPrs: maxPrs === null ? DEFAULT_MAX_PRS : Number(maxPrs),
  };
}
