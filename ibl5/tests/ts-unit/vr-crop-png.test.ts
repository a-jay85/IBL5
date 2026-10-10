import { describe, it, expect } from 'vitest';
import { PNG } from 'pngjs';
import { cropPair } from '../e2e/vr-crop-png';

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

describe('cropPair', () => {
  it('9a crops a 4x4 change in a 64x64 image to a padded 48x48 pair', () => {
    const before = PNG.sync.write(solidPng(64, 64, WHITE));
    const after = PNG.sync.write(paint(solidPng(64, 64, WHITE), 40, 40, 4, 4, BLACK));
    const res = cropPair(before, after);

    expect(res.plan.kind).toBe('spots');
    expect(res.pairs).toHaveLength(1);
    expect(res.pairs[0].rect).toEqual({ x: 16, y: 16, w: 48, h: 48 });

    const b = PNG.sync.read(res.pairs[0].before);
    const a = PNG.sync.read(res.pairs[0].after);
    expect([b.width, b.height]).toEqual([48, 48]);
    expect([a.width, a.height]).toEqual([48, 48]);
    expect(b.data.every((v) => v === 255)).toBe(true);
    // (40,40) in the source is (24,24) in the crop.
    const o = (24 * 48 + 24) * 4;
    expect([a.data[o], a.data[o + 1], a.data[o + 2]]).toEqual(BLACK);
  });

  it('9b returns the input buffers uncropped when dimensions change', () => {
    const before = PNG.sync.write(solidPng(64, 64, WHITE));
    const after = PNG.sync.write(solidPng(64, 80, WHITE));
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
