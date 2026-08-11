import { describe, it, expect, beforeEach, vi } from 'vitest';
import type { Client } from 'discord.js';

const h = vi.hoisted(() => {
    const routes: Record<string, (req: unknown, res: unknown) => unknown> = {};
    const listenSpy = vi.fn();
    return { routes, listenSpy };
});

vi.mock('express', () => {
    const app = {
        use: vi.fn(),
        post: vi.fn((path: string, handler: (req: unknown, res: unknown) => unknown) => { h.routes[path] = handler; }),
        get: vi.fn((path: string, handler: (req: unknown, res: unknown) => unknown) => { h.routes[path] = handler; }),
        listen: h.listenSpy,
    };
    const expressFn = vi.fn(() => app) as unknown as { (): typeof app; json: () => unknown };
    expressFn.json = vi.fn(() => 'json-mw');
    return { default: expressFn };
});

vi.mock('./config.js', () => ({
    config: { express: { port: 50001 }, bugChannelId: 'BUG_CHANNEL' },
}));

vi.mock('./php-client.js', () => ({
    threadByPr: vi.fn(),
}));

import { startBugBotServer } from './server.js';
import * as phpClient from './php-client.js';

function makeRes() {
    const res = {
        statusCode: 200,
        status: vi.fn((c: number) => { res.statusCode = c; return res; }),
        send: vi.fn(() => res),
        json: vi.fn(() => res),
    };
    return res;
}

// A fake client whose channels.fetch resolves the supplied channel.
function clientWithChannel(channel: unknown): Client {
    return { channels: { fetch: vi.fn().mockResolvedValue(channel) } } as unknown as Client;
}

// discord.js's "Unknown Message" REST failure — what the gateway raises once the GM
// has deleted their own report. server.ts duck-types on `.code`, so a plain Error
// carrying the code exercises the real branch without constructing a REST error.
function unknownMessageError(): Error {
    return Object.assign(new Error('Unknown Message'), { code: 10008 });
}

async function invoke(path: string, body: unknown, client: Client) {
    h.routes = {};
    startBugBotServer(client);
    const res = makeRes();
    await h.routes[path]({ body }, res);
    return res;
}

beforeEach(() => {
    vi.clearAllMocks();
    h.routes = {};
});

describe('loopback bind (gate 14b)', () => {
    it('binds app.listen to 127.0.0.1', () => {
        startBugBotServer(clientWithChannel(null));
        expect(h.listenSpy).toHaveBeenCalledTimes(1);
        expect(h.listenSpy.mock.calls[0][0]).toBe(50001);
        expect(h.listenSpy.mock.calls[0][1]).toBe('127.0.0.1');
    });
});

describe('/react', () => {
    const goodChannel = {
        isTextBased: () => true,
        messages: { fetch: vi.fn().mockResolvedValue({ react: vi.fn().mockResolvedValue(undefined) }) },
    };

    it('400 when a required field is missing', async () => {
        const res = await invoke('/react', { message_id: '1' }, clientWithChannel(goodChannel));
        expect(res.statusCode).toBe(400);
    });

    it('200 on a resolving react', async () => {
        const res = await invoke('/react', { message_id: '1', emoji: '✅' }, clientWithChannel(goodChannel));
        expect(res.statusCode).toBe(200);
        expect(res.send).toHaveBeenCalledWith('reacted');
    });

    it('500 when the discord call rejects', async () => {
        const badChannel = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockRejectedValue(new Error('boom')) },
        };
        const res = await invoke('/react', { message_id: '1', emoji: '✅' }, clientWithChannel(badChannel));
        expect(res.statusCode).toBe(500);
    });
});

describe('/create-thread', () => {
    const channel = {
        isTextBased: () => true,
        messages: { fetch: vi.fn().mockResolvedValue({ startThread: vi.fn().mockResolvedValue({ id: 'THREAD_ID' }) }) },
    };

    it('400 when name is missing', async () => {
        const res = await invoke('/create-thread', { message_id: '1' }, clientWithChannel(channel));
        expect(res.statusCode).toBe(400);
    });

    it('returns {thread_id} as a string', async () => {
        const res = await invoke('/create-thread', { message_id: '1', name: 'bug' }, clientWithChannel(channel));
        expect(res.json).toHaveBeenCalledWith({ thread_id: 'THREAD_ID' });
        expect(typeof res.json.mock.calls[0][0].thread_id).toBe('string');
    });

    it('500 when startThread rejects', async () => {
        const bad = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue({ startThread: vi.fn().mockRejectedValue(new Error('x')) }) },
        };
        const res = await invoke('/create-thread', { message_id: '1', name: 'bug' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });

    it('410 when the source message was deleted (10008), not 500', async () => {
        const gone = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockRejectedValue(unknownMessageError()) },
        };
        const res = await invoke('/create-thread', { message_id: '1', name: 'bug' }, clientWithChannel(gone));
        expect(res.statusCode).toBe(410);
        expect(res.send).toHaveBeenCalledWith('Source message deleted');
    });
});

