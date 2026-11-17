import { describe, it, expect, vi } from 'vitest';
import { createSimRecapApi } from './api.js';

const BASE_URL = 'https://example.test/ibl5/api/v1';

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

function makeApi(fetchFn: typeof fetch) {
    return createSimRecapApi({ baseUrl: BASE_URL, apiKey: 'secret-key', fetchFn });
}

describe('createSimRecapApi enabled()', () => {
    it('enabled() posts to the sim-recap-publish/enabled route with the API key', async () => {
        const fetchFn = vi.fn(async () => jsonResponse({ enabled: true }));
        const api = makeApi(fetchFn as unknown as typeof fetch);

        const result = await api.enabled();

        expect(result).toBe(true);
        expect(fetchFn).toHaveBeenCalledTimes(1);
        const [url, init] = fetchFn.mock.calls[0] as unknown as [string, RequestInit];
        expect(url).toBe(`${BASE_URL}/sim-recap-publish/enabled`);
        expect(init.method).toBe('POST');
        expect((init.headers as Record<string, string>)['X-API-Key']).toBe('secret-key');
    });

    it('enabled() returns the boolean from a bare or wrapped body', async () => {
        const bareOff = makeApi((async () => jsonResponse({ enabled: false })) as unknown as typeof fetch);
        const wrappedOn = makeApi((async () => jsonResponse({ status: 'success', data: { enabled: true } })) as unknown as typeof fetch);
        const wrappedOff = makeApi((async () => jsonResponse({ status: 'success', data: { enabled: false } })) as unknown as typeof fetch);

        expect(await bareOff.enabled()).toBe(false);
        expect(await wrappedOn.enabled()).toBe(true);
        expect(await wrappedOff.enabled()).toBe(false);
    });

    it.each([500, 403])('enabled() throws on a non-2xx status (%i)', async (status) => {
        const api = makeApi((async () => jsonResponse({ enabled: true }, status)) as unknown as typeof fetch);

        await expect(api.enabled()).rejects.toThrow(`sim-recap api enabled failed: http ${status}`);
    });

    it.each([{}, { enabled: 'On' }, null])('enabled() throws on a malformed body (%j)', async (body) => {
        const api = makeApi((async () => jsonResponse(body)) as unknown as typeof fetch);

        await expect(api.enabled()).rejects.toThrow('malformed body');
    });

    it('enabled() throws on a body-less 204', async () => {
        const api = makeApi((async () => new Response(null, { status: 204 })) as unknown as typeof fetch);

        await expect(api.enabled()).rejects.toThrow();
    });
});
