import { describe, it, expect, vi } from 'vitest';
import { createServer, type IncomingMessage, type Server } from 'node:http';
import type { AddressInfo } from 'node:net';
import { createErrorReporter, formatAlert, redact } from './error-reporter.js';

const WEBHOOK = 'https://discord.com/api/webhooks/1/SECRETTOKEN';

function okFetch() {
    return vi.fn(async (..._args: Parameters<typeof fetch>) => new Response(null, { status: 204 }));
}

function gaveUp(sim: number, kind: 'step-gave-up' | 'stuck' = 'step-gave-up') {
    return { kind, sim, step: { index: 0, kind: 'thread_message' as const }, error: new Error('x') };
}

function listen(server: Server): Promise<number> {
    return new Promise((resolve) => {
        server.listen(0, '127.0.0.1', () => resolve((server.address() as AddressInfo).port));
    });
}

function readBody(req: IncomingMessage): Promise<string> {
    return new Promise((resolve) => {
        let data = '';
        req.on('data', (chunk) => (data += String(chunk)));
        req.on('end', () => resolve(data));
    });
}

describe('error-reporter', () => {
    it('names the sim, step, and error class in the alert', () => {
        const text = formatAlert({
            kind: 'step-gave-up',
            sim: 42,
            step: { index: 3, kind: 'thread_message' },
            error: new TypeError('boom'),
        });

        expect(text).toBe('sim-recap: sim 42 step 3 (thread_message) gave up: TypeError: boom');
    });

    it('skips a second report for the same sim and kind', async () => {
        const fetchFn = okFetch();
        const reporter = createErrorReporter({ webhookUrl: WEBHOOK, fetchFn, log: vi.fn() });

        await reporter.report(gaveUp(7));
        await reporter.report(gaveUp(7));
        expect(fetchFn).toHaveBeenCalledTimes(1);

        await reporter.report({ kind: 'stuck', sim: 7, step: null, lastStep: 2 });
        expect(fetchFn).toHaveBeenCalledTimes(2);
    });

    it('makes no HTTP call when the webhook URL is not set', async () => {
        const fetchFn = okFetch();
        const log = vi.fn();
        const reporter = createErrorReporter({ fetchFn, log });

        await reporter.report(gaveUp(1));
        await reporter.report(gaveUp(2));

        expect(fetchFn).not.toHaveBeenCalled();
        const lines = log.mock.calls.map((c) => String(c[0]));
        expect(lines.filter((l) => l.includes('is not set'))).toHaveLength(1);
        expect(lines.filter((l) => l.includes('gave up'))).toHaveLength(2);
    });

    it('never logs the webhook URL when the post fails', async () => {
        const log = vi.fn();
        const rejecting = vi.fn(async (..._args: Parameters<typeof fetch>): Promise<Response> => {
            throw new Error('connect failed https://discord.com/api/webhooks/1/SECRETTOKEN');
        });
        await createErrorReporter({ webhookUrl: WEBHOOK, fetchFn: rejecting, log }).report(gaveUp(1));

        const failing = vi.fn(async (..._args: Parameters<typeof fetch>) => new Response(null, { status: 500 }));
        await createErrorReporter({ webhookUrl: WEBHOOK, fetchFn: failing, log }).report(gaveUp(1));

        const joined = log.mock.calls.map((c) => String(c[0])).join('\n');
        expect(joined).not.toContain('SECRETTOKEN');
        expect(joined).not.toContain('api/webhooks');
        expect(joined).toContain('http 500');
    });

    it('redacts webhook and other URLs from the alert text', async () => {
        const messages = [
            'bad https://discord.com/api/webhooks/1/SECRETTOKEN here',
            'bad discord.com/api/webhooks/1/SECRETTOKEN here',
            'bad http://10.0.0.5:3000/x here',
        ];
        for (const message of messages) {
            const text = formatAlert({ kind: 'sweep-crash', sim: null, step: null, error: new Error(message) });
            expect(text).toContain('<url>');
            expect(text).not.toContain('SECRETTOKEN');
            expect(text).not.toContain('10.0.0.5');
        }

        const fetchFn = okFetch();
        const reporter = createErrorReporter({ webhookUrl: WEBHOOK, fetchFn, log: vi.fn() });
        await reporter.report({
            kind: 'sweep-crash',
            sim: null,
            step: null,
            error: new Error(messages[0] ?? ''),
        });
        const init = fetchFn.mock.calls[0]?.[1] as { body: string };
        expect(init.body).not.toContain('SECRETTOKEN');
    });

    it('leaves near-miss text without a URL untouched', () => {
        expect(redact('https handshake failed')).toBe('https handshake failed');
        expect(redact('see discord.com/channels/1')).toBe('see discord.com/channels/1');
    });

    it('posts JSON with mentions disabled to a real local listener', async () => {
        let seen: { method?: string; contentType?: string; body: string } = { body: '' };
        const server = createServer(async (req, res) => {
            seen = {
                method: req.method,
                contentType: req.headers['content-type'],
                body: await readBody(req),
            };
            res.statusCode = 204;
            res.end();
        });
        try {
            const port = await listen(server);
            const reporter = createErrorReporter({
                webhookUrl: `http://127.0.0.1:${port}/api/webhooks/1/SECRETTOKEN`,
                log: vi.fn(),
            });

            await reporter.report({
                kind: 'step-gave-up',
                sim: 42,
                step: { index: 1, kind: 'thread_message' },
                error: new Error('boom'),
            });

            expect(seen.method).toBe('POST');
            expect(seen.contentType).toBe('application/json');
            const parsed = JSON.parse(seen.body) as { content: string; allowed_mentions: unknown };
            expect(parsed.allowed_mentions).toEqual({ parse: [] });
            expect(parsed.content).toContain('sim 42');
        } finally {
            server.closeAllConnections();
            server.close();
        }
    });

    it('resolves and logs when the webhook hangs past the timeout', async () => {
        const server = createServer(() => {
            // never answers
        });
        const log = vi.fn();
        try {
            const port = await listen(server);
            const reporter = createErrorReporter({
                webhookUrl: `http://127.0.0.1:${port}/api/webhooks/1/SECRETTOKEN`,
                timeoutMs: 100,
                log,
            });

            await reporter.report(gaveUp(5));

            const lines = log.mock.calls.map((c) => String(c[0]));
            expect(lines.filter((l) => l.startsWith('sim-recap: errors webhook post failed ('))).toHaveLength(1);
            expect(lines.join('\n')).not.toContain('SECRETTOKEN');
        } finally {
            server.closeAllConnections();
            server.close();
        }
    }, 3000);
});
