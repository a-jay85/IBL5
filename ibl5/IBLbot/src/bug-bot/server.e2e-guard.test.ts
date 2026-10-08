import { describe, it, expect } from 'vitest';
import { spawnSync } from 'node:child_process';
import * as os from 'node:os';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

// Pins the BUG_BOT_E2E_MESSAGE_ID guard in server.e2e.test.ts (beforeAll inside
// describe.runIf(E2E)). E2E/SEED/BASE are module-level consts read at import, so
// the only way to exercise the guard is a child vitest run with its own env.
// SAFETY: the child env is an allowlist built from scratch. It NEVER carries
// BUG_BOT_E2E_MESSAGE_ID with a value, and BUG_BOT_E2E_PORT is pinned to a closed
// port, so even if the guard were deleted the child cannot reach the live
// bug-bot-test instance on :50002 or drive real Discord writes.

const IBLBOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const VITEST_BIN = path.join(IBLBOT, 'node_modules', 'vitest', 'vitest.mjs');
const TARGET = 'src/bug-bot/server.e2e.test.ts';
const GUARD_MESSAGE = 'BUG_BOT_E2E_MESSAGE_ID is required when BUG_BOT_E2E=1';
const CLOSED_PORT = '1';
const CHILD_TIMEOUT_MS = 60_000;
const TEST_TIMEOUT_MS = 90_000;

type ChildResult = { status: number | null; output: string };

function runE2eFile(extraEnv: Record<string, string>): ChildResult {
    const env: Record<string, string> = {
        PATH: process.env['PATH'] ?? '',
        HOME: process.env['HOME'] ?? os.tmpdir(),
        TMPDIR: process.env['TMPDIR'] ?? os.tmpdir(),
        NO_COLOR: '1',
        FORCE_COLOR: '0',
        BUG_BOT_E2E_PORT: CLOSED_PORT,
        ...extraEnv,
    };
    const res = spawnSync(process.execPath, [VITEST_BIN, 'run', TARGET], {
        cwd: IBLBOT,
        env,
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        timeout: CHILD_TIMEOUT_MS,
    });
    return { status: res.status, output: `${res.stdout ?? ''}\n${res.stderr ?? ''}` };
}

describe('server.e2e.test.ts BUG_BOT_E2E_MESSAGE_ID guard', () => {
    it('fails the suite with the exact message when the seed is unset', () => {
        const r = runE2eFile({ BUG_BOT_E2E: '1' });
        expect(r.status).not.toBe(0);
        expect(r.status).not.toBeNull();
        expect(r.output).toContain(GUARD_MESSAGE);
        expect(r.output).toMatch(/Test Files\s+1 failed \(1\)/);
    }, TEST_TIMEOUT_MS);

    it('fails the suite with the exact message when the seed is the empty string', () => {
        const r = runE2eFile({ BUG_BOT_E2E: '1', BUG_BOT_E2E_MESSAGE_ID: '' });
        expect(r.status).not.toBe(0);
        expect(r.status).not.toBeNull();
        expect(r.output).toContain(GUARD_MESSAGE);
        expect(r.output).toMatch(/Test Files\s+1 failed \(1\)/);
    }, TEST_TIMEOUT_MS);

    it('skips the whole file and exits 0 when BUG_BOT_E2E is unset', () => {
        const r = runE2eFile({});
        expect(r.status).toBe(0);
        expect(r.output).not.toContain(GUARD_MESSAGE);
        expect(r.output).toMatch(/Test Files\s+1 skipped \(1\)/);
    }, TEST_TIMEOUT_MS);
});
