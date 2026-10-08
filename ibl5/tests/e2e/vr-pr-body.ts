// Pure builder for the top-of-PR-body "new VR screens" section. No I/O — the
// bin/vr-review-comment wrapper reads the change-driven gallery JSON
// (bin/vr-build-gallery output) and calls these functions, plus does the
// fs copy + `gh pr edit` glue (tests/ts-unit/vr-pr-body.test.ts covers this
// module; the CLI-executable checks in the plan cover the glue).
//
// Sibling of vr-review-comment.ts, same no-I/O contract. Reuses its
// base-URL normalization + encodeURIComponent pattern (reportLink) and its
// module-grouping / localeCompare / title-sort pattern (renderModuleSection).
import type { Viewport } from './vr-manifest';

export type LeanCell = { module: string; viewport: Viewport; title: string };

export const PR_BODY_MARKER_BEGIN = '<!-- vr-new-screens:begin -->';
export const PR_BODY_MARKER_END = '<!-- vr-new-screens:end -->';

export const AGENT_SHOTS_BEGIN = '<!-- vr-agent-shots:begin -->';
export const AGENT_SHOTS_END = '<!-- vr-agent-shots:end -->';

// Leaves ~25K of GitHub's 65,536-char PR-body limit for the human body.
export const MAX_CHANGED_SPOTS = 40;
export const MAX_BLOCK_CHARS = 40000;

// One changed region (or an uncropped full-page fallback) of a changed cell.
// This is the shape written to changed/spots.json; beforeFile/afterFile are
// paths relative to the Pages dir (changed/<title>.<index>.before.png).
export interface ChangedSpot {
  module: string;
  viewport: Viewport;
  title: string;
  index: number;
  count: number;
  kind: 'spot' | 'uncropped';
  reason?: 'dimensions-changed' | 'no-pixel-mask';
  region: string;
  beforeFile: string;
  afterFile: string;
}

// Lowercase, collapse runs outside [a-z0-9] to '-', trim '-', cut to 60; empty => null.
export function sanitizeLabel(raw: string): string | null {
  const cleaned = raw
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 60)
    .replace(/-+$/, '');
  return cleaned === '' ? null : cleaned;
}

export function formatStamp(headSha: string, runTime: Date): string {
  const sha = /^[0-9a-f]{7,40}$/.test(headSha) ? headSha.slice(0, 7) : 'unknown';
  const when = runTime.toISOString().slice(0, 16).replace('T', ' ');
  return `_VR screens for head \`${sha}\` at ${when} UTC._`;
}

// Returns the agent-shot sub-block (markers inclusive) from the first managed block, wherever it sits; '' otherwise. Text outside that block is never read.
export function extractAgentShots(body: string): string {
  const [b] = findManagedBlocks(body);
  if (b === undefined) return '';
  const block = body.slice(b.start, b.end);
  const blockEnd = block.length;
  const begin = block.indexOf(AGENT_SHOTS_BEGIN);
  if (begin === -1 || begin >= blockEnd) return '';
  const end = block.indexOf(AGENT_SHOTS_END, begin + AGENT_SHOTS_BEGIN.length);
  if (end === -1 || end + AGENT_SHOTS_END.length > blockEnd) return '';
  return block.slice(begin, end + AGENT_SHOTS_END.length);
}

const AGENT_SHOT_ENTRY_END = '<!-- /vr-agent-shot -->';
const AGENT_SHOT_ENTRY_RE = /<!-- vr-agent-shot:([a-z0-9-]+) -->/g;

// Agent shots live under the PR head commit's per-SHA tree, so
// bin/prune-vr-galleries ages them with the rest of that gallery.
export function agentShotUrl(sha: string, label: string, side: 'before' | 'after'): string {
  return `https://a-jay85.github.io/IBL5/${sha}/visual-review/agent-shots/${label}.${side}.png`;
}

// `label` must already be sanitizeLabel output.
export function buildAgentShotEntry(sha: string, label: string, hasBefore: boolean): string {
  const lines = [`<!-- vr-agent-shot:${label} -->`, `### Agent shot · ${label}`, ''];
  if (hasBefore) {
    lines.push('**Before**', '', `![${label} before](${agentShotUrl(sha, label, 'before')})`, '');
  }
  lines.push('**After**', '', `![${label} after](${agentShotUrl(sha, label, 'after')})`, AGENT_SHOT_ENTRY_END);
  return lines.join('\n');
}

// Split a sub-block body into (label, entry) pairs by their start markers.
function splitAgentEntries(inner: string): { label: string; entry: string }[] {
  const starts = [...inner.matchAll(AGENT_SHOT_ENTRY_RE)];
  return starts.map((m, i) => {
    const from = m.index ?? 0;
    const to = i + 1 < starts.length ? (starts[i + 1].index ?? inner.length) : inner.length;
    return { label: m[1], entry: inner.slice(from, to).trim() };
  });
}

