import { describe, it, expect, beforeAll, afterAll, afterEach } from 'vitest';
import { execFileSync } from 'node:child_process';
import * as fs from 'node:fs';
import * as fsp from 'node:fs/promises';
import * as os from 'node:os';
import * as path from 'node:path';
import { fileURLToPath } from 'node:url';

// ecosystem.bugbot-test.config.cjs fails closed so the test bot can never boot on
// production identity. Each case runs a COPY of the config in a temp dir through
// `node -e`: the config reads its env files from __dirname, so the real gitignored
// .env.bugbot* files in IBLbot/ are never read or written, and a subprocess per case
// gives a fresh module cache and turns a real throw into a non-zero exit.

const IBLBOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const CJS_SRC =
    process.env['BUGBOT_CJS_UNDER_TEST'] ?? path.join(IBLBOT, 'ecosystem.bugbot-test.config.cjs');

// Literal copy of the names in the .cjs. Keep it literal so a name dropped from the
// source is caught instead of silently shrinking this list with it.
const REQUIRED = [
    'BUG_BOT_DISCORD_TOKEN',
    'DISCORD_GUILD_ID',
    'BUG_CHANNEL_ID',
    'BUG_PIPELINE_API_BASE_URL',
    'API_KEY',
] as const;

const VALID: Record<string, string> = {
    BUG_BOT_DISCORD_TOKEN: 'test-token-aaa',
    DISCORD_GUILD_ID: '111',
    BUG_CHANNEL_ID: '222',
    BUG_PIPELINE_API_BASE_URL: 'http://127.0.0.1:18080',
    API_KEY: 'test-key',
};

const PRINT_SCRIPT =
    'const a=require(process.argv[1]).apps[0];' +
    'process.stdout.write(JSON.stringify({name:a.name,port:a.env.EXPRESS_PORT,token:a.env.BUG_BOT_DISCORD_TOKEN}))';

type LoadResult = { ok: true; out: string } | { ok: false; stderr: string };

function load(dir: string): LoadResult {
    const cjsCopy = path.join(dir, 'ecosystem.bugbot-test.config.cjs');
    fs.copyFileSync(CJS_SRC, cjsCopy);
    try {
        const out = execFileSync(process.execPath, ['-e', PRINT_SCRIPT, cjsCopy], {
            env: { PATH: process.env['PATH'] ?? '', NODE_PATH: path.join(IBLBOT, 'node_modules') },
            encoding: 'utf8',
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        return { ok: true, out };
    } catch (e) {
        const stderr = (e as { stderr?: string | Buffer }).stderr;
        return { ok: false, stderr: stderr === undefined ? String(e) : stderr.toString() };
    }
}

// A value of the literal string '"   "' stays quoted so dotenv keeps the spaces.
async function writeEnv(dir: string, name: string, vars: Record<string, string>): Promise<void> {
    const body = Object.entries(vars).map(([k, v]) => `${k}=${v}`).join('\n') + '\n';
    await fsp.writeFile(path.join(dir, name), body);
}

function without(vars: Record<string, string>, ...keys: string[]): Record<string, string> {
    const copy = { ...vars };
    for (const k of keys) delete copy[k];
    return copy;
}

function failedStderr(r: LoadResult): string {
    expect(r.ok).toBe(false);
    return r.ok ? '' : r.stderr;
}

const REAL_FILES = ['.env.bugbot.test', '.env.bugbot'].map((n) => path.join(IBLBOT, n));
type FileState = { exists: boolean; mtimeMs: number | null };
function snapshot(): FileState[] {
    return REAL_FILES.map((f) =>
        fs.existsSync(f) ? { exists: true, mtimeMs: fs.statSync(f).mtimeMs } : { exists: false, mtimeMs: null },
    );
}

describe('ecosystem.bugbot-test.config.cjs fail-closed checks', () => {
    let before: FileState[] = [];
    const dirs: string[] = [];

    async function makeDir(): Promise<string> {
        const dir = await fsp.mkdtemp(path.join(os.tmpdir(), 'bugbot-cfg-'));
        dirs.push(dir);
        return dir;
    }

    beforeAll(() => {
        before = snapshot();
    });

    afterEach(async () => {
        for (const d of dirs.splice(0)) await fsp.rm(d, { recursive: true, force: true });
    });

    afterAll(() => {
        expect(snapshot()).toEqual(before);
    });

    it('loads with a complete test env and no production env', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', VALID);
        const r = load(dir);
        expect(r.ok).toBe(true);
        expect(JSON.parse(r.ok ? r.out : '{}')).toEqual({
            name: 'ibl-bug-bot-test',
            port: '50002',
            token: 'test-token-aaa',
        });
    });

    it('throws when .env.bugbot.test is missing', async () => {
        const dir = await makeDir();
        const stderr = failedStderr(load(dir));
        expect(stderr).toContain('.env.bugbot.test — copy .env.bugbot.test.example and fill it in');
    });

    it.each(REQUIRED)('throws when %s is unset', async (key) => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', without(VALID, key));
        expect(failedStderr(load(dir))).toContain(`.env.bugbot.test: unset or empty: ${key}`);
    });

    it.each(REQUIRED)('throws when %s is empty', async (key) => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', { ...VALID, [key]: '' });
        expect(failedStderr(load(dir))).toContain(`.env.bugbot.test: unset or empty: ${key}`);
    });

    it('throws when API_KEY is whitespace-only', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', { ...VALID, API_KEY: '"   "' });
        expect(failedStderr(load(dir))).toContain('.env.bugbot.test: unset or empty: API_KEY');
    });

    it('names every missing var in REQUIRED order', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', without(VALID, 'API_KEY', 'DISCORD_GUILD_ID'));
        expect(failedStderr(load(dir))).toContain(
            '.env.bugbot.test: unset or empty: DISCORD_GUILD_ID, API_KEY',
        );
    });

    it('refuses the production token', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', VALID);
        await writeEnv(dir, '.env.bugbot', { BUG_BOT_DISCORD_TOKEN: 'test-token-aaa', BUG_CHANNEL_ID: '999' });
        expect(failedStderr(load(dir))).toContain(
            'refusing to start: test token equals the production bug-bot token',
        );
    });

    it('refuses the production channel', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', VALID);
        await writeEnv(dir, '.env.bugbot', { BUG_BOT_DISCORD_TOKEN: 'prod-token', BUG_CHANNEL_ID: '222' });
        expect(failedStderr(load(dir))).toContain(
            'refusing to start: test channel equals the production bug channel',
        );
    });

    it('loads when the production env differs', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', VALID);
        await writeEnv(dir, '.env.bugbot', { BUG_BOT_DISCORD_TOKEN: 'prod-token', BUG_CHANNEL_ID: '999' });
        expect(load(dir).ok).toBe(true);
    });

    it('loads when the production env lacks token and channel', async () => {
        const dir = await makeDir();
        await writeEnv(dir, '.env.bugbot.test', VALID);
        await writeEnv(dir, '.env.bugbot', { API_KEY: 'x' });
        expect(load(dir).ok).toBe(true);
    });

    it('never touches the real env files', () => {
        expect(snapshot()).toEqual(before);
    });
});
