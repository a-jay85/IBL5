// Pure logic for the PR-keyed manual-row screens block (before = master, after
// = this PR, phone + desktop). No I/O — no gh, no fetch, no fs. The bin/ glue
// and the capture spec call these; tests/ts-unit/vr-pr-screens.test.ts covers
// them.
//
// Reuses normalizeBody (vr-pr-body.ts) and the ManualVrRow type
// (vr-manual-rows.ts); its parseVrCell already enforces the kebab label
// grammar and this module re-checks it as defense in depth.
import { createHash } from 'node:crypto';
import { normalizeBody } from './vr-pr-body';
import type { ManualVrRow } from './vr-manual-rows';

export const SCREENS_BEGIN = '<!-- vr-screens:begin -->';
export const SCREENS_END = '<!-- vr-screens:end -->';
export const PAGES_BASE = 'https://a-jay85.github.io/IBL5/';
export const MAX_INLINE_CELLS = 12;
export const MAX_BODY_CHARS = 60000;

export const VIEWPORTS = {
  phone: { width: 375, height: 812 },
  desktop: { width: 1280, height: 900 },
} as const;

export type Viewport = keyof typeof VIEWPORTS;
export type Side = 'before' | 'after';

const SIDE_ORDER: Side[] = ['before', 'after'];

export function manualShotFileFor(label: string, viewport: Viewport): string {
  return `${label}.${viewport}.png`;
}

export const LABEL_RE = /^[a-z0-9]+(-[a-z0-9]+)*$/;

export function parsePrNumber(s: string): number | null {
  return /^[1-9][0-9]{0,6}$/.test(s) ? Number(s) : null;
}

export function isHeadSha(s: string): boolean {
  return /^[0-9a-f]{40}$/.test(s);
}

export function isRunUrl(s: string): boolean {
  return /^https:\/\/github\.com\/a-jay85\/IBL5\/actions\/runs\/[0-9]+$/.test(s);
}

export function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function sha256Hex(s: string): string {
  return createHash('sha256').update(s).digest('hex');
}

export function cellsHash(rows: ManualVrRow[]): string {
  return sha256Hex(JSON.stringify(rows.map((r) => [r.label, r.role, r.url, r.anchor, r.setup])));
}

export type Manifest = { schema: 1; cellsHash: string; beforeTree: string; afterTree: string };

type CurrentState = { cellsHash: string; beforeTree: string; afterTree: string };

export function renderToken(m: Manifest): string {
  return sha256Hex(`${m.cellsHash}\n${m.beforeTree}\n${m.afterTree}`).slice(0, 12);
}

export function planRender(prev: Manifest | null, cur: CurrentState): Side[] {
  if (prev === null || prev.cellsHash !== cur.cellsHash) return ['before', 'after'];
  const sides: Side[] = [];
  if (prev.beforeTree === '' || prev.beforeTree !== cur.beforeTree) sides.push('before');
  if (prev.afterTree === '' || prev.afterTree !== cur.afterTree) sides.push('after');
  return sides;
}

export function screenUrl(
  pr: number,
  side: Side,
  label: string,
  viewport: Viewport,
  headSha: string,
  token: string
): string {
  return `${PAGES_BASE}pr/${pr}/manual/${side}/${manualShotFileFor(label, viewport)}?sha=${headSha}&r=${token}`;
}

export function galleryUrl(pr: number): string {
  return `${PAGES_BASE}pr/${pr}/visual-review/`;
}

export type CaptureStatus = 'ok' | 'failed' | 'skipped' | 'missing';
export type SideStatus = Record<string, Record<Viewport, CaptureStatus>>;
export type ScreensRows = { cells: string[]; before: SideStatus; after: SideStatus };
export type CapturedRow = { label: string; viewports?: { phone?: string; desktop?: string } };

function missingSlots(): Record<Viewport, CaptureStatus> {
  return { phone: 'missing', desktop: 'missing' };
}

function mapStatus(v: string | undefined): CaptureStatus {
  return v === 'ok' || v === 'skipped' ? v : 'failed';
}

