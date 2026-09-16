import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import type { Client } from 'discord.js';
import fs from 'fs';
import os from 'os';
import path from 'path';

const h = vi.hoisted(() => {
    const routes: Record<string, (req: unknown, res: unknown) => unknown> = {};
    const listenSpy = vi.fn();
    const state = { owner: 'OWNER_SNOWFLAKE' };
    return { routes, listenSpy, state };
});

vi.mock('express', () => {
    const app = {
        use: vi.fn(),
        post: vi.fn((p: string, handler: (req: unknown, res: unknown) => unknown) => { h.routes[p] = handler; }),
        get: vi.fn((p: string, handler: (req: unknown, res: unknown) => unknown) => { h.routes[p] = handler; }),
        listen: h.listenSpy,
    };
    const expressFn = vi.fn(() => app) as unknown as { (): typeof app; json: () => unknown };
    expressFn.json = vi.fn(() => 'json-mw');
    return { default: expressFn };
});

vi.mock('../config.js', () => ({
    config: {
        express: { port: 50001 },
        planReview: {
            get ownerDiscordId() { return h.state.owner; },
        },
    },
}));

import { startExpressServer } from './express.js';
import { PLAN_SLUG_RE } from './plan-review-dm.js';
import { appendDecision, readPending, DECISIONS_FILE } from './decision-store.js';

function makeRes() {
    const res = {
        statusCode: 200,
        body: undefined as unknown,
        status: vi.fn((c: number) => { res.statusCode = c; return res; }),
        send: vi.fn((b?: unknown) => { res.body = b; return res; }),
        json: vi.fn((b?: unknown) => { res.body = b; return res; }),
    };
    return res;
}

function clientWithSend(impl: () => Promise<unknown> = async () => undefined): { client: Client; send: ReturnType<typeof vi.fn> } {
    const send = vi.fn(impl);
    return { client: { users: { send } } as unknown as Client, send };
}

/** Mount the real route table and drive one route, flushing the handler's promise chain. */
async function invoke(
    method: 'post' | 'get',
    route: string,
    body: unknown,
    client: Client,
    dir?: string,
) {
    h.routes = {};
    startExpressServer(client, dir);
    const res = makeRes();
    void method;
    await h.routes[route]!({ body }, res);
    // The DM handler resolves through client.users.send(...).then(...)
    await new Promise((r) => { setImmediate(r); });
    await new Promise((r) => { setImmediate(r); });
    return res;
}

let tmp: string;

beforeEach(() => {
    vi.clearAllMocks();
    h.routes = {};
    h.state.owner = 'OWNER_SNOWFLAKE';
    tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'plandm-'));
});

afterEach(() => {
    fs.rmSync(tmp, { recursive: true, force: true });
});

function sinkBytes(dir: string): string | null {
    const f = path.join(dir, DECISIONS_FILE);
    return fs.existsSync(f) ? fs.readFileSync(f, 'utf8') : null;
}

function embedOf(send: ReturnType<typeof vi.fn>) {
    const payload = send.mock.calls[0]![1] as { embeds: { data: { description?: string } }[] };
    return payload.embeds[0]!.data;
}

function rowOf(send: ReturnType<typeof vi.fn>) {
    const payload = send.mock.calls[0]![1] as {
        components: { components: { data: { custom_id?: string; disabled?: boolean } }[] }[];
    };
    return payload.components[0]!.components;
}