// Replace same-label entries in place, append new labels in input order, and
// keep everything outside the managed block (the human text) untouched.
export function upsertAgentShots(body: string, entries: { label: string; entry: string }[]): string {
  const current = extractAgentShots(body);
  const inner = current === '' ? '' : current.slice(AGENT_SHOTS_BEGIN.length, -AGENT_SHOTS_END.length);
  const merged = splitAgentEntries(inner);
  for (const e of entries) {
    const at = merged.findIndex((m) => m.label === e.label);
    if (at === -1) merged.push(e);
    else merged[at] = e;
  }
  const subBlock = [AGENT_SHOTS_BEGIN, ...merged.map((m) => m.entry), AGENT_SHOTS_END].join('\n');

  const [b] = findManagedBlocks(body);
  if (b === undefined) {
    return spliceBody(body, [PR_BODY_MARKER_BEGIN, '', subBlock, PR_BODY_MARKER_END].join('\n'));
  }
  if (current !== '') {
    const at = body.indexOf(current, b.start);
    return body.slice(0, at) + subBlock + body.slice(at + current.length);
  }
  const end = b.end - PR_BODY_MARKER_END.length;
  return body.slice(0, end) + subBlock + '\n' + body.slice(end);
}

// Pages-relative file path -> URL (slash-normalized base, per-segment encoding).
function pagesFileUrl(pagesUrl: string, file: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return base + file.split('/').map(encodeURIComponent).join('/');
}

function spotRegion(spot: ChangedSpot): string {
  if (spot.kind === 'uncropped') {
    return spot.reason === 'no-pixel-mask' ? 'full page (no pixel mask)' : 'full page (size changed)';
  }
  return spot.region;
}

function spotLines(spot: ChangedSpot, pagesUrl: string): string[] {
  const module = sanitizeLabel(spot.module) ?? 'unknown';
  const title = sanitizeLabel(spot.title) ?? 'unknown';
  return [
    `### ${module} · ${title} · ${spotRegion(spot)}`,
    '',
    '**Before**',
    '',
    `![${title} before](${pagesFileUrl(pagesUrl, spot.beforeFile)})`,
    '',
    '**After**',
    '',
    `![${title} after](${pagesFileUrl(pagesUrl, spot.afterFile)})`,
    '',
  ];
}

function compareSpots(a: ChangedSpot, b: ChangedSpot): number {
  return a.module.localeCompare(b.module) || a.title.localeCompare(b.title) || a.index - b.index;
}

export function buildPrBodyBlock(input: {
  newCells: LeanCell[];
  spots: ChangedSpot[];
  pagesUrl: string;
  headSha: string;
  runTime: Date;
  agentShots: string;
  maxSpots?: number;
  maxChars?: number;
}): string {
  const { newCells, spots, pagesUrl, headSha, runTime, agentShots } = input;
  if (newCells.length === 0 && spots.length === 0 && agentShots === '') return '';
  const maxSpots = input.maxSpots ?? MAX_CHANGED_SPOTS;
  const maxChars = input.maxChars ?? MAX_BLOCK_CHARS;

  const head: string[] = [PR_BODY_MARKER_BEGIN, '', formatStamp(headSha, runTime), ''];
  const tail: string[] = [];
  if (newCells.length > 0) tail.push(...newScreensLines(newCells, pagesUrl));
  if (agentShots !== '') tail.push(agentShots);
  tail.push(PR_BODY_MARKER_END);

  // Desktop first, then mobile; the cap counts both together.
  const desktop = spots.filter((s) => s.viewport !== 'mobile').sort(compareSpots);
  const mobile = spots.filter((s) => s.viewport === 'mobile').sort(compareSpots);
  const ordered = [...desktop, ...mobile];

  const heading = '## 🔍 Changed VR screens';
  let running = [...head, ...tail].join('\n').length + (spots.length > 0 ? heading.length + 2 : 0);
  const keptDesktop: ChangedSpot[] = [];
  const keptMobile = new Map<string, ChangedSpot[]>();
  let emitted = 0;
  for (const spot of ordered) {
    if (emitted >= maxSpots) break;
    let cost = spotLines(spot, pagesUrl).join('\n').length + 1;
    const isMobile = spot.viewport === 'mobile';
    if (isMobile && !keptMobile.has(spot.module)) {
      cost += `<details><summary>${spot.module}: mobile (000 spots)</summary>`.length + '</details>'.length + 4;
    }
    if (running + cost > maxChars) break;
    running += cost;
    emitted++;
    if (isMobile) {
      const group = keptMobile.get(spot.module);
      if (group) group.push(spot);
      else keptMobile.set(spot.module, [spot]);
    } else {
      keptDesktop.push(spot);
    }
  }

  const body: string[] = [];
  if (spots.length > 0) {
    body.push(heading, '');
    for (const spot of keptDesktop) body.push(...spotLines(spot, pagesUrl));
    for (const moduleName of [...keptMobile.keys()].sort((a, b) => a.localeCompare(b))) {
      const group = keptMobile.get(moduleName)!;
      const label = sanitizeLabel(moduleName) ?? 'unknown';
      body.push(`<details><summary>${label}: mobile (${group.length} spots)</summary>`, '');
      for (const spot of group) body.push(...spotLines(spot, pagesUrl));
      body.push('</details>', '');
    }
    const remaining = spots.length - emitted;
    if (remaining > 0) {
      const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
      body.push(`_…and ${remaining} more changed spots. [Full gallery](${base})_`, '');
    }
  }

  return [...head, ...body, ...tail].join('\n');
}

