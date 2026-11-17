// Sim-recap drip publisher. PHP builds the whole script (steps, text, channel, mention ids,
// delays); this module only posts the steps in order and reports progress back. All I/O goes
// through the injected PublisherDeps so Vitest can drive the real loop.

export interface PublishStep {
    index: number;
    kind: 'thread_start' | 'thread_message' | 'injuries' | 'gif';
    channel: 'live_sims' | 'thread' | 'injuries';
    content: string;
    mentionUsers: string[];
    mentionRoles: string[];
    delayBeforeMs: number;
    gifQuery?: string;
}

export interface PublishClaim {
    sim: number;
    threadId: string | null;
    lastStep: number;
    script: { sim: number; title: string; steps: PublishStep[] };
}

export interface PublishTarget {
    liveSimsChannelId: string;
    injuriesChannelId: string;
    stripMentions: boolean;
}

export interface AllowedMentions {
    parse: never[];
    users: string[];
    roles: string[];
    repliedUser: false;
}

export interface PublisherDeps {
    send(channelId: string, content: string, allowed: AllowedMentions): Promise<{ id: string }>;
    startThread(channelId: string, messageId: string, name: string): Promise<{ id: string }>;
    lastOwnMessage(channelId: string): Promise<{ id: string; content: string; threadId: string | null } | null>;
    searchGif(query: string): Promise<string | null>;
    progress(sim: number, step: number, threadId: string | null): Promise<boolean>;
    finish(sim: number): Promise<boolean>;
    postingEnabled(): Promise<boolean>;
    sleep(ms: number): Promise<void>;
    log(line: string): void;
}

const MAX_RATE_LIMIT_RETRIES = 3;
const DEFAULT_RETRY_AFTER_MS = 5000;
const THREAD_NAME_LIMIT = 100;

/** A step failed for good: a non-429 error, or a 429 after MAX_RATE_LIMIT_RETRIES retries. */
export class StepGaveUpError extends Error {
    readonly sim: number;
    readonly stepIndex: number;
    readonly stepKind: PublishStep['kind'];
    readonly original: unknown;

    constructor(sim: number, step: PublishStep, original: unknown) {
        super(original instanceof Error ? original.message : 'unknown error');
        this.name = 'StepGaveUpError';
        this.sim = sim;
        this.stepIndex = step.index;
        this.stepKind = step.kind;
        this.original = original;
    }
}

interface StepCursor {
    step: PublishStep | null;
}

function isRateLimited(error: unknown): boolean {
    return typeof error === 'object' && error !== null && (error as { status?: unknown }).status === 429;
}

function retryAfterMs(error: unknown): number {
    const e = error as { retryAfter?: unknown; rawError?: { retry_after?: unknown } };
    if (typeof e.retryAfter === 'number' && e.retryAfter >= 0) return e.retryAfter;
    const raw = e.rawError?.retry_after;
    if (typeof raw === 'number' && raw >= 0) return raw * 1000;
    return DEFAULT_RETRY_AFTER_MS;
}

async function withRateLimitRetry<T>(fn: () => Promise<T>, deps: PublisherDeps): Promise<T> {
    for (let attempt = 0; ; attempt++) {
        try {
            return await fn();
        } catch (error) {
            if (!isRateLimited(error) || attempt >= MAX_RATE_LIMIT_RETRIES) {
                throw error;
            }
            await deps.sleep(retryAfterMs(error));
        }
    }
}

function allowedMentionsFor(step: PublishStep, stripMentions: boolean): AllowedMentions {
    return {
        parse: [],
        users: stripMentions ? [] : step.mentionUsers,
        roles: stripMentions ? [] : step.mentionRoles,
        repliedUser: false,
    };
}

function channelIdFor(step: PublishStep, target: PublishTarget, threadId: string | null): string {
    if (step.channel === 'live_sims') return target.liveSimsChannelId;
    if (step.channel === 'injuries') return target.injuriesChannelId;
    if (threadId === null) {
        throw new Error(`sim-recap: step ${step.index} needs a thread but none exists`);
    }
    return threadId;
}

/** Fail closed: a false, a throw, or a rejected read all mean "posting is off". */
async function postingAllowed(deps: PublisherDeps): Promise<boolean> {
    return deps.postingEnabled().then((on) => on === true, () => false);
}

async function publishSteps(
    claim: PublishClaim,
    target: PublishTarget,
    deps: PublisherDeps,
    cursor: StepCursor,
): Promise<'finished' | 'lost-claim' | 'stopped'> {
    const { sim, script } = claim;
    let threadId = claim.threadId;
    let reconcilePending = true;

    for (const step of script.steps) {
        if (step.index <= claim.lastStep) continue;
        cursor.step = step;

        await deps.sleep(step.delayBeforeMs);

        // Checked only between steps, never between a send and its progress call, so last_step
        // always equals the last posted message when the run stops.
        if (!(await postingAllowed(deps))) {
            deps.log(`sim-recap: sim ${sim} posting switched off; stopping before step ${step.index}`);
            return 'stopped';
        }

        const channelId = channelIdFor(step, target, threadId);
        const allowed = allowedMentionsFor(step, target.stripMentions);
        let alreadyPosted: { id: string; threadId: string | null } | null = null;

        // A crash between a Discord post and its progress call leaves last_step one behind.
        // Only the first pending step can be in that state, so only it is reconciled.
        if (reconcilePending) {
            reconcilePending = false;
            const own = await deps.lastOwnMessage(channelId);
            if (own !== null) {
                const same = step.kind === 'gif' ? own.content.startsWith('https://') : own.content.trim() === step.content.trim();
                if (same) alreadyPosted = own;
            }
        }

        if (step.kind === 'thread_start') {
            let messageId: string;
            let foundThreadId: string | null = null;
            if (alreadyPosted !== null) {
                messageId = alreadyPosted.id;
                foundThreadId = alreadyPosted.threadId;
            } else {
                const msg = await withRateLimitRetry(() => deps.send(channelId, step.content, allowed), deps);
                messageId = msg.id;
            }
            if (foundThreadId === null) {
                const name = script.title.slice(0, THREAD_NAME_LIMIT);
                const thread = await withRateLimitRetry(() => deps.startThread(channelId, messageId, name), deps);
                foundThreadId = thread.id;
            }
            threadId = foundThreadId;
        } else if (step.kind === 'gif') {
            if (alreadyPosted === null) {
                const url = await deps.searchGif(step.gifQuery ?? 'game over');
                if (url === null) {
                    deps.log('sim-recap: gif skipped');
                } else {
                    await withRateLimitRetry(() => deps.send(channelId, url, allowed), deps);
                }
            }
        } else if (alreadyPosted === null) {
            await withRateLimitRetry(() => deps.send(channelId, step.content, allowed), deps);
        }

        const accepted = await deps.progress(sim, step.index, threadId);
        if (!accepted) {
            deps.log(`sim-recap: claim lost at step ${step.index}`);
            return 'lost-claim';
        }
    }

    cursor.step = null;
    const finished = await deps.finish(sim);
    if (!finished) {
        deps.log('sim-recap: finish refused');
    }
    return 'finished';
}

export async function runPublish(
    claim: PublishClaim,
    target: PublishTarget,
    deps: PublisherDeps,
): Promise<'finished' | 'lost-claim' | 'stopped'> {
    const cursor: StepCursor = { step: null };
    try {
        return await publishSteps(claim, target, deps, cursor);
    } catch (error) {
        if (cursor.step === null) throw error;
        throw new StepGaveUpError(claim.sim, cursor.step, error);
    }
}