describe('/post-to-thread', () => {
    const sendable = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };

    it('400 when message is missing', async () => {
        const res = await invoke('/post-to-thread', { thread_id: 'T' }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(400);
    });

    it('200 posted on success', async () => {
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: 'hi' }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(200);
        expect(res.send).toHaveBeenCalledWith('posted');
    });

    it('500 when send rejects', async () => {
        const bad = { isSendable: () => true, send: vi.fn().mockRejectedValue(new Error('x')) };
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: 'hi' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });
});

describe('/mention', () => {
    const sendable = { isSendable: () => true, send: vi.fn().mockResolvedValue({ id: 'MENTION_MSG_ID' }) };

    it('400 when discord_id is missing', async () => {
        const res = await invoke('/mention', { thread_id: 'T', message: 'm' }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(400);
    });

    it('returns {message_id} as a string (the approval_message_id)', async () => {
        const res = await invoke('/mention', { thread_id: 'T', discord_id: '42', message: 'm' }, clientWithChannel(sendable));
        expect(res.json).toHaveBeenCalledWith({ message_id: 'MENTION_MSG_ID' });
        expect(typeof res.json.mock.calls[0][0].message_id).toBe('string');
    });

    it('interpolates the mention and message into the send', async () => {
        await invoke('/mention', { thread_id: 'T', discord_id: '42', message: 'ping' }, clientWithChannel(sendable));
        expect(sendable.send).toHaveBeenCalledWith('<@42> ping');
    });

    it('500 when send rejects', async () => {
        const bad = { isSendable: () => true, send: vi.fn().mockRejectedValue(new Error('x')) };
        const res = await invoke('/mention', { thread_id: 'T', discord_id: '42', message: 'm' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });
});

describe('/get-thread-messages', () => {
    function threadChannel(messages: unknown[]) {
        // A live Message always carries an `attachments` Collection; default an empty one
        // so a test message that omits it maps to `attachments: []` (never a null crash).
        const withAtt = messages.map((m) => ({ attachments: new Map(), ...(m as object) }));
        return {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue(new Map(withAtt.map((m, i) => [String(i), m]))) },
        };
    }

    it('400 when thread_id is missing', async () => {
        const res = await invoke('/get-thread-messages', {}, clientWithChannel(threadChannel([])));
        expect(res.statusCode).toBe(400);
    });

    it('maps author_id as string, includes content + numeric ts, oldest-first', async () => {
        // supplied newest-first; expect oldest-first output
        const channel = threadChannel([
            { author: { id: '20' }, content: 'second', createdTimestamp: 2000 },
            { author: { id: '10' }, content: 'first', createdTimestamp: 1000 },
        ]);
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        const payload = res.json.mock.calls[0][0];
        expect(payload.messages).toEqual([
            { author_id: '10', content: 'first', ts: 1000, attachments: [] },
            { author_id: '20', content: 'second', ts: 2000, attachments: [] },
        ]);
        expect(typeof payload.messages[0].author_id).toBe('string');
    });

    it('requests an explicit limit of 100', async () => {
        const channel = threadChannel([]);
        await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        expect(channel.messages.fetch).toHaveBeenCalledWith({ limit: 100 });
    });

    it('maps per-message attachments to the metadata wire shape', async () => {
        const channel = threadChannel([{
            author: { id: '10' }, content: 'shot', createdTimestamp: 1000,
            attachments: new Map([['A', {
                id: '700000000000000001',
                url: 'https://cdn.discordapp.com/attachments/1/2/shot.png',
                proxyURL: 'https://media.discordapp.net/attachments/1/2/shot.png',
                name: 'shot.png',
                contentType: 'image/png',
            }]]),
        }]);
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        const payload = res.json.mock.calls[0][0];
        expect(payload.messages[0].attachments).toEqual([{
            id: '700000000000000001',
            url: 'https://cdn.discordapp.com/attachments/1/2/shot.png',
            proxy_url: 'https://media.discordapp.net/attachments/1/2/shot.png',
            name: 'shot.png',
            content_type: 'image/png',
        }]);
        expect(typeof payload.messages[0].attachments[0].id).toBe('string');
    });

    it('emits an empty attachments array for a text-only message', async () => {
        const channel = threadChannel([{ author: { id: '10' }, content: 'text', createdTimestamp: 1000 }]);
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        expect(res.json.mock.calls[0][0].messages[0].attachments).toEqual([]);
    });

    it('caps per-message attachments at 10', async () => {
        const many = new Map(Array.from({ length: 12 }, (_, i) => [String(i), {
            id: `${i}`, url: 'https://cdn.discordapp.com/x', proxyURL: 'https://media.discordapp.net/x',
            name: `f${i}.png`, contentType: 'image/png',
        }]));
        const channel = threadChannel([{ author: { id: '10' }, content: 'many', createdTimestamp: 1000, attachments: many }]);
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        expect(res.json.mock.calls[0][0].messages[0].attachments).toHaveLength(10);
    });

    it('500 when fetch rejects', async () => {
        const bad = { isTextBased: () => true, messages: { fetch: vi.fn().mockRejectedValue(new Error('x')) } };
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });
});

describe('/prMerged', () => {
    const sendable = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };

    it('400 when pr_number is missing', async () => {
        const res = await invoke('/prMerged', {}, clientWithChannel(sendable));
        expect(res.statusCode).toBe(400);
    });

    it('no-ops with 200 when threadByPr resolves null (boundary)', async () => {
        vi.mocked(phpClient.threadByPr).mockResolvedValue({ thread_id: null });
        const res = await invoke('/prMerged', { pr_number: 5 }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(200);
        expect(res.send).toHaveBeenCalledWith('no thread for PR');
        expect(sendable.send).not.toHaveBeenCalled();
    });

    it('sends "Fixed!" when threadByPr resolves a thread_id', async () => {
        vi.mocked(phpClient.threadByPr).mockResolvedValue({ thread_id: 'THREAD_ID' });
        const res = await invoke('/prMerged', { pr_number: 5 }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(200);
        expect(sendable.send).toHaveBeenCalledWith('Fixed! ✅');
    });

    it('500 when the thread send rejects', async () => {
        vi.mocked(phpClient.threadByPr).mockResolvedValue({ thread_id: 'THREAD_ID' });
        const bad = { isSendable: () => true, send: vi.fn().mockRejectedValue(new Error('x')) };
        const res = await invoke('/prMerged', { pr_number: 5 }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });
});

describe('/get-message', () => {
    it('400 when message_id is missing', async () => {
        const channel = { isTextBased: () => true, messages: { fetch: vi.fn() } };
        const res = await invoke('/get-message', {}, clientWithChannel(channel));
        expect(res.statusCode).toBe(400);
    });

    it('returns the live content with deleted:false', async () => {
        const channel = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue({ content: 'edited text' }) },
        };
        const res = await invoke('/get-message', { message_id: '1' }, clientWithChannel(channel));
        expect(res.statusCode).toBe(200);
        expect(res.json).toHaveBeenCalledWith({ content: 'edited text', deleted: false });
    });

    // The tick reads this in a $( ) subshell — 200-always is what makes it fail open.
    it('200 with deleted:true on a 10008, NOT a 4xx', async () => {
        const gone = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockRejectedValue(unknownMessageError()) },
        };
        const res = await invoke('/get-message', { message_id: '1' }, clientWithChannel(gone));
        expect(res.statusCode).toBe(200);
        expect(res.json).toHaveBeenCalledWith({ content: null, deleted: true });
    });

    it('200 with deleted:false on a generic error (fails open)', async () => {
        const bad = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockRejectedValue(new Error('bot restarting')) },
        };
        const res = await invoke('/get-message', { message_id: '1' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(200);
        expect(res.json).toHaveBeenCalledWith({ content: null, deleted: false });
    });
});

