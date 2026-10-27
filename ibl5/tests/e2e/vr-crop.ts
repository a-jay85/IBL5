// Pure, I/O-free crop-region math for the visual-review PR-body gallery. Mirrors
// vr-gallery.ts / vr-pr-body.ts: no fs, no git, no network, and no pngjs or
// pixelmatch import — the glue layer decodes PNGs, builds the per-pixel change
// mask, and does the actual crop. This module only turns a mask into a short
// list of padded, clamped rectangles, so it is unit-testable against hand-built
// masks (see tests/ts-unit/vr-crop.test.ts).
//
// Rects are half-open: a rect covers columns x .. x+w-1 and rows y .. y+h-1.

export interface Rect { x: number; y: number; w: number; h: number }
export interface Size { width: number; height: number }
export interface CropOptions { padding: number; cell: number; maxSpots: number }
export const DEFAULT_CROP: CropOptions = { padding: 24, cell: 16, maxSpots: 3 };

/** Tight bbox of set pixels (mask[y*width+x] !== 0), or null when none is set. */
export function boundingBox(mask: Uint8Array, width: number, height: number): Rect | null {
  let minX = width;
  let minY = height;
  let maxX = -1;
  let maxY = -1;
  for (let y = 0; y < height; y++) {
    const row = y * width;
    for (let x = 0; x < width; x++) {
      if (mask[row + x] !== 0) {
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
      }
    }
  }
  if (maxX < 0) return null;
  return { x: minX, y: minY, w: maxX - minX + 1, h: maxY - minY + 1 };
}

/** Grow r by `padding` on every side, clamped to [0,width) x [0,height). */
export function padRect(r: Rect, padding: number, width: number, height: number): Rect {
  const x0 = Math.max(0, r.x - padding);
  const y0 = Math.max(0, r.y - padding);
  const x1 = Math.min(width, r.x + r.w + padding);
  const y1 = Math.min(height, r.y + r.h + padding);
  return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
}

function unionRect(a: Rect, b: Rect): Rect {
  const x0 = Math.min(a.x, b.x);
  const y0 = Math.min(a.y, b.y);
  const x1 = Math.max(a.x + a.w, b.x + b.w);
  const y1 = Math.max(a.y + a.h, b.y + b.h);
  return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
}

function overlapsOrTouches(a: Rect, b: Rect): boolean {
  return a.x <= b.x + b.w && b.x <= a.x + a.w && a.y <= b.y + b.h && b.y <= a.y + a.h;
}

/** Grid-dilated connected components -> padded, clamped, merged, capped rects, sorted by (y, x). */
export function clusterSpots(
  mask: Uint8Array,
  width: number,
  height: number,
  opts: CropOptions = DEFAULT_CROP,
): Rect[] {
  const { padding, cell, maxSpots } = opts;
  const cols = Math.ceil(width / cell);
  const rows = Math.ceil(height / cell);
  if (cols === 0 || rows === 0) return [];

  // Per-tile tight bbox of set pixels; null = tile is off.
  const tileBox: Array<Rect | null> = new Array<Rect | null>(cols * rows).fill(null);
  let anyOn = false;
  for (let y = 0; y < height; y++) {
    const row = y * width;
    const ty = Math.floor(y / cell);
    for (let x = 0; x < width; x++) {
      if (mask[row + x] === 0) continue;
      anyOn = true;
      const idx = ty * cols + Math.floor(x / cell);
      const b = tileBox[idx];
      if (b === null || b === undefined) {
        tileBox[idx] = { x, y, w: 1, h: 1 };
      } else {
        const x1 = Math.max(b.x + b.w, x + 1);
        const y1 = Math.max(b.y + b.h, y + 1);
        b.x = Math.min(b.x, x);
        b.y = Math.min(b.y, y);
        b.w = x1 - b.x;
        b.h = y1 - b.y;
      }
    }
  }
  if (!anyOn) return [];

  // 8-connected components over "on" tiles, iterative queue.
  const seen = new Uint8Array(cols * rows);
  let rects: Rect[] = [];
  for (let start = 0; start < tileBox.length; start++) {
    if (tileBox[start] === null || seen[start] === 1) continue;
    seen[start] = 1;
    const queue: number[] = [start];
    let comp: Rect | null = null;
    for (let head = 0; head < queue.length; head++) {
      const cur = queue[head]!;
      const b = tileBox[cur]!;
      comp = comp === null ? { ...b } : unionRect(comp, b);
      const cx = cur % cols;
      const cy = Math.floor(cur / cols);
      for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
          if (dx === 0 && dy === 0) continue;
          const nx = cx + dx;
          const ny = cy + dy;
          if (nx < 0 || ny < 0 || nx >= cols || ny >= rows) continue;
          const ni = ny * cols + nx;
          if (tileBox[ni] === null || seen[ni] === 1) continue;
          seen[ni] = 1;
          queue.push(ni);
        }
      }
    }
    if (comp !== null) rects.push(padRect(comp, padding, width, height));
  }

  // Merge overlapping or touching rects to a fixpoint.
  let merged = true;
  while (merged) {
    merged = false;
    outer: for (let i = 0; i < rects.length; i++) {
      for (let j = i + 1; j < rects.length; j++) {
        if (overlapsOrTouches(rects[i]!, rects[j]!)) {
          const u = unionRect(rects[i]!, rects[j]!);
          rects = rects.filter((_, k) => k !== i && k !== j);
          rects.push(u);
          merged = true;
          break outer;
        }
      }
    }
  }

  rects.sort((a, b) => a.y - b.y || a.x - b.x);

  if (rects.length > maxSpots) {
    const kept = rects.slice(0, maxSpots - 1);
    const rest = rects.slice(maxSpots - 1);
    let u = rest[0]!;
    for (let i = 1; i < rest.length; i++) u = unionRect(u, rest[i]!);
    return [...kept, u];
  }
  return rects;
}

