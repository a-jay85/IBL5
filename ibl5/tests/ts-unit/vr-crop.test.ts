import { describe, it, expect } from 'vitest';
import {
  boundingBox,
  padRect,
  clusterSpots,
  planCrop,
  planSpots,
  bestSplit,
  bandMask,
  mapRow,
  regionLabel,
  type Rect,
} from '../e2e/vr-crop';

function maskOf(width: number, height: number, points: Array<[number, number]>): Uint8Array {
  const m = new Uint8Array(width * height);
  for (const [x, y] of points) m[y * width + x] = 1;
  return m;
}

function union(a: Rect, b: Rect): Rect {
  const x0 = Math.min(a.x, b.x);
  const y0 = Math.min(a.y, b.y);
  const x1 = Math.max(a.x + a.w, b.x + b.w);
  const y1 = Math.max(a.y + a.h, b.y + b.h);
  return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
}

describe('boundingBox', () => {
  it('1a returns a tight bbox for a single set pixel', () => {
    expect(boundingBox(maskOf(100, 100, [[10, 20]]), 100, 100)).toEqual({ x: 10, y: 20, w: 1, h: 1 });
  });

  it('1b returns null on an empty mask', () => {
    expect(boundingBox(new Uint8Array(100 * 100), 100, 100)).toBeNull();
  });
});

describe('padRect', () => {
  it('2a pads an interior rect by exactly padding on all sides', () => {
    expect(padRect({ x: 50, y: 60, w: 10, h: 10 }, 24, 200, 200)).toEqual({ x: 26, y: 36, w: 58, h: 58 });
  });

  it('2b clamps at the top-left corner', () => {
    expect(padRect({ x: 0, y: 0, w: 1, h: 1 }, 24, 100, 100)).toEqual({ x: 0, y: 0, w: 25, h: 25 });
  });

  it('2c clamps at the bottom-right corner', () => {
    expect(padRect({ x: 99, y: 99, w: 1, h: 1 }, 24, 100, 100)).toEqual({ x: 75, y: 75, w: 25, h: 25 });
  });
});

describe('clusterSpots', () => {
  it('3a splits two pixels 400 px apart into 2 rects', () => {
    const rects = clusterSpots(maskOf(100, 1000, [[50, 100], [50, 500]]), 100, 1000);
    expect(rects).toHaveLength(2);
  });

  it('3b merges two pixels whose padded rects touch into 1 rect', () => {
    // Tiles 0 and 3 are not 8-adjacent, so they are separate components, but the
    // padded rects ([0,35) and [34,83)) overlap and must merge.
    const rects = clusterSpots(maskOf(200, 100, [[10, 50], [58, 50]]), 200, 100);
    expect(rects).toHaveLength(1);
  });

  it('3c caps at maxSpots, the last rect being the union of the rest', () => {
    const ys = [50, 250, 450, 650, 850];
    const w = 100;
    const h = 1000;
    const rects = clusterSpots(maskOf(w, h, ys.map((y): [number, number] => [50, y])), w, h, {
      padding: 24,
      cell: 16,
      maxSpots: 3,
    });
    expect(rects).toHaveLength(3);
    const padded = ys.map((y) => padRect({ x: 50, y, w: 1, h: 1 }, 24, w, h));
    expect(rects[2]).toEqual(union(union(padded[2]!, padded[3]!), padded[4]!));
  });

  it('3d sorts output top-to-bottom', () => {
    const rects = clusterSpots(maskOf(100, 1000, [[50, 800], [50, 100], [50, 450]]), 100, 1000);
    const ys = rects.map((r) => r.y);
    expect(ys).toEqual([...ys].sort((a, b) => a - b));
  });

  it('3e keeps every rect inside the image for pixels on all four edges', () => {
    const w = 100;
    const h = 100;
    const rects = clusterSpots(maskOf(w, h, [[0, 50], [99, 50], [50, 0], [50, 99]]), w, h);
    expect(rects.length).toBeGreaterThan(0);
    for (const r of rects) {
      expect(r.x).toBeGreaterThanOrEqual(0);
      expect(r.y).toBeGreaterThanOrEqual(0);
      expect(r.x + r.w).toBeLessThanOrEqual(w);
      expect(r.y + r.h).toBeLessThanOrEqual(h);
    }
  });
});