export function assembleMeta(args: {
  cells: ManualVrRow[];
  sides: Side[];
  captured: { before: CapturedRow[] | null; after: CapturedRow[] | null };
  prevRows: ScreensRows | null;
  prevManifest: Manifest | null;
  cur: CurrentState;
}): { rows: ScreensRows; manifest: Manifest } {
  const { cells, sides, captured, prevRows, prevManifest, cur } = args;
  const labels = cells.map((c) => c.label);
  const sideRows: Record<Side, SideStatus> = { before: {}, after: {} };
  const trees: Record<Side, string> = { before: '', after: '' };
  const curTrees: Record<Side, string> = { before: cur.beforeTree, after: cur.afterTree };
  const prevTrees: Record<Side, string> = {
    before: prevManifest?.beforeTree ?? '',
    after: prevManifest?.afterTree ?? '',
  };

  for (const side of SIDE_ORDER) {
    const status: SideStatus = {};
    if (sides.includes(side)) {
      const got = captured[side];
      if (got === null) {
        for (const l of labels) status[l] = missingSlots();
        trees[side] = '';
      } else {
        for (const l of labels) {
          const found = got.find((r) => r.label === l);
          if (!found) {
            status[l] = missingSlots();
          } else {
            status[l] = {
              phone: mapStatus(found.viewports?.phone),
              desktop: mapStatus(found.viewports?.desktop),
            };
          }
        }
        trees[side] = curTrees[side];
      }
    } else {
      const prev = prevRows?.[side] ?? {};
      for (const l of labels) status[l] = prev[l] ?? missingSlots();
      trees[side] = prevTrees[side];
    }
    sideRows[side] = status;
  }

  return {
    rows: { cells: labels, before: sideRows.before, after: sideRows.after },
    manifest: { schema: 1, cellsHash: cur.cellsHash, beforeTree: trees.before, afterTree: trees.after },
  };
}

function slot(
  args: { pr: number; headSha: string; token: string; runUrl: string },
  rows: ScreensRows,
  side: Side,
  label: string,
  viewport: Viewport
): string {
  const status = rows[side][label]?.[viewport] ?? 'missing';
  if (status === 'ok') {
    const src = escapeHtml(screenUrl(args.pr, side, label, viewport, args.headSha, args.token));
    const width = viewport === 'phone' ? ' width="180"' : '';
    return `<img src="${src}"${width} alt="${label} ${viewport} ${side}">`;
  }
  const log = isRunUrl(args.runUrl) ? ` (<a href="${args.runUrl}">run log</a>)` : '';
  return `<i>capture failed</i>${log}`;
}

export function buildScreensBlock(args: {
  pr: number;
  headSha: string;
  token: string;
  rows: ScreensRows;
  galleryPresent: boolean;
  runUrl: string;
}): string {
  const valid = args.rows.cells.filter((l) => LABEL_RE.test(l));
  if (valid.length === 0) return '';
  const inlined = valid.slice(0, MAX_INLINE_CELLS);
  const overflow = valid.length - inlined.length;

  const groups = inlined.map((label) => {
    const s = (side: Side, vp: Viewport): string => slot(args, args.rows, side, label, vp);
    return [
      `**${label}**`,
      '',
      `<table><tr><th>Phone before</th><th>Phone after</th></tr><tr><td>${s('before', 'phone')}</td><td>${s('after', 'phone')}</td></tr></table>`,
      '',
      `<b>Desktop before</b><br>${s('before', 'desktop')}`,
      '',
      `<b>Desktop after</b><br>${s('after', 'desktop')}`,
    ].join('\n');
  });

  const gallery = args.galleryPresent
    ? `[Full visual-review gallery](${galleryUrl(args.pr)})`
    : '<i>Full visual-review gallery not published for this PR yet.</i>';

  const parts: string[] = groups.slice();
  if (overflow > 0) {
    parts.push(`<i>${overflow} more manual rows were not inlined (${MAX_INLINE_CELLS}-row cap).</i>`);
  }

  const intro = `<sub>Manual-row screenshots for <code>${args.headSha.slice(0, 7)}</code>. Before is master, after is this PR.</sub>`;
  // Groups, overflow note and gallery line are separated by one blank line.
  return [SCREENS_BEGIN, intro, '', parts.join('\n\n'), '', gallery, SCREENS_END].join('\n');
}

