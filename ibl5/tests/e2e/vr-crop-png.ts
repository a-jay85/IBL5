// PNG layer for the changed-screen crop (Buffer in, Buffer out; no fs).
// Sibling of vr-gallery.ts, same pngjs + pixelmatch stack. The pure spot
// math lives in vr-crop.ts; bin/vr-review-comment does the file I/O.
//
// The mask is pixelmatch's own diff at the gate threshold with `diffMask`,
// keeping only the red diff pixels. Anti-aliased (yellow) pixels are not
// counted, so a crop marks the same pixels the VR gate counts.
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import {
  DEFAULT_FOCUS,
  bandMask,
  bestSplit,
  mapRow,
  planCrop,
  type CropPlan,
  type FocusOptions,
  type Rect,
} from './vr-crop';
import { GATE_PIXEL_THRESHOLD } from './vr-gallery';

/** rect: the after-side crop window (null when uncropped); boxes: window-relative, already drawn. */
export type CropPair = { rect: Rect | null; boxes: Rect[]; before: Buffer; after: Buffer };

export type CropPairResult = { plan: CropPlan; imageHeight: number; pairs: CropPair[] };

// Red change box: #e11d48, 3 px stroke drawn inside the box rect. The rect is
// outset from the changed pixels (DEFAULT_FOCUS.boxOutset), so the stroke sits
// just outside the change and never hides a thin one.
type Rgb = [number, number, number];
export const BOX_RGB: Rgb = [225, 29, 72];
export const BOX_STROKE = 3;

/** Red-only pixelmatch mask (gate threshold) of `rows` rows: a from ay0 vs b from by0. */
function redMask(a: PNG, ay0: number, b: PNG, by0: number, rows: number): Uint8Array {
  const width = a.width;
  const mask = new Uint8Array(width * rows);
  if (rows <= 0) return mask;
  const slice = (p: PNG, y0: number) => p.data.subarray(y0 * width * 4, (y0 + rows) * width * 4);
  const out = new PNG({ width, height: rows });
  pixelmatch(slice(a, ay0), slice(b, by0), out.data, width, rows, {
    threshold: GATE_PIXEL_THRESHOLD,
    diffMask: true,
    diffColor: [255, 0, 0],
    aaColor: [255, 255, 0],
  });
  for (let i = 0; i < width * rows; i++) {
    const o = i * 4;
    if (out.data[o + 3] > 0 && out.data[o] === 255 && out.data[o + 1] === 0 && out.data[o + 2] === 0) {
      mask[i] = 1;
    }
  }
  return mask;
}

function rowFlags(mask: Uint8Array, width: number, rows: number): Uint8Array {
  const flags = new Uint8Array(rows);
  for (let y = 0; y < rows; y++) {
    for (let x = 0; x < width; x++) {
      if (mask[y * width + x] !== 0) {
        flags[y] = 1;
        break;
      }
    }
  }
  return flags;
}

/** Cut w x h at (x, y); rows past the bottom of src stay white. */
function cutPng(src: PNG, x: number, y: number, w: number, h: number): PNG {
  const dst = new PNG({ width: w, height: h });
  dst.data.fill(255);
  const rows = Math.max(0, Math.min(h, src.height - y));
  if (rows > 0) PNG.bitblt(src, dst, x, y, w, rows, 0, 0);
  return dst;
}

function fill(dst: PNG, r: Rect, rgb: Rgb): void {
  const x0 = Math.max(0, r.x);
  const y0 = Math.max(0, r.y);
  const x1 = Math.min(dst.width, r.x + r.w);
  const y1 = Math.min(dst.height, r.y + r.h);
  for (let y = y0; y < y1; y++) {
    for (let x = x0; x < x1; x++) {
      const o = (y * dst.width + x) * 4;
      dst.data[o] = rgb[0];
      dst.data[o + 1] = rgb[1];
      dst.data[o + 2] = rgb[2];
      dst.data[o + 3] = 255;
    }
  }
}

function drawBox(dst: PNG, r: Rect): void {
  const t = BOX_STROKE;
  fill(dst, { x: r.x, y: r.y, w: r.w, h: t }, BOX_RGB);
  fill(dst, { x: r.x, y: r.y + r.h - t, w: r.w, h: t }, BOX_RGB);
  fill(dst, { x: r.x, y: r.y, w: t, h: r.h }, BOX_RGB);
  fill(dst, { x: r.x + r.w - t, y: r.y, w: t, h: r.h }, BOX_RGB);
}

/**
 * Crop a before/after pair to its changed spots and draw a red box around
 * each change on both halves, at the same window-relative spot. Height
 * changes are aligned as one inserted (or removed) band; see the content-shift
 * note in vr-crop.ts. Only a width change falls back to the uncropped pair.
 */
