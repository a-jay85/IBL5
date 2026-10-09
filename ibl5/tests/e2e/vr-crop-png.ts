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
