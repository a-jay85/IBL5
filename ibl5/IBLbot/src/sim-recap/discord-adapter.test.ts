import { describe, it, expect, vi } from 'vitest';
import type { Client } from 'discord.js';
import { createPublisherDeps, GuildMismatchError } from './discord-adapter.js';
import type { EnabledSimRecapSettings } from './settings.js';

const SETTINGS: EnabledSimRecapSettings = {
    enabled: true,
    target: 'prod',
    guildId: 'GUILD_OK',
    liveSimsChannelId: 'LIVE',
    injuriesChannelId: 'INJ',
    stripMentions: false,
    apiBaseUrl: 'https://example.test',
    apiKey: 'k',
    giphyKey: 'giphy',
};

interface FakeChannel {
    guildId: string;
    isSendable(): boolean;
    send: ReturnType<typeof vi.fn>;
    messages: { fetch: ReturnType<typeof vi.fn> };
}

function makeChannel(guildId: string): FakeChannel {
    return {
        guildId,
        isSendable: () => true,
        send: vi.fn(async () => ({ id: 'MSG1' })),
        messages: { fetch: vi.fn() },
    };
}

function makeDeps(channel: FakeChannel | null) {
    const client = {
        user: { id: 'BOT' },
        channels: { fetch: vi.fn(async () => channel) },
    } as unknown as Client;
    const api = { progress: vi.fn(async () => true), finish: vi.fn(async () => true) };
    const deps = createPublisherDeps({ client, settings: SETTINGS, api, token: 'tok', log: vi.fn() });
    return { deps, api };
}

describe('createPublisherDeps', () => {
    it('send refuses a channel in another guild', async () => {
        const channel = makeChannel('GUILD_OTHER');
        const { deps } = makeDeps(channel);

        await expect(deps.send('LIVE', 'hi', { parse: [], users: [], roles: [], repliedUser: false }))
            .rejects.toBeInstanceOf(GuildMismatchError);
        expect(channel.send).not.toHaveBeenCalled();
    });

    it('send posts to a channel in the target guild with the allowed mentions', async () => {
        const channel = makeChannel('GUILD_OK');
        const { deps } = makeDeps(channel);
        const allowed = { parse: [] as never[], users: ['1'], roles: [], repliedUser: false as const };

        const result = await deps.send('LIVE', 'hi', allowed);

        expect(result).toEqual({ id: 'MSG1' });
        expect(channel.send).toHaveBeenCalledWith({ content: 'hi', allowedMentions: allowed });
    });

    it('send throws when the channel is missing or not sendable', async () => {
        const { deps } = makeDeps(null);

        await expect(deps.send('LIVE', 'hi', { parse: [], users: [], roles: [], repliedUser: false })).rejects.toThrow('not sendable');
    });

    it('startThread opens a one-day thread from the fetched message', async () => {
        const channel = makeChannel('GUILD_OK');
        const startThread = vi.fn(async () => ({ id: 'THREAD1' }));
        channel.messages.fetch.mockResolvedValue({ startThread });
        const { deps } = makeDeps(channel);

        const thread = await deps.startThread('LIVE', 'MSG1', 'Title');

        expect(thread).toEqual({ id: 'THREAD1' });
        expect(channel.messages.fetch).toHaveBeenCalledWith('MSG1');
        expect(startThread).toHaveBeenCalledWith({ name: 'Title', autoArchiveDuration: 1440 });
    });

    it('lastOwnMessage returns the newest message authored by the bot', async () => {
        const channel = makeChannel('GUILD_OK');
        channel.messages.fetch.mockResolvedValue(new Map([
            ['1', { id: '1', content: 'old own', createdTimestamp: 100, author: { id: 'BOT' }, thread: null }],
            ['2', { id: '2', content: 'human', createdTimestamp: 300, author: { id: 'HUMAN' }, thread: null }],
            ['3', { id: '3', content: 'new own', createdTimestamp: 200, author: { id: 'BOT' }, thread: { id: 'TH' } }],
        ]));
        const { deps } = makeDeps(channel);

        const own = await deps.lastOwnMessage('LIVE');

        expect(channel.messages.fetch).toHaveBeenCalledWith({ limit: 10 });
        expect(own).toEqual({ id: '3', content: 'new own', threadId: 'TH' });
    });

    it('progress and finish forward the process token to the api', async () => {
        const { deps, api } = makeDeps(makeChannel('GUILD_OK'));

        await deps.progress(12, 4, 'TH');
        await deps.finish(12);

        expect(api.progress).toHaveBeenCalledWith(12, 'tok', 4, 'TH');
        expect(api.finish).toHaveBeenCalledWith(12, 'tok');
    });
});
