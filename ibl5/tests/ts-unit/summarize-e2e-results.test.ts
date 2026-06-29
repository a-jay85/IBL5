import { describe, it, expect } from 'vitest';
import { execFileSync } from 'child_process';
import { writeFileSync } from 'fs';
import { tmpdir } from 'os';
import { resolve } from 'path';

// summarize-e2e-results.mjs has no importable exports (it runs main at the top
// level), so we drive it as a CLI exactly as .github/workflows/e2e-tests.yml does
// and assert on stdout. Mirrors the CLI-end-to-end block in vr-review-comment.test.ts.

const repoRoot = resolve(__dirname, '../../..');
const SCRIPT = '.github/scripts/summarize-e2e-results.mjs';

function runSummary(report: unknown): string {
  const fixture = `${tmpdir()}/summarize-e2e-${Date.now()}-${Math.random().toString(36).slice(2)}.json`;
  writeFileSync(fixture, JSON.stringify(report));
  return execFileSync('node', [SCRIPT, `--functional=${fixture}`], {
    cwd: repoRoot,
    encoding: 'utf-8',
  });
}

const ALL_PASSED = {
  suites: [{ specs: [{ title: 'loads home', tests: [{ status: 'expected', results: [] }] }] }],
};

const ONE_FAILED = {
  suites: [
    {
      specs: [
        {
          title: 'broken form',
          tests: [
            {
              status: 'unexpected',
              results: [{ errors: [{ message: 'expected true to be false' }], attachments: [] }],
            },
          ],
        },
      ],
    },
  ],
};

describe('summarize-e2e-results.mjs collapse', () => {
  it('clean → all-passed section collapses under a <details> summary', () => {
    const md = runSummary(ALL_PASSED);
    expect(md).toContain('<details><summary>✅ Functional E2E — all 1 tests passed</summary></details>');
  });

  it('failures → section header stays expanded (no ✅ collapse summary)', () => {
    const md = runSummary(ONE_FAILED);
    expect(md).toContain('### Functional E2E — 1 failed (1 total)');
    expect(md).not.toContain('✅ Functional E2E');
  });
});