describe('/reply-to-message', () => {
    function channelWithSource(source: unknown) {
        return { isTextBased: () => true, messages: { fetch: vi.fn().mockResolvedValue(source) } };
    }

    it('400 when message is missing', async () => {
        const res = await invoke('/reply-to-message', { message_id: '1' }, clientWithChannel(channelWithSource({})));
        expect(res.statusCode).toBe(400);
    });

    it('replies once with the literal argument and returns the sent id as a string', async () => {
        const reply = vi.fn().mockResolvedValue({ id: 'REPLY_MSG_ID' });
        const res = await invoke(
            '/reply-to-message',
            { message_id: '1', message: 'Not a bug — closing.' },
            clientWithChannel(channelWithSource({ reply })),
        );
        expect(reply).toHaveBeenCalledTimes(1);
        expect(reply).toHaveBeenCalledWith('Not a bug — closing.');
        expect(res.json).toHaveBeenCalledWith({ message_id: 'REPLY_MSG_ID' });
        expect(typeof res.json.mock.calls[0][0].message_id).toBe('string');
    });

    it('410 when the source message was deleted (10008)', async () => {
        const gone = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockRejectedValue(unknownMessageError()) },
        };
        const res = await invoke('/reply-to-message', { message_id: '1', message: 'x' }, clientWithChannel(gone));
        expect(res.statusCode).toBe(410);
        expect(res.send).toHaveBeenCalledWith('Source message deleted');
    });

    it('500 when reply rejects for any other reason', async () => {
        const reply = vi.fn().mockRejectedValue(new Error('rate limited'));
        const res = await invoke(
            '/reply-to-message',
            { message_id: '1', message: 'x' },
            clientWithChannel(channelWithSource({ reply })),
        );
        expect(res.statusCode).toBe(500);
    });
});
