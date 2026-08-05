import { describe, it, expect, beforeEach, vi } from 'vitest';
import type { Message, MessageReaction, User } from 'discord.js';

vi.mock('./config.js', () => ({
    config: { bugChannelId: 'BUG_CHANNEL' },
}));

vi.mock('./php-client.js', () => ({
    enqueue: vi.fn().mockResolvedValue({ authorized: true, report_id: 1 }),
    threadReply: vi.fn().mockResolvedValue({ matched: true }),
    reaction: vi.fn().mockResolvedValue({ advanced: true }),
}));

// Attachment capture is exercised end-to-end in attachments.test.ts; here we mock the two
// seams so handleEnqueue's wiring (sanitize → download → shape → enqueue) is tested without
// touching the network or filesystem.
vi.mock('./attachments.js', () => ({
    validateAndSanitize: vi.fn(),
    downloadAttachment: vi.fn(),
}));

import {
    classifyMessage,
    toRoutable,
    handleEnqueue,
    handleReaction,
    type RoutableMessage,
} from './handlers.js';
import * as phpClient from './php-client.js';
import { validateAndSanitize, downloadAttachment } from './attachments.js';
import type { SanitizedAttachment } from './attachments.js';

// A well-formed sanitized image, as validateAndSanitize would return it.
function sanitized(over: Partial<SanitizedAttachment> = {}): SanitizedAttachment {
    return {
        attachmentId: '700000000000000001',
        originalUrl: 'https://cdn.discordapp.com/attachments/1/2/shot.png',
        proxyUrl: 'https://media.discordapp.net/attachments/1/2/shot.png',
        filename: 'shot.png',
        contentType: 'image/png',
        fileSize: 1234,
        ext: 'png',
        isImage: true,
        cachePath: '/cache/333333333333333333-700000000000000001.png',
        ...over,
    };
}

const base: RoutableMessage = {
    authorId: 'GM',
    isBot: false,
    channelId: 'BUG_CHANNEL',
    isThread: false,
    parentId: null,
};

describe('classifyMessage', () => {
    it('top-level message in the bug channel → enqueue', () => {
        expect(classifyMessage(base, 'CLIENT')).toBe('enqueue');
    });

    it('thread under the bug channel → thread-reply', () => {
        expect(classifyMessage({ ...base, channelId: 'THREAD', isThread: true, parentId: 'BUG_CHANNEL' }, 'CLIENT'))
            .toBe('thread-reply');
    });

    it('bot-self → ignore', () => {
        expect(classifyMessage({ ...base, isBot: true }, 'CLIENT')).toBe('ignore');
    });

    it('authorId === clientUserId → ignore', () => {
        expect(classifyMessage({ ...base, authorId: 'CLIENT' }, 'CLIENT')).toBe('ignore');
    });

    it('message in a different channel → ignore', () => {
        expect(classifyMessage({ ...base, channelId: 'OTHER' }, 'CLIENT')).toBe('ignore');
    });

    it('thread under some OTHER parent → ignore', () => {
        expect(classifyMessage({ ...base, channelId: 'THREAD', isThread: true, parentId: 'OTHER' }, 'CLIENT'))
            .toBe('ignore');
    });
});

describe('toRoutable', () => {
    it('maps a non-thread live Message', () => {
        const msg = {
            author: { id: 'A1', bot: false },
            channelId: 'BUG_CHANNEL',
            channel: { isThread: () => false },
        } as unknown as Message;
        expect(toRoutable(msg)).toEqual({
            authorId: 'A1', isBot: false, channelId: 'BUG_CHANNEL', isThread: false, parentId: null,
        });
    });

    it('maps a thread live Message with parentId', () => {
        const msg = {
            author: { id: 'A2', bot: true },
            channelId: 'THREAD',
            channel: { isThread: () => true, parentId: 'BUG_CHANNEL' },
        } as unknown as Message;
        expect(toRoutable(msg)).toEqual({
            authorId: 'A2', isBot: true, channelId: 'THREAD', isThread: true, parentId: 'BUG_CHANNEL',
        });
    });
});

