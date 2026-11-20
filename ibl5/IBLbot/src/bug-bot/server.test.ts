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
    config: { express: { port: 50001 }, bugChannelId: '111111111111111111' },
}));

vi.mock('./preflight.js', () => ({
    checkBugChannelPermissions: vi.fn(),
}));

vi.mock('./php-client.js', () => ({
    threadByPr: vi.fn(),
}));

import { startBugBotServer } from './server.js';
import * as phpClient from './php-client.js';
import { checkBugChannelPermissions } from './preflight.js';

const BUG_CHANNEL = '111111111111111111';
const BOT_ID = '999999999999999999';
const OTHER_ID = '888888888888888888';
const REPORTER_ID = '777777777777777777';
const THREAD_ID = '222222222222222222';
const MSG_ID = '333333333333333333';
const SENT_ID = '444444444444444444';

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
    vi.mocked(checkBugChannelPermissions).mockResolvedValue({ status: 'ok' });
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
        expect(res.json).toHaveBeenCalledWith({ ok: true, chunks: 1 });
    });

    it('500 when send rejects', async () => {
        const bad = { isSendable: () => true, send: vi.fn().mockRejectedValue(new Error('x')) };
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: 'hi' }, clientWithChannel(bad));
        expect(res.statusCode).toBe(500);
    });

    it('(a) under-cap: short message produces one chunk, verbatim, no prefix', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };
        const msg = 'Short message under cap';
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: msg }, clientWithChannel(ch));
        expect(ch.send).toHaveBeenCalledTimes(1);
        expect(ch.send).toHaveBeenCalledWith({ content: msg, allowedMentions: { parse: [] } });
        expect(res.json).toHaveBeenCalledWith({ ok: true, chunks: 1 });
    });

    it('post-to-thread sends with parse none', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };
        await invoke('/post-to-thread', { thread_id: 'T', message: 'hello @everyone <@&5>' }, clientWithChannel(ch));
        expect(ch.send).toHaveBeenCalledWith({ content: 'hello @everyone <@&5>', allowedMentions: { parse: [] } });
    });

    it('(b) over-cap: 5599-char fixture splits into multiple ordered chunks, content preserved whitespace-insensitively', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };
        // 56 lines of 99 chars joined by \n → 5599 chars; newlines land every 100 chars
        // so lastIndexOf('\n', 1900) finds position 1899, well before the 1900 cap
        const original = Array.from({ length: 56 }, () => 'x'.repeat(99)).join('\n');
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: original }, clientWithChannel(ch));
        const calls = ch.send.mock.calls.map((c: unknown[]) => (c[0] as { content: string }).content);
        expect(calls.length).toBeGreaterThan(1);
        // Ordering: each chunk after the first must carry the exact (N/total) counter
        const parts = calls.map((c: string, i: number) => {
            if (i === 0) return c;
            const prefix = `(${i + 1}/${calls.length}): `;
            expect(c.startsWith(prefix)).toBe(true);
            return c.slice(prefix.length);
        });
        // Content preservation — whitespace-insensitive because trimStart eats the split separator
        const joined = parts.join('');
        expect(joined.replace(/\s+/g, '')).toBe(original.replace(/\s+/g, ''));
        // No chunk may exceed Discord's 2000-char hard cap
        for (const chunk of calls) {
            expect(chunk.length).toBeLessThanOrEqual(2000);
        }
        expect(res.json).toHaveBeenCalledWith({ ok: true, chunks: calls.length });
    });

    it('(c) boundary: 1901-char fixture produces exactly 2 chunks, all content preserved, each within cap', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };
        // No whitespace → split falls back to CHUNK_MAX=1900; trimStart eats nothing
        const original = 'a'.repeat(1901);
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: original }, clientWithChannel(ch));
        const calls = ch.send.mock.calls.map((c: unknown[]) => (c[0] as { content: string }).content);
        expect(calls).toHaveLength(2);
        const prefix2 = '(2/2): ';
        expect(calls[1].startsWith(prefix2)).toBe(true);
        const parts = [calls[0], calls[1].slice(prefix2.length)];
        const joined = parts.join('');
        expect(joined.replace(/\s+/g, '')).toBe(original.replace(/\s+/g, ''));
        for (const chunk of calls) {
            expect(chunk.length).toBeLessThanOrEqual(2000);
        }
        expect(res.json).toHaveBeenCalledWith({ ok: true, chunks: 2 });
    });

    it('(d) security: content-supplied "(N/M): " marker is passed verbatim; server counters use actual chunk total', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue(undefined) };
        // Over-cap so real server markers coexist with the content-supplied forged prefix;
        // newline-rich so the cut lands at ~1806 (not at position 6 via space fallback)
        const original = '(2/9): ' + Array.from({ length: 40 }, () => 'x'.repeat(99)).join('\n');
        const res = await invoke('/post-to-thread', { thread_id: 'T', message: original }, clientWithChannel(ch));
        const calls = ch.send.mock.calls.map((c: unknown[]) => (c[0] as { content: string }).content);
        expect(calls.length).toBeGreaterThan(1);
        // Chunk 1: verbatim prefix of original — no server prefix prepended, no content stripped
        expect(calls[0]).toBe(original.slice(0, calls[0].length));
        // Server markers use actual chunk count (calls.length), NOT the content's forged "9"
        expect(calls[1].startsWith(`(2/${calls.length}): `)).toBe(true);
        if (calls.length > 2) {
            expect(calls[2].startsWith(`(3/${calls.length}): `)).toBe(true);
        }
        expect(res.json).toHaveBeenCalledWith({ ok: true, chunks: calls.length });
    });
});

