import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import fs from 'fs';
import os from 'os';
import path from 'path';
import type { Client } from 'discord.js';

vi.mock('../config.js', () => ({
    config: {
        planReview: {
            ownerDiscordId: 'OWNER_SNOWFLAKE',
        },
    },
}));

import { appendDecision, readPending, DECISIONS_FILE } from './decision-store.js';
import {
    sweepOnce,
    startStaleSweeper,
    staleWarning,
    STALE_THRESHOLD_MS,
    SWEEP_INTERVAL_MS,
} from './stale-decision-sweeper.js';

let tmp: string;

// Build a fully-wired client double. `fakeMessage` is mutated between tests to
// control the `content` the sweeper reads back.
function makeClient(fakeMessage: { content: string; edit: ReturnType<typeof vi.fn> }): {
    client: Client;
    fetch: ReturnType<typeof vi.fn>;
    send: ReturnType<typeof vi.fn>;
} {
    const msgFetch = vi.fn(async () => fakeMessage);
    const createDM = vi.fn(async () => ({ messages: { fetch: msgFetch } }));
    const fetch = vi.fn(async () => ({ createDM }));
    const send = vi.fn(async () => undefined);
    const client = { users: { fetch, send } } as unknown as Client;
    return { client, fetch, send };
}

/** Rewrite the ts field of all records in the JSONL file. */
function backdateRecords(dir: string, ageMs: number): void {
    const file = path.join(dir, DECISIONS_FILE);
    if (!fs.existsSync(file)) return;
    const oldTs = new Date(Date.now() - ageMs).toISOString();
    const lines = fs.readFileSync(file, 'utf8').split('\n');
    const rewritten = lines.map(line => {
        if (!line.trim()) return line;
        try {
            const rec = JSON.parse(line) as Record<string, unknown>;
            if (rec['kind'] === 'decision') {
                rec['ts'] = oldTs;
                return JSON.stringify(rec);
            }
        } catch { /* skip malformed */ }
        return line;
    });
    fs.writeFileSync(file, rewritten.join('\n'), 'utf8');
}

beforeEach(() => {
    vi.useFakeTimers();
    tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'sweeper-'));
});

afterEach(() => {
    vi.useRealTimers();
    fs.rmSync(tmp, { recursive: true, force: true });
});

describe('staleWarning', () => {
    it('queue action produces bin/automouse/queue command', () => {
        const w = staleWarning('my-plan', 'queue');
        expect(w).toContain('bin/automouse/queue my-plan');
        expect(w).toContain('⚠️');
    });

    it('discard action produces mv command', () => {
        const w = staleWarning('my-plan', 'discard');
        expect(w).toContain('mv ~/claude-plans/my-plan.md ~/claude-plans/discarded/');
    });
});

