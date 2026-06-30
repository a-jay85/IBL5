// Pure, I/O-free triage + HTML rendering for the change-driven visual-review
// gallery. Mirrors vr-review-comment.ts / vr-coverage-map.ts: no fs, no git, no
// network — every dependency is passed in as a Buffer, so the whole module is
// unit-testable against synthetic PNGs (see tests/ts-unit/vr-gallery.test.ts).
//
// The gallery is CHANGE-driven, not failure-driven: a cell appears only when the
// PR's actual render differs from master's COMMITTED baseline (read by the I/O
// wrapper via `git show <base.sha>:<snapshot>`, so it is immune to a baseline
// regen into the PR branch). Two independent PR renders (A and a post-reload B)
// let us demote self-disagreeing (flaky) cells into an infra section instead of
// reporting them as real changes.

import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import type { Viewport } from './vr-manifest';

// Per-pixel YIQ-distance threshold for what counts as a "different" pixel. This
// MIRRORS the VR gate: `toHaveScreenshot` in ibl5/playwright.visual.config.ts
// sets only `maxDiffPixelRatio` and leaves `threshold` at Playwright's default
// of 0.2 (Playwright drives the same pixelmatch under the hood). Matching it here
// is the other half of gate parity: with pixelmatch's own stricter 0.1 default
// the gallery would flag MORE anti-aliasing/font pixels per cell than the gate,
// inflating the diff ratio above the gate's and re-surfacing sub-gate noise the
// ratio floor alone can't suppress. See ADR-0074 Consequences for both sync
// points (this threshold and the ratio floor).
const GATE_PIXEL_THRESHOLD = 0.2;

export type CellVerdict = 'changed' | 'unchanged' | 'flake' | 'new' | 'infra';

export interface TriageInput {
  /** master's committed baseline (git show base.sha:snapshot), or null if none. */
  before: Buffer | null;
  /** the PR's first render of this cell, or null if it failed to render. */
  afterA: Buffer | null;
  /** the PR's second (post-reload) render, or null if it failed/was skipped. */
  afterB: Buffer | null;
  /** per-cell ratio tolerance (VrRow.extraMaxDiffPixelRatio); defaults to 0. */
  maxDiffPixelRatio?: number;
}

export interface TriageResult {
  verdict: CellVerdict;
  /** PNG bytes to display in the "diff" column; present only for 'changed'. */
  diff?: Buffer;
  /** fraction of pixels that differ from the baseline; present for pixel-diff 'changed'. */
  changedRatio?: number;
}

function decode(buf: Buffer): PNG {
  return PNG.sync.read(buf);
}

function dimsEqual(a: PNG, b: PNG): boolean {
  return a.width === b.width && a.height === b.height;
}

// pixelmatch THROWS when the two images differ in size, so every call site must
// be guarded by a dimsEqual() check first — those guards are load-bearing.
function diffRatio(a: PNG, b: PNG): { ratio: number; diff: PNG } {
  const { width, height } = a;
  const diff = new PNG({ width, height });
  const changed = pixelmatch(a.data, b.data, diff.data, width, height, {
    threshold: GATE_PIXEL_THRESHOLD,
  });
  return { ratio: changed / (width * height), diff };
}

/**
 * Triage one cell against master's baseline using two PR renders.
 *
 * Ordered logic (first match wins):
 *   1. afterA missing          → infra   (PR failed to render the cell)
 *   2. before missing          → new     (no committed baseline)
 *   3. dims(afterA) ≠ dims(afterB)   → flake (renders self-disagree)
 *   4. afterA vs afterB ratio > T    → flake
 *   5. dims(before) ≠ dims(afterA)   → changed (show the new render; no pixel diff)
 *   6. before vs afterA ratio ≤ T    → unchanged
 *   7. otherwise                     → changed (with pixelmatch diff + ratio)
 *
 * When afterB is missing we cannot confirm self-stability, so steps 3–4 are
 * skipped and any would-be 'changed' verdict is demoted to 'infra'; 'unchanged'
 * is safe to keep.
 */
