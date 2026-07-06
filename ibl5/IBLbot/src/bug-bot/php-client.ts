import { config } from './config.js';

// ApiError is re-declared LOCALLY on purpose. NEVER `import { ApiError } from
// '../api/client.js'` — api/client.ts imports ../config.js, whose module body runs
// dotenv.config() + requireEnv('DISCORD_TOKEN'|'DISCORD_CLIENT_ID'|'API_BASE_URL')
// at load time. Since .env.bugbot defines BUG_BOT_DISCORD_TOKEN /
// BUG_PIPELINE_API_BASE_URL — not those prod keys — importing it would either throw
// on boot or silently load the prod bot's secrets, defeating the config isolation.
// HARD RULE: the bug-bot imports NOTHING from '../api/' or '../config.js'.
export class ApiError extends Error {
    constructor(
        public readonly statusCode: number,
        public readonly errorCode: string,
        message: string,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

// The IBL API wraps every response: JsonResponder::success($data) →
// {status:'success', data:{…}}; error() → {status:'error', error:{code,message}}.
interface ApiEnvelope<T> {
    status: 'success' | 'error';
    data?: T;
    error?: { code: string; message: string };
}

/**
 * POST JSON to a bug-pipeline endpoint and return the UNWRAPPED `data` payload.
 * Throws ApiError on a non-ok HTTP status OR a `{status:'error'}` envelope (which
 * PR #3 may return even with HTTP 200). Every thin wrapper below reads its response
 * fields straight off this return value ONLY because the envelope is stripped here.
 */
export async function apiPost<T>(endpoint: string, payload: unknown): Promise<T> {
    const urlString = `${config.phpApi.baseUrl}/api/bug-pipeline/${endpoint}`;
    const response = await fetch(urlString, {
        method: 'POST',
        headers: {
            'X-API-Key': config.phpApi.key,
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const body = await response.json() as ApiEnvelope<T>;

    if (!response.ok || body.status === 'error') {
        throw new ApiError(
            response.status,
            body.error?.code ?? 'HTTP_ERROR',
            body.error?.message ?? `HTTP ${response.status}`,
        );
    }

    return body.data as T;
}

// ── Typed thin wrappers (one per endpoint) ──────────────────────────────────
// Snowflake wire-type LOCKED: every *_id field is `string` and passed through
// untouched — NEVER Number()/parseInt a snowflake. `report_id` / `pr_number` are
// the ONLY numeric fields in this module.

// The 4 §3b writers (frozen contract):

export interface EnqueueBody {
    author_id: string;
    channel_id: string;
    message_id: string;
    text: string;
}
export interface EnqueueResult {
    authorized: boolean;
    report_id: number | null;
}
export function enqueue(body: EnqueueBody): Promise<EnqueueResult> {
    return apiPost<EnqueueResult>('enqueue', body);
}

export interface ThreadReplyBody {
    thread_id: string;
    message_id: string;
}
export interface ThreadReplyResult {
    matched: boolean;
}
export function threadReply(body: ThreadReplyBody): Promise<ThreadReplyResult> {
    return apiPost<ThreadReplyResult>('thread-reply', body);
}

export interface ReactionBody {
    message_id: string;
    emoji: string;
    reactor_id: string;
}
export interface ReactionResult {
    advanced: boolean;
}
export function reaction(body: ReactionBody): Promise<ReactionResult> {
    return apiPost<ReactionResult>('reaction', body);
}

export interface LastSeenBody {
    channel_id: string;
    message_id: string;
}
export interface LastSeenResult {
    ok: true;
}
export function lastSeen(body: LastSeenBody): Promise<LastSeenResult> {
    return apiPost<LastSeenResult>('last-seen', body);
}

// The 2 gap readers (PR #3 additions — the DB-less bot's only READ path):

export interface StateBody {
    channel_id: string;
}
export interface StateResult {
    last_processed_message_id: string | null;
}
// Gap #1 — backfill cursor.
export function getState(body: StateBody): Promise<StateResult> {
    return apiPost<StateResult>('state', body);
}

export interface ThreadByPrBody {
    pr_number: number;
}
export interface ThreadByPrResult {
    thread_id: string | null;
}
// Gap #2 — /prMerged resolver (pr_number → thread_id).
export function threadByPr(body: ThreadByPrBody): Promise<ThreadByPrResult> {
    return apiPost<ThreadByPrResult>('thread-by-pr', body);
}
