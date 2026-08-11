import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';

vi.mock('./config.js', () => ({
    config: {
        phpApi: { baseUrl: 'http://test.localhost', key: 'test-api-key' },
    },
}));

import { apiPost, enqueue, sourceUpdated, sourceDeleted, ApiError } from './php-client.js';

function mockFetchOnce(body: unknown, init?: { ok?: boolean; status?: number }): ReturnType<typeof vi.fn> {
    const fn = vi.fn().mockResolvedValue({
        ok: init?.ok ?? true,
        status: init?.status ?? 200,
        json: async () => body,
    });
    vi.stubGlobal('fetch', fn);
    return fn;
}

describe('apiPost', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('builds the URL, sets headers, and sends the JSON body', async () => {
        const fetchFn = mockFetchOnce({ status: 'success', data: { authorized: true, report_id: 7 } });

        await apiPost('enqueue', { message_id: '123456789012345678', text: 'hi' });

        expect(fetchFn).toHaveBeenCalledTimes(1);
        const [url, opts] = fetchFn.mock.calls[0];
        expect(url).toBe('http://test.localhost/api/v1/bug-pipeline/enqueue');
        expect(opts.method).toBe('POST');
        expect(opts.headers['X-API-Key']).toBe('test-api-key');
        expect(opts.headers['Content-Type']).toBe('application/json');
        expect(opts.body).toBe(JSON.stringify({ message_id: '123456789012345678', text: 'hi' }));
    });

    it('keeps a string snowflake a string in the serialized body (no numeric coercion)', async () => {
        const fetchFn = mockFetchOnce({ status: 'success', data: {} });

        await enqueue({
            author_id: '111111111111111111',
            channel_id: '222222222222222222',
            message_id: '333333333333333333',
            text: 'bug',
        });

        const sent = JSON.parse(fetchFn.mock.calls[0][1].body);
        expect(typeof sent.message_id).toBe('string');
        expect(sent.message_id).toBe('333333333333333333');
    });

    it('returns body.data (envelope stripped), not the whole envelope', async () => {
        mockFetchOnce({ status: 'success', data: { authorized: true, report_id: 7 } });

        const result = await apiPost<{ authorized: boolean; report_id: number }>('enqueue', {});

        expect(result).toEqual({ authorized: true, report_id: 7 });
    });

    it('throws ApiError on a non-ok HTTP response', async () => {
        mockFetchOnce({ status: 'error', error: { code: 'BAD', message: 'nope' } }, { ok: false, status: 500 });

        await expect(apiPost('enqueue', {})).rejects.toBeInstanceOf(ApiError);
    });

    it('throws ApiError on a {status:"error"} envelope returned with HTTP 200', async () => {
        mockFetchOnce({ status: 'error', error: { code: 'x', message: 'y' } }, { ok: true, status: 200 });

        await expect(apiPost('enqueue', {})).rejects.toBeInstanceOf(ApiError);
    });
});

describe('sourceUpdated', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('posts to /api/v1/bug-pipeline/source-updated with X-API-Key header', async () => {
        const fetchFn = mockFetchOnce({
            status: 'success',
            data: { matched: true, changed: true, revived: false, status: 'open', thread_id: '111111111111111111' },
        });

        await sourceUpdated({ message_id: '222222222222222222', text: 'updated text' });

        expect(fetchFn).toHaveBeenCalledTimes(1);
        const [url, opts] = fetchFn.mock.calls[0];
        expect(url).toBe('http://test.localhost/api/v1/bug-pipeline/source-updated');
        expect(opts.headers['X-API-Key']).toBe('test-api-key');
        expect(JSON.parse(opts.body)).toEqual({ message_id: '222222222222222222', text: 'updated text' });
    });

    it('rejects with ApiError on {status:"error"} envelope', async () => {
        mockFetchOnce({ status: 'error', error: { code: 'NOT_FOUND', message: 'no match' } }, { ok: true, status: 200 });

        await expect(sourceUpdated({ message_id: '222222222222222222', text: 'x' })).rejects.toBeInstanceOf(ApiError);
    });
});

describe('sourceDeleted', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('posts to /api/v1/bug-pipeline/source-deleted with X-API-Key header', async () => {
        const fetchFn = mockFetchOnce({
            status: 'success',
            data: { matched: true, dropped: true, status: null, thread_id: '333333333333333333' },
        });

        await sourceDeleted({ message_id: '444444444444444444' });

        expect(fetchFn).toHaveBeenCalledTimes(1);
        const [url, opts] = fetchFn.mock.calls[0];
        expect(url).toBe('http://test.localhost/api/v1/bug-pipeline/source-deleted');
        expect(opts.headers['X-API-Key']).toBe('test-api-key');
        expect(JSON.parse(opts.body)).toEqual({ message_id: '444444444444444444' });
    });

    it('rejects with ApiError on {status:"error"} envelope', async () => {
        mockFetchOnce({ status: 'error', error: { code: 'NOT_FOUND', message: 'no match' } }, { ok: true, status: 200 });

        await expect(sourceDeleted({ message_id: '444444444444444444' })).rejects.toBeInstanceOf(ApiError);
    });
});
