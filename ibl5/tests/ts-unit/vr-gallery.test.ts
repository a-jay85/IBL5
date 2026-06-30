import { describe, it, expect } from 'vitest';
import { PNG } from 'pngjs';
import {
  triageCell,
  buildGalleryHtml,
  type GalleryCell,
} from '../e2e/vr-gallery';

// Build a solid-color PNG of the given size as encoded bytes. The triage logic
// runs the real pixelmatch/pngjs path against these synthetic images — no mocks.
function solidPng(width: number, height: number, rgb: [number, number, number]): Buffer {
  const png = new PNG({ width, height });
  for (let i = 0; i < width * height; i++) {
    const o = i * 4;
    png.data[o] = rgb[0];
    png.data[o + 1] = rgb[1];
    png.data[o + 2] = rgb[2];
    png.data[o + 3] = 255;
  }
  return PNG.sync.write(png);
}

const RED: [number, number, number] = [255, 0, 0];
const BLUE: [number, number, number] = [0, 0, 255];

describe('triageCell', () => {
  it('V3a: afterA missing → infra', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: null,
      afterB: solidPng(4, 4, RED),
    });
    expect(res.verdict).toBe('infra');
  });

  it('V3b: no committed baseline → new', () => {
    const res = triageCell({
      before: null,
      afterA: solidPng(4, 4, RED),
      afterB: solidPng(4, 4, RED),
    });
    expect(res.verdict).toBe('new');
  });

  it('V3c: PR renders disagree on size → flake', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, RED),
      afterB: solidPng(8, 8, RED),
    });
    expect(res.verdict).toBe('flake');
  });

  it('V3d: PR renders disagree on pixels (> T) → flake', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, RED),
      afterB: solidPng(4, 4, BLUE),
    });
    expect(res.verdict).toBe('flake');
  });

  it('V3e: baseline dims ≠ render dims → changed, diff is the render itself', () => {
    const afterA = solidPng(8, 8, RED);
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA,
      afterB: solidPng(8, 8, RED),
    });
    expect(res.verdict).toBe('changed');
    expect(res.diff).toEqual(afterA);
    expect(res.changedRatio).toBeUndefined();
  });

  it('V3f: render matches baseline within T → unchanged', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, RED),
      afterB: solidPng(4, 4, RED),
    });
    expect(res.verdict).toBe('unchanged');
  });

  it('V3g: render differs from baseline → changed with diff + ratio', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, BLUE),
      afterB: solidPng(4, 4, BLUE),
    });
    expect(res.verdict).toBe('changed');
    expect(res.diff).toBeInstanceOf(Buffer);
    expect(res.changedRatio).toBeCloseTo(1, 5);
  });

  it('V3h: afterB missing + would-be-changed → demoted to infra', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, BLUE),
      afterB: null,
    });
    expect(res.verdict).toBe('infra');
  });

  it('V3i: afterB missing + unchanged → kept as unchanged', () => {
    const res = triageCell({
      before: solidPng(4, 4, RED),
      afterA: solidPng(4, 4, RED),
      afterB: null,
    });
    expect(res.verdict).toBe('unchanged');
  });

  it('honors a per-cell maxDiffPixelRatio tolerance', () => {
    // One differing pixel out of 16 = ratio 0.0625. With T just above that, it
    // is within tolerance → unchanged; with the default T=0 it would be changed.
    const before = solidPng(4, 4, RED);
    const after = new PNG({ width: 4, height: 4 });
    for (let i = 0; i < 16; i++) {
      const o = i * 4;
      after.data[o] = 255;
      after.data[o + 1] = 0;
      after.data[o + 2] = 0;
      after.data[o + 3] = 255;
    }
    // flip a single pixel to blue
    after.data[0] = 0;
    after.data[2] = 255;
    const afterBuf = PNG.sync.write(after);
    expect(
      triageCell({ before, afterA: afterBuf, afterB: afterBuf, maxDiffPixelRatio: 0.1 }).verdict,
    ).toBe('unchanged');
    expect(
      triageCell({ before, afterA: afterBuf, afterB: afterBuf }).verdict,
    ).toBe('changed');
  });
});

describe('buildGalleryHtml', () => {
  const cells: GalleryCell[] = [
    { module: 'Standings', viewport: 'desktop', title: 'standings', verdict: 'changed' },
    { module: 'Team', viewport: 'mobile', title: 'team-roster-mobile', verdict: 'new', isNew: true },
  ];

  it('emits an anchored h3 per cell so comment links can jump to it', () => {
    const html = buildGalleryHtml(cells);
    expect(html).toContain('<h3 id="standings">');
    expect(html).toContain('<h3 id="team-roster-mobile">');
  });

  it('shows before/after/diff for a changed cell, after-only for a new cell', () => {
    const html = buildGalleryHtml(cells);
    expect(html).toContain('src="standings.before.png"');
    expect(html).toContain('src="standings.after.png"');
    expect(html).toContain('src="standings.diff.png"');
    expect(html).toContain('src="team-roster-mobile.after.png"');
    expect(html).not.toContain('src="team-roster-mobile.before.png"');
  });

  it('groups cells under a module heading', () => {
    const html = buildGalleryHtml(cells);
    expect(html).toContain('<h2>Standings</h2>');
    expect(html).toContain('<h2>Team</h2>');
  });
});
