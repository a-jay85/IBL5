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
export const MANUAL_ROW_MARKER = '<!-- Sticky Pull Request Commentmanual-row-screenshots -->';
export const BASE_SHA_MARKER_RE = /<!-- vr-base-sha: ([0-9a-f]{40}) -->/;
export const STALE_BANNER_MARKER = '<!-- vr-stale-banner -->';
export const STALE_BANNER_TEXT = 'Taken against an older master. Push or run VR Refresh to update.';
export const STACKED_NOTE_PREFIX = 'Screenshots removed while this PR is stacked on';
// Begin markers of the two PR-body VR blocks. vr-pr-body.ts owns them; a ts-unit drift test pins equality.
export const VR_BODY_BEGIN_MARKERS = ['<!-- vr-new-screens:begin -->', '<!-- vr-agent-shots:begin -->'] as const;
export const COMPARE_FILES_CAP = 300;
// Copy of the push `paths:` list in .github/workflows/e2e-tests.yml; a ts-unit drift test pins equality.
export const VR_RELEVANT_PATHS = [
  '**.php',
  '**.ts',
  '**.js',
  '**.css',
  'ibl5/design/**',
  'ibl5/migrations/**.sql',
  'Dockerfile',
  'docker-compose*.yml',
  'docker/**',
  'ibl5/tests/e2e/**',
  '.github/workflows/e2e-tests.yml',
  '.github/actions/setup-docker-e2e/**',
  '.github/scripts/**',
] as const;

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
export type TidyKind = 'cleanup' | 'banner';
export interface TidyAction {
  pr: number;
  kind: TidyKind;
  base: string; // the PR's base ref; 'master' for a banner
  staleSha: string | null; // banner only: the base-sha marker the comment carried, null = none
}
export interface TidyPlan {
  actions: TidyAction[];
  noops: { pr: number; kind: TidyKind }[]; // already in the target state
}

const SECONDS_PER_DAY = 86400;
const SHA_40_RE = /^[0-9a-f]{40}$/;

export function baseShaMarker(sha: string): string {
  return `<!-- vr-base-sha: ${sha.toLowerCase()} -->`;
}
export function parseBaseSha(text: string): string | null {
  const m = BASE_SHA_MARKER_RE.exec(text);
  return m === null ? null : (m[1] as string);
}
export function staleBannerLine(): string {
  return `> ⚠️ ${STALE_BANNER_TEXT} ${STALE_BANNER_MARKER}`;
}
export function normalizeComment(text: string): string {
  return text.replace(/\r\n/g, '\n').trim();
}
// The whole visual-review comment body for a stacked PR: one visible line, plus the
// marocchino header marker so the next E2E sticky post overwrites this same comment.
export function stackedNoteBody(base: string): string {
  return `${STACKED_NOTE_PREFIX} \`${base.replace(/`/g, '')}\`. They come back when it targets master.\n${VISUAL_REVIEW_MARKER}`;
}
export function isStackedNote(comment: string): boolean {
  return comment.includes(STACKED_NOTE_PREFIX);
}

function globToRegExp(glob: string): RegExp {
  let re = '';
  for (let i = 0; i < glob.length; i++) {
    const ch = glob[i] as string;
    if (ch === '*' && glob[i + 1] === '*') {
      re += '.*';
      i++;
    } else if (ch === '*') re += '[^/]*';
    else re += ch.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
  }
  return new RegExp(`^${re}$`);
}
// GitHub path-filter semantics: later patterns win, a leading ! excludes.
export function isVrRelevantPath(path: string): boolean {
  let matched = false;
  for (const glob of VR_RELEVANT_PATHS as readonly string[]) {
    if (glob.startsWith('!')) {
      if (globToRegExp(glob.slice(1)).test(path)) matched = false;
    } else if (globToRegExp(glob).test(path)) matched = true;
  }
  return matched;
}
// compare's files array stops at 300 entries; a capped list counts as relevant.
export function masterAdvancedInVrPaths(files: string[], totalFiles: number): boolean {
  return totalFiles >= COMPARE_FILES_CAP || files.some(isVrRelevantPath);
}

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

