import { describe, it, expect } from 'vitest';
import { PNG } from 'pngjs';
import {
  AFTER_BAND,
  BOX_RGB,
  BEFORE_BAND,
  GAP_GREY,
  PAIR_BAND,
  PAIR_GAP,
  composePair,
  cropPair,
  focusPair,
} from '../e2e/vr-crop-png';

function solidPng(width: number, height: number, rgb: [number, number, number]): PNG {
  const png = new PNG({ width, height });
  for (let i = 0; i < width * height; i++) {
    const o = i * 4;
    png.data[o] = rgb[0];
    png.data[o + 1] = rgb[1];
    png.data[o + 2] = rgb[2];
    png.data[o + 3] = 255;
  }
  return png;
}

function paint(png: PNG, x0: number, y0: number, w: number, h: number, rgb: [number, number, number]): PNG {
  for (let y = y0; y < y0 + h; y++) {
    for (let x = x0; x < x0 + w; x++) {
      const o = (y * png.width + x) * 4;
      png.data[o] = rgb[0];
      png.data[o + 1] = rgb[1];
      png.data[o + 2] = rgb[2];
    }
  }
  return png;
}

const WHITE: [number, number, number] = [255, 255, 255];
const BLACK: [number, number, number] = [0, 0, 0];

const ORANGE: [number, number, number] = [234, 88, 12];
const rgbAt = (png: PNG, x: number, y: number) => {
  const o = (y * png.width + x) * 4;
  return [png.data[o], png.data[o + 1], png.data[o + 2]];
};

// A 200x300 page with dark bars at distinct rows, so a shift is detectable.
function page(): PNG {
  const png = solidPng(200, 300, WHITE);
  for (const [y, rgb] of [
    [20, BLACK],
    [150, [40, 90, 200]],
    [200, [20, 140, 60]],
    [250, [120, 30, 160]],
  ] as Array<[number, [number, number, number]]>) {
    paint(png, 20, y, 160, 10, rgb);
  }
  return png;
}

/** Copy of src with `band` rows of colour inserted at row y (x0..x1 coloured, rest white). */
function insertBand(src: PNG, y: number, band: number, x0: number, x1: number, rgb: [number, number, number]): PNG {
  const dst = solidPng(src.width, src.height + band, WHITE);
  PNG.bitblt(src, dst, 0, 0, src.width, y, 0, 0);
  PNG.bitblt(src, dst, 0, y, src.width, src.height - y, 0, y + band);
  return paint(dst, x0, y, x1 - x0, band, rgb);
}

/** Absolute after-image rows/cols covered by every box in a result. */
function absBoxes(res: ReturnType<typeof cropPair>) {
  return res.pairs.flatMap((p) => p.boxes.map((b) => ({ x: p.rect!.x + b.x, y: p.rect!.y + b.y, w: b.w, h: b.h })));
}