// Content shift. A change that adds or removes rows (a new 6px border, an
// extra table row) pushes everything below it down or up. A plain
// same-position diff then marks the whole lower page. We model the change as
// ONE band of d = |tallH - shortH| rows inserted into the taller image at a
// split row s: taller rows [0,s) line up with shorter rows [0,s), and taller
// rows [s+d, tallH) line up with shorter rows [s, shortH). The glue layer
// diffs both alignments (offset 0 and offset d), and bestSplit picks the s
// that leaves the fewest changed rows. Only the band plus rows that still
// differ under their alignment get boxed. Two separate insertions break the
// one-band model. The rows between them then show as changed, which
// over-boxes but never hides a change. Same-height pairs have d = 0 and
// reduce to the plain diff.

/**
 * Split row s in [0, shortH] minimising (changed rows above s at offset 0) +
 * (changed rows at or below s at offset d). diff0[i] / diffD[i] are 1 when
 * shorter row i differs from taller row i / taller row i + d. Ties keep the
 * lowest s.
 */
export function bestSplit(diff0: Uint8Array, diffD: Uint8Array, shortH: number): number {
  let below = 0;
  for (let i = 0; i < shortH; i++) below += diffD[i]!;
  let above = 0;
  let best = 0;
  let bestCost = below;
  for (let s = 1; s <= shortH; s++) {
    above += diff0[s - 1]!;
    below -= diffD[s - 1]!;
    if (above + below < bestCost) {
      bestCost = above + below;
      best = s;
    }
  }
  return best;
}

/** Map a taller-image row to the shorter image under split s and band height d. */
export function mapRow(y: number, s: number, d: number): number {
  if (y < s) return y;
  if (y < s + d) return s;
  return y - d;
}

/** Max per-channel RGB delta that still counts as "the same colour" in a band. */
export const BAND_TOLERANCE = 24;

/**
 * Change mask (width x d) for the d band rows inserted at taller row s. tall
 * and short are RGBA. A band pixel counts as changed when it differs from the
 * shorter image's rows on both sides of the seam (rows s-1 and s, where they
 * exist). So a full-width inserted line is boxed where it is visible, not
 * across the page background. A band that matches its neighbours everywhere
 * (pure blank space) is marked across its full width, so the shift is still
 * boxed.
 */
