/**
 * End-to-end check of the three approve routes through the REAL express app.
 *
 * Uses the real express, a real temp directory and the global fetch. The only
 * mock is ../config.js, which would otherwise throw at load for lack of env vars.
 * The Discord client is a double whose users.send records the DM payload.
 */
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest';
import type { Server } from 'http';
import fs from 'fs';
import os from 'os';
import path from 'path';

const { OWNER } = vi.hoisted(() => ({ OWNER: '283183467804491776' }));

vi.mock('../config.js', () => ({
    config: {
        express: { port: 0 },
        planReview: { ownerDiscordId: OWNER },
    },
}));

import { startExpressServer } from './express.js';
import { CLAIM_WINDOW_MS, recordClick } from './approve-click-store.js';

const SHA = 'd'.repeat(40);

let server: Server;
let port: number;
let tmp: string;
let sendSpy: ReturnType<typeof vi.fn>;

beforeAll(async () => {
    tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'approve-integration-'));
    sendSpy = vi.fn(async () => undefined);
    server = startExpressServer({ users: { send: sendSpy } } as never, tmp);
    await new Promise<void>((resolve) => {
        if (server.listening) resolve();
        else server.once('listening', () => { resolve(); });
    });
    const addr = server.address();
    if (!addr || typeof addr === 'string') throw new Error('Unexpected server address');
    port = addr.port;
});

afterAll(async () => {
    await new Promise<void>((resolve, reject) => {
        server.close((err) => { if (err) reject(err); else resolve(); });
    });
    fs.rmSync(tmp, { recursive: true, force: true });
});

function url(p: string) {
    return `http://127.0.0.1:${port}${p}`;
}

async function postJson(p: string, body: unknown) {
    return fetch(url(p), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
}

async function mint(pr: number): Promise<string> {
    const res = await postJson('/discordApproveDM', { pr, head_sha: SHA });
    expect(res.status).toBe(200);
    return ((await res.json()) as { nonce: string }).nonce;
}

describe('approve routes through the real app', () => {
    it('lists only pressed, unclaimed clicks', async () => {
        const pressed = await mint(101);
        await mint(102); // minted, never pressed
        recordClick(pressed, 'ok', OWNER, null, Date.now(), tmp);

        const res = await fetch(url('/approveClicks'));
        expect(res.status).toBe(200);
        const body = (await res.json()) as { clicks: { nonce: string; pr: number; actor: string }[] };
        expect(body.clicks.map((c) => c.nonce)).toEqual([pressed]);
        expect(body.clicks[0]!.pr).toBe(101);
        expect(body.clicks[0]!.actor).toBe(OWNER);

        // once claimed it drops off the list
        const claim = await postJson('/approveClicks/claim', { nonce: pressed });
        expect(claim.status).toBe(200);
        const after = (await (await fetch(url('/approveClicks'))).json()) as { clicks: unknown[] };
        expect(after.clicks).toEqual([]);
    });

    it('claim answers 404 unknown, 409 repeat, 410 lapsed and 200 with the record', async () => {
        const unknown = await postJson('/approveClicks/claim', { nonce: 'e'.repeat(32) });
        expect(unknown.status).toBe(404);

        const bad = await postJson('/approveClicks/claim', { nonce: 'xyz' });
        expect(bad.status).toBe(400);

        const nonce = await mint(201);
        recordClick(nonce, 'fix', OWNER, 'rebase first', Date.now(), tmp);
        const ok = await postJson('/approveClicks/claim', { nonce });
        expect(ok.status).toBe(200);
        const record = (await ok.json()) as Record<string, unknown>;
        expect(record).toMatchObject({
            nonce,
            pr: 201,
            head_sha: SHA,
            button: 'fix',
            condition: 'rebase first',
            actor: OWNER,
        });
        expect(typeof record['clicked_at_epoch']).toBe('number');

        const again = await postJson('/approveClicks/claim', { nonce });
        expect(again.status).toBe(409);

        const old = await mint(301);
        recordClick(old, 'ok', OWNER, null, Date.now() - CLAIM_WINDOW_MS - 5000, tmp);
        const lapsed = await postJson('/approveClicks/claim', { nonce: old });
        expect(lapsed.status).toBe(410);
    });

    it('POST /discordApproveDM rejects a bad body with 400 and sends nothing', async () => {
        const before = sendSpy.mock.calls.length;
        const res = await postJson('/discordApproveDM', { pr: 0, head_sha: SHA });
        expect(res.status).toBe(400);
        expect(sendSpy.mock.calls.length).toBe(before);
    });
});