// URL under the per-SHA Pages tree; mirrors reportLink's slash-normalization + encoding.
export function newScreenUrl(pagesUrl: string, title: string): string {
  const base = pagesUrl.endsWith('/') ? pagesUrl : pagesUrl + '/';
  return `${base}new-screens/${encodeURIComponent(title)}.png`;
}

// Deterministic copy plan (no I/O). src = <renders>/<title>.after.png ; dest = <dest>/<title>.png
export function buildCopyPlan(
  newCells: LeanCell[],
  rendersDir: string,
  destDir: string
): { src: string; dest: string }[] {
  return newCells.map((c) => ({
    src: `${rendersDir}/${c.title}.after.png`,
    dest: `${destDir}/${c.title}.png`,
  }));
}

// Body lines of the new-screens list (from the heading through the last module's
// trailing blank line), grouped by module (localeCompare), cells sorted by title.
function newScreensLines(newCells: LeanCell[], pagesUrl: string): string[] {
  const byModule = new Map<string, LeanCell[]>();
  for (const cell of newCells) {
    const group = byModule.get(cell.module);
    if (group) group.push(cell);
    else byModule.set(cell.module, [cell]);
  }

  const lines: string[] = ['### 🆕 New VR screens', ''];
  const modules = [...byModule.keys()].sort((a, b) => a.localeCompare(b));
  for (const moduleName of modules) {
    const cells = byModule.get(moduleName)!.slice().sort((a, b) => a.title.localeCompare(b.title));
    lines.push(`**${moduleName}**`, '');
    for (const c of cells) {
      lines.push(`![${c.title} · ${c.viewport}](${newScreenUrl(pagesUrl, c.title)})`);
    }
    lines.push('');
  }
  return lines;
}

// Managed section markdown, grouped by module (localeCompare), cells sorted by title.
// Empty newCells => '' (splice will strip the block).
export function buildNewScreensSection(newCells: LeanCell[], pagesUrl: string): string {
  if (newCells.length === 0) return '';
  return [PR_BODY_MARKER_BEGIN, '', ...newScreensLines(newCells, pagesUrl), PR_BODY_MARKER_END].join('\n');
}

// "null"/nullish -> ""; strip trailing newlines.
export function normalizeBody(raw: string | null | undefined): string {
  const b = raw == null || raw === 'null' ? '' : raw;
  return b.replace(/\n+$/, '');
}

export interface ManagedBlock { start: number; end: number } // [start, end): BEGIN line start .. just past END marker text

// Every managed vr-new-screens block in body order. A block starts at an own-line BEGIN
// marker outside any fenced code block and ends at the next own-line END marker. Inline
// mentions, fenced markers, an unterminated BEGIN and a stray END are human text.
export function findManagedBlocks(body: string): ManagedBlock[] {
  const blocks: ManagedBlock[] = [];
  let open: number | null = null;
  let fence: { ch: string; len: number } | null = null;
  let offset = 0;
  for (const line of body.split('\n')) {
    const lineStart = offset;
    offset += line.length + 1;
    const f = /^ {0,3}(`{3,}|~{3,})/.exec(line);
    if (f !== null) {
      const run = f[1];
      if (fence === null) fence = { ch: run[0], len: run.length };
      else if (run[0] === fence.ch && run.length >= fence.len) fence = null;
      continue;
    }
    if (fence !== null) continue;
    const t = line.trim();
    if (t === PR_BODY_MARKER_BEGIN) {
      open = lineStart;
    } else if (t === PR_BODY_MARKER_END && open !== null) {
      blocks.push({ start: open, end: lineStart + line.indexOf(PR_BODY_MARKER_END) + PR_BODY_MARKER_END.length });
      open = null;
    }
  }
  return blocks;
}

// Replaces the first managed block in place, strips every later one, and prepends only when none exists. currentBody must already be normalized.
export function spliceBody(currentBody: string, section: string): string {
  const blocks = findManagedBlocks(currentBody);
  if (blocks.length === 0) {
    if (section === '') return currentBody;
    return currentBody === '' ? section : `${section}\n\n${currentBody}`;
  }
  const before = currentBody.slice(0, blocks[0].start).replace(/\s+$/, '');
  const after: string[] = [];
  for (let i = 0; i < blocks.length; i++) {
    const from = blocks[i].end;
    const to = i + 1 < blocks.length ? blocks[i + 1].start : currentBody.length;
    const gap = currentBody.slice(from, to).replace(/^\s+/, '');
    const piece = i + 1 < blocks.length ? gap.replace(/\s+$/, '') : gap;
    if (piece !== '') after.push(piece);
  }
  return [before, section, after.join('\n\n')].filter((p) => p !== '').join('\n\n');
}
