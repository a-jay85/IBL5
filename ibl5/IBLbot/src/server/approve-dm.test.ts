import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import type { Client, EmbedBuilder } from 'discord.js';
import fs from 'fs';
import os from 'os';
import path from 'path';

const h = vi.hoisted(() => ({ state: { owner: 'OWNER_SNOWFLAKE' } }));

vi.mock('../config.js', () => ({
    config: {
        express: { port: 50001 },
        planReview: {
            get ownerDiscordId() { return h.state.owner; },
        },
    },
}));

import { handleApproveDM } from './approve-dm.js';
import { APPROVE_CLICKS_FILE, foldState } from './approve-click-store.js';

const SHA = 'c'.repeat(40);

function makeRes() {
    const res = {
        statusCode: 200,
        body: undefined as unknown,
        status: vi.fn((c: number) => { res.statusCode = c; return res; }),
        json: vi.fn((b?: unknown) => { res.body = b; return res; }),
    };
    return res;
}

function clientWithSend(impl: () => Promise<unknown> = async () => undefined) {
    const send = vi.fn(impl);
    return { client: { users: { send } } as unknown as Client, send };
}

/** Run the handler and wait for the detached send promise to settle. */
async function call(
    client: Client,
    dir: string,
    body: unknown,
): Promise<ReturnType<typeof makeRes>> {
    const res = makeRes();
    handleApproveDM(client, dir)({ body } as never, res as never);
    await new Promise((resolve) => setTimeout(resolve, 0));
    return res;
}

const FALLBACK_CELL = '❌ Could not read';

function fullDisplay(overrides: Record<string, unknown> = {}) {
    return {
        title: 'Fix the cap',
        size: '3 files, +32 −10',
        cells: {
            tests: { icon: '✅', text: '' },
            comments: { icon: '⏳', text: '2 open' },
            bot_review: { icon: '❌', text: '' },
            easy_undo: { icon: '🚨', text: 'migration' },
            your_ok: { icon: '', text: 'none' },
        },
        verdict: 'READY, held for you.',
        decision: 'Merge if the new DM looks right.',
        ...overrides,
    };
}

/** Every display string at `length` chars, with valid icons so the cell cap applies. */
function oversizedDisplay(length: number) {
    const long = 'a'.repeat(length);
    const cell = { icon: '✅', text: long };
    return {
        title: long,
        size: long,
        cells: { tests: cell, comments: cell, bot_review: cell, easy_undo: cell, your_ok: cell },
        verdict: long,
        decision: long,
    };
}

type SentPayload = {
    embeds: EmbedBuilder[];
    components: { components: { data: { custom_id?: string } }[] }[];
    allowedMentions?: unknown;
};

function sentPayload(send: ReturnType<typeof clientWithSend>['send']): SentPayload {
    expect(send).toHaveBeenCalledTimes(1);
    return (send.mock.calls[0] as unknown as [string, SentPayload])[1];
}

/** POST one body and return the embed that was sent. */
async function sentEmbed(body: Record<string, unknown>) {
    const { client, send } = clientWithSend();
    const res = await call(client, dir, body);
    expect(res.statusCode).toBe(200);
    return sentPayload(send).embeds[0]!.toJSON();
}

let dir: string;

beforeEach(() => {
    h.state.owner = 'OWNER_SNOWFLAKE';
    dir = fs.mkdtempSync(path.join(os.tmpdir(), 'approve-dm-'));
});

afterEach(() => {
    fs.rmSync(dir, { recursive: true, force: true });
});

