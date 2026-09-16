/**
 * End-to-end round trip for the plan-review feature.
 *
 * Uses the REAL express (no vi.mock('express')), a real temp directory, and the
 * global fetch. The only mock is ../config.js — required because config.ts calls
 * requireEnv() at module load and would throw in test without env vars.
 *
 * Key invariant: startExpressServer and handlePlanReviewButton both receive the
 * SAME tmp dir. That shared seam is what makes the round trip real.
 */
import { describe, it, expect, beforeAll, afterAll, vi } from 'vitest';
import type { Server } from 'http';
import fs from 'fs';
import os from 'os';
import path from 'path';

// vi.mock is hoisted before const declarations; use vi.hoisted to share the
// owner value between the factory and the test body.
const { OWNER } = vi.hoisted(() => ({ OWNER: 'INTEGRATION_OWNER' }));

vi.mock('../config.js', () => ({
    config: {
        express: { port: 0 }, // OS picks a free port
        planReview: {
            ownerDiscordId: OWNER,
        },
    },
}));

import { startExpressServer } from './express.js';
import { handlePlanReviewButton } from '../interactions/plan-review-buttons.js';

let server: Server;
let port: number;
let bindAddr: string;
let tmp: string;
let sendSpy: ReturnType<typeof vi.fn>;

beforeAll(async () => {
    tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'plan-integration-'));

    sendSpy = vi.fn(async () => undefined);
    const fakeClient = { users: { send: sendSpy } };

    server = startExpressServer(fakeClient as never, tmp);

    // Wait for the server to be listening before reading the port
    await new Promise<void>((resolve) => {
        if (server.listening) {
            resolve();
        } else {
            server.once('listening', () => { resolve(); });
        }
    });

    const addr = server.address();
    if (!addr || typeof addr === 'string') {
        throw new Error('Unexpected server address: ' + String(addr));
    }
    port = addr.port;
    bindAddr = addr.address;
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

async function getJson(p: string) {
    return fetch(url(p), { method: 'GET' });
}