describe('sweepOnce — Row 11: at 14 min (< threshold): no edit, no DM send', () => {
    it('does nothing for a record younger than the threshold', async () => {
        appendDecision({ slug: 'young-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);
        // 14 minutes old
        backdateRecords(tmp, 14 * 60 * 1000);

        const fakeMessage = { content: '', edit: vi.fn(async () => undefined) };
        const { client, fetch, send } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        expect(fetch).not.toHaveBeenCalled();
        expect(send).not.toHaveBeenCalled();
        expect(fakeMessage.edit).not.toHaveBeenCalled();
    });
});

describe('sweepOnce — Row 12: past 15 min: exactly one edit and one send', () => {
    it('edits the message and sends a DM when the record is stale', async () => {
        appendDecision({ slug: 'stale-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);
        // 16 minutes old
        backdateRecords(tmp, 16 * 60 * 1000);

        const fakeMessage = { content: '', edit: vi.fn(async () => undefined) };
        const { client, send } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        expect(fakeMessage.edit).toHaveBeenCalledTimes(1);
        expect(send).toHaveBeenCalledTimes(1);
        const sentContent = send.mock.calls[0]![1] as string;
        expect(sentContent).toContain('⚠️');
        expect(sentContent).toContain('stale-plan');
        expect(sentContent).toContain('bin/automouse/queue stale-plan');
    });
});

describe('sweepOnce — Row 13: second sweep after warning: no second DM', () => {
    it('does not re-edit or re-send when content already matches', async () => {
        appendDecision({ slug: 'warned-plan', action: 'discard', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);
        backdateRecords(tmp, 20 * 60 * 1000);

        const expectedWarning = staleWarning('warned-plan', 'discard');
        // The message already has the warning content
        const fakeMessage = { content: expectedWarning, edit: vi.fn(async () => undefined) };
        const { client, send } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        expect(fakeMessage.edit).not.toHaveBeenCalled();
        expect(send).not.toHaveBeenCalled();
    });
});

describe('sweepOnce — Row 14: record with no messageId: skipped, zero users.fetch', () => {
    it('skips records without messageId', async () => {
        appendDecision({ slug: 'no-msg-plan', action: 'queue', actor: 'ACTOR' }, tmp);
        backdateRecords(tmp, 20 * 60 * 1000);

        const fakeMessage = { content: '', edit: vi.fn(async () => undefined) };
        const { client, fetch } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('sweepOnce — Row 15: acked record: never seen by sweeper', () => {
    it('does not process acked records', async () => {
        const d = appendDecision({ slug: 'acked-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);
        backdateRecords(tmp, 20 * 60 * 1000);

        // Ack the record
        const { ackDecisions } = await import('./decision-store.js');
        ackDecisions([d.id], tmp);

        const fakeMessage = { content: '', edit: vi.fn(async () => undefined) };
        const { client, fetch } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('sweepOnce — Row 16: record with unparseable ts: skipped', () => {
    it('skips records whose ts is not a valid date', async () => {
        appendDecision({ slug: 'bad-ts-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);

        // Corrupt the ts field
        const file = path.join(tmp, DECISIONS_FILE);
        const lines = fs.readFileSync(file, 'utf8').split('\n');
        const rewritten = lines.map(line => {
            if (!line.trim()) return line;
            try {
                const rec = JSON.parse(line) as Record<string, unknown>;
                if (rec['kind'] === 'decision') {
                    rec['ts'] = 'not-a-date';
                    return JSON.stringify(rec);
                }
            } catch { /* skip */ }
            return line;
        });
        fs.writeFileSync(file, rewritten.join('\n'), 'utf8');

        const fakeMessage = { content: '', edit: vi.fn(async () => undefined) };
        const { client, fetch } = makeClient(fakeMessage);

        await sweepOnce(client, tmp);

        // NaN > STALE_THRESHOLD_MS is false, so nothing is fetched
        expect(fetch).not.toHaveBeenCalled();
    });
});

describe('sweepOnce — Row 17: re-entrant sweepOnce and startStaleSweeper', () => {
    it('a second sweepOnce while one is in-flight is a no-op, and clearInterval stops the sweeper', async () => {
        appendDecision({ slug: 'reentr-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M' }, tmp);
        backdateRecords(tmp, 20 * 60 * 1000);

        let resolveFirst!: () => void;
        const firstFinished = new Promise<void>((r) => { resolveFirst = r; });

        // Slow fetch: blocks the first sweep so we can call sweepOnce again concurrently
        let fetchCount = 0;
        const slowFetch = vi.fn(async () => {
            fetchCount++;
            // Wait for the test to release
            await firstFinished;
            return { createDM: async () => ({ messages: { fetch: async () => ({ content: '', edit: vi.fn(async () => undefined) }) } }) };
        });
        const send = vi.fn(async () => undefined);
        const client = { users: { fetch: slowFetch, send } } as unknown as Client;

        // Start first sweep (will block at fetch)
        const first = sweepOnce(client, tmp);

        // Second sweep should be a no-op because `sweeping` is true
        const second = sweepOnce(client, tmp);
        await second; // resolves immediately

        // Release the first sweep
        resolveFirst();
        await first;

        // fetch was only called once (the second sweep was no-op)
        expect(fetchCount).toBe(1);

        // startStaleSweeper + clearInterval stops firing
        appendDecision({ slug: 'interval-plan', action: 'queue', actor: 'ACTOR', channelId: 'C', messageId: 'M2' }, tmp);
        backdateRecords(tmp, 20 * 60 * 1000);

        let sweepCalls = 0;
        const countClient = { users: { fetch: vi.fn(async () => { sweepCalls++; throw new Error('count only'); }), send: vi.fn() } } as unknown as Client;
        const handle = startStaleSweeper(countClient, tmp);
        clearInterval(handle);

        // Advance time past multiple intervals — no sweeps should have fired
        await vi.advanceTimersByTimeAsync(SWEEP_INTERVAL_MS * 3);

        expect(sweepCalls).toBe(0);
    });
});
