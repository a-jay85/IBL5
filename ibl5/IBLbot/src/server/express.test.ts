import { describe, it, expect, beforeEach, vi } from 'vitest';
import { MessageFlags } from 'discord.js';
import type { Client } from 'discord.js';

const h = vi.hoisted(() => {
    const routes: Record<string, (req: unknown, res: unknown) => unknown> = {};
    const listenSpy = vi.fn();
    return { routes, listenSpy };
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
        planReview: { ownerDiscordId: 'OWNER_SNOWFLAKE' },
    },
}));

import { startExpressServer } from './express.js';

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

function clientWithSend(): { client: Client; send: ReturnType<typeof vi.fn> } {
    const send = vi.fn().mockResolvedValue(undefined);
    return { client: { users: { send } } as unknown as Client, send };
}

async function invokeDiscordDM(body: unknown, client: Client) {
    h.routes = {};
    startExpressServer(client);
    const res = makeRes();
    await h.routes['/discordDM']!({ body }, res);
    await new Promise((r) => { setImmediate(r); });
    await new Promise((r) => { setImmediate(r); });
    return res;
}

beforeEach(() => {
    vi.clearAllMocks();
    h.routes = {};
});

describe('POST /discordDM', () => {
    it('sends object payload with SuppressEmbeds flag when suppressEmbeds is true', async () => {
        const { client, send } = clientWithSend();
        const res = await invokeDiscordDM(
            { content: { receivingUserDiscordID: 'USER_123', message: 'hello', suppressEmbeds: true } },
            client,
        );

        expect(res.statusCode).toBe(200);
        expect(send).toHaveBeenCalledTimes(1);
        expect(send).toHaveBeenCalledWith('USER_123', {
            content: 'hello',
            flags: MessageFlags.SuppressEmbeds,
        });
    });

    it('sends plain string when suppressEmbeds is absent', async () => {
        const { client, send } = clientWithSend();
        const res = await invokeDiscordDM(
            { content: { receivingUserDiscordID: 'USER_456', message: 'world' } },
            client,
        );

        expect(res.statusCode).toBe(200);
        expect(send).toHaveBeenCalledTimes(1);
        expect(send).toHaveBeenCalledWith('USER_456', 'world');
    });

    it('400s when message is missing and never calls users.send', async () => {
        const { client, send } = clientWithSend();
        const res = await invokeDiscordDM(
            { content: { receivingUserDiscordID: 'USER_789' } },
            client,
        );

        expect(res.statusCode).toBe(400);
        expect(send).not.toHaveBeenCalled();
    });
});