describe('plan-review end-to-end round trip', () => {
    it('completes the full POST → button-press → list → ack → confirm-empty sequence', async () => {
        // 1. POST /discordPlanReviewDM → 200, body { ok: true, slug }
        const dmRes = await postJson('/discordPlanReviewDM', {
            slug: 'integration-plan',
            digest: 'a digest',
        });
        expect(dmRes.status).toBe(200);
        const dmBody = await dmRes.json() as unknown;
        expect(dmBody).toEqual({ ok: true, slug: 'integration-plan' });

        // 2. users.send called exactly once (response sent inside .then, so this
        //    is guaranteed to have fired by the time fetch resolved above)
        expect(sendSpy).toHaveBeenCalledTimes(1);

        // Read the custom_id off the captured users.send payload — never re-type
        // the string; this proves the emitter and consumer agree.
        const dmPayload = sendSpy.mock.calls[0]![1] as {
            components: { components: { data: { custom_id?: string } }[] }[];
        };
        const extractedCustomId = dmPayload.components[0]!.components[0]!.data.custom_id!;
        expect(extractedCustomId).toBeDefined();

        // 3. The extracted id equals the expected value
        expect(extractedCustomId).toBe('plan_queue_integration-plan');

        // 4. Feed that exact extracted id into handlePlanReviewButton
        const editReply = vi.fn(async () => undefined);
        const deferUpdate = vi.fn(async () => undefined);
        const reply = vi.fn(async () => undefined);

        await handlePlanReviewButton({
            customId: extractedCustomId,
            user: { id: OWNER },
            reply,
            deferUpdate,
            editReply,
        } as never, tmp);

        expect(editReply).toHaveBeenCalledTimes(1);
        const editPayload = editReply.mock.calls[0]![0] as { content: string };
        expect(editPayload.content).toBe('queued — pending pickup');

        // 5. GET /planDecisions → 200, one record for integration-plan
        const listRes = await getJson('/planDecisions');
        expect(listRes.status).toBe(200);
        const listBody = await listRes.json() as { decisions: { id: string; slug: string; action: string }[] };
        expect(listBody.decisions).toHaveLength(1);
        expect(listBody.decisions[0]!.slug).toBe('integration-plan');
        expect(listBody.decisions[0]!.action).toBe('queue');

        const decisionId = listBody.decisions[0]!.id;
        expect(decisionId).toBeTruthy();

        // 6. POST /planDecisions/ack with that decision's id → 200, { acked: 1 }
        const ackRes = await postJson('/planDecisions/ack', { ids: [decisionId] });
        expect(ackRes.status).toBe(200);
        const ackBody = await ackRes.json() as unknown;
        expect(ackBody).toEqual({ acked: 1 });

        // 7. GET /planDecisions again → 200, { decisions: [] }
        const emptyRes = await getJson('/planDecisions');
        expect(emptyRes.status).toBe(200);
        const emptyBody = await emptyRes.json() as unknown;
        expect(emptyBody).toEqual({ decisions: [] });
    });

    // Row 27 — the listener must bind 127.0.0.1, not 0.0.0.0
    it('server address is 127.0.0.1 (not exposed to LAN)', () => {
        expect(bindAddr).toBe('127.0.0.1');
    });

    // Row 27 (second half) / Row 21 integration — non-owner press leaves sink byte-identical
    it('non-owner press writes nothing and leaves sink unchanged', async () => {
        const sinkPath = path.join(tmp, 'plan-decisions.jsonl');
        const before = fs.existsSync(sinkPath) ? fs.readFileSync(sinkPath) : null;

        await handlePlanReviewButton({
            customId: 'plan_queue_nonowner-plan',
            user: { id: 'SOMEONE_ELSE' },
            reply: vi.fn(async () => undefined),
            deferUpdate: vi.fn(async () => undefined),
            editReply: vi.fn(async () => undefined),
        } as never, tmp);

        const after = fs.existsSync(sinkPath) ? fs.readFileSync(sinkPath) : null;
        if (before === null) {
            expect(after).toBeNull();
        } else {
            expect(after!.equals(before)).toBe(true);
        }
    });

    // Row 26 dedup half — second owner press for same slug leaves exactly one record
    it('second owner press for one slug adds no record', async () => {
        await postJson('/discordPlanReviewDM', { slug: 'dedup-plan', digest: 'x' });
        const customId = 'plan_queue_dedup-plan';

        await handlePlanReviewButton({
            customId,
            user: { id: OWNER },
            reply: vi.fn(async () => undefined),
            deferUpdate: vi.fn(async () => undefined),
            editReply: vi.fn(async () => undefined),
        } as never, tmp);

        const sinkPath = path.join(tmp, 'plan-decisions.jsonl');
        const afterFirst = fs.readFileSync(sinkPath, 'utf8');

        const replySecond = vi.fn(async () => undefined);
        await handlePlanReviewButton({
            customId,
            user: { id: OWNER },
            reply: replySecond,
            deferUpdate: vi.fn(async () => undefined),
            editReply: vi.fn(async () => undefined),
        } as never, tmp);

        const afterSecond = fs.readFileSync(sinkPath, 'utf8');
        const records = afterSecond.split('\n').filter(Boolean)
            .map((l) => JSON.parse(l) as { kind: string; slug: string })
            .filter((r) => r.kind === 'decision' && r.slug === 'dedup-plan');
        expect(records).toHaveLength(1);
        expect(afterSecond).toBe(afterFirst);
        expect(replySecond).toHaveBeenCalledTimes(1);
    });

    // Row 28 — path-traversal slug yields 400 with no DM and no file outside tmpdir
    it('POST slug "../../etc/passwd" → 400, no DM, no file outside tmpdir', async () => {
        const preSendCount = sendSpy.mock.calls.length;
        const res = await postJson('/discordPlanReviewDM', { slug: '../../etc/passwd', digest: 'x' });
        expect(res.status).toBe(400);
        expect(sendSpy.mock.calls.length).toBe(preSendCount);

        // Confirm no file was written above the tmp directory
        const parentEntries = fs.readdirSync(path.dirname(tmp));
        const leakedFile = parentEntries.find((e) => e.includes('passwd'));
        expect(leakedFile).toBeUndefined();
    });
});