describe('POST /discordPlanReviewDM — payload validation', () => {
    it('400s a missing slug, a missing digest, and a slug with uppercase/punctuation, sending no DM', async () => {
        for (const body of [
            { digest: 'hello' },
            { slug: 'good-slug' },
            { slug: 'Bad_Slug!', digest: 'hello' },
            { slug: '../../etc/passwd', digest: 'hello' },
        ]) {
            const { client, send } = clientWithSend();
            const res = await invoke('post', '/discordPlanReviewDM', body, client, tmp);
            expect(res.statusCode, JSON.stringify(body)).toBe(400);
            expect(send).not.toHaveBeenCalled();
        }
        expect(sinkBytes(tmp)).toBeNull();
    });

    it('accepts a 50-char slug — the length of the two longest real plan names today', async () => {
        const slug = 'maint-2-1-module-entrypoint-globals-to-controllers';
        expect(slug.length).toBe(50);

        const { client, send } = clientWithSend();
        const res = await invoke('post', '/discordPlanReviewDM', { slug, digest: 'x' }, client, tmp);

        expect(res.statusCode).toBe(200);
        expect(send).toHaveBeenCalledTimes(1);
    });

    it('accepts an 80-char slug (custom_id 93 <= 100) and rejects 81 with the sink untouched', async () => {
        const eighty = `a${'b'.repeat(79)}`;
        expect(eighty.length).toBe(80);

        const ok = clientWithSend();
        const okRes = await invoke('post', '/discordPlanReviewDM', { slug: eighty, digest: 'x' }, ok.client, tmp);
        expect(okRes.statusCode).toBe(200);

        const ids = rowOf(ok.send).map((c) => c.data.custom_id!);
        expect(ids).toEqual([`plan_queue_${eighty}`, `plan_discard_${eighty}`]);
        for (const id of ids) {
            expect(id.length).toBeLessThanOrEqual(100);
        }
        expect(`plan_discard_${eighty}`.length).toBe(93);

        const before = sinkBytes(tmp);
        const tooLong = clientWithSend();
        const badRes = await invoke('post', '/discordPlanReviewDM', { slug: `${eighty}c`, digest: 'x' }, tooLong.client, tmp);
        expect(badRes.statusCode).toBe(400);
        expect(tooLong.send).not.toHaveBeenCalled();
        expect(sinkBytes(tmp)).toBe(before);
    });

    it('PLAN_SLUG_RE has exactly one definition and admits no path separators', () => {
        expect(PLAN_SLUG_RE.test('a/b')).toBe(false);
        expect(PLAN_SLUG_RE.test('a.b')).toBe(false);
        expect(PLAN_SLUG_RE.test('-leading-hyphen')).toBe(false);
        expect(PLAN_SLUG_RE.test('_pending-prompt')).toBe(false);
        expect(PLAN_SLUG_RE.test('good-slug-1')).toBe(true);
    });
});

describe('POST /discordPlanReviewDM — digest sizing', () => {
    it('clamps a 5000-char digest under the 4096 embed cap and appends the overflow tail', async () => {
        const digest = 'line\n'.repeat(1000); // 5000 chars
        expect(digest.length).toBe(5000);

        const { client, send } = clientWithSend();
        const res = await invoke('post', '/discordPlanReviewDM', { slug: 'big-plan', digest }, client, tmp);

        expect(res.statusCode).toBe(200);
        expect(send).toHaveBeenCalledTimes(1);

        const description = embedOf(send).description!;
        expect(description.length).toBeLessThanOrEqual(4096);
        expect(description).toMatch(/…\d+ more lines — full plan at ~\/claude-plans\/big-plan\.md$/);
    });

    it('leaves a short digest byte-identical', async () => {
        const { client, send } = clientWithSend();
        await invoke('post', '/discordPlanReviewDM', { slug: 'small-plan', digest: 'just this' }, client, tmp);
        expect(embedOf(send).description).toBe('just this');
    });
});

describe('POST /discordPlanReviewDM — recipient resolution', () => {
    it('503s with no DM when the owner id is empty', async () => {
        h.state.owner = '';
        const { client, send } = clientWithSend();
        const res = await invoke('post', '/discordPlanReviewDM', { slug: 'a-plan', digest: 'x' }, client, tmp);

        expect(res.statusCode).toBe(503);
        expect(send).not.toHaveBeenCalled();
    });

    it('never lets a body-supplied recipient reach users.send', async () => {
        const { client, send } = clientWithSend();
        await invoke('post', '/discordPlanReviewDM', {
            slug: 'a-plan',
            digest: 'x',
            recipientId: 'ATTACKER',
            userId: 'ATTACKER',
            receivingUserDiscordID: 'ATTACKER',
        }, client, tmp);

        expect(send).toHaveBeenCalledTimes(1);
        expect(send.mock.calls[0]![0]).toBe('OWNER_SNOWFLAKE');
        expect(JSON.stringify(send.mock.calls[0])).not.toContain('ATTACKER');
    });

    it('500s and writes no decision record when users.send rejects', async () => {
        const { client, send } = clientWithSend(async () => { throw new Error('discord down'); });
        const res = await invoke('post', '/discordPlanReviewDM', { slug: 'a-plan', digest: 'x' }, client, tmp);

        expect(send).toHaveBeenCalledTimes(1);
        expect(res.statusCode).toBe(500);
        expect(sinkBytes(tmp)).toBeNull();
        expect(readPending(tmp)).toEqual([]);
    });
});