export function bandMask(
  tall: Uint8Array,
  short: Uint8Array,
  width: number,
  shortH: number,
  s: number,
  d: number,
): Uint8Array {
  const mask = new Uint8Array(width * d);
  const neighbours = [s - 1, s].filter((r) => r >= 0 && r < shortH);
  let any = false;
  for (let k = 0; k < d; k++) {
    for (let x = 0; x < width; x++) {
      const to = ((s + k) * width + x) * 4;
      let differs = true;
      for (const r of neighbours) {
        const so = (r * width + x) * 4;
        const delta = Math.max(
          Math.abs(tall[to]! - short[so]!),
          Math.abs(tall[to + 1]! - short[so + 1]!),
          Math.abs(tall[to + 2]! - short[so + 2]!),
        );
        if (delta <= BAND_TOLERANCE) {
          differs = false;
          break;
        }
      }
      if (differs) {
        mask[k * width + x] = 1;
        any = true;
      }
    }
  }
  if (!any) mask.fill(1);
  return mask;
}

// Crop windows and red boxes. Changed pixels cluster into boxes with a small
// outset, so the box stroke never paints over a thin change. Each box is
// padded by the crop margin into a window. Overlapping windows merge, so one
// window can hold several boxes. Past maxSpots windows, the tail folds into
// one union window that keeps every box.
export interface FocusOptions extends CropOptions { boxOutset: number; maxBoxes: number }
export const DEFAULT_FOCUS: FocusOptions = { padding: 56, cell: 16, maxSpots: 3, boxOutset: 5, maxBoxes: 8 };

/** One crop window (in the mask's coordinates) and its boxes (window-relative). */
export interface Spot { rect: Rect; boxes: Rect[] }

export function planSpots(mask: Uint8Array, width: number, height: number, opts: FocusOptions = DEFAULT_FOCUS): Spot[] {
  const boxes = clusterSpots(mask, width, height, {
    padding: opts.boxOutset,
    cell: opts.cell,
    maxSpots: opts.maxBoxes,
  });
  let groups = boxes.map((b) => ({ rect: padRect(b, opts.padding, width, height), boxes: [b] }));
  let merged = true;
  while (merged) {
    merged = false;
    outer: for (let i = 0; i < groups.length; i++) {
      for (let j = i + 1; j < groups.length; j++) {
        if (overlapsOrTouches(groups[i]!.rect, groups[j]!.rect)) {
          const u = {
            rect: unionRect(groups[i]!.rect, groups[j]!.rect),
            boxes: [...groups[i]!.boxes, ...groups[j]!.boxes],
          };
          groups = groups.filter((_, k) => k !== i && k !== j);
          groups.push(u);
          merged = true;
          break outer;
        }
      }
    }
  }
  groups.sort((a, b) => a.rect.y - b.rect.y || a.rect.x - b.rect.x);
  if (groups.length > opts.maxSpots) {
    const kept = groups.slice(0, opts.maxSpots - 1);
    const rest = groups.slice(opts.maxSpots - 1);
    let u = rest[0]!;
    for (let i = 1; i < rest.length; i++) {
      u = { rect: unionRect(u.rect, rest[i]!.rect), boxes: [...u.boxes, ...rest[i]!.boxes] };
    }
    groups = [...kept, u];
  }
  return groups.map((g) => ({
    rect: g.rect,
    boxes: g.boxes.map((b) => ({ x: b.x - g.rect.x, y: b.y - g.rect.y, w: b.w, h: b.h })),
  }));
}

export type CropPlan =
  | { kind: 'spots'; spots: Spot[] }
  | { kind: 'uncropped'; reason: 'dimensions-changed' | 'no-pixel-mask' };

/**
 * Top-level decision for one changed cell. mask covers the taller image
 * (width x max height), built by the glue layer with the shift alignment
 * above. It is null when the widths differ, the one size change we cannot
 * align.
 */
export function planCrop(
  before: Size,
  after: Size,
  mask: Uint8Array | null,
  opts: FocusOptions = DEFAULT_FOCUS,
): CropPlan {
  if (before.width !== after.width || mask === null) {
    return { kind: 'uncropped', reason: 'dimensions-changed' };
  }
  const spots = planSpots(mask, after.width, Math.max(before.height, after.height), opts);
  if (spots.length === 0) return { kind: 'uncropped', reason: 'no-pixel-mask' };
  return { kind: 'spots', spots };
}

/** Human region label for a heading: "top|middle|bottom, y <y0>-<y1> px" (band from the rect's vertical centre vs. image thirds). */
export function regionLabel(r: Rect, imageHeight: number): string {
  const cy = r.y + r.h / 2;
  const band = cy < imageHeight / 3 ? 'top' : cy >= (2 * imageHeight) / 3 ? 'bottom' : 'middle';
  return `${band}, y ${r.y}-${r.y + r.h} px`;
}
