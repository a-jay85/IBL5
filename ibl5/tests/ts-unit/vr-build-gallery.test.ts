import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { PNG } from 'pngjs';
import { execFileSync, spawnSync } from 'child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'fs';
import { tmpdir } from 'os';
import { dirname, join, resolve } from 'path';
import { fileURLToPath } from 'url';

// Drives the real bin/vr-build-gallery as a subprocess (it runs top-level code
// on import), so the gallery.json bytes and the stderr summary line are pinned
// end to end. Every output path is absolute under the OS temp dir, so nothing
// lands in the repo tree.
const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const script = join(repoRoot, 'bin/vr-build-gallery');
const baselinePath = 'ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots/standings.png';

const headSha = execFileSync('git', ['rev-parse', 'HEAD'], {
  cwd: repoRoot,
  encoding: 'utf-8',
}).trim();
const baseline = execFileSync('git', ['show', `HEAD:${baselinePath}`], {
  cwd: repoRoot,
  maxBuffer: 64 * 1024 * 1024,
});

// Subtract `delta` from R, G and B inside the region. A uniform 40-step luma
// shift has min-t of about 0.1515: invisible at the gate's 0.2, visible at the
// strict 0.05, and its 400 pixels clear the 25-pixel floor.
function shiftRegion(png: Buffer, delta = 40, x = 0, y = 0, w = 20, h = 20): Buffer {
  const img = PNG.sync.read(png);
  for (let row = y; row < y + h; row++) {
    for (let col = x; col < x + w; col++) {
      const o = (row * img.width + col) * 4;
      img.data[o] = Math.max(0, img.data[o] - delta);
      img.data[o + 1] = Math.max(0, img.data[o + 1] - delta);
      img.data[o + 2] = Math.max(0, img.data[o + 2] - delta);
    }
  }
  return PNG.sync.write(img);
}

describe('bin/vr-build-gallery', () => {
  let tmp: string;
  let act: string;
  let out: string;
  let json: string;

  beforeEach(() => {
    tmp = mkdtempSync(join(tmpdir(), 'vrbg-'));
    act = join(tmp, 'actuals');
    out = join(tmp, 'gallery');
    json = join(tmp, 'gallery.json');
    mkdirSync(act, { recursive: true });
  });

  afterEach(() => {
    rmSync(tmp, { recursive: true, force: true });
  });

  function run(): { stderr: string; status: number | null } {
    const res = spawnSync(
      'bun',
      [script, `--base-sha=${headSha}`, `--actuals=${act}`, `--out-dir=${out}`, `--gallery-json=${json}`],
      { cwd: repoRoot, encoding: 'utf-8', stdio: ['ignore', 'pipe', 'pipe'] },
    );
    return { stderr: res.stderr, status: res.status };
  }

  const writeCell = (suffix: 'a' | 'b', buf: Buffer): void => {
    writeFileSync(join(act, `standings.${suffix}.png`), buf);
  };

  it('G1: identical renders produce an empty gallery and the unchanged summary line', () => {
    writeCell('a', baseline);
    writeCell('b', baseline);
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stderr).toBe('vr-build-gallery: 0 changed, 0 new, 0 infra/flake\n');
    expect(readFileSync(json, 'utf-8')).toBe(
      JSON.stringify({ changedCells: [], newCells: [], flakeCells: [] }, null, 2) + '\n',
    );
  });

  it('G2: a sub-gate recolor surfaces as a changed cell with the lean gallery.json shape', () => {
    const shifted = shiftRegion(baseline);
    writeCell('a', shifted);
    writeCell('b', shifted);
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stderr).toContain('vr-build-gallery: 1 changed, 0 new, 0 infra/flake');
    const parsed = JSON.parse(readFileSync(json, 'utf-8'));
    expect(Object.keys(parsed).sort()).toEqual(['changedCells', 'flakeCells', 'newCells']);
    expect(Object.keys(parsed.changedCells[0]).sort()).toEqual(['module', 'title', 'viewport']);
    expect(parsed.changedCells[0].title).toBe('standings');
    expect(parsed.changedCells[0].viewport).toBe('desktop');
    for (const suffix of ['before', 'after', 'diff']) {
      expect(existsSync(join(out, `standings.${suffix}.png`))).toBe(true);
    }
  });

  it('G3: a strict-unstable cell stays out of the gallery and out of flakeCells', () => {
    writeCell('a', shiftRegion(baseline));
    writeCell('b', baseline);
    const res = run();
    expect(res.stderr).toContain('vr-build-gallery: 0 changed, 0 new, 0 infra/flake');
  });

  it('G4: a missing reload render blocks the strict upgrade', () => {
    writeCell('a', shiftRegion(baseline));
    const res = run();
    expect(res.stderr).toContain('vr-build-gallery: 0 changed, 0 new, 0 infra/flake');
  });
});