describe('planCrop', () => {
  it('4a falls back uncropped when the width changes', () => {
    expect(planCrop({ width: 100, height: 100 }, { width: 120, height: 100 }, new Uint8Array(120 * 100))).toEqual({
      kind: 'uncropped',
      reason: 'dimensions-changed',
    });
  });

  it('4b falls back uncropped on an empty mask at the same size', () => {
    expect(planCrop({ width: 100, height: 100 }, { width: 100, height: 100 }, new Uint8Array(100 * 100))).toEqual({
      kind: 'uncropped',
      reason: 'no-pixel-mask',
    });
  });

  it('4c returns one spot for a single set pixel at the same size', () => {
    const plan = planCrop({ width: 100, height: 100 }, { width: 100, height: 100 }, maskOf(100, 100, [[40, 40]]));
    expect(plan.kind).toBe('spots');
    if (plan.kind === 'spots') expect(plan.spots).toHaveLength(1);
  });

  it('4d accepts a height change with a mask over the taller image', () => {
    const plan = planCrop({ width: 100, height: 100 }, { width: 100, height: 106 }, maskOf(100, 106, [[40, 40]]));
    expect(plan.kind).toBe('spots');
  });
});

describe('bestSplit', () => {
  it('6a finds the row where a band was inserted', () => {
    // Rows >= 30 differ at offset 0 and match at offset d.
    const diff0 = new Uint8Array(100).fill(0, 0, 30).fill(1, 30);
    const diffD = new Uint8Array(100).fill(1, 0, 30).fill(0, 30);
    expect(bestSplit(diff0, diffD, 100)).toBe(30);
  });

  it('6b keeps the lowest split on a tie', () => {
    expect(bestSplit(new Uint8Array(10), new Uint8Array(10), 10)).toBe(0);
  });
});

describe('mapRow', () => {
  it('7a maps rows above, inside and below the band', () => {
    expect(mapRow(10, 30, 6)).toBe(10);
    expect(mapRow(32, 30, 6)).toBe(30);
    expect(mapRow(40, 30, 6)).toBe(34);
  });
});

describe('bandMask', () => {
  const rgba = (w: number, h: number, v: number) => new Uint8Array(w * h * 4).fill(v);

  it('8a marks only band pixels that differ from both seam rows', () => {
    const short = rgba(10, 4, 255);
    const tall = rgba(10, 6, 255);
    // Band rows 2-3, dark at x 3..6.
    for (const y of [2, 3]) for (let x = 3; x < 7; x++) tall.fill(0, (y * 10 + x) * 4, (y * 10 + x) * 4 + 3);
    const m = bandMask(tall, short, 10, 4, 2, 2);
    expect(Array.from(m.subarray(0, 10))).toEqual([0, 0, 0, 1, 1, 1, 1, 0, 0, 0]);
  });

  it('8b marks the full band when it matches its neighbours (blank space)', () => {
    const m = bandMask(rgba(10, 6, 255), rgba(10, 4, 255), 10, 4, 2, 2);
    expect(m.every((v) => v === 1)).toBe(true);
  });
});

describe('planSpots', () => {
  it('9a puts nearby boxes in one window and far ones in separate windows', () => {
    const near = planSpots(maskOf(300, 1000, [[50, 100], [150, 110]]), 300, 1000);
    expect(near).toHaveLength(1);
    expect(near[0]!.boxes).toHaveLength(2);
    const far = planSpots(maskOf(300, 1000, [[50, 100], [50, 800]]), 300, 1000);
    expect(far).toHaveLength(2);
  });

  it('9b gives window-relative boxes', () => {
    const [spot] = planSpots(maskOf(300, 300, [[150, 150]]), 300, 300);
    expect(spot!.rect).toEqual({ x: 89, y: 89, w: 123, h: 123 });
    expect(spot!.boxes).toEqual([{ x: 56, y: 56, w: 11, h: 11 }]);
  });
});

describe('regionLabel', () => {
  it('5a labels a rect centred near the top as top', () => {
    expect(regionLabel({ x: 0, y: 0, w: 10, h: 20 }, 900)).toMatch(/^top/);
  });

  it('5b labels a rect centred near the bottom as bottom', () => {
    expect(regionLabel({ x: 0, y: 840, w: 10, h: 20 }, 900)).toMatch(/^bottom/);
  });
});