// A master-based PR still showing the stacked note (its parent merged and GitHub
// retargeted it) is refreshed like a gallery of unknown age. Such a PR is usually
// behind master after the squash-merge, so selection skips it as `behind-master`
// until a push brings it level; the refresh does not happen on its own.
export function refreshGalleryDir(pr: OpenPr): string | null {
  const linked = linkedGalleryDir(pr);
  if (linked !== null) return linked;
  const marked = pr.comments.filter((c) => c.includes(VISUAL_REVIEW_MARKER));
  const last = marked[marked.length - 1];
  return last !== undefined && isStackedNote(last) ? `pr/${pr.number}/visual-review` : null;
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

export function classifyTidy(prs: OpenPr[], masterSha: string, advanced: Record<string, boolean>): TidyPlan {
  const plan: TidyPlan = { actions: [], noops: [] };
  const master = masterSha.toLowerCase();
  for (const pr of prs) {
    if (pr.isCrossRepository) continue;
    const visual = pr.comments.filter((c) => c.includes(VISUAL_REVIEW_MARKER));
    if (pr.baseRefName !== 'master') {
      const dirty =
        visual.some((c) => normalizeComment(c) !== stackedNoteBody(pr.baseRefName)) ||
        pr.comments.some((c) => c.includes(MANUAL_ROW_MARKER)) ||
        VR_BODY_BEGIN_MARKERS.some((m) => pr.body.includes(m));
      if (dirty) plan.actions.push({ pr: pr.number, kind: 'cleanup', base: pr.baseRefName, staleSha: null });
      else if (visual.length > 0) plan.noops.push({ pr: pr.number, kind: 'cleanup' });
      continue;
    }
    const last = visual[visual.length - 1];
    if (last === undefined || isStackedNote(last)) continue;
    // A no-diff comment links no gallery. VR Refresh skips it as not-visual, so a banner could never clear.
    if (linkedGalleryDir(pr) === null) continue;
    const sha = parseBaseSha(last);
    const stale = sha === null || (sha !== master && advanced[sha] !== false);
    if (!stale) continue;
    const bodyNeedsBanner = pr.body.includes(VR_BODY_BEGIN_MARKERS[0]) && !pr.body.includes(STALE_BANNER_MARKER);
    if (!last.includes(STALE_BANNER_MARKER) || bodyNeedsBanner) {
      plan.actions.push({ pr: pr.number, kind: 'banner', base: 'master', staleSha: sha });
    } else {
      plan.noops.push({ pr: pr.number, kind: 'banner' });
    }
  }
  return plan;
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
    const galleryDir = refreshGalleryDir(pr);
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
    const ts = linkedGalleryDir(pr) === null ? null : (galleryCommitTs[galleryDir] ?? null);
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

export const SKIP_REASON_TEXT: Record<SkipReason, string> = {
  'not-open': 'the PR is not open',
  'not-master-base': 'its base is not master and it has no screenshots to remove',
  fork: 'fork PRs are never refreshed',
  'update-baselines-label': 'the update-baselines label is set, so the baseline regen run owns its gallery',
  draft: 'the PR is a draft',
  'not-visual': 'no visual-review comment links a gallery',
  'behind-master': 'the head is behind master; push a merge or rebase first',
  'behind-unknown': 'the compare against master failed',
  fresh: 'the gallery is fresh',
  'over-cap': 'it is over the per-run PR cap',
};
// Non-null = the select job must fail red with this text.
export function singleDispatchError(mode: SelectMode, sel: Selection, tidy: TidyPlan): string | null {
  if (mode.kind !== 'single') return null;
  const n = mode.pr;
  if (sel.targets.some((t) => t.pr === n)) return null;
  // Only a cleanup (stacked PR, nothing to refresh) excuses the skip. A banner means the PR still has a stale gallery.
  if (
    tidy.actions.some((a) => a.pr === n && a.kind === 'cleanup') ||
    tidy.noops.some((a) => a.pr === n && a.kind === 'cleanup')
  ) {
    return null;
  }
  const reason: SkipReason = sel.skipped.find((s) => s.pr === n)?.reason ?? 'not-open';
  if (reason === 'fresh') return null;
  return `PR #${n} was not refreshed: ${reason} (${SKIP_REASON_TEXT[reason]})`;
}
export function renderTidyTable(plan: TidyPlan): string {
  const lines = ['#### Stale-screenshot tidy', '', '| PR | Action |', '|---|---|'];
  for (const a of plan.actions) lines.push(`| #${a.pr} | ${a.kind} |`);
  for (const n of plan.noops) lines.push(`| #${n.pr} | ${n.kind} (already applied) |`);
  if (plan.actions.length + plan.noops.length === 0) lines.push('| none | |');
  return lines.join('\n');
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
  | { mode: 'tidy'; pr: number | null; dryRun: boolean }
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
  let dryRun = false;

  for (const arg of argv) {
    if (arg === '--help' || arg === '-h') return { mode: 'help' };
    if (arg === '--all') {
      all = true;
      continue;
    }
    if (arg === '--dry-run') {
      dryRun = true;
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

  if (mode === null) return { error: '--mode=select|keep|tidy is required' };
  if (mode !== 'select' && mode !== 'keep' && mode !== 'tidy') {
    return { error: `--mode must be select, keep or tidy, got: ${mode}` };
  }
  if (dryRun && mode !== 'tidy') return { error: '--dry-run is only valid with --mode=tidy' };
  if (all && mode === 'tidy') return { error: '--all is not valid with --mode=tidy' };
  if (mode === 'tidy') {
    if (pagesDir !== null || staleDays !== null || maxPrs !== null) {
      return { error: '--mode=tidy takes only --pr=N and --dry-run' };
    }
    if (pr !== null && !POSITIVE_INT_RE.test(pr)) return { error: `--pr must be a positive integer, got: ${pr}` };
    return { mode: 'tidy', pr: pr === null ? null : Number(pr), dryRun };
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
