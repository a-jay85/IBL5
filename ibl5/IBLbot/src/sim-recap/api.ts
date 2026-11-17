import type { PublishClaim } from './publisher.js';

type FetchFn = typeof fetch;

export interface SimRecapApi {
    claimNext(token: string): Promise<PublishClaim | null>;
    enabled(): Promise<boolean>;
    progress(sim: number, token: string, step: number, threadId: string | null): Promise<boolean>;
    finish(sim: number, token: string): Promise<boolean>;
}

export interface SimRecapApiOptions {
    baseUrl: string;
    apiKey: string;
    fetchFn?: FetchFn;
}

// Mirrors apiPost in ../bug-bot/php-client.ts, with the sim-recap status contract:
// 204 -> null, 409 -> false, any other non-2xx throws an Error that carries only the status.
// The key is never placed in an error or a log line.
export function createSimRecapApi(options: SimRecapApiOptions): SimRecapApi {
    const fetchFn = options.fetchFn ?? fetch;

    async function post(verb: string, payload: unknown): Promise<Response> {
        return fetchFn(`${options.baseUrl}/sim-recap-publish/${verb}`, {
            method: 'POST',
            headers: {
                'X-API-Key': options.apiKey,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });
    }

    function failure(verb: string, response: Response): Error {
        return new Error(`sim-recap api ${verb} failed: http ${response.status}`);
    }

    return {
        async enabled() {
            const response = await post('enabled', {});
            if (!response.ok) throw failure('enabled', response);
            const raw = await response.json() as { status?: unknown; data?: unknown } | null;
            // The IBL API wraps successes as {status:'success', data:{...}}; accept a bare payload too.
            const body: unknown = raw !== null && typeof raw === 'object' && raw.status === 'success' && raw.data !== undefined
                ? raw.data
                : raw;
            if (typeof body !== 'object' || body === null || typeof (body as { enabled?: unknown }).enabled !== 'boolean') {
                throw new Error('sim-recap api enabled failed: malformed body');
            }
            return (body as { enabled: boolean }).enabled;
        },

        async claimNext(token) {
            const response = await post('claim-next', { token });
            if (response.status === 204) return null;
            if (!response.ok) throw failure('claim-next', response);
            const body = await response.json() as { status?: string; data?: unknown };
            // The IBL API wraps successes as {status:'success', data:{...}}; accept a bare payload too.
            const payload = body.status === 'success' && body.data !== undefined ? body.data : body;
            return payload as PublishClaim;
        },

        async progress(sim, token, step, threadId) {
            const response = await post('progress', { sim, token, step, threadId });
            if (response.status === 409) return false;
            if (!response.ok) throw failure('progress', response);
            return true;
        },

        async finish(sim, token) {
            const response = await post('finish', { sim, token });
            if (response.status === 409) return false;
            if (!response.ok) throw failure('finish', response);
            return true;
        },
    };
}
