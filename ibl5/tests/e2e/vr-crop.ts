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

export type CropPlan =
  | { kind: 'spots'; rects: Rect[] }
  | { kind: 'uncropped'; reason: 'dimensions-changed' | 'no-pixel-mask' };

/** Top-level decision for one changed cell. mask is null when dims differ. */
export function planCrop(
  before: Size,
  after: Size,
  mask: Uint8Array | null,
  opts: CropOptions = DEFAULT_CROP,
): CropPlan {
  if (before.width !== after.width || before.height !== after.height || mask === null) {
    return { kind: 'uncropped', reason: 'dimensions-changed' };
  }
  const rects = clusterSpots(mask, after.width, after.height, opts);
  if (rects.length === 0) return { kind: 'uncropped', reason: 'no-pixel-mask' };
  return { kind: 'spots', rects };
}

/** Human region label for a heading: "top|middle|bottom, y <y0>-<y1> px" (band from the rect's vertical centre vs. image thirds). */
export function regionLabel(r: Rect, imageHeight: number): string {
  const cy = r.y + r.h / 2;
  const band = cy < imageHeight / 3 ? 'top' : cy >= (2 * imageHeight) / 3 ? 'bottom' : 'middle';
  return `${band}, y ${r.y}-${r.y + r.h} px`;
}