function blockRange(body: string): { start: number; end: number } | null {
  const start = body.indexOf(SCREENS_BEGIN);
  if (start === -1) return null;
  const endAt = body.indexOf(SCREENS_END, start);
  if (endAt === -1) return null;
  return { start, end: endAt + SCREENS_END.length };
}

export function extractScreensBlock(body: string): string {
  const r = blockRange(body);
  return r ? body.slice(r.start, r.end) : '';
}

export function stripScreensBlock(body: string): string {
  const r = blockRange(body);
  if (!r) return body;
  let before = body.slice(0, r.start);
  let after = body.slice(r.end);
  if (after.startsWith('\n\n')) after = after.slice(2);
  else if (before.endsWith('\n\n')) before = before.slice(0, -2);
  if (after.trim() === '') after = '';
  const out = before + after;
  return after === '' ? out.replace(/\n+$/, '') : out;
}

function countOf(body: string, needle: string): number {
  return body.split(needle).length - 1;
}

export type SpliceOutcome = 'unchanged' | 'replaced' | 'inserted' | 'appended' | 'removed' | 'malformed' | 'too-large';

export function spliceScreensBlock(rawBody: string, block: string): { body: string; outcome: SpliceOutcome } {
  const body = normalizeBody(rawBody);
  const begins = countOf(body, SCREENS_BEGIN);
  const ends = countOf(body, SCREENS_END);
  const bi = body.indexOf(SCREENS_BEGIN);
  const ei = body.indexOf(SCREENS_END);
  const present = begins > 0 || ends > 0;
  if (begins > 1 || ends > 1 || (present && (begins !== 1 || ends !== 1 || ei < bi))) {
    return { body, outcome: 'malformed' };
  }

  let result: string;
  let outcome: SpliceOutcome;
  if (present) {
    if (block === '') {
      result = stripScreensBlock(body);
      outcome = 'removed';
    } else {
      result = body.slice(0, bi) + block + body.slice(ei + SCREENS_END.length);
      outcome = 'replaced';
    }
  } else if (block === '') {
    return { body, outcome: 'unchanged' };
  } else {
    const m = /^## Manual Testing\b/m.exec(body);
    if (m) {
      result = body.slice(0, m.index) + block + '\n\n' + body.slice(m.index);
      outcome = 'inserted';
    } else {
      result = body.replace(/\s+$/, '') + '\n\n' + block + '\n';
      outcome = 'appended';
    }
  }

  if (normalizeBody(result) === body) return { body, outcome: 'unchanged' };
  if (result.length > MAX_BODY_CHARS) return { body, outcome: 'too-large' };
  return { body: result, outcome };
}

export type SpliceIo = { read(): string; write(body: string): void };

// Races the PR-body edit against concurrent editors: an optimistic re-read
// before the write, and a verifying re-read after it. Never throws on a lost race.
export function spliceWithRetry(
  io: SpliceIo,
  block: string,
  attempts = 3
): { outcome: SpliceOutcome | 'lost'; attempts: number } {
  for (let n = 1; n <= attempts; n++) {
    const b0 = io.read();
    const r = spliceScreensBlock(b0, block);
    if (r.outcome === 'unchanged' || r.outcome === 'malformed' || r.outcome === 'too-large') {
      return { outcome: r.outcome, attempts: n };
    }
    const b1 = io.read();
    if (normalizeBody(b1) !== normalizeBody(b0)) continue;
    io.write(r.body);
    const b2 = normalizeBody(io.read());
    if (
      extractScreensBlock(b2) === block &&
      stripScreensBlock(b2) === stripScreensBlock(normalizeBody(b0))
    ) {
      return { outcome: r.outcome, attempts: n };
    }
  }
  return { outcome: 'lost', attempts };
}

export function blockImageUrls(block: string): string[] {
  const urls: string[] = [];
  for (const m of block.matchAll(/<img src="([^"]*)"/g)) {
    const url = m[1].replace(/&amp;/g, '&');
    if (url.startsWith(PAGES_BASE + 'pr/')) urls.push(url);
  }
  return urls;
}

export function isNoop(args: { sides: Side[]; expectedBlock: string; bodyBlock: string; prevBlock: string }): boolean {
  return (
    args.sides.length === 0 &&
    args.expectedBlock === args.bodyBlock &&
    args.bodyBlock === args.prevBlock
  );
}
