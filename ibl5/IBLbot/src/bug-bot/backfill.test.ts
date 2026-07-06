import { describe, it, expect, beforeEach, vi } from 'vitest';
import type { Client } from 'discord.js';

vi.mock('./config.js', () => ({
    config: { bugChannelId: 'BUG_CHANNEL' },
}));

vi.mock('./php-client.js', () => ({
    getState: vi.fn(),
    lastSeen: vi.fn().mockResolvedValue({ ok: true }),
}));

import { runBackfill } from './backfill.js';
import * as phpClient from './php-client.js';

// discord.js messages.fetch returns a Collection (extends Map, has .first()/.size).
function fakeCollection(arr: unknown[]) {
    return {
        size: arr.length,
        values: () => arr[Symbol.iterator](),
        first: () => arr[0],
    };
}

function msg(id: string, ts: number) {
    return { id, createdTimestamp: ts };
}

function clientWithChannel(channel: unknown): Client {
    return { channels: { fetch: vi.fn().mockResolvedValue(channel) } } as unknown as Client;
}

beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
});

describe('runBackfill', () => {
    it('replays each new top-level message via the shared handleEnqueue, oldest-first, string ids', async () => {
        const getState = vi.fn().mockResolvedValue({ last_processed_message_id: '100' });
        const handleEnqueue = vi.fn().mockResolvedValue(undefined);
        // supplied newest-first (discord order); expect oldest-first replay
        const channel = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue(fakeCollection([msg('300', 3000), msg('200', 2000)])) },
        };

        await runBackfill(clientWithChannel(channel), { getState, handleEnqueue });

        expect(handleEnqueue).toHaveBeenCalledTimes(2);
        expect(handleEnqueue.mock.calls[0][0].id).toBe('200');   // oldest first
        expect(handleEnqueue.mock.calls[1][0].id).toBe('300');
        expect(typeof handleEnqueue.mock.calls[0][0].id).toBe('string');
    });

    it('does NOT walk thread history — only the parent channel is fetched', async () => {
        const getState = vi.fn().mockResolvedValue({ last_processed_message_id: '100' });
        const handleEnqueue = vi.fn().mockResolvedValue(undefined);
        const channel = {
            isTextBased: () => true,
            messages: { fetch: vi.fn().mockResolvedValue(fakeCollection([msg('200', 2000)])) },
        };
        const client = clientWithChannel(channel);

        await runBackfill(client, { getState, handleEnqueue });

        // channels.fetch only ever called with the bug channel id — never a thread id.
        const fetchMock = vi.mocked(client.channels.fetch);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock).toHaveBeenCalledWith('BUG_CHANNEL');
    });

    it('null cursor → no replay, start-fresh (boundary)', async () => {
        const getState = vi.fn().mockResolvedValue({ last_processed_message_id: null });
        const handleEnqueue = vi.fn().mockResolvedValue(undefined);
        const fetch = vi.fn().mockResolvedValue(fakeCollection([msg('500', 5000)]));
        const channel = { isTextBased: () => true, messages: { fetch } };

        await runBackfill(clientWithChannel(channel), { getState, handleEnqueue });

        expect(handleEnqueue).not.toHaveBeenCalled();
        // no messages.fetch({ after }) issued (only the {limit:1} seed peek)
        for (const call of fetch.mock.calls) {
            expect(call[0]).not.toHaveProperty('after');
        }
        // seeded the cursor from the newest message
        expect(phpClient.lastSeen).toHaveBeenCalledWith({ channel_id: 'BUG_CHANNEL', message_id: '500' });
    });

    it('swallows a getState rejection without throwing (negative)', async () => {
        const getState = vi.fn().mockRejectedValue(new Error('cursor read failed'));
        const handleEnqueue = vi.fn();

        await expect(runBackfill(clientWithChannel(null), { getState, handleEnqueue })).resolves.toBeUndefined();
        expect(handleEnqueue).not.toHaveBeenCalled();
        expect(console.error).toHaveBeenCalled();
    });

    it('paging advances `after` and stops on a short page (no infinite loop)', async () => {
        const getState = vi.fn().mockResolvedValue({ last_processed_message_id: '0' });
        const handleEnqueue = vi.fn().mockResolvedValue(undefined);

        const fullPage = Array.from({ length: 100 }, (_, i) => msg(String(i + 1), (i + 1) * 10));
        const fetch = vi.fn()
            .mockResolvedValueOnce(fakeCollection(fullPage))                 // 100 → keep paging
            .mockResolvedValueOnce(fakeCollection([msg('101', 1010)]));      // short → stop
        const channel = { isTextBased: () => true, messages: { fetch } };

        await runBackfill(clientWithChannel(channel), { getState, handleEnqueue });

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(fetch.mock.calls[0][0]).toEqual({ after: '0', limit: 100 });
        // after advanced to the newest id of the first page (100, the last after oldest-first sort)
        expect(fetch.mock.calls[1][0]).toEqual({ after: '100', limit: 100 });
        expect(handleEnqueue).toHaveBeenCalledTimes(101);
    });
});