describe('cropPair', () => {
  it('9a crops a 4x4 change and boxes it red on both halves', () => {
    const before = PNG.sync.write(solidPng(200, 200, WHITE));
    const after = PNG.sync.write(paint(solidPng(200, 200, WHITE), 100, 100, 4, 4, BLACK));
    const res = cropPair(before, after);

    expect(res.plan.kind).toBe('spots');
    expect(res.pairs).toHaveLength(1);
    const pair = res.pairs[0];
    // Change bbox (100,100,4,4) outset by 5 -> box; box padded by 56 -> window.
    expect(absBoxes(res)).toEqual([{ x: 95, y: 95, w: 14, h: 14 }]);
    expect(pair.rect).toEqual({ x: 39, y: 39, w: 126, h: 126 });

    const b = PNG.sync.read(pair.before);
    const a = PNG.sync.read(pair.after);
    expect([a.width, a.height]).toEqual([126, 126]);
    expect([b.width, b.height]).toEqual([126, 126]);
    // Same box spot on both halves; the change itself is not painted over.
    expect(rgbAt(a, 56, 56)).toEqual(BOX_RGB);
    expect(rgbAt(b, 56, 56)).toEqual(BOX_RGB);
    expect(rgbAt(a, 61, 61)).toEqual(BLACK);
    expect(rgbAt(b, 61, 61)).toEqual(WHITE);
  });

  it('9f boxes only an inserted band when everything below it shifts down', () => {
    const src = page();
    const before = PNG.sync.write(src);
    const after = PNG.sync.write(insertBand(src, 100, 6, 40, 160, ORANGE));
    const res = cropPair(before, after);

    expect(res.plan.kind).toBe('spots');
    expect(res.pairs).toHaveLength(1);
    // Band rows 100-105, cols 40-159, outset 5. Nothing from the shifted bars.
    expect(absBoxes(res)).toEqual([{ x: 35, y: 95, w: 130, h: 16 }]);
    const pair = res.pairs[0];
    expect(pair.rect!.y).toBe(39);
    expect(pair.rect!.h).toBe(128);

    const a = PNG.sync.read(pair.after);
    const b = PNG.sync.read(pair.before);
    expect([b.width, b.height]).toEqual([a.width, a.height]);
    const box = pair.boxes[0];
    expect(rgbAt(a, box.x, box.y)).toEqual(BOX_RGB);
    expect(rgbAt(b, box.x, box.y)).toEqual(BOX_RGB);
    // Inside the box: orange after, white before.
    expect(rgbAt(a, box.x + 10, box.y + 6)).toEqual(ORANGE);
    expect(rgbAt(b, box.x + 10, box.y + 6)).toEqual(WHITE);
  });

  it('9g boxes only the removed band when content shifts up', () => {
    const banded = insertBand(page(), 100, 6, 40, 160, ORANGE);
    const res = cropPair(PNG.sync.write(banded), PNG.sync.write(page()));

    expect(res.pairs).toHaveLength(1);
    expect(res.pairs[0].boxes).toHaveLength(1);
    expect(absBoxes(res)).toEqual([{ x: 35, y: 95, w: 130, h: 16 }]);
  });

  it('9h two separate changes give two crops with one box each', () => {
    const src = solidPng(200, 400, WHITE);
    const before = PNG.sync.write(src);
    const after = PNG.sync.write(paint(paint(solidPng(200, 400, WHITE), 30, 30, 10, 10, BLACK), 150, 330, 10, 10, BLACK));
    const res = cropPair(before, after);

    expect(res.pairs).toHaveLength(2);
    expect(res.pairs.map((p) => p.boxes.length)).toEqual([1, 1]);
    expect(absBoxes(res)).toEqual([
      { x: 25, y: 25, w: 20, h: 20 },
      { x: 145, y: 325, w: 20, h: 20 },
    ]);
  });

  it('9i no change gives no box and the full frame', () => {
    const before = PNG.sync.write(page());
    const after = PNG.sync.write(page());
    const res = cropPair(before, after);

    expect(res.plan).toEqual({ kind: 'uncropped', reason: 'no-pixel-mask' });
    expect(res.pairs).toHaveLength(1);
    expect(res.pairs[0].boxes).toEqual([]);
    expect(res.pairs[0].before.equals(before)).toBe(true);
    expect(res.pairs[0].after.equals(after)).toBe(true);
  });

  it('9j focusPair folds every spot into one boxed window', () => {
    const before = PNG.sync.write(solidPng(200, 400, WHITE));
    const after = PNG.sync.write(paint(paint(solidPng(200, 400, WHITE), 30, 30, 10, 10, BLACK), 150, 330, 10, 10, BLACK));
    const f = focusPair(before, after);
    const a = PNG.sync.read(f.after);
    const b = PNG.sync.read(f.before);
    // Union of both padded windows: x 0..200, y 0..400 clamped.
    expect([a.width, a.height]).toEqual([b.width, b.height]);
    expect(rgbAt(a, 25, 25)).toEqual(BOX_RGB);
    expect(rgbAt(b, 145, 325)).toEqual(BOX_RGB);
  });

  it('9b returns the input buffers uncropped when the width changes', () => {
    const before = PNG.sync.write(solidPng(64, 64, WHITE));
    const after = PNG.sync.write(solidPng(80, 64, WHITE));
    const res = cropPair(before, after);

    expect(res.plan).toEqual({ kind: 'uncropped', reason: 'dimensions-changed' });
    expect(res.pairs).toHaveLength(1);
    expect(res.pairs[0].rect).toBeNull();
    expect(res.pairs[0].before.equals(before)).toBe(true);
    expect(res.pairs[0].after.equals(after)).toBe(true);
  });

  it('9c a sub-threshold grey shift gives uncropped no-pixel-mask', () => {
    const before = PNG.sync.write(solidPng(64, 64, [255, 255, 255]));
    const after = PNG.sync.write(solidPng(64, 64, [250, 250, 250]));
    const res = cropPair(before, after);

    expect(res.plan).toEqual({ kind: 'uncropped', reason: 'no-pixel-mask' });
    expect(res.pairs[0].rect).toBeNull();
  });
});

describe('composePair', () => {
  const RED: [number, number, number] = [255, 0, 0];
  const BLUE: [number, number, number] = [0, 0, 255];
  const px = (png: PNG, x: number, y: number) => {
    const o = (y * png.width + x) * 4;
    return [png.data[o], png.data[o + 1], png.data[o + 2]];
  };
  // before 10x20 red, after 30x8 blue: unequal, like an uncropped size change.
  const before = PNG.sync.write(solidPng(10, 20, RED));
  const after = PNG.sync.write(solidPng(30, 8, BLUE));

  it('9d side puts before left of after, banded, with a grey gap', () => {
    const out = PNG.sync.read(composePair(before, after, 'side'));
    expect([out.width, out.height]).toEqual([10 + PAIR_GAP + 30, 20 + PAIR_BAND]);
    expect(px(out, 0, 0)).toEqual(BEFORE_BAND);
    expect(px(out, 10 + PAIR_GAP, 0)).toEqual(AFTER_BAND);
    expect(px(out, 0, PAIR_BAND)).toEqual(RED);
    expect(px(out, 10 + PAIR_GAP, PAIR_BAND)).toEqual(BLUE);
    expect(px(out, 10, PAIR_BAND)).toEqual(GAP_GREY);
    // Short after half leaves white below it.
    expect(px(out, 10 + PAIR_GAP, PAIR_BAND + 8)).toEqual(WHITE);
  });

  it('9e stack puts before above after, banded, with a grey gap', () => {
    const out = PNG.sync.read(composePair(before, after, 'stack'));
    expect([out.width, out.height]).toEqual([30, 20 + 8 + 2 * PAIR_BAND + PAIR_GAP]);
    const afterTop = 20 + PAIR_BAND + PAIR_GAP;
    expect(px(out, 0, 0)).toEqual(BEFORE_BAND);
    expect(px(out, 0, PAIR_BAND)).toEqual(RED);
    expect(px(out, 0, 20 + PAIR_BAND)).toEqual(GAP_GREY);
    expect(px(out, 0, afterTop)).toEqual(AFTER_BAND);
    expect(px(out, 0, afterTop + PAIR_BAND)).toEqual(BLUE);
    // Narrow before half leaves white to its right.
    expect(px(out, 10, PAIR_BAND)).toEqual(WHITE);
  });
});