describe('handleEnqueue', () => {
    beforeEach(() => vi.clearAllMocks());

    it('forwards the four fields with string ids (no numeric coercion)', async () => {
        const msg = {
            author: { id: '111111111111111111' },
            channelId: '222222222222222222',
            id: '333333333333333333',
            content: 'a bug',
            attachments: new Map(),
            react: vi.fn().mockResolvedValue(undefined),
        } as unknown as Message;

        await handleEnqueue(msg);

        expect(phpClient.enqueue).toHaveBeenCalledTimes(1);
        const arg = vi.mocked(phpClient.enqueue).mock.calls[0][0];
        expect(arg).toEqual({
            author_id: '111111111111111111',
            channel_id: '222222222222222222',
            message_id: '333333333333333333',
            text: 'a bug',
            attachments: [],
        });
        expect(typeof arg.author_id).toBe('string');
        expect(typeof arg.message_id).toBe('string');
    });

    it('reacts ✴️ to the original message after a successful enqueue', async () => {
        const msg = {
            author: { id: '111111111111111111' },
            channelId: '222222222222222222',
            id: '333333333333333333',
            content: 'a bug',
            attachments: new Map(),
            react: vi.fn().mockResolvedValue(undefined),
        } as unknown as Message;

        await handleEnqueue(msg);

        expect(phpClient.enqueue).toHaveBeenCalledTimes(1);
        expect(msg.react).toHaveBeenCalledTimes(1);
        expect(msg.react).toHaveBeenCalledWith('✴️');
        // enqueue must complete before the ack react fires
        const enqueueOrder = vi.mocked(phpClient.enqueue).mock.invocationCallOrder[0];
        const reactOrder = vi.mocked(msg.react).mock.invocationCallOrder[0];
        expect(enqueueOrder).toBeLessThan(reactOrder);
    });

    it('swallows a react() rejection — handleEnqueue still resolves, enqueue still fired', async () => {
        const msg = {
            author: { id: '111111111111111111' },
            channelId: '222222222222222222',
            id: '333333333333333333',
            content: 'a bug',
            attachments: new Map(),
            react: vi.fn().mockRejectedValue(new Error('Missing Permissions')),
        } as unknown as Message;

        await expect(handleEnqueue(msg)).resolves.toBeUndefined();

        expect(phpClient.enqueue).toHaveBeenCalledTimes(1);
        expect(msg.react).toHaveBeenCalledTimes(1);
    });

    function msgWith(attachments: Map<string, unknown>): Message {
        return {
            author: { id: '111111111111111111' },
            channelId: '222222222222222222',
            id: '333333333333333333',
            content: 'crash with screenshot',
            attachments,
            react: vi.fn().mockResolvedValue(undefined),
        } as unknown as Message;
    }

    it('captures an image attachment into a 1-element attachments array', async () => {
        vi.mocked(validateAndSanitize).mockReturnValue(sanitized());
        vi.mocked(downloadAttachment).mockResolvedValue('/cache/333333333333333333-700000000000000001.png');

        await handleEnqueue(msgWith(new Map([['A', { id: '700000000000000001' }]])));

        const arg = vi.mocked(phpClient.enqueue).mock.calls[0][0];
        expect(arg.attachments).toEqual([{
            attachment_id: '700000000000000001',
            original_url: 'https://cdn.discordapp.com/attachments/1/2/shot.png',
            local_path: '/cache/333333333333333333-700000000000000001.png',
            filename: 'shot.png',
            content_type: 'image/png',
            file_size: 1234,
        }]);
        expect(typeof arg.attachments![0].attachment_id).toBe('string');
    });

    it('a text-only message enqueues with an empty attachments array', async () => {
        await handleEnqueue(msgWith(new Map()));

        expect(vi.mocked(phpClient.enqueue).mock.calls[0][0].attachments).toEqual([]);
        expect(validateAndSanitize).not.toHaveBeenCalled();
    });

    it('a validateAndSanitize-rejected attachment is dropped, but the report still enqueues', async () => {
        vi.mocked(validateAndSanitize).mockReturnValue(null);

        await handleEnqueue(msgWith(new Map([['A', { id: 'not-a-snowflake' }]])));

        expect(phpClient.enqueue).toHaveBeenCalledTimes(1);
        expect(vi.mocked(phpClient.enqueue).mock.calls[0][0].attachments).toEqual([]);
        expect(downloadAttachment).not.toHaveBeenCalled();
    });

    it('a failed download still enqueues the report with local_path: null', async () => {
        vi.mocked(validateAndSanitize).mockReturnValue(sanitized());
        vi.mocked(downloadAttachment).mockResolvedValue(null); // download degraded to URL-only

        await handleEnqueue(msgWith(new Map([['A', { id: '700000000000000001' }]])));

        const arg = vi.mocked(phpClient.enqueue).mock.calls[0][0];
        expect(arg.attachments).toHaveLength(1);
        expect(arg.attachments![0].local_path).toBeNull();
        expect(phpClient.enqueue).toHaveBeenCalledTimes(1);
    });
});

