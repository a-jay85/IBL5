import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import {
    APPROVE_CLICKS_FILE,
    CLAIM_WINDOW_MS,
    ClickRefusedError,
    TOKEN_TTL_MS,
    claimClick,
    listPending,
    lookupLive,
    mintToken,
    recordClick,
    revokeToken,
} from './approve-click-store.js';

const SHA = 'a'.repeat(40);
const OWNER = '283183467804491776';
const T0 = 1_700_000_000_000;

let dir: string;

beforeEach(() => {
    dir = fs.mkdtempSync(path.join(os.tmpdir(), 'approve-store-'));
});

afterEach(() => {
    fs.rmSync(dir, { recursive: true, force: true });
});

function fileText(): string {
    try {
        return fs.readFileSync(path.join(dir, APPROVE_CLICKS_FILE), 'utf8');
    } catch {
        return '';
    }
}

function refusal(fn: () => unknown): string {
    try {
        fn();
    } catch (e) {
        if (e instanceof ClickRefusedError) return e.reason;
        throw e;
    }
    return 'no-throw';
}

describe('approve-click-store', () => {
    it('mints a 32-hex nonce bound to the pr, the sha and a 24h expiry', () => {
        const { nonce, expires_at_ms } = mintToken(4242, SHA, T0, dir);
        expect(nonce).toMatch(/^[0-9a-f]{32}$/);
        expect(expires_at_ms).toBe(T0 + TOKEN_TTL_MS);
        const mint = lookupLive(nonce, T0, dir);
        expect(mint.pr).toBe(4242);
        expect(mint.head_sha).toBe(SHA);
        expect(mint.expires_at_ms).toBe(T0 + TOKEN_TTL_MS);
    });

    it('refuses an expired token and leaves the file unchanged', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        const before = fileText();
        const at = T0 + TOKEN_TTL_MS;
        expect(refusal(() => recordClick(nonce, 'ok', OWNER, null, at, dir))).toBe('expired');
        expect(fileText()).toBe(before);
        // one ms earlier is still live
        expect(() => lookupLive(nonce, at - 1, dir)).not.toThrow();
    });

    it('refuses a replayed click on a used token', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        recordClick(nonce, 'ok', OWNER, null, T0 + 1000, dir);
        const before = fileText();
        expect(refusal(() => recordClick(nonce, 'ok', OWNER, null, T0 + 2000, dir))).toBe('used');
        expect(fileText()).toBe(before);
    });

    it('refuses a superseded token after a newer mint for the same PR', () => {
        const first = mintToken(4242, SHA, T0, dir);
        const second = mintToken(4242, 'b'.repeat(40), T0 + 1000, dir);
        expect(refusal(() => recordClick(first.nonce, 'ok', OWNER, null, T0 + 2000, dir))).toBe('superseded');
        expect(() => lookupLive(second.nonce, T0 + 2000, dir)).not.toThrow();
        // a different PR is untouched
        const other = mintToken(77, SHA, T0 + 3000, dir);
        expect(() => lookupLive(second.nonce, T0 + 3000, dir)).not.toThrow();
        expect(() => lookupLive(other.nonce, T0 + 3000, dir)).not.toThrow();
    });

    it('refuses a revoked token as superseded', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        revokeToken(nonce, T0 + 1, dir);
        expect(refusal(() => lookupLive(nonce, T0 + 2, dir))).toBe('superseded');
    });

    it('claim of an unknown nonce writes nothing', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        const before = fileText();
        expect(claimClick('f'.repeat(32), T0 + 5, dir)).toEqual({ status: 'unknown' });
        // minted but never clicked is also unknown to the claim route
        expect(claimClick(nonce, T0 + 5, dir)).toEqual({ status: 'unknown' });
        expect(fileText()).toBe(before);

        const emptyDir = fs.mkdtempSync(path.join(os.tmpdir(), 'approve-empty-'));
        try {
            expect(claimClick('f'.repeat(32), T0, emptyDir)).toEqual({ status: 'unknown' });
            expect(fs.existsSync(path.join(emptyDir, APPROVE_CLICKS_FILE))).toBe(false);
        } finally {
            fs.rmSync(emptyDir, { recursive: true, force: true });
        }
    });

    it('a second claim is already-claimed', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        recordClick(nonce, 'ok', OWNER, null, T0 + 1000, dir);
        const first = claimClick(nonce, T0 + 2000, dir);
        expect(first.status).toBe('ok');
        const before = fileText();
        expect(claimClick(nonce, T0 + 3000, dir)).toEqual({ status: 'already-claimed' });
        expect(fileText()).toBe(before);
        expect(listPending(T0 + 3000, dir)).toEqual([]);
    });

    it('a claim after the window is lapsed', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        const pressed = T0 + 1000;
        recordClick(nonce, 'ok', OWNER, null, pressed, dir);
        const before = fileText();
        expect(claimClick(nonce, pressed + CLAIM_WINDOW_MS + 1000, dir)).toEqual({ status: 'lapsed' });
        expect(fileText()).toBe(before);
        expect(listPending(pressed + CLAIM_WINDOW_MS + 1000, dir)).toEqual([]);
        // 3599 s after the press is still ok
        expect(claimClick(nonce, pressed + 3599 * 1000, dir).status).toBe('ok');
    });

    it('keeps the actor snowflake a string', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        recordClick(nonce, 'fix', OWNER, 'rebase first', T0 + 1000, dir);
        const raw = fileText();
        expect(raw).toContain('"actor":"283183467804491776"');
        const pending = listPending(T0 + 2000, dir);
        expect(pending).toHaveLength(1);
        expect(pending[0]?.actor).toBe(OWNER);
        expect(typeof pending[0]?.actor).toBe('string');
    });

    it('returns the claim record with an ISO time and whole-second epoch', () => {
        const { nonce } = mintToken(4242, SHA, T0, dir);
        recordClick(nonce, 'fix', OWNER, 'rebase first', T0 + 1500, dir);
        const res = claimClick(nonce, T0 + 2000, dir);
        expect(res).toEqual({
            status: 'ok',
            record: {
                nonce,
                pr: 4242,
                head_sha: SHA,
                button: 'fix',
                condition: 'rebase first',
                actor: OWNER,
                clicked_at: new Date(T0 + 1500).toISOString(),
                clicked_at_epoch: Math.floor((T0 + 1500) / 1000),
            },
        });
    });

    it('lists only pressed, unclaimed clicks and survives a malformed line', () => {
        const a = mintToken(1, SHA, T0, dir);
        mintToken(2, SHA, T0, dir); // never pressed
        recordClick(a.nonce, 'ok', OWNER, null, T0 + 10, dir);
        fs.appendFileSync(path.join(dir, APPROVE_CLICKS_FILE), 'not json\n');
        const pending = listPending(T0 + 20, dir);
        expect(pending.map((p) => p.nonce)).toEqual([a.nonce]);
    });
});
