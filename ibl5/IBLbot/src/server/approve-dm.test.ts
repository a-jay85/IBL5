import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import type { Client } from 'discord.js';
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
});
