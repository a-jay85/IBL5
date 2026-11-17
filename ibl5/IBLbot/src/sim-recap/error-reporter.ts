// Sim-recap failure alerts. Posts one short line per (sim, failure kind) to the #errors
// webhook. The webhook URL is a secret: it never reaches a log line or an alert body.
import type { PublishStep } from './publisher.js';

type FetchFn = typeof fetch;

export type FailureKind = 'sweep-crash' | 'step-gave-up' | 'stuck';

export interface FailureReport {
    kind: FailureKind;
    sim: number | null;
    step: { index: number; kind: PublishStep['kind'] } | null;
    lastStep?: number;
    error?: unknown;
}

export interface ErrorReporter {
    /** Never rejects. A (sim, kind) pair that already alerted is dropped. */
    report(failure: FailureReport): Promise<void>;
    /** Re-arms one (sim, kind) pair so its next failure alerts again. */
    resolve(kind: FailureKind, sim: number | null): void;
}

export interface ErrorReporterOptions {
    webhookUrl?: string | undefined;
    fetchFn?: FetchFn;
    log?: (line: string) => void;
    timeoutMs?: number;
}

export const ERRORS_WEBHOOK_TIMEOUT_MS = 5000;
const MESSAGE_LIMIT = 200;
const URL_PATTERN = /\b(?:https?:\/\/|discord(?:app)?\.com\/api\/webhooks\/)\S+/gi;

export function redact(text: string): string {
    return text.replace(URL_PATTERN, '<url>');
}

function errorName(error: unknown): string {
    if (typeof error === 'object' && error !== null) {
        const e = error as { name?: unknown; status?: unknown };
        if (typeof e.name === 'string' && e.name !== '') return e.name;
        if (typeof e.status === 'number') return `http ${e.status}`;
    }
    return 'unknown error';
}

function errorMessage(error: unknown): string {
    return error instanceof Error ? error.message : '';
}

function failureLine(error: unknown): string {
    return 'sim-recap: errors webhook post failed (' + errorName(error) + ')';
}

export function formatAlert(failure: FailureReport): string {
    let head: string;
    if (failure.kind === 'step-gave-up' && failure.step !== null) {
        head = `sim-recap: sim ${failure.sim} step ${failure.step.index} (${failure.step.kind}) gave up`;
    } else if (failure.kind === 'stuck') {
        head = `sim-recap: sim ${failure.sim} stuck unfinished at step ${failure.lastStep ?? -1}`;
    } else {
        head = failure.sim === null ? 'sim-recap: sweep crashed' : `sim-recap: sweep crashed on sim ${failure.sim}`;
    }
    if (failure.error === undefined) return head;
    const detail = redact(errorMessage(failure.error)).slice(0, MESSAGE_LIMIT);
    return detail === '' ? `${head}: ${errorName(failure.error)}` : `${head}: ${errorName(failure.error)}: ${detail}`;
}

export function createErrorReporter(options: ErrorReporterOptions = {}): ErrorReporter {
    const webhookUrl = options.webhookUrl;
    const fetchFn = options.fetchFn ?? fetch;
    const log = options.log ?? ((line: string) => console.log(line));
    const timeoutMs = options.timeoutMs ?? ERRORS_WEBHOOK_TIMEOUT_MS;
    const seen = new Set<string>();

    if (webhookUrl === undefined) {
        log('sim-recap: SIM_RECAP_ERRORS_WEBHOOK_URL is not set; failure alerts are logged only');
    }

    function keyFor(kind: FailureKind, sim: number | null): string {
        return `${sim ?? 'none'}:${kind}`;
    }

    return {
        async report(failure) {
            const key = keyFor(failure.kind, failure.sim);
            if (seen.has(key)) return;
            seen.add(key);
            const content = formatAlert(failure);
            log(content);
            if (webhookUrl === undefined) return;
            try {
                const response = await fetchFn(webhookUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content, allowed_mentions: { parse: [] } }),
                    signal: AbortSignal.timeout(timeoutMs),
                });
                if (!response.ok) log(`sim-recap: errors webhook post failed: http ${response.status}`);
            } catch (error) {
                log(failureLine(error));
            }
        },
        resolve(kind, sim) {
            seen.delete(keyFor(kind, sim));
        },
    };
}