describe('GET /planDecisions', () => {
    it('returns only unacked records with exactly the wire field set', async () => {
        const one = appendDecision({ slug: 'plan-one', action: 'queue', actor: '111' }, tmp);
        appendDecision({ slug: 'plan-two', action: 'discard', actor: '222' }, tmp);

        const { client } = clientWithSend();
        const first = await invoke('get', '/planDecisions', undefined, client, tmp);
        const listed = (first.body as { decisions: Record<string, unknown>[] }).decisions;

        expect(listed).toHaveLength(2);
        expect(Object.keys(listed[0]!).sort()).toEqual(['action', 'actor', 'id', 'slug', 'ts']);
        expect(listed.map((d) => d['slug'])).toEqual(['plan-one', 'plan-two']);

        await invoke('post', '/planDecisions/ack', { ids: [one.id] }, client, tmp);

        const second = await invoke('get', '/planDecisions', undefined, client, tmp);
        const after = (second.body as { decisions: { slug: string }[] }).decisions;
        expect(after.map((d) => d.slug)).toEqual(['plan-two']);
    });

    it('200s with an empty list and creates no directory when the sink does not exist', async () => {
        const unused = path.join(tmp, 'never-created');
        const { client } = clientWithSend();
        const res = await invoke('get', '/planDecisions', undefined, client, unused);

        expect(res.statusCode).toBe(200);
        expect(res.body).toEqual({ decisions: [] });
        expect(fs.existsSync(unused)).toBe(false);
    });
});

describe('POST /planDecisions/ack', () => {
    it('is a no-op on the second and third drain, and writes no second tombstone', async () => {
        const a = appendDecision({ slug: 'plan-a', action: 'queue', actor: '1' }, tmp);
        const b = appendDecision({ slug: 'plan-b', action: 'queue', actor: '1' }, tmp);
        const { client } = clientWithSend();

        const acked = await invoke('post', '/planDecisions/ack', { ids: [a.id, b.id] }, client, tmp);
        expect(acked.statusCode).toBe(200);

        const afterFirstAck = sinkBytes(tmp)!;

        const empty = await invoke('get', '/planDecisions', undefined, client, tmp);
        expect(empty.body).toEqual({ decisions: [] });

        const repeat = await invoke('post', '/planDecisions/ack', { ids: [a.id, b.id] }, client, tmp);
        expect(repeat.statusCode).toBe(200);
        expect(repeat.body).toEqual({ acked: 0 });
        expect(sinkBytes(tmp)).toBe(afterFirstAck);

        const third = await invoke('post', '/planDecisions/ack', { ids: [a.id, b.id] }, client, tmp);
        expect(third.statusCode).toBe(200);
        expect(sinkBytes(tmp)).toBe(afterFirstAck);
    });

    it('ignores unknown ids without throwing', async () => {
        appendDecision({ slug: 'plan-a', action: 'queue', actor: '1' }, tmp);
        const { client } = clientWithSend();

        const res = await invoke('post', '/planDecisions/ack', { ids: ['no-such-id'] }, client, tmp);
        expect(res.statusCode).toBe(200);
        expect(readPending(tmp)).toHaveLength(1);
    });

    it('400s a malformed ids field and leaves the file unchanged', async () => {
        appendDecision({ slug: 'plan-a', action: 'queue', actor: '1' }, tmp);
        const before = sinkBytes(tmp);
        const { client } = clientWithSend();

        for (const body of [{}, { ids: 'abc' }, { ids: [1, 2] }, undefined]) {
            const res = await invoke('post', '/planDecisions/ack', body, client, tmp);
            expect(res.statusCode, JSON.stringify(body)).toBe(400);
        }

        expect(sinkBytes(tmp)).toBe(before);
    });
});
