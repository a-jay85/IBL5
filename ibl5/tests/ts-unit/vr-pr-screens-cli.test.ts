import { describe, it, expect } from 'vitest';
import { spawnSync } from 'child_process';
import { resolve } from 'path';

// End-to-end checks of the bin/vr-review-comment screens modes (ADR-0179). The pure
// logic is covered in vr-pr-screens.test.ts; these pin the CLI flag parsing, exit
// codes and stdout contract that .github/workflows/vr-pr-screens.yml relies on.

const repoRoot = resolve(__dirname, '../../..');
const CLI = resolve(repoRoot, 'bin/vr-review-comment');
const FIX = 'ibl5/tests/ts-unit/fixtures/vr-screens';
const A = 'a'.repeat(40);
const B = 'b'.repeat(40);
const C = 'c'.repeat(40);
const D = 'd'.repeat(40);
const RUN_URL = 'https://github.com/a-jay85/IBL5/actions/runs/1';

function run(args: string[]): { code: number | null; out: string } {
  const r = spawnSync('bun', [CLI, ...args], { cwd: repoRoot, encoding: 'utf-8', timeout: 60000 });
  return { code: r.status, out: r.stdout ?? '' };
}

function plan(headSha: string): { code: number | null; out: string } {
  return run([
    '--screens-plan',
    '--pr=42',
    `--head-sha=${headSha}`,
    `--cells-json=${FIX}/cells.json`,
    `--prev-dir=${FIX}/prev`,
    `--before-tree=${B}`,
    `--after-tree=${C}`,
    '--gallery-present=true',
    `--run-url=${RUN_URL}`,
    `--body-file-in=${FIX}/body-with-block.md`,
  ]);
}

describe('bin/vr-review-comment screens modes', () => {
  it('cli-splice-dry-run', () => {
    const { code, out } = run([
      '--splice-screens=42',
      `--block-file=${FIX}/prev/block.md`,
      `--body-file-in=${FIX}/body.md`,
      '--dry-run',
    ]);
    expect(code).toBe(0);
    expect(out.split('vr-screens:begin').length - 1).toBe(1);
    expect(out.indexOf('vr-screens:begin')).toBeLessThan(out.indexOf('## Manual Testing'));
  });

  it('cli-plan-noop', () => {
    const { code, out } = plan(A);
    expect(code).toBe(0);
    expect(out).toContain('noop=true');
    expect(out).toContain('sides=[]');
  });

  it('cli-plan-new-head', () => {
    const { code, out } = plan(D);
    expect(code).toBe(0);
    expect(out).toContain('noop=false');
    expect(out).toContain('sides=[]');
  });

  it('cli-rejects-space-form', () => {
    expect(run(['--splice-screens', '42']).code).toBe(2);
  });

  it('cli-rejects-bad-pr', () => {
    expect(run(['--splice-screens=4x2', '--block-file=x', '--dry-run']).code).toBe(2);
  });

  it('cli-live-check-404', () => {
    expect(run(['--screens-live-check=42', `--body-file-in=${FIX}/body-bogus-url.md`]).code).toBe(1);
  });
});
