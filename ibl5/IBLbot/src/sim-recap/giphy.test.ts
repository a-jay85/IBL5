import { describe, it, expect, vi } from 'vitest';
import { searchGif } from './giphy.js';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('searchGif', () => {
    it('returns null and logs when the key is missing', async () => {
        const fetchFn = vi.fn();
        const log = vi.fn();

        expect(await searchGif('game over', '', fetchFn as unknown as typeof fetch, Math.random, log)).toBeNull();
        expect(await searchGif('game over', undefined, fetchFn as unknown as typeof fetch, Math.random, log)).toBeNull();

        expect(fetchFn).not.toHaveBeenCalled();
        expect(log).toHaveBeenCalledWith('sim-recap: GIPHY_API_KEY unset; skipping GIF');
    });

    it('returns null on a non-OK response', async () => {
        const fetchFn = vi.fn(async () => jsonResponse({}, 503));
        const log = vi.fn();

        const result = await searchGif('game over', 'KEY', fetchFn as unknown as typeof fetch, Math.random, log);

        expect(result).toBeNull();
        expect(log).toHaveBeenCalledWith('sim-recap: giphy http 503');
    });

    it('returns null for empty data and for a thrown fetch', async () => {
        const log = vi.fn();
        const empty = vi.fn(async () => jsonResponse({ data: [] }));
        const boom = vi.fn(async () => { throw new Error('network down'); });

        expect(await searchGif('q', 'KEY', empty as unknown as typeof fetch, Math.random, log)).toBeNull();
        expect(await searchGif('q', 'KEY', boom as unknown as typeof fetch, Math.random, log)).toBeNull();
    });

    it('picks a result url with the injected random', async () => {
        const data = [{ url: 'https://giphy.com/a' }, { url: 'https://giphy.com/b' }, { url: 'https://giphy.com/c' }];
        const fetchFn = vi.fn(async () => jsonResponse({ data }));

        const result = await searchGif('game over', 'KEY', fetchFn as unknown as typeof fetch, () => 0.9, vi.fn());

        expect(result).toBe('https://giphy.com/c');
        const calledUrl = new URL(String((fetchFn.mock.calls[0] as unknown[])[0]));
        expect(calledUrl.origin + calledUrl.pathname).toBe('https://api.giphy.com/v1/gifs/search');
        expect(calledUrl.searchParams.get('q')).toBe('game over');
        expect(calledUrl.searchParams.get('limit')).toBe('25');
        expect(calledUrl.searchParams.get('rating')).toBe('pg');
    });

    it('never logs the api key', async () => {
        const log = vi.fn();
        const key = 'SECRET-GIPHY-KEY';
        const failing = vi.fn(async () => { throw new Error(`boom ${key}`); });
        const badStatus = vi.fn(async () => jsonResponse({}, 500));
        const empty = vi.fn(async () => jsonResponse({ data: [] }));

        await searchGif('q', key, failing as unknown as typeof fetch, Math.random, log);
        await searchGif('q', key, badStatus as unknown as typeof fetch, Math.random, log);
        await searchGif('q', key, empty as unknown as typeof fetch, Math.random, log);

        expect(log).toHaveBeenCalled();
        for (const call of log.mock.calls) {
            expect(String(call[0])).not.toContain(key);
            expect(String(call[0])).not.toContain('api.giphy.com');
        }
    });
});
