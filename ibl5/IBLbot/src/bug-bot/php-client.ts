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
    const urlString = `${config.phpApi.baseUrl}/api/v1/bug-pipeline/${endpoint}`;
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

// One captured attachment, in the wire shape EnqueueController validates. Snowflake
// ids stay strings (never Number()); local_path is null when the download failed and
// the report degrades to URL-only.
export interface AttachmentInput {
    attachment_id: string;
    original_url: string;
    local_path: string | null;
    filename: string;
    content_type: string;
    file_size: number | null;
}
export interface EnqueueBody {
    author_id: string;
    channel_id: string;
    message_id: string;
    text: string;
    // Optional so backfill.ts and every existing enqueue() caller compiles untouched.
    attachments?: AttachmentInput[];
}
export interface EnqueueResult {
    authorized: boolean;
    report_id: number | null;
    attachments_stored?: number;
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

// The 2 message-state writers (PR #4 additions — source-updated / source-deleted):

export interface SourceUpdatedResult {
    matched: boolean;
    changed: boolean;
    revived: boolean;
    status: string | null;
    thread_id: string | null;
}
export function sourceUpdated(body: { message_id: string; text: string }): Promise<SourceUpdatedResult> {
    return apiPost<SourceUpdatedResult>('source-updated', body);
}

export interface SourceDeletedResult {
    matched: boolean;
    dropped: boolean;
    status: string | null;
    thread_id: string | null;
}
export function sourceDeleted(body: { message_id: string }): Promise<SourceDeletedResult> {
    return apiPost<SourceDeletedResult>('source-deleted', body);
}
