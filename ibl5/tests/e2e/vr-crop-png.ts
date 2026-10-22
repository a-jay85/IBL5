// PNG layer for the changed-screen crop (Buffer in, Buffer out; no fs).
// Sibling of vr-gallery.ts, same pngjs + pixelmatch stack. The pure spot
// math lives in vr-crop.ts; bin/vr-review-comment does the file I/O.
//
// The mask is pixelmatch's own diff at the gate threshold with `diffMask`,
// keeping only the red diff pixels. Anti-aliased (yellow) pixels are not
// counted, so a crop marks the same pixels the VR gate counts.
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import { DEFAULT_CROP, planCrop, type CropOptions, type CropPlan, type Rect } from './vr-crop';
import { GATE_PIXEL_THRESHOLD } from './vr-gallery';

export type CropPair = { rect: Rect | null; before: Buffer; after: Buffer };

export type CropPairResult = { plan: CropPlan; imageHeight: number; pairs: CropPair[] };

function cut(src: PNG, r: Rect): Buffer {
  const dst = new PNG({ width: r.w, height: r.h });
  PNG.bitblt(src, dst, r.x, r.y, r.w, r.h, 0, 0);
  return PNG.sync.write(dst);
}

export function cropPair(before: Buffer, after: Buffer, opts: CropOptions = DEFAULT_CROP): CropPairResult {
  const a = PNG.sync.read(before);
  const b = PNG.sync.read(after);
  const sameDims = a.width === b.width && a.height === b.height;

  let mask: Uint8Array | null = null;
  if (sameDims) {
    const { width, height } = a;
    const out = new PNG({ width, height });
    pixelmatch(a.data, b.data, out.data, width, height, {
      threshold: GATE_PIXEL_THRESHOLD,
      diffMask: true,
      diffColor: [255, 0, 0],
      aaColor: [255, 255, 0],
    });
    mask = new Uint8Array(width * height);
    for (let i = 0; i < width * height; i++) {
      const o = i * 4;
      if (out.data[o + 3] > 0 && out.data[o] === 255 && out.data[o + 1] === 0 && out.data[o + 2] === 0) {
        mask[i] = 1;
      }
    }
  }

  const plan = planCrop({ width: a.width, height: a.height }, { width: b.width, height: b.height }, mask, opts);
  if (plan.kind === 'uncropped') {
    return { plan, imageHeight: b.height, pairs: [{ rect: null, before, after }] };
  }
  return {
    plan,
    imageHeight: b.height,
    pairs: plan.rects.map((rect) => ({ rect, before: cut(a, rect), after: cut(b, rect) })),
  };
}

// Before/after composites for the PR body. `side` puts before left of after
// (desktop), `stack` puts before above after (mobile). Each half sits under a
// PAIR_BAND-px colour band (red = before, green = after) with a grey PAIR_GAP
// between halves. Unequal sizes (the uncropped case) sit top-left on white.
export type PairLayout = 'side' | 'stack';
export const PAIR_GAP = 16;
export const PAIR_BAND = 12;
type Rgb = [number, number, number];
export const BEFORE_BAND: Rgb = [209, 36, 47];
export const AFTER_BAND: Rgb = [26, 127, 55];
export const GAP_GREY: Rgb = [208, 215, 222];

function fill(dst: PNG, r: Rect, rgb: Rgb): void {
  for (let y = r.y; y < r.y + r.h; y++) {
    for (let x = r.x; x < r.x + r.w; x++) {
      const o = (y * dst.width + x) * 4;
      dst.data[o] = rgb[0];
      dst.data[o + 1] = rgb[1];
      dst.data[o + 2] = rgb[2];
      dst.data[o + 3] = 255;
    }
  }
}

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