describe('handleApproveDM', () => {
    it('answers 503 when the owner id is empty and mints nothing', async () => {
        h.state.owner = '';
        const { client, send } = clientWithSend();
        const res = await call(client, dir, { pr: 4242, head_sha: SHA });
        expect(res.statusCode).toBe(503);
        expect(send).not.toHaveBeenCalled();
        expect(fs.existsSync(path.join(dir, APPROVE_CLICKS_FILE))).toBe(false);
    });

    it('rejects a bad sha with 400 and mints nothing', async () => {
        const { client, send } = clientWithSend();
        const bad: unknown[] = [
            { pr: 4242, head_sha: 'c'.repeat(39) },
            { pr: 4242, head_sha: 'C'.repeat(40) },
            { pr: 4242, head_sha: undefined },
            { pr: 0, head_sha: SHA },
            { pr: 1.5, head_sha: SHA },
            { pr: '4242', head_sha: SHA },
            { pr: 1_000_000, head_sha: SHA },
            undefined,
        ];
        for (const body of bad) {
            const res = await call(client, dir, body);
            expect(res.statusCode).toBe(400);
        }
        expect(send).not.toHaveBeenCalled();
        expect(fs.existsSync(path.join(dir, APPROVE_CLICKS_FILE))).toBe(false);
    });

    it('custom ids carry only the nonce', async () => {
        const { client, send } = clientWithSend();
        const res = await call(client, dir, { pr: 4242, head_sha: SHA });
        expect(res.statusCode).toBe(200);
        const { nonce } = res.body as { nonce: string };
        expect(nonce).toMatch(/^[0-9a-f]{32}$/);

        expect(send).toHaveBeenCalledTimes(1);
        const [recipient, payload] = send.mock.calls[0] as unknown as [
            string,
            { components: { components: { data: { custom_id?: string } }[] }[] },
        ];
        expect(recipient).toBe('OWNER_SNOWFLAKE');
        const ids = payload.components[0]!.components.map((c) => c.data.custom_id!);
        expect(ids).toEqual([`approve_ok_${nonce}`, `approve_fix_${nonce}`]);
        for (const id of ids) {
            expect(id.length).toBeLessThan(100);
            expect(id).not.toContain('4242');
            expect(id).not.toContain(SHA);
        }
    });

    it('revokes the token and answers 502 when the send fails', async () => {
        const { client } = clientWithSend(async () => { throw new Error('discord down'); });
        const errSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined);
        const res = await call(client, dir, { pr: 4242, head_sha: SHA });
        errSpy.mockRestore();
        expect(res.statusCode).toBe(502);
        const states = [...foldState(dir).values()];
        expect(states).toHaveLength(1);
        expect(states[0]!.superseded).toBe(true);
    });

    it('renders fields in mock order with inline flags', async () => {
        const embed = await sentEmbed({ pr: 4242, head_sha: SHA, display: fullDisplay() });
        expect(embed.fields!.map((f) => [f.name, f.inline])).toEqual([
            ['Tests', true], ['Comments', true], ['Bot review', true], ['Easy undo', true], ['Your OK', true],
            ['Verdict', false], ['Decision', false],
        ]);
        expect(embed.fields!.map((f) => f.value)).toEqual([
            '✅', '⏳ 2 open', '❌', '🚨 migration', 'none',
            'READY, held for you.', '> Merge if the new DM looks right.',
        ]);
        expect(embed.title).toBe('PR #4242 is held for you');
        expect(embed.description!.split('\n')).toEqual([
            'Fix the cap',
            'Head `ccccccc` · 3 files, +32 −10',
        ]);
        expect(embed.footer!.text).toContain('bin/owner-approve 4242 --finish');
        expect(embed.footer!.text).toContain('This button expires in 24h.');
    });

    it('keeps the full embed under 6000 at every field max', async () => {
        const embed = await sentEmbed({ pr: 999999, head_sha: SHA, display: oversizedDisplay(5000) });
        const fields = embed.fields!;
        expect(fields).toHaveLength(7);
        for (const field of fields) {
            expect(field.value.length).toBeLessThanOrEqual(1024);
        }
        const total = embed.title!.length + embed.description!.length + embed.footer!.text.length +
            fields.reduce((sum, f) => sum + f.name.length + f.value.length, 0);
        expect(total).toBeLessThanOrEqual(6000);
    });

    it('sends with empty allowedMentions', async () => {
        const { client, send } = clientWithSend();
        await call(client, dir, { pr: 4242, head_sha: SHA, display: fullDisplay() });
        expect(sentPayload(send).allowedMentions).toEqual({ parse: [] });
    });

    it('renders Could not read when display is absent', async () => {
        const embed = await sentEmbed({ pr: 4242, head_sha: SHA });
        const fields = embed.fields!;
        expect(fields.slice(0, 5).map((f) => f.value)).toEqual(Array(5).fill(FALLBACK_CELL));
        expect(fields.find((f) => f.name === 'Verdict')!.value).toBe('Could not read');
        expect(fields.map((f) => f.name)).not.toContain('Decision');
        expect(embed.description).toBe('Head `ccccccc`');
    });

    it('omits Decision when it is null', async () => {
        const embed = await sentEmbed({ pr: 4242, head_sha: SHA, display: fullDisplay({ decision: null }) });
        expect(embed.fields!.map((f) => f.name)).not.toContain('Decision');
        expect(embed.fields).toHaveLength(6);
    });

    it('does not 400 on an invalid display', async () => {
        const invalid: unknown[] = ['junk', [], { cells: 5 }, { verdict: 'a'.repeat(50_000) }];
        for (const display of invalid) {
            const { client, send } = clientWithSend();
            const res = await call(client, dir, { pr: 4242, head_sha: SHA, display });
            expect(res.statusCode).toBe(200);
            expect((res.body as { nonce: string }).nonce).toMatch(/^[0-9a-f]{32}$/);
            expect(send).toHaveBeenCalledTimes(1);
        }
    });

    it('still 400s a bad pr or sha when display is valid', async () => {
        const { client, send } = clientWithSend();
        const badPr = await call(client, dir, { pr: 0, head_sha: SHA, display: fullDisplay() });
        expect(badPr.statusCode).toBe(400);
        expect(badPr.body).toEqual({ error: 'invalid or missing pr' });
        const badSha = await call(client, dir, { pr: 4242, head_sha: 'abc', display: fullDisplay() });
        expect(badSha.statusCode).toBe(400);
        expect(badSha.body).toEqual({ error: 'invalid or missing head_sha' });
        expect(send).not.toHaveBeenCalled();
    });

    it('never copies display text into custom ids', async () => {
        const { client, send } = clientWithSend();
        await call(client, dir, { pr: 4242, head_sha: SHA, display: fullDisplay({ title: 'approve_ok_x' }) });
        const ids = sentPayload(send).components.flatMap((row) => row.components.map((c) => c.data.custom_id!));
        expect(ids).toHaveLength(2);
        for (const id of ids) {
            expect(id).toMatch(/^approve_(ok|fix)_[0-9a-f]{32}$/);
        }
    });
});