describe('/mention', () => {
    const sendable = { isSendable: () => true, send: vi.fn().mockResolvedValue({ id: 'MENTION_MSG_ID' }) };

    it('400 when discord_id is missing', async () => {
        const res = await invoke('/mention', { thread_id: 'T', message: 'm' }, clientWithChannel(sendable));
        expect(res.statusCode).toBe(400);
    });

    it('returns {message_id} as a string (the approval_message_id)', async () => {
        const res = await invoke('/mention', { thread_id: 'T', discord_id: REPORTER_ID, message: 'm' }, clientWithChannel(sendable));
        expect(res.json).toHaveBeenCalledWith({ message_id: 'MENTION_MSG_ID' });
        expect(typeof res.json.mock.calls[0][0].message_id).toBe('string');
    });

    it('interpolates the mention and message into the send', async () => {
        await invoke('/mention', { thread_id: 'T', discord_id: REPORTER_ID, message: 'ping' }, clientWithChannel(sendable));
        expect(sendable.send).toHaveBeenCalledWith(expect.objectContaining({ content: `<@${REPORTER_ID}> ping` }));
    });

    it('mention allowedMentions only target user', async () => {
        const ch = { isSendable: () => true, send: vi.fn().mockResolvedValue({ id: 'MENTION_MSG_ID' }) };
        await invoke('/mention', { thread_id: 'T', discord_id: REPORTER_ID, message: 'hi @everyone <@&5>' }, clientWithChannel(ch));
        expect(ch.send).toHaveBeenCalledWith({
            content: `<@${REPORTER_ID}> hi @everyone <@&5>`,
            allowedMentions: { parse: [], users: [REPORTER_ID] },
        });
    });

    it('400 when discord_id is not a snowflake', async () => {
        const ch = { isSendable: () => true, send: vi.fn() };
        const res = await invoke('/mention', { thread_id: 'T', discord_id: '42', message: 'm' }, clientWithChannel(ch));
        expect(res.statusCode).toBe(400);
        expect(ch.send).not.toHaveBeenCalled();
    });

    it('400 when discord_id is 20 digits (overflows BIGINT UNSIGNED)', async () => {
        const ch = { isSendable: () => true, send: vi.fn() };
        const res = await invoke('/mention', { thread_id: 'T', discord_id: '99999999999999999999', message: 'm' }, clientWithChannel(ch));
        expect(res.statusCode).toBe(400);
        expect(ch.send).not.toHaveBeenCalled();
    });

    it('500 when send rejects', async () => {
        const bad = { isSendable: () => true, send: vi.fn().mockRejectedValue(new Error('x')) };
        const res = await invoke('/mention', { thread_id: 'T', discord_id: REPORTER_ID, message: 'm' }, clientWithChannel(bad));
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
            { id: '987654321098765432', author: { id: '20' }, content: 'second', createdTimestamp: 2000 },
            { id: '987654321098765431', author: { id: '10' }, content: 'first', createdTimestamp: 1000 },
        ]);
        const res = await invoke('/get-thread-messages', { thread_id: 'T' }, clientWithChannel(channel));
        const payload = res.json.mock.calls[0][0];
        expect(payload.messages).toEqual([
            { id: '987654321098765431', author_id: '10', content: 'first', ts: 1000, attachments: [] },
            { id: '987654321098765432', author_id: '20', content: 'second', ts: 2000, attachments: [] },
        ]);
        expect(typeof payload.messages[0].author_id).toBe('string');
        // The tick omits operator mentions from the transcript by this id; a snowflake
        // must stay a string or it loses precision past 2^53.
        expect(typeof payload.messages[0].id).toBe('string');
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

// A fake client resolving channels by id, with an identified bot user.
function clientWithChannels(channels: Record<string, unknown>): Client {
    return {
        user: { id: BOT_ID },
        channels: {
            fetch: vi.fn((id: string) => {
                const hit = channels[id];
                if (hit instanceof Error) return Promise.reject(hit);
                return Promise.resolve(hit ?? null);
            }),
        },
    } as unknown as Client;
}

function bugThread(overrides: Record<string, unknown> = {}) {
    return {
        id: THREAD_ID,
        isThread: () => true,
        parentId: BUG_CHANNEL,
        locked: false,
        archived: false,
        setArchived: vi.fn().mockResolvedValue(undefined),
        send: vi.fn().mockResolvedValue({ id: SENT_ID }),
        ...overrides,
    };
}

function bugChannelWith(extra: Record<string, unknown> = {}) {
    return {
        id: BUG_CHANNEL,
        isThread: () => false,
        isTextBased: () => true,
        isSendable: () => true,
        send: vi.fn().mockResolvedValue({ id: SENT_ID }),
        messages: { fetch: vi.fn() },
        ...extra,
    };
}

describe('/edit-message', () => {
    function channelWithMessage(message: unknown) {
        return bugChannelWith({ messages: { fetch: vi.fn().mockResolvedValue(message) } });
    }

    it('edit-message refuses non-bot author', async () => {
        const edit = vi.fn();
        const channel = channelWithMessage({ id: MSG_ID, author: { id: OTHER_ID }, edit });
        const res = await invoke(
            '/edit-message',
            { channel_id: BUG_CHANNEL, message_id: MSG_ID, content: 'new' },
            clientWithChannels({ [BUG_CHANNEL]: channel }),
        );
        expect(res.statusCode).toBe(403);
        expect(res.json).toHaveBeenCalledWith({ error: 'not_bot_author' });
        expect(edit).not.toHaveBeenCalled();
    });

    it('edit-message refuses channel outside bug channel', async () => {
        const OUTSIDE = '555555555555555555';
        const fetchMsg = vi.fn();
        const outside = {
            id: OUTSIDE,
            isThread: () => false,
            isTextBased: () => true,
            messages: { fetch: fetchMsg },
        };
        const res = await invoke(
            '/edit-message',
            { channel_id: OUTSIDE, message_id: MSG_ID, content: 'new' },
            clientWithChannels({ [OUTSIDE]: outside }),
        );
        expect(res.statusCode).toBe(403);
        expect(fetchMsg).not.toHaveBeenCalled();
    });

    it('edit-message edits bot message with allowedMentions.parse empty', async () => {
        const edit = vi.fn().mockResolvedValue(undefined);
        const channel = channelWithMessage({ id: MSG_ID, author: { id: BOT_ID }, edit });
        const res = await invoke(
            '/edit-message',
            { channel_id: BUG_CHANNEL, message_id: MSG_ID, content: 'new @everyone' },
            clientWithChannels({ [BUG_CHANNEL]: channel }),
        );
        expect(res.statusCode).toBe(200);
        expect(edit).toHaveBeenCalledWith({ content: 'new @everyone', allowedMentions: { parse: [] } });
    });

    it('edits a bot message inside a bug-channel thread', async () => {
        const edit = vi.fn().mockResolvedValue(undefined);
        const thread = bugThread({
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue({ id: MSG_ID, author: { id: BOT_ID }, edit }) },
        });
        const res = await invoke(
            '/edit-message',
            { channel_id: THREAD_ID, message_id: MSG_ID, content: 'new' },
            clientWithChannels({ [THREAD_ID]: thread }),
        );
        expect(res.statusCode).toBe(200);
        expect(edit).toHaveBeenCalledTimes(1);
    });

    it('400 on a non-snowflake id or empty content', async () => {
        const client = clientWithChannels({ [BUG_CHANNEL]: bugChannelWith() });
        const badId = await invoke('/edit-message', { channel_id: 'nope', message_id: MSG_ID, content: 'x' }, client);
        expect(badId.statusCode).toBe(400);
        const empty = await invoke('/edit-message', { channel_id: BUG_CHANNEL, message_id: MSG_ID, content: '' }, client);
        expect(empty.statusCode).toBe(400);
    });

    it('410 when the message no longer exists', async () => {
        const channel = bugChannelWith({ messages: { fetch: vi.fn().mockRejectedValue(unknownMessageError()) } });
        const res = await invoke(
            '/edit-message',
            { channel_id: BUG_CHANNEL, message_id: MSG_ID, content: 'x' },
            clientWithChannels({ [BUG_CHANNEL]: channel }),
        );
        expect(res.statusCode).toBe(410);
    });
});

describe('/list-threads', () => {
    function threadsOf(...ts: Array<{ id: string; archived: boolean; locked: boolean }>) {
        return new Map(ts.map((t) => [t.id, t]));
    }

    it('list-threads pages archived threads', async () => {
        const T1 = '100000000000000001';
        const T2 = '100000000000000002';
        const fetchArchived = vi.fn().mockResolvedValue({
            threads: threadsOf({ id: T1, archived: true, locked: false }, { id: T2, archived: true, locked: true }),
            hasMore: true,
        });
        const channel = bugChannelWith({ threads: { fetchArchived, fetchActive: vi.fn() } });
        const BEFORE = '100000000000000009';
        const res = await invoke(
            '/list-threads',
            { archived: true, before: BEFORE },
            clientWithChannels({ [BUG_CHANNEL]: channel }),
        );
        expect(fetchArchived).toHaveBeenCalledWith({ type: 'public', before: BEFORE, limit: 100 });
        expect(res.json).toHaveBeenCalledWith({
            threads: [
                { id: T1, archived: true, locked: false },
                { id: T2, archived: true, locked: true },
            ],
            has_more: true,
            next_before: T2,
        });
    });

    it('lists active threads without paging', async () => {
        const T1 = '100000000000000001';
        const fetchActive = vi.fn().mockResolvedValue({ threads: threadsOf({ id: T1, archived: false, locked: false }) });
        const channel = bugChannelWith({ threads: { fetchArchived: vi.fn(), fetchActive } });
        const res = await invoke('/list-threads', { archived: false }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(res.json).toHaveBeenCalledWith({
            threads: [{ id: T1, archived: false, locked: false }],
            has_more: false,
            next_before: null,
        });
    });

    it('list-threads 503 when permission missing', async () => {
        vi.mocked(checkBugChannelPermissions).mockResolvedValue({ status: 'missing', missing: ['SendMessages'] });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const fetchArchived = vi.fn();
        const channel = bugChannelWith({ threads: { fetchArchived, fetchActive: vi.fn() } });
        const res = await invoke('/list-threads', { archived: true }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(res.statusCode).toBe(503);
        expect(res.json).toHaveBeenCalledWith({ error: 'missing_permissions', detail: 'missing: SendMessages' });
        expect(fetchArchived).not.toHaveBeenCalled();
        expect(console.error).toHaveBeenCalledWith(expect.stringContaining('SendMessages'));
    });

    it('503 when permissions are unknown', async () => {
        vi.mocked(checkBugChannelPermissions).mockResolvedValue({ status: 'unknown', reason: 'no member' });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const res = await invoke('/list-threads', { archived: false }, clientWithChannels({ [BUG_CHANNEL]: bugChannelWith() }));
        expect(res.statusCode).toBe(503);
    });

    it('400 when archived is not a boolean', async () => {
        const res = await invoke('/list-threads', { archived: 'yes' }, clientWithChannels({}));
        expect(res.statusCode).toBe(400);
    });
});

describe('/scan-messages', () => {
    it('scan-messages returns only bot messages', async () => {
        const M1 = '300000000000000001';
        const M2 = '300000000000000002';
        const M3 = '300000000000000003';
        const page = new Map<string, unknown>([
            [M3, { id: M3, author: { id: BOT_ID }, content: 'bot note', reference: { messageId: MSG_ID }, createdTimestamp: 3000 }],
            [M2, { id: M2, author: { id: OTHER_ID }, content: 'gm chatter', reference: null, createdTimestamp: 2000 }],
            [M1, { id: M1, author: { id: BOT_ID }, content: 'older bot note', reference: null, createdTimestamp: 1000 }],
        ]);
        const fetchMsgs = vi.fn().mockResolvedValue(page);
        const channel = bugChannelWith({ messages: { fetch: fetchMsgs } });
        const res = await invoke('/scan-messages', { channel_id: BUG_CHANNEL }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(fetchMsgs).toHaveBeenCalledWith({ limit: 100, before: undefined });
        expect(res.json).toHaveBeenCalledWith({
            messages: [
                { id: M3, channel_id: BUG_CHANNEL, content: 'bot note', reference_message_id: MSG_ID, created_timestamp: 3000 },
                { id: M1, channel_id: BUG_CHANNEL, content: 'older bot note', reference_message_id: null, created_timestamp: 1000 },
            ],
            next_before: null,
        });
    });

    it('returns next_before when the page is full', async () => {
        const full = new Map<string, unknown>(
            Array.from({ length: 100 }, (_, i) => {
                const id = `5000000000000${String(1000 + (100 - i))}`;
                return [id, { id, author: { id: OTHER_ID }, content: '', reference: null, createdTimestamp: 1000 - i }];
            }),
        );
        const channel = bugChannelWith({ messages: { fetch: vi.fn().mockResolvedValue(full) } });
        const res = await invoke('/scan-messages', { channel_id: BUG_CHANNEL }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(res.json.mock.calls[0][0].next_before).toBe('50000000000001001');
    });

    it('403 outside the bug channel scope', async () => {
        const OUTSIDE = '555555555555555555';
        const outside = { id: OUTSIDE, isThread: () => false, isTextBased: () => true, messages: { fetch: vi.fn() } };
        const res = await invoke('/scan-messages', { channel_id: OUTSIDE }, clientWithChannels({ [OUTSIDE]: outside }));
        expect(res.statusCode).toBe(403);
    });

    it('503 when permission missing', async () => {
        vi.mocked(checkBugChannelPermissions).mockResolvedValue({ status: 'missing', missing: ['ReadMessageHistory'] });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const res = await invoke('/scan-messages', { channel_id: BUG_CHANNEL }, clientWithChannels({ [BUG_CHANNEL]: bugChannelWith() }));
        expect(res.statusCode).toBe(503);
    });
});

describe('/post-with-mention', () => {
    const ALLOWED = { parse: [], users: [REPORTER_ID], repliedUser: false };

    it('post-with-mention unarchives before send', async () => {
        const order: string[] = [];
        const thread = bugThread({
            archived: true,
            setArchived: vi.fn(async () => { order.push('unarchive'); }),
            send: vi.fn(async () => { order.push('send'); return { id: SENT_ID }; }),
        });
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', discord_id: REPORTER_ID, thread_id: THREAD_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: bugChannelWith() }),
        );
        expect(order).toEqual(['unarchive', 'send']);
        expect(thread.setArchived).toHaveBeenCalledWith(false, 'bug close notice');
        expect(res.json).toHaveBeenCalledWith({ ok: true, target: 'thread', message_id: SENT_ID });
    });

    it('post-with-mention locked thread falls back', async () => {
        const thread = bugThread({ locked: true, archived: true });
        const channel = bugChannelWith();
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', discord_id: REPORTER_ID, thread_id: THREAD_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: channel }),
        );
        expect(thread.setArchived).not.toHaveBeenCalled();
        expect(thread.send).not.toHaveBeenCalled();
        expect(channel.send).toHaveBeenCalledTimes(1);
        expect(res.json).toHaveBeenCalledWith({ ok: true, target: 'channel', message_id: SENT_ID });
    });

    it('post-with-mention unknown thread replies to original', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        const reply = vi.fn().mockResolvedValue({ id: SENT_ID });
        const channel = bugChannelWith({ messages: { fetch: vi.fn().mockResolvedValue({ reply }) } });
        const gone = Object.assign(new Error('Unknown Channel'), { code: 10003 });
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', discord_id: REPORTER_ID, thread_id: THREAD_ID, reply_to_message_id: MSG_ID },
            clientWithChannels({ [THREAD_ID]: gone, [BUG_CHANNEL]: channel }),
        );
        expect(reply).toHaveBeenCalledTimes(1);
        expect(channel.send).not.toHaveBeenCalled();
        expect(res.json).toHaveBeenCalledWith({ ok: true, target: 'reply', message_id: SENT_ID });
    });

    it('post-with-mention sets allowedMentions.users to the reporter only', async () => {
        const thread = bugThread();
        await invoke(
            '/post-with-mention',
            { message: 'closed', discord_id: REPORTER_ID, thread_id: THREAD_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: bugChannelWith() }),
        );
        expect(thread.send).toHaveBeenCalledWith({ content: `<@${REPORTER_ID}> closed`, allowedMentions: ALLOWED });
    });

    it('allowedMentions pings nobody when no discord_id is given', async () => {
        const channel = bugChannelWith();
        await invoke('/post-with-mention', { message: 'closed' }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(channel.send).toHaveBeenCalledWith({
            content: 'closed',
            allowedMentions: { parse: [], users: [], repliedUser: false },
        });
    });

    it('post-with-mention rejects non-digit discord_id', async () => {
        const channel = bugChannelWith();
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', discord_id: '12ab' },
            clientWithChannels({ [BUG_CHANNEL]: channel }),
        );
        expect(res.statusCode).toBe(400);
        expect(channel.send).not.toHaveBeenCalled();
    });

    it('post-with-mention rejects mention token in message', async () => {
        const channel = bugChannelWith();
        for (const message of ['hi @everyone', 'hi @here', 'hi <@123>']) {
            const res = await invoke('/post-with-mention', { message }, clientWithChannels({ [BUG_CHANNEL]: channel }));
            expect(res.statusCode).toBe(400);
        }
        expect(channel.send).not.toHaveBeenCalled();
    });

    it('post-with-mention send failure returns 502 without fallback', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const thread = bugThread({ send: vi.fn().mockRejectedValue(new Error('boom')) });
        const reply = vi.fn();
        const channel = bugChannelWith({ messages: { fetch: vi.fn().mockResolvedValue({ reply }) } });
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', thread_id: THREAD_ID, reply_to_message_id: MSG_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: channel }),
        );
        expect(res.statusCode).toBe(502);
        expect(reply).not.toHaveBeenCalled();
        expect(channel.send).not.toHaveBeenCalled();
    });

    it('falls back when the unarchive throws', async () => {
        vi.spyOn(console, 'warn').mockImplementation(() => {});
        const thread = bugThread({ archived: true, setArchived: vi.fn().mockRejectedValue(new Error('403')) });
        const channel = bugChannelWith();
        const res = await invoke(
            '/post-with-mention',
            { message: 'closed', thread_id: THREAD_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: channel }),
        );
        expect(thread.send).not.toHaveBeenCalled();
        expect(res.json).toHaveBeenCalledWith({ ok: true, target: 'channel', message_id: SENT_ID });
    });

    it('falls back when the thread belongs to another parent', async () => {
        const thread = bugThread({ parentId: '555555555555555555' });
        const channel = bugChannelWith();
        await invoke(
            '/post-with-mention',
            { message: 'closed', thread_id: THREAD_ID },
            clientWithChannels({ [THREAD_ID]: thread, [BUG_CHANNEL]: channel }),
        );
        expect(thread.send).not.toHaveBeenCalled();
        expect(channel.send).toHaveBeenCalledTimes(1);
    });

    it('503 when permission missing, nothing posted', async () => {
        vi.mocked(checkBugChannelPermissions).mockResolvedValue({ status: 'missing', missing: ['SendMessages'] });
        vi.spyOn(console, 'error').mockImplementation(() => {});
        const channel = bugChannelWith();
        const res = await invoke('/post-with-mention', { message: 'closed' }, clientWithChannels({ [BUG_CHANNEL]: channel }));
        expect(res.statusCode).toBe(503);
        expect(channel.send).not.toHaveBeenCalled();
    });
});