export function cropPair(before: Buffer, after: Buffer, opts: FocusOptions = DEFAULT_FOCUS): CropPairResult {
  const a = PNG.sync.read(before);
  const b = PNG.sync.read(after);
  const uncropped = (plan: CropPlan): CropPairResult => ({
    plan,
    imageHeight: b.height,
    pairs: [{ rect: null, boxes: [], before, after }],
  });
  if (a.width !== b.width) return uncropped({ kind: 'uncropped', reason: 'dimensions-changed' });

  const width = a.width;
  const afterTaller = b.height >= a.height;
  const tall = afterTaller ? b : a;
  const short = afterTaller ? a : b;
  const shortH = short.height;
  const d = tall.height - shortH;

  // Mask in taller-image rows: offset-0 rows above the split, the band, then
  // offset-d rows below it.
  const mask0 = redMask(short, 0, tall, 0, shortH);
  let mask = mask0;
  let s = shortH;
  if (d > 0) {
    const maskD = redMask(short, 0, tall, d, shortH);
    s = bestSplit(rowFlags(mask0, width, shortH), rowFlags(maskD, width, shortH), shortH);
    mask = new Uint8Array(width * tall.height);
    mask.set(mask0.subarray(0, s * width), 0);
    mask.set(bandMask(tall.data, short.data, width, shortH, s, d), s * width);
    mask.set(maskD.subarray(s * width), (s + d) * width);
  }

  const plan = planCrop({ width, height: a.height }, { width, height: b.height }, mask, opts);
  if (plan.kind === 'uncropped') return uncropped(plan);

  const pairs = plan.spots.map((spot) => {
    const { x, w, h } = spot.rect;
    const tallY = spot.rect.y;
    const shortY = mapRow(tallY, s, d);
    const tallCut = cutPng(tall, x, tallY, w, h);
    const shortCut = cutPng(short, x, shortY, w, h);
    for (const box of spot.boxes) {
      drawBox(tallCut, box);
      drawBox(shortCut, box);
    }
    const [beforeCut, afterCut] = afterTaller ? [shortCut, tallCut] : [tallCut, shortCut];
    return {
      rect: { x, y: afterTaller ? tallY : shortY, w, h },
      boxes: spot.boxes,
      before: PNG.sync.write(beforeCut),
      after: PNG.sync.write(afterCut),
    };
  });
  return { plan, imageHeight: b.height, pairs };
}

/**
 * One focused before/after pair for surfaces with a single image slot (manual
 * rows, agent shots): every spot folds into one window with all its boxes.
 * An uncropped pair comes back unchanged.
 */
export function focusPair(before: Buffer, after: Buffer): { before: Buffer; after: Buffer } {
  const pair = cropPair(before, after, { ...DEFAULT_FOCUS, maxSpots: 1 }).pairs[0]!;
  return { before: pair.before, after: pair.after };
}

// Before/after composites for the PR body. `side` puts before left of after
// (desktop), `stack` puts before above after (mobile). Each half sits under a
// PAIR_BAND-px colour band (red = before, green = after) with a grey PAIR_GAP
// between halves. Unequal sizes (the uncropped case) sit top-left on white.
export type PairLayout = 'side' | 'stack';
export const PAIR_GAP = 16;
export const PAIR_BAND = 12;
export const BEFORE_BAND: Rgb = [209, 36, 47];
export const AFTER_BAND: Rgb = [26, 127, 55];
export const GAP_GREY: Rgb = [208, 215, 222];

export function composePair(before: Buffer, after: Buffer, layout: PairLayout): Buffer {
  const a = PNG.sync.read(before);
  const b = PNG.sync.read(after);
  const side = layout === 'side';
  const width = side ? a.width + PAIR_GAP + b.width : Math.max(a.width, b.width);
  const height = side
    ? Math.max(a.height, b.height) + PAIR_BAND
    : a.height + b.height + 2 * PAIR_BAND + PAIR_GAP;
  const dst = new PNG({ width, height });
  dst.data.fill(255); // opaque white

  const bx = side ? a.width + PAIR_GAP : 0;
  const by = side ? 0 : a.height + PAIR_BAND + PAIR_GAP;
  const gap = side
    ? { x: a.width, y: 0, w: PAIR_GAP, h: height }
    : { x: 0, y: a.height + PAIR_BAND, w: width, h: PAIR_GAP };
  fill(dst, gap, GAP_GREY);
  fill(dst, { x: 0, y: 0, w: side ? a.width : width, h: PAIR_BAND }, BEFORE_BAND);
  fill(dst, { x: bx, y: by, w: side ? b.width : width, h: PAIR_BAND }, AFTER_BAND);
  PNG.bitblt(a, dst, 0, 0, a.width, a.height, 0, PAIR_BAND);
  PNG.bitblt(b, dst, 0, 0, b.width, b.height, bx, by + PAIR_BAND);
  return PNG.sync.write(dst);
}