export function triageCell(input: TriageInput): TriageResult {
  const { before, afterA, afterB } = input;
  const T = input.maxDiffPixelRatio ?? 0;

  // 1. The PR produced no render for this cell → infrastructure failure.
  if (afterA === null) return { verdict: 'infra' };

  // 2. No committed baseline on the PR base → brand-new snapshot.
  if (before === null) return { verdict: 'new' };

  const a = decode(afterA);
  const baseline = decode(before);
  const hasB = afterB !== null;

  // 3–4. Self-stability: if the PR's two renders disagree (different size, or
  // pixels beyond tolerance) the cell is flaky — surface it in the infra
  // section, never as a real change.
  if (afterB !== null) {
    const b = decode(afterB);
    if (!dimsEqual(a, b)) return { verdict: 'flake' };
    if (diffRatio(a, b).ratio > T) return { verdict: 'flake' };
  }

  // Resolve the change verdict against master's baseline.
  let result: TriageResult;
  if (!dimsEqual(baseline, a)) {
    // Dimensions changed — can't pixel-diff; surface the new render itself.
    result = { verdict: 'changed', diff: afterA };
  } else {
    const { ratio, diff } = diffRatio(baseline, a);
    result =
      ratio <= T
        ? { verdict: 'unchanged' }
        : { verdict: 'changed', diff: PNG.sync.write(diff), changedRatio: ratio };
  }

  // Without a second render we can't confirm the change is stable, so demote a
  // single-render 'changed' to infra. 'unchanged' stays as-is.
  if (!hasB && result.verdict === 'changed') return { verdict: 'infra' };

  return result;
}

export type GalleryCell = {
  module: string;
  viewport: Viewport;
  title: string;
  verdict: CellVerdict;
  isNew?: boolean;
};

const GALLERY_CSS = `
  body { font-family: system-ui, sans-serif; margin: 1.5rem; background: #fafafa; color: #1a1a1a; }
  h1 { font-size: 1.4rem; }
  section.vr-module { margin-bottom: 2rem; }
  section.vr-module > h2 { font-size: 1.1rem; border-bottom: 1px solid #ddd; padding-bottom: .25rem; }
  h3 { font-size: .95rem; margin: 1rem 0 .25rem; scroll-margin-top: 1rem; }
  .vr-viewport { color: #777; font-weight: normal; }
  .vr-cell { display: flex; flex-wrap: wrap; gap: 1rem; }
  figure { margin: 0; }
  figcaption { font-size: .75rem; color: #555; margin-bottom: .25rem; }
  img { max-width: 360px; border: 1px solid #ccc; background: #fff; }
`.trim();

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function escapeAttr(s: string): string {
  return escapeHtml(s).replace(/"/g, '&quot;');
}

function imgFig(caption: string, src: string): string {
  return `<figure><figcaption>${escapeHtml(caption)}</figcaption><img loading="lazy" src="${escapeAttr(src)}" alt="${escapeAttr(caption)}"></figure>`;
}

function cellBlock(cell: GalleryCell): string {
  const isNew = cell.isNew === true || cell.verdict === 'new';
  const figures = isNew
    ? [imgFig('After (new)', `${cell.title}.after.png`)]
    : [
        imgFig('Before (master)', `${cell.title}.before.png`),
        imgFig('After (this PR)', `${cell.title}.after.png`),
        imgFig('Diff', `${cell.title}.diff.png`),
      ];
  return [
    `<h3 id="${escapeAttr(cell.title)}">${escapeHtml(cell.title)} <span class="vr-viewport">(${escapeHtml(cell.viewport)})</span></h3>`,
    '<div class="vr-cell">',
    ...figures,
    '</div>',
  ].join('\n');
}

/**
 * Render the change gallery HTML. Callers pass only the cells worth showing
 * (changed + new); each becomes an anchored `<h3 id="<title>">` so the PR
 * comment's per-cell links (`…/visual-review/#<title>`) jump straight to it.
 */
export function buildGalleryHtml(cells: GalleryCell[]): string {
  const byModule = new Map<string, GalleryCell[]>();
  for (const cell of cells) {
    const list = byModule.get(cell.module) ?? [];
    list.push(cell);
    byModule.set(cell.module, list);
  }

  const sections = [...byModule.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([module, group]) => {
      const blocks = group.map(cellBlock).join('\n');
      return `<section class="vr-module">\n<h2>${escapeHtml(module)}</h2>\n${blocks}\n</section>`;
    });

  return [
    '<!doctype html>',
    '<html lang="en">',
    '<head>',
    '<meta charset="utf-8">',
    '<meta name="viewport" content="width=device-width, initial-scale=1">',
    '<title>Visual review — changed renders</title>',
    `<style>\n${GALLERY_CSS}\n</style>`,
    '</head>',
    '<body>',
    '<h1>Visual review — changed renders</h1>',
    sections.join('\n'),
    '</body>',
    '</html>',
  ].join('\n');
}