describe('handleReaction', () => {
    beforeEach(() => vi.clearAllMocks());

    function makeReaction(over: Partial<{ partial: boolean; channelId: string; channel: unknown; msgPartial: boolean }>) {
        return {
            partial: over.partial ?? false,
            emoji: { name: '✅' },
            fetch: vi.fn().mockResolvedValue(undefined),
            message: {
                partial: over.msgPartial ?? false,
                id: 'MSG',
                channelId: over.channelId ?? 'BUG_CHANNEL',
                channel: over.channel ?? { isThread: () => false },
                fetch: vi.fn().mockResolvedValue(undefined),
            },
        } as unknown as MessageReaction;
    }

    it('skips the bot\'s own reaction', async () => {
        await handleReaction(makeReaction({}), { bot: true, id: 'BOT' } as unknown as User, 'CLIENT');
        expect(phpClient.reaction).not.toHaveBeenCalled();
    });

    it('skips a reaction by the client user', async () => {
        await handleReaction(makeReaction({}), { bot: false, id: 'CLIENT' } as unknown as User, 'CLIENT');
        expect(phpClient.reaction).not.toHaveBeenCalled();
    });

    it('skips a reaction outside the bug channel (scope guard)', async () => {
        await handleReaction(makeReaction({ channelId: 'OTHER' }), { bot: false, id: 'U' } as unknown as User, 'CLIENT');
        expect(phpClient.reaction).not.toHaveBeenCalled();
    });

    it('skips a reaction in a thread under some OTHER parent (scope guard)', async () => {
        const reaction = makeReaction({ channelId: 'THREAD', channel: { isThread: () => true, parentId: 'OTHER' } });
        await handleReaction(reaction, { bot: false, id: 'U' } as unknown as User, 'CLIENT');
        expect(phpClient.reaction).not.toHaveBeenCalled();
    });

    it('fetches partials and forwards string ids for a real user on a bug-channel message', async () => {
        const reaction = makeReaction({ partial: true, msgPartial: true });
        const user = { bot: false, id: '999999999999999999' } as unknown as User;

        await handleReaction(reaction, user, 'CLIENT');

        expect(reaction.fetch).toHaveBeenCalled();
        expect(reaction.message.fetch).toHaveBeenCalled();
        expect(phpClient.reaction).toHaveBeenCalledTimes(1);
        const arg = vi.mocked(phpClient.reaction).mock.calls[0][0];
        expect(arg).toEqual({ message_id: 'MSG', emoji: '✅', reactor_id: '999999999999999999' });
        expect(typeof arg.reactor_id).toBe('string');
    });

    it('forwards a reaction in a thread under the bug channel (scope guard positive)', async () => {
        const reaction = makeReaction({ channelId: 'THREAD', channel: { isThread: () => true, parentId: 'BUG_CHANNEL' } });
        await handleReaction(reaction, { bot: false, id: 'U' } as unknown as User, 'CLIENT');
        expect(phpClient.reaction).toHaveBeenCalledTimes(1);
    });
});
