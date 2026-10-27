import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { spawnSync } from 'child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'fs';
import { tmpdir } from 'os';
import { dirname, join, resolve } from 'path';
import { fileURLToPath } from 'url';
import { PR_BODY_MARKER_BEGIN, PR_BODY_MARKER_END } from '../e2e/vr-pr-body';

// Drives the real bin/vr-review-comment --update-pr-body in --dry-run mode, so
// the guard that keeps the managed block on a missing or failed gallery build
// is pinned end to end (bin/vr-review-comment runs top-level code on import).
const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
const script = join(repoRoot, 'bin/vr-review-comment');
const PAGES_URL = 'https://a-jay85.github.io/IBL5/deadbeef/visual-review/';
const OLD_PAIR = 'https://a-jay85.github.io/IBL5/old/visual-review/changed/standings.1.before.png';
const OLD_BODY =
  `${PR_BODY_MARKER_BEGIN}\n\n### Changed · standings\n\n![standings before](${OLD_PAIR})\n` +
  `${PR_BODY_MARKER_END}\n\n## Summary\n\nHuman text stays here.`;
const SKIP_LINE = 'leaving the PR body untouched';

describe('vr-review-comment --update-pr-body input guard', () => {
  let dir: string;
  let bodyIn: string;
  let gallery: string;
  let spots: string;

  beforeEach(() => {
    dir = mkdtempSync(join(tmpdir(), 'vr-update-body-'));
    bodyIn = join(dir, 'body-in.md');
    gallery = join(dir, 'gallery.json');
    spots = join(dir, 'spots.json');
    writeFileSync(bodyIn, OLD_BODY);
  });

  afterEach(() => {
    rmSync(dir, { recursive: true, force: true });
  });

  function run(): { stdout: string; stderr: string; status: number | null } {
    const res = spawnSync(
      'bun',
      [
        script,
        `--gallery=${gallery}`,
        `--pages-url=${PAGES_URL}`,
        '--update-pr-body=1',
        `--spots=${spots}`,
        '--head-sha=0000000000000000000000000000000000000000',
        '--run-time=2026-10-27T00:00:00Z',
        '--dry-run',
        `--body-file-in=${bodyIn}`,
      ],
      { cwd: repoRoot, encoding: 'utf-8', stdio: ['ignore', 'pipe', 'pipe'] },
    );
    return { stdout: res.stdout, stderr: res.stderr, status: res.status };
  }

  const emptyGallery = (renderedCells: number): string =>
    JSON.stringify({ changedCells: [], newCells: [], flakeCells: [], renderedCells });

  it('U1: a missing gallery file keeps the old managed block', () => {
    writeFileSync(spots, '[]');
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stdout).toContain(PR_BODY_MARKER_BEGIN);
    expect(res.stdout).toContain(OLD_PAIR);
    expect(res.stdout).toContain('Human text stays here.');
    expect(res.stderr).toContain(SKIP_LINE);
  });

  it('U2: a garbled gallery file keeps the old managed block', () => {
    writeFileSync(gallery, '{not json');
    writeFileSync(spots, '[]');
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stdout).toContain(OLD_PAIR);
    expect(res.stderr).toContain(SKIP_LINE);
  });

  it('U3: a gallery built from zero rendered screens keeps the old managed block', () => {
    writeFileSync(gallery, emptyGallery(0));
    writeFileSync(spots, '[]');
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stdout).toContain(OLD_PAIR);
    expect(res.stderr).toContain(SKIP_LINE);
  });

  it('U4: a changed-cell gallery with a missing spots.json keeps the old managed block', () => {
    writeFileSync(
      gallery,
      JSON.stringify({
        changedCells: [{ module: 'standings', viewport: 'desktop', title: 'standings' }],
        newCells: [],
        flakeCells: [],
        renderedCells: 4,
      }),
    );
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stdout).toContain(OLD_PAIR);
    expect(res.stderr).toContain(SKIP_LINE);
  });

  it('U5: a valid gallery with zero changed screens clears the old managed block', () => {
    writeFileSync(gallery, emptyGallery(4));
    writeFileSync(spots, '[]');
    const res = run();
    expect(res.status).toBe(0);
    expect(res.stdout).not.toContain(PR_BODY_MARKER_BEGIN);
    expect(res.stdout).not.toContain(OLD_PAIR);
    expect(res.stdout).toContain('Human text stays here.');
    expect(res.stderr).not.toContain(SKIP_LINE);
  });
});
